<?php

namespace Tests\Feature\Publishing;

use App\Models\MembershipApplication;
use App\Models\Publication;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class PublishingAuthorizationTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_guest_cannot_access_publishing_admin(): void
    {
        $publication = Publication::factory()->create();

        $this->get(route('admin.publishing.publications.index'))
            ->assertRedirect(route('login'));
        $this->get(route('admin.publishing.publications.create'))
            ->assertRedirect(route('login'));
        $this->get(route('admin.publishing.publications.edit', $publication))
            ->assertRedirect(route('login'));
        $this->post(route('admin.publishing.publications.publish', $publication))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_permission_cannot_access_publishing_admin(): void
    {
        $this->syncAclFromConfig();
        $user = User::factory()->create();
        $publication = Publication::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.publishing.publications.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('admin.publishing.publications.edit', $publication))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.publishing.publications.store'), [
                'type' => 'berita',
                'title' => 'Tidak boleh',
            ])
            ->assertForbidden();
    }

    public function test_view_permission_does_not_imply_update_or_publish(): void
    {
        $user = $this->userWithPermissions(['publishing-publications.view']);
        $publication = Publication::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.publishing.publications.index'))
            ->assertOk();
        $this->actingAs($user)
            ->get(route('admin.publishing.publications.edit', $publication))
            ->assertOk();
        $this->actingAs($user)
            ->put(route('admin.publishing.publications.update', $publication), [
                'type' => 'berita',
                'title' => 'Diubah',
                'slug' => $publication->slug,
            ])
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.publishing.publications.publish', $publication))
            ->assertForbidden();

        $this->assertSame('draft', $publication->fresh()->status->value);
        $this->assertSame($publication->title, $publication->fresh()->title);
    }

    public function test_update_permission_does_not_imply_publish(): void
    {
        $user = $this->userWithPermissions([
            'publishing-publications.view',
            'publishing-publications.update',
        ]);
        $publication = Publication::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.publishing.publications.update', $publication), [
                'type' => 'berita',
                'title' => 'Diedit tanpa terbit',
                'slug' => $publication->slug,
            ])
            ->assertRedirect(route('admin.publishing.publications.edit', $publication));

        $this->actingAs($user)
            ->post(route('admin.publishing.publications.publish', $publication))
            ->assertForbidden();

        $this->assertSame('draft', $publication->fresh()->status->value);
        $this->assertSame('Diedit tanpa terbit', $publication->fresh()->title);
    }

    public function test_seeded_editor_receives_publishing_and_not_membership(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $application = MembershipApplication::factory()->underReview()->create();
        $publication = Publication::factory()->create();

        $this->assertTrue($editor->can('publishing-publications.view'));
        $this->assertTrue($editor->can('publishing-publications.create'));
        $this->assertTrue($editor->can('publishing-publications.update'));
        $this->assertTrue($editor->can('publishing-publications.publish'));
        $this->assertTrue($editor->can('publishing-publications.delete'));
        $this->assertFalse($editor->can('membership-applications.view'));
        $this->assertFalse($editor->can('membership-applications.approve'));
        $this->assertFalse($editor->can('membership-members.view'));
        $this->assertFalse($editor->can('membership-documents.view'));

        $this->actingAs($editor)
            ->post(route('admin.publishing.publications.publish', $publication))
            ->assertRedirect();

        $this->actingAs($editor)
            ->get(route('admin.membership.applications.index'))
            ->assertForbidden();
        $this->actingAs($editor)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertForbidden();
    }

    public function test_membership_admin_does_not_receive_publishing(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole('membership-admin');
        $publication = Publication::factory()->create();

        $this->actingAs($staff)
            ->get(route('admin.publishing.publications.index'))
            ->assertForbidden();
        $this->actingAs($staff)
            ->post(route('admin.publishing.publications.publish', $publication))
            ->assertForbidden();
    }

    public function test_editor_role_name_is_not_the_authorization_check(): void
    {
        $this->syncAclFromConfig();
        $role = Role::findOrCreate('editor', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);
        $publication = Publication::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.publishing.publications.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.publishing.publications.publish', $publication))
            ->assertForbidden();
    }
}
