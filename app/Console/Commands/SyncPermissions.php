<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class SyncPermissions extends Command
{
    /**
     * Nama command yang akan kita ketik di terminal.
     */
    protected $signature = 'acl:sync {--clean : Hapus permission di DB yang tidak ada di config}';

    /**
     * Deskripsi command.
     */
    protected $description = 'Sinkronisasi permission dari config/acl.php ke Database';

    public function handle()
    {
        $this->info('Memulai sinkronisasi Access Control List (ACL)...');

        // 1. Ambil semua permission dari Config dan ratakan (Flatten) menjadi satu array
        $configPermissions = collect(config('acl.permissions'))
            ->flatten() // Mengubah array multidimensi (grouping) jadi satu list panjang
            ->unique()
            ->values()
            ->toArray();

        if (empty($configPermissions)) {
            $this->error('Config acl.php kosong atau tidak ditemukan!');
            return;
        }

        // 2. Ambil semua permission yang ada di Database saat ini
        $dbPermissions = Permission::pluck('name')->toArray();

        // 3. LOGIKA: Tambahkan yang Baru (Config - DB)
        $newPermissions = array_diff($configPermissions, $dbPermissions);

        if (count($newPermissions) > 0) {
            $this->info('Menambahkan ' . count($newPermissions) . ' permission baru:');
            foreach ($newPermissions as $perm) {
                Permission::create(['name' => $perm]);
                $this->comment(" + Created: $perm");
            }
        } else {
            $this->comment('Tidak ada permission baru untuk ditambahkan.');
        }

        // 4. LOGIKA: Hapus yang Usang (DB - Config) - Hanya jika flag --clean dipakai
        $obsoletePermissions = array_diff($dbPermissions, $configPermissions);

        if (count($obsoletePermissions) > 0) {
            if ($this->option('clean')) {
                $this->warn('Menghapus ' . count($obsoletePermissions) . ' permission usang:');
                foreach ($obsoletePermissions as $perm) {
                    $p = Permission::where('name', $perm)->first();
                    $p->delete(); // Hapus permission (otomatis copot dari semua role)
                    $this->error(" - Deleted: $perm");
                }
            } else {
                $this->warn('Ditemukan ' . count($obsoletePermissions) . ' permission di database yang tidak ada di config.');
                $this->line('Gunakan opsi --clean jika ingin menghapusnya.');
            }
        }

        // 5. Reset Cache Permission
        $this->call('permission:cache-reset');

        $this->info('Sinkronisasi Selesai!');
    }
}
