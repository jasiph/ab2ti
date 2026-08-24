<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'acl:sync
                            {--clean : Remove database permissions that are not in config/acl.php}
                            {--dry-run : Report planned changes without writing}';

    protected $description = 'Synchronize permissions from config/acl.php into the database';

    public function handle(PermissionRegistrar $registrar): int
    {
        $clean = (bool) $this->option('clean');
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'ACL dry-run (no database writes).'
            : ($clean ? 'ACL sync with explicit stale-permission cleanup.' : 'ACL sync (additive).'));

        $configPermissions = collect(config('acl.permissions'))
            ->flatten()
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();

        if ($configPermissions === []) {
            $this->error('config/acl.php has no permissions.');

            return self::FAILURE;
        }

        $dbPermissions = Permission::query()->pluck('name')->all();
        $newPermissions = array_values(array_diff($configPermissions, $dbPermissions));
        $stalePermissions = array_values(array_diff($dbPermissions, $configPermissions));

        $this->line('Canonical permissions: '.count($configPermissions));
        $this->line('Database permissions: '.count($dbPermissions));

        if ($newPermissions === []) {
            $this->comment('No permissions to add.');
        } else {
            $this->info(($dryRun ? 'Would add ' : 'Adding ').count($newPermissions).' permission(s):');
            foreach ($newPermissions as $name) {
                $this->line('  + '.$name);
            }
        }

        if ($stalePermissions === []) {
            $this->comment('No stale permissions.');
        } else {
            $this->warn(count($stalePermissions).' database permission(s) are not in config/acl.php:');
            foreach ($stalePermissions as $name) {
                $this->line('  - '.$name);
            }

            if (! $clean) {
                $this->comment('Ordinary sync does not delete them. Re-run with --clean to remove stale permissions.');
            }
        }

        if ($dryRun) {
            if ($clean && $stalePermissions !== []) {
                $this->comment('Dry-run: stale permissions would be detached from roles/users, then deleted. Roles and users would be kept.');
            }

            return self::SUCCESS;
        }

        DB::transaction(function () use ($newPermissions, $stalePermissions, $clean): void {
            foreach ($newPermissions as $name) {
                Permission::findOrCreate($name, 'web');
            }

            if ($clean) {
                foreach ($stalePermissions as $name) {
                    $permission = Permission::query()->where('name', $name)->first();

                    if ($permission === null) {
                        continue;
                    }

                    $this->detachPermissionAssignments($permission);
                    $permission->delete();
                    $this->error('  deleted: '.$name);
                }
            }
        });

        $registrar->forgetCachedPermissions();
        $this->call('permission:cache-reset');

        $this->info('ACL sync finished. Roles and users were not deleted.');

        return self::SUCCESS;
    }

    private function detachPermissionAssignments(Permission $permission): void
    {
        $tables = config('permission.table_names');

        DB::table($tables['role_has_permissions'])
            ->where('permission_id', $permission->id)
            ->delete();

        DB::table($tables['model_has_permissions'])
            ->where('permission_id', $permission->id)
            ->delete();
    }
}
