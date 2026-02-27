<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MergeLanguageFiles extends Command
{
    /**
     * Signature perintah.
     * Menerima argumen opsional 'locale', defaultnya 'id'.
     */
    protected $signature = 'lang:merge {locale=id}';

    /**
     * Deskripsi perintah.
     */
    protected $description = 'Menggabungkan file JSON partial dari folder sources menjadi satu file utama.';

    public function handle()
    {
        $locale = $this->argument('locale');
        $locale = $locale ?: 'idd';

        // PATH DEFINITION
        // Sumber: lang/id/sources/*.json
        $sourcePath = lang_path($locale . '/sources');
        // Tujuan: lang/id.json
        $outputPath = lang_path($locale . '.json');

        // Cek apakah folder sumber ada
        if (!File::exists($sourcePath)) {
            $this->error("Folder sumber tidak ditemukan: {$sourcePath}");
            $this->line("Pastikan Anda membuat folder 'sources' di dalam 'lang/{$locale}/'.");
            return 1;
        }


        $this->info("Memproses file bahasa untuk locale: [{$locale}]...");

        $files = File::files($sourcePath);
        $finalJson = [];
        $count = 0;

        foreach ($files as $file) {
            // Validasi hanya proses file .json
            if ($file->getExtension() !== 'json') {
                continue;
            }

            // 1. Ambil nama file untuk jadi PREFIX
            // Contoh: 'auth.json' -> 'auth'
            $filename = $file->getFilenameWithoutExtension();

            // Ubah format nama file jadi rapi (StudlyCase)
            // auth -> Auth, user-profile -> UserProfile
            $prefix = Str::studly($filename);

            // 2. Baca konten file
            $content = json_decode(File::get($file), true);

            // Cek jika JSON valid
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("❌ Gagal membaca JSON di file: " . $file->getFilename());
                continue;
            }

            // 3. Gabungkan (Flattening) dengan Prefix
            foreach ($content as $key => $value) {
                // Format Akhir: "NamaFile.KeyAsli": "Value"
                // Contoh: "Auth.Log in": "Masuk"
                //$finalJson["{$prefix}.{$key}"] = $value;
                $finalJson["{$key}"] = $value;
            }

            $this->line("   ✓ Menggabungkan: <comment>{$file->getFilename()}</comment> -> Prefix: <comment>{$prefix}</comment>");
            $count++;
        }

        // 4. Simpan ke file tujuan (id.json)
        // JSON_PRETTY_PRINT: Agar file output rapi (multiline)
        // JSON_UNESCAPED_UNICODE: Agar karakter seperti emoji/aksen tidak rusak
        File::put($outputPath, json_encode($finalJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info("✅ SUKSES! {$count} file digabungkan ke: {$outputPath}");

        return 0;
    }
}
