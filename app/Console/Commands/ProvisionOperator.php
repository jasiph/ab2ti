<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ProvisionOperator extends Command
{
    protected $signature = 'ab2ti:provision-operator
                            {email : Operator login email}
                            {--name= : Display name}
                            {--password= : Password (required; never stored in the repository)}';

    protected $description = 'Create the first super-admin operator without a default password';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $password = (string) $this->option('password');
        $name = trim((string) $this->option('name'));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email is required.');

            return self::FAILURE;
        }

        if ($password === '') {
            $this->error('Pass --password= with a strong unique password. Do not use a repository default.');

            return self::FAILURE;
        }

        if (in_array($password, ['password', 'admin', 'admin123', 'secret'], true)) {
            $this->error('That password is not allowed.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with that email already exists. Assign the super-admin role explicitly if needed.');

            return self::FAILURE;
        }

        $role = Role::findOrCreate('super-admin', 'web');

        $user = User::query()->create([
            'name' => $name !== '' ? $name : 'Operator',
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        $this->info('Operator created and assigned super-admin: '.$email);

        return self::SUCCESS;
    }
}
