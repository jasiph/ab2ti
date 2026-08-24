<?php

namespace Tests\Feature\Release;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RcReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_timezone_is_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_production_http_routes_are_controller_actions(): void
    {
        $web = (string) file_get_contents(base_path('routes/web.php'));
        $api = (string) file_get_contents(base_path('routes/api.php'));

        $this->assertStringContainsString('LanguageController::class', $web);
        $this->assertStringContainsString("PageController::class, 'show'", $web);
        $this->assertStringContainsString('runningUnitTests()', $web);
        $this->assertStringContainsString('PhpunitErrorController::class', $web);
        $this->assertStringContainsString('AuthenticatedUserController::class', $api);
        $this->assertStringNotContainsString('function (Request $request)', $api);
    }

    public function test_roles_and_permissions_seeder_does_not_create_demo_users_in_production(): void
    {
        $this->app['env'] = 'production';
        (new RolesAndPermissionsSeeder)->run();

        $this->assertDatabaseMissing('users', ['email' => 'admin@admin.com']);
        $this->assertDatabaseMissing('users', ['email' => 'user@user.com']);
        $this->assertTrue(Role::query()->where('name', 'super-admin')->exists());
        $this->assertDatabaseHas('permissions', ['name' => 'users.view']);
    }

    public function test_operator_provision_requires_a_non_default_password_and_assigns_super_admin(): void
    {
        $this->artisan('ab2ti:provision-operator', [
            'email' => 'ops@ab2ti.test',
            '--name' => 'Operator',
        ])->assertFailed();

        $this->artisan('ab2ti:provision-operator', [
            'email' => 'ops@ab2ti.test',
            '--name' => 'Operator',
            '--password' => 'password',
        ])->assertFailed();

        $this->artisan('ab2ti:provision-operator', [
            'email' => 'ops@ab2ti.test',
            '--name' => 'Operator',
            '--password' => 'unique-operator-pass-32',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'ops@ab2ti.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('super-admin'));

        $this->artisan('ab2ti:provision-operator', [
            'email' => 'ops@ab2ti.test',
            '--password' => 'another-unique-pass-32',
        ])->assertFailed();
    }

    public function test_route_cache_succeeds_for_the_http_kernel(): void
    {
        $exit = Artisan::call('route:cache');
        $this->assertSame(0, $exit);

        Artisan::call('route:clear');
        File::delete(base_path('bootstrap/cache/routes-v7.php'));
    }
}
