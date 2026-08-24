<?php

namespace Tests\Feature\Admin;

use App\Models\Hero;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class HeroAdminTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_authorized_staff_can_create_update_and_delete_a_hero(): void
    {
        $staff = $this->userWithPermissions(['heroes.view', 'heroes.create', 'heroes.update', 'heroes.delete']);

        $this->actingAs($staff)
            ->post(route('admin.heroes.store'), [
                'title' => 'Landing',
                'primary_text' => 'Halo',
                'secondary_text' => 'AB2TI',
                'button_text' => 'Gabung',
                'button_url' => '/keanggotaan',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.heroes.index'));

        $hero = Hero::query()->where('title', 'Landing')->first();
        $this->assertNotNull($hero);

        $this->actingAs($staff)
            ->get(route('admin.heroes.edit', $hero))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Hero/Form')
                ->where('hero.title', 'Landing')
                ->where('hero.primary_text', 'Halo'));

        $this->actingAs($staff)
            ->put(route('admin.heroes.update', $hero), [
                'title' => 'Landing',
                'primary_text' => 'Halo lagi',
                'secondary_text' => 'AB2TI',
                'button_text' => 'Gabung',
                'button_url' => '/keanggotaan',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.heroes.index'));

        $this->assertDatabaseHas('heroes', [
            'id' => $hero->id,
            'primary_text' => 'Halo lagi',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Landing/Welcome')
                ->where('hero.primary_text', 'Halo lagi'));

        $this->actingAs($staff)
            ->delete(route('admin.heroes.destroy', $hero))
            ->assertRedirect(route('admin.heroes.index'));

        $this->assertDatabaseMissing('heroes', ['id' => $hero->id]);
    }

    public function test_toggle_does_not_wipe_hero_copy(): void
    {
        $staff = $this->userWithPermissions(['heroes.update']);
        $hero = Hero::query()->create([
            'title' => 'Landing',
            'primary_text' => 'Halo',
            'secondary_text' => 'Dunia',
            'button_text' => 'Gabung',
            'button_url' => '/keanggotaan',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.heroes.toggle', $hero))
            ->assertRedirect();

        $fresh = $hero->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertSame('Halo', $fresh->primary_text);
        $this->assertSame('Dunia', $fresh->secondary_text);
        $this->assertSame('Gabung', $fresh->button_text);
        $this->assertSame('/keanggotaan', $fresh->button_url);
    }

    public function test_store_validates_title(): void
    {
        $staff = $this->userWithPermissions(['heroes.create']);

        $this->actingAs($staff)
            ->from(route('admin.heroes.create'))
            ->post(route('admin.heroes.store'), [
                'primary_text' => 'Tanpa judul',
            ])
            ->assertRedirect(route('admin.heroes.create'))
            ->assertSessionHasErrors(['title']);
    }

    public function test_unauthorized_access_is_denied(): void
    {
        $user = User::factory()->create();
        $hero = Hero::query()->create([
            'title' => 'Private',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.heroes.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.heroes.store'), [
            'title' => 'Blocked',
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.heroes.toggle', $hero))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.heroes.destroy', $hero))->assertForbidden();
    }
}
