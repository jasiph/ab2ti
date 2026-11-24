<?php

namespace App\Support\Cooperative\Import;

use RuntimeException;

final class ImportException extends RuntimeException
{
    public static function unreadableFile(): self
    {
        return new self('The spreadsheet could not be read. Check that it is a valid CSV or XLSX file.');
    }

    public static function missingCoreHeaders(): self
    {
        return new self('Required columns could not be identified (name plus total, principal saving, or share reference).');
    }

    public static function duplicateHeaders(): self
    {
        return new self('The spreadsheet has duplicate column labels that map to the same field.');
    }

    public static function extraWorksheet(): self
    {
        return new self('The workbook has more than one sheet with data. Select one worksheet to import.');
    }

    public static function noCompatibleWorksheet(): self
    {
        return new self('No worksheet with recognizable Cooperative member headers was found.');
    }

    public static function worksheetNotFound(): self
    {
        return new self('The selected worksheet was not found in this workbook.');
    }

    public static function tooManyRows(int $max): self
    {
        return new self('The file has more than '.$max.' data rows. Split it before importing.');
    }

    public static function emptyWorkbook(): self
    {
        return new self('The spreadsheet has no header row.');
    }

    public static function noMemberRows(): self
    {
        return new self('The selected worksheet has no recognizable member rows after the header.');
    }
}
