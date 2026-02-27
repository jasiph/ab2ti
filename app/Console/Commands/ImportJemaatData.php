<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Congregation;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ImportJemaatData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:jemaat {file=data_jemaat.json : The path to the JSON file relative to storage/app}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import congregation data from a legacy JSON file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('file');
        $path = storage_path('app/' . $filename);

        if (!file_exists($path)) {
            $this->error("File not found at: {$path}");
            return 1;
        }

        $this->info("Reading file: {$path}...");
        $jsonContent = file_get_contents($path);
        $data = json_decode($jsonContent, true);

        if (!$data) {
            $this->error("Invalid JSON format or empty file.");
            return 1;
        }

        $count = count($data);
        $this->info("Found {$count} records. Starting import...");

        DB::beginTransaction();

        try {
            $idMap = []; // old_id => new_uuid

            // Pass 1: Create records
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            foreach ($data as $record) {
                // Parse date: 1952/Oct/28 -> Y-m-d
                $dob = null;
                if (!empty($record['tanggal_lahir'])) {
                    try {
                        $dob = Carbon::createFromFormat('Y/M/d', $record['tanggal_lahir'])->format('Y-m-d');
                    }
                    catch (\Exception $e) {
                        $this->warn("\nFailed to parse date for {$record['nama']}: {$record['tanggal_lahir']}");
                    }
                }

                // Map gender
                $gender = match ($record['jenis_kelamin']) {
                        'P' => 'male',
                        'W' => 'female',
                        default => 'male', // Default or handle error
                    };

                // Generate unit number
                $unitNumber = 'J-' . str_pad($record['id'], 4, '0', STR_PAD_LEFT);

                $congregation = Congregation::create([
                    'unit_number' => $unitNumber,
                    'name' => $record['nama'],
                    'gender' => $gender,
                    'place_of_birth' => $record['tempat_lahir'] ?? null,
                    'date_of_birth' => $dob,
                    'address' => $record['domisili'],
                    // 'phone' => $record['nomor_hp'], // null in sample, map if exists
                    'status' => 'active', // Defaulting to active
                ]);

                $idMap[$record['id']] = $congregation->id;
                $bar->advance();
            }

            $bar->finish();
            $this->info("\nPass 1 complete. Starting Pass 2 (Relationships)...");

            // Pass 2: Update relationships
            $bar2 = $this->output->createProgressBar($count);
            $bar2->start();

            foreach ($data as $record) {
                $newId = $idMap[$record['id']] ?? null;

                if (!$newId)
                    continue;

                $updates = [];

                // Father
                if (!empty($record['ayah']['id']) && isset($idMap[$record['ayah']['id']])) {
                    $updates['father_id'] = $idMap[$record['ayah']['id']];
                }

                // Mother
                if (!empty($record['ibu']['id']) && isset($idMap[$record['ibu']['id']])) {
                    $updates['mother_id'] = $idMap[$record['ibu']['id']];
                }

                if (!empty($updates)) {
                    Congregation::where('id', $newId)->update($updates);
                }

                $bar2->advance();
            }

            $bar2->finish();
            DB::commit();

            $this->info("\nImport completed successfully!");

        }
        catch (\Exception $e) {
            DB::rollBack();
            $this->error("\nImport failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
