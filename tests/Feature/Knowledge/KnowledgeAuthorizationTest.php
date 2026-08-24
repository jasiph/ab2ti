<?php

namespace Tests\Feature\Knowledge;

use App\Models\KnowledgeArticle;
use App\Models\MembershipApplication;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class KnowledgeAuthorizationTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_guest_cannot_access_knowledge_admin(): void
    {
        $article = KnowledgeArticle::factory()->create();

        $this->get(route('admin.knowledge.articles.index'))
            ->assertRedirect(route('login'));
        $this->get(route('admin.knowledge.articles.create'))
            ->assertRedirect(route('login'));
        $this->get(route('admin.knowledge.articles.edit', $article))
            ->assertRedirect(route('login'));
        $this->post(route('admin.knowledge.articles.publish', $article))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_permission_cannot_access_knowledge_admin(): void
    {
        $this->syncAclFromConfig();
        $user = User::factory()->create();
        $article = KnowledgeArticle::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.knowledge.articles.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('admin.knowledge.articles.edit', $article))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.knowledge.articles.store'), [
                'title' => 'Tidak boleh',
            ])
            ->assertForbidden();
    }

    public function test_view_permission_does_not_imply_update_or_publish(): void
    {
        $user = $this->userWithPermissions(['knowledge-articles.view']);
        $article = KnowledgeArticle::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.knowledge.articles.index'))
            ->assertOk();
        $this->actingAs($user)
            ->get(route('admin.knowledge.articles.edit', $article))
            ->assertOk();
        $this->actingAs($user)
            ->put(route('admin.knowledge.articles.update', $article), [
                'title' => 'Diubah',
                'slug' => $article->slug,
            ])
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.knowledge.articles.publish', $article))
            ->assertForbidden();

        $this->assertSame('draft', $article->fresh()->status->value);
        $this->assertSame($article->title, $article->fresh()->title);
    }

    public function test_update_permission_does_not_imply_publish(): void
    {
        $user = $this->userWithPermissions([
            'knowledge-articles.view',
            'knowledge-articles.update',
        ]);
        $article = KnowledgeArticle::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.knowledge.articles.update', $article), [
                'title' => 'Diedit tanpa terbit',
                'slug' => $article->slug,
            ])
            ->assertRedirect(route('admin.knowledge.articles.edit', $article));

        $this->actingAs($user)
            ->post(route('admin.knowledge.articles.publish', $article))
            ->assertForbidden();

        $this->assertSame('draft', $article->fresh()->status->value);
        $this->assertSame('Diedit tanpa terbit', $article->fresh()->title);
    }

    public function test_seeded_editor_receives_knowledge_and_not_membership(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $application = MembershipApplication::factory()->underReview()->create();
        $article = KnowledgeArticle::factory()->create();

        $this->assertTrue($editor->can('knowledge-articles.view'));
        $this->assertTrue($editor->can('knowledge-articles.create'));
        $this->assertTrue($editor->can('knowledge-articles.update'));
        $this->assertTrue($editor->can('knowledge-articles.publish'));
        $this->assertTrue($editor->can('knowledge-articles.delete'));
        $this->assertTrue($editor->can('publishing-publications.view'));
        $this->assertTrue($editor->can('cms.view'));
        $this->assertTrue($editor->can('media.view'));
        $this->assertFalse($editor->can('membership-applications.view'));
        $this->assertFalse($editor->can('membership-applications.approve'));
        $this->assertFalse($editor->can('membership-members.view'));
        $this->assertFalse($editor->can('membership-documents.view'));

        $this->actingAs($editor)
            ->post(route('admin.knowledge.articles.publish', $article))
            ->assertRedirect();

        $this->actingAs($editor)
            ->get(route('admin.membership.applications.index'))
            ->assertForbidden();
        $this->actingAs($editor)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertForbidden();
    }

    public function test_membership_admin_does_not_receive_knowledge(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole('membership-admin');
        $article = KnowledgeArticle::factory()->create();

        $this->actingAs($staff)
            ->get(route('admin.knowledge.articles.index'))
            ->assertForbidden();
        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.publish', $article))
            ->assertForbidden();
    }

    public function test_editor_role_name_is_not_the_authorization_check(): void
    {
        $this->syncAclFromConfig();
        $role = Role::findOrCreate('editor', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);
        $article = KnowledgeArticle::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.knowledge.articles.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.knowledge.articles.publish', $article))
            ->assertForbidden();
    }
}
