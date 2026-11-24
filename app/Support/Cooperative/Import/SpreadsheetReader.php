<?php

namespace App\Support\Cooperative\Import;

use DateTimeInterface;
use OpenSpout\Common\Entity\Cell as SpoutCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\SheetWithVisibilityInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

final class SpreadsheetReader
{
    public function __construct(
        private readonly HeaderMapper $headers = new HeaderMapper,
        private readonly SourceRowClassifier $classifier = new SourceRowClassifier,
    ) {}

    /**
     * Inspect every worksheet without combining or staging rows.
     *
     * @return list<array{
     *     name: string,
     *     visible: bool,
     *     header_row: ?int,
     *     estimated_source_rows: int,
     *     ignored_footer_or_summary_rows: int,
     *     compatible: bool,
     *     candidate: bool,
     *     status: string
     * }>
     */
    public function inspect(string $path, string $originalFilename): array
    {
        $sheets = [];

        $this->withReader($path, $originalFilename, function (ReaderInterface $reader) use (&$sheets): void {
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheets[] = $this->summarizeSheet($sheet);
            }
        });

        if ($sheets === []) {
            throw ImportException::emptyWorkbook();
        }

        return $sheets;
    }

    /**
     * Stream classified member rows from one selected worksheet.
     *
     * @param  callable(list<string>, array{source_row_number: int, cells: list<mixed>}): void  $onRow
     * @return array{header_row: int, visible: bool, member_rows: int, ignored_footer_or_summary_rows: int}
     */
    public function processSheet(string $path, string $originalFilename, string $worksheetName, callable $onRow): array
    {
        $result = null;

        $this->withReader($path, $originalFilename, function (ReaderInterface $reader) use ($worksheetName, $onRow, &$result): void {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($this->sheetName($sheet) !== $worksheetName) {
                    continue;
                }

                $walk = $this->walkSheet($sheet, $onRow);
                if ($walk['header_row'] === null) {
                    throw ImportException::missingCoreHeaders();
                }

                $result = [
                    'header_row' => $walk['header_row'],
                    'visible' => $this->sheetVisible($sheet),
                    'member_rows' => $walk['member_rows'],
                    'ignored_footer_or_summary_rows' => $walk['ignored_footer_or_summary_rows'],
                ];

                return;
            }

            throw ImportException::worksheetNotFound();
        });

        return $result ?? throw ImportException::worksheetNotFound();
    }

    /**
     * @param  callable(ReaderInterface): void  $callback
     */
    private function withReader(string $path, string $originalFilename, callable $callback): void
    {
        $reader = $this->makeReader($originalFilename);

        try {
            $reader->open($path);
            $callback($reader);
        } catch (ImportException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ImportException::unreadableFile();
        } finally {
            $reader->close();
        }
    }

    private function makeReader(string $originalFilename): ReaderInterface
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->csvReader(),
            'xlsx' => $this->xlsxReader(),
            default => throw ImportException::unreadableFile(),
        };
    }

    private function csvReader(): CsvReader
    {
        $options = new CsvOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new CsvReader($options);
    }

    private function xlsxReader(): XlsxReader
    {
        $options = new XlsxOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new XlsxReader($options);
    }

    /**
     * @return array{
     *     name: string,
     *     visible: bool,
     *     header_row: ?int,
     *     estimated_source_rows: int,
     *     ignored_footer_or_summary_rows: int,
     *     compatible: bool,
     *     candidate: bool,
     *     status: string
     * }
     */
    private function summarizeSheet(SheetInterface $sheet): array
    {
        $walk = $this->walkSheet($sheet, null);
        $compatible = $walk['header_row'] !== null && $walk['member_rows'] > 0;

        return [
            'name' => $this->sheetName($sheet),
            'visible' => $this->sheetVisible($sheet),
            'header_row' => $walk['header_row'],
            'estimated_source_rows' => $walk['member_rows'],
            'ignored_footer_or_summary_rows' => $walk['ignored_footer_or_summary_rows'],
            'compatible' => $compatible,
            'candidate' => $compatible,
            'status' => $compatible ? 'candidate' : 'incompatible',
        ];
    }

    /**
     * @param  null|callable(list<string>, array{source_row_number: int, cells: list<mixed>}): void  $onRow
     * @return array{header_row: ?int, member_rows: int, ignored_footer_or_summary_rows: int}
     */
    private function walkSheet(SheetInterface $sheet, ?callable $onRow): array
    {
        $scanLimit = (int) config('cooperative.imports_header_scan_rows', 20);
        $maxRows = (int) config('cooperative.imports_max_rows', 5000);
        $rowNumber = 0;
        $headerRow = null;
        $headers = [];
        $columnMap = [];
        $memberRows = 0;
        $ignored = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $rowNumber++;
            $cells = $this->cells($row);

            if ($headerRow === null) {
                if ($rowNumber > $scanLimit) {
                    break;
                }

                if ($this->headers->isCanonicalHeaderRow($cells)) {
                    $headers = array_map(
                        fn (mixed $cell): string => trim((string) $cell),
                        $cells,
                    );
                    $columnMap = $this->headers->map($headers);
                    $this->headers->assertCoreHeaders($columnMap);
                    $headerRow = $rowNumber;
                }

                continue;
            }

            if ($this->isEmpty($cells)) {
                continue;
            }

            $raw = $this->rawFromCells($headers, $columnMap, $cells);

            if (! $this->classifier->isMemberRow($raw)) {
                $ignored++;

                continue;
            }

            $memberRows++;

            if ($memberRows > $maxRows) {
                throw ImportException::tooManyRows($maxRows);
            }

            if ($onRow !== null) {
                $onRow($headers, [
                    'source_row_number' => $rowNumber,
                    'cells' => $cells,
                ]);
            }
        }

        return [
            'header_row' => $headerRow,
            'member_rows' => $memberRows,
            'ignored_footer_or_summary_rows' => $ignored,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, int>  $columnMap
     * @param  list<mixed>  $cells
     * @return array<string, mixed>
     */
    public function rawFromCells(array $headers, array $columnMap, array $cells): array
    {
        $raw = [];

        foreach ($headers as $index => $header) {
            $raw['_source'][$header] = $cells[$index] ?? null;
        }

        foreach ($columnMap as $canonical => $index) {
            $raw[$canonical] = $cells[$index] ?? null;
        }

        return $raw;
    }

    /**
     * @return list<mixed>
     */
    private function cells(Row $row): array
    {
        $cells = [];

        foreach ($row->getCells() as $cell) {
            $cells[] = $this->cellValue($cell);
        }

        return $cells;
    }

    private function cellValue(SpoutCell $cell): mixed
    {
        $value = $cell instanceof FormulaCell
            ? ($cell->getComputedValue() ?? $cell->getValue())
            : $cell->getValue();

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    private function sheetName(SheetInterface $sheet): string
    {
        $name = trim($sheet->getName());

        return $name === '' ? 'CSV' : $name;
    }

    private function sheetVisible(SheetInterface $sheet): bool
    {
        if ($sheet instanceof SheetWithVisibilityInterface) {
            return $sheet->isVisible();
        }

        return true;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function isEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
