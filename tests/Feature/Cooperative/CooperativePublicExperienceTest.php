<?php

namespace Tests\Feature\Cooperative;

use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CooperativePublicExperienceTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://ab2ti.test',
            'public_delivery.canonical_origin' => 'https://ab2ti.test',
            'public_delivery.site_name' => 'AB2TI 2.0',
        ]);
    }

    public function test_koperasi_landing_is_public_with_aggregate_statistics(): void
    {
        Member::factory()->count(2)->create();
        $named = Member::factory()->create(['legal_name' => 'Rahasia Anggota Koperasi Budi']);
        CooperativeMembership::factory()->create([
            'member_id' => $named->id,
            'notes' => 'Catatan internal tidak untuk publik',
        ]);
        CooperativeMembership::factory()->inactive()->create();

        $this->get('/koperasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Cooperative/Index')
                ->where('stats.member_registry_complete', false)
                ->where('stats.total_ab2ti_members', null)
                ->where('stats.active_cooperative_members', 1)
                ->where('stats.participation_percentage', null)
                ->where('stats.total_psn_shares', null)
                ->where('stats.psn_participants', null)
                ->where('stats.psn_legacy_reconciliation_complete', false)
                ->missing('stats.notes')
                ->missing('memberships')
                ->has('updates')
                ->where('seo.canonical_url', 'https://ab2ti.test/koperasi'))
            ->assertDontSee('Rahasia Anggota Koperasi Budi')
            ->assertDontSee('Catatan internal tidak untuk publik')
            ->assertDontSee('25,0%')
            ->assertDontSee('100,0%');
    }

    public function test_zero_member_denominator_does_not_error(): void
    {
        $this->get('/koperasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.member_registry_complete', false)
                ->where('stats.total_ab2ti_members', null)
                ->where('stats.active_cooperative_members', 0)
                ->where('stats.participation_percentage', null)
                ->where('stats.total_psn_shares', null)
                ->where('stats.psn_participants', null));
    }

    public function test_complete_registry_restores_ab2ti_count_and_participation_ratio(): void
    {
        config([
            'cooperative.ab2ti_member_registry_complete' => true,
            'cooperative.psn_legacy_reconciliation_complete' => true,
        ]);

        Member::factory()->count(3)->create();
        $named = Member::factory()->create();
        CooperativeMembership::factory()->create(['member_id' => $named->id]);

        $this->get('/koperasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.member_registry_complete', true)
                ->where('stats.psn_legacy_reconciliation_complete', true)
                ->where('stats.total_ab2ti_members', 4)
                ->where('stats.active_cooperative_members', 1)
                ->where('stats.participation_percentage', 25)
                ->where('stats.total_psn_shares', 0)
                ->where('stats.psn_participants', 0));
    }

    public function test_incomplete_registry_does_not_claim_false_full_participation(): void
    {
        $member = Member::factory()->create();
        CooperativeMembership::factory()->create(['member_id' => $member->id]);

        $this->get('/koperasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.member_registry_complete', false)
                ->where('stats.total_ab2ti_members', null)
                ->where('stats.active_cooperative_members', 1)
                ->where('stats.participation_percentage', null))
            ->assertDontSee('100,0%')
            ->assertDontSee('100.0%');
    }

    public function test_published_cooperative_update_appears_and_drafts_or_unrelated_do_not(): void
    {
        $visible = Publication::factory()->published()->create([
            'title' => 'Update Koperasi terlihat',
            'is_cooperative_update' => true,
        ]);
        Publication::factory()->create([
            'title' => 'Draf Koperasi tersembunyi',
            'is_cooperative_update' => true,
        ]);
        Publication::factory()->published()->create([
            'title' => 'Berita biasa',
            'is_cooperative_update' => false,
        ]);

        $this->get('/koperasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('updates', 1)
                ->where('updates.0.id', $visible->id)
                ->where('updates.0.title', 'Update Koperasi terlihat'));
    }

    public function test_koperasi_appears_in_sitemap_and_admin_membership_urls_do_not(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>https://ab2ti.test/koperasi</loc>', $xml);
        $this->assertStringNotContainsString('/admin/koperasi', $xml);
        $this->assertStringNotContainsString('/koperasi/anggota', $xml);
    }

    public function test_canonical_cooperative_permissions_are_two_segment_names(): void
    {
        $permissions = collect(config('acl.permissions'))->flatten()->unique()->values();

        foreach (['cooperative.view', 'cooperative.manage'] as $name) {
            $this->assertTrue($permissions->contains($name), "Missing permission {$name}");
            $this->assertSame(1, substr_count($name, '.'));
        }

        $this->assertFalse($permissions->contains('cooperative.memberships.view'));
        $this->assertFalse($permissions->contains('cooperative.*'));
    }
}
