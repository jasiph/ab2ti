<?php

namespace Tests\Feature\Lapak;

use App\Models\Lapak;
use App\Models\LapakListing;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class LapakAuthorizationTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_guest_cannot_access_lapak_admin(): void
    {
        $lapak = Lapak::factory()->create();

        $this->get(route('admin.lapak.storefronts.index'))
            ->assertRedirect(route('login'));
        $this->get(route('admin.lapak.storefronts.show', $lapak))
            ->assertRedirect(route('login'));
        $this->get(route('admin.lapak.listings.index'))
            ->assertRedirect(route('login'));
        $this->get(route('admin.lapak.categories.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_permission_cannot_access_lapak_admin(): void
    {
        $this->syncAclFromConfig();
        $user = User::factory()->create();
        $lapak = Lapak::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.lapak.storefronts.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('admin.lapak.storefronts.show', $lapak))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.lapak.storefronts.store'), [
                'member_id' => Member::factory()->create()->id,
                'name' => 'Tidak boleh',
            ])
            ->assertForbidden();
    }

    public function test_view_permission_does_not_imply_manage_or_moderate(): void
    {
        $user = $this->userWithPermissions(['lapak.view']);
        $lapak = Lapak::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.lapak.storefronts.index'))
            ->assertOk();
        $this->actingAs($user)
            ->get(route('admin.lapak.storefronts.create'))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), [
                'status' => 'published',
            ])
            ->assertForbidden();
    }

    public function test_manage_permission_does_not_imply_moderate(): void
    {
        $user = $this->userWithPermissions(['lapak.view', 'lapak.manage']);
        $lapak = Lapak::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.lapak.storefronts.create'))
            ->assertOk();
        $this->actingAs($user)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), [
                'status' => 'published',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_access_lapak_admin_without_explicit_permissions(): void
    {
        $admin = $this->superAdmin();
        $lapak = Lapak::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.lapak.storefronts.index'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.lapak.storefronts.show', $lapak))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.lapak.listings.index'))
            ->assertOk();
    }

    public function test_editor_role_does_not_receive_lapak_administration(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $lapak = Lapak::factory()->create();

        $this->actingAs($editor)
            ->get(route('admin.lapak.storefronts.index'))
            ->assertForbidden();
        $this->actingAs($editor)
            ->post(route('admin.lapak.storefronts.moderate', $lapak), [
                'status' => 'published',
            ])
            ->assertForbidden();
    }

    public function test_membership_admin_receives_lapak_capabilities_and_no_member_spatie_role_exists(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole('membership-admin');

        $this->assertTrue($staff->can('lapak.view'));
        $this->assertTrue($staff->can('lapak.manage'));
        $this->assertTrue($staff->can('lapak.moderate'));
        $this->assertFalse(Role::query()->where('name', 'member')->exists());

        $this->actingAs($staff)
            ->get(route('admin.lapak.storefronts.index'))
            ->assertOk();
    }

    public function test_account_member_link_is_one_to_one_and_not_on_lapak(): void
    {
        $this->assertTrue(method_exists(User::class, 'member'));
        $this->assertTrue(Schema::hasColumn('members', 'user_id'));
        $this->assertTrue(Schema::hasColumn('members', 'linked_at'));
        $this->assertFalse(Schema::hasColumn('users', 'member_id'));
        $this->assertFalse(Schema::hasColumn('lapaks', 'user_id'));
        $this->assertFalse(Schema::hasColumn('lapak_listings', 'user_id'));
    }

    public function test_listing_belongs_to_lapak_not_directly_to_user(): void
    {
        $listing = LapakListing::factory()->create();

        $this->assertNotNull($listing->lapak);
        $this->assertNotNull($listing->lapak->member);
        $this->assertFalse(method_exists($listing, 'user'));
        $this->assertFalse(method_exists($listing, 'member'));
    }
}
