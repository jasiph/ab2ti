<?php

namespace Tests\Feature\Admin;

use App\Models\BlockTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class BlockTemplateAdminTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_authorized_staff_can_store_update_toggle_and_delete_block_templates(): void
    {
        $staff = $this->userWithPermissions(['cms.view', 'cms.create', 'cms.update', 'cms.delete']);

        $this->actingAs($staff)
            ->post(route('admin.block-templates.store'), [
                'name' => 'Highlight Card',
                'description' => 'Kartu',
                'html_content' => '<h3>Judul</h3><p>Isi</p>',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.block-templates.index'));

        $block = BlockTemplate::query()->where('name', 'Highlight Card')->first();
        $this->assertNotNull($block);
        $this->assertTrue($block->is_active);

        $this->actingAs($staff)
            ->get(route('admin.block-templates.edit', $block))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/BlockTemplates/Form')
                ->where('blockTemplate.id', $block->id));

        $this->actingAs($staff)
            ->put(route('admin.block-templates.update', $block), [
                'name' => 'Highlight Card',
                'description' => 'Kartu diperbarui',
                'html_content' => '<h3>Judul baru</h3>',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.block-templates.index'));

        $this->assertDatabaseHas('block_templates', [
            'id' => $block->id,
            'description' => 'Kartu diperbarui',
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.block-templates.toggle', $block))
            ->assertRedirect();

        $this->assertFalse($block->fresh()->is_active);

        $this->actingAs($staff)
            ->delete(route('admin.block-templates.destroy', $block))
            ->assertRedirect();

        $this->assertDatabaseMissing('block_templates', ['id' => $block->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $staff = $this->userWithPermissions(['cms.create']);

        $this->actingAs($staff)
            ->from(route('admin.block-templates.create'))
            ->post(route('admin.block-templates.store'), [])
            ->assertRedirect(route('admin.block-templates.create'))
            ->assertSessionHasErrors(['name', 'html_content']);
    }

    public function test_unauthorized_access_is_denied(): void
    {
        $user = User::factory()->create();
        $block = BlockTemplate::query()->create([
            'name' => 'Private',
            'html_content' => '<p>x</p>',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.block-templates.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.block-templates.store'), [
            'name' => 'Blocked',
            'html_content' => '<p>x</p>',
        ])->assertForbidden();
        $this->actingAs($user)->put(route('admin.block-templates.update', $block), [
            'name' => 'Blocked',
            'html_content' => '<p>x</p>',
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.block-templates.destroy', $block))->assertForbidden();
    }
}
