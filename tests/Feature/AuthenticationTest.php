<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_ordinary_user_logs_in_to_the_public_home_not_admin(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('welcome', absolute: false));

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_linked_member_logs_in_to_owner_workspace_not_admin_403(): void
    {
        $user = User::factory()->create();
        Member::factory()->linked($user)->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('account.lapak.index', absolute: false));

        $this->actingAs($user)->get(route('account.lapak.index'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_platform_staff_logs_in_to_admin_dashboard(): void
    {
        $staff = $this->superAdmin();

        $response = $this->post('/login', [
            'email' => $staff->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_non_staff_login_does_not_honor_admin_intended_url(): void
    {
        $user = User::factory()->create();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('welcome', absolute: false));
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }
}
