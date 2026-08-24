<?php

namespace Tests\Feature\PublicExperience;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class PublicNavigationRepairTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_primary_public_navigation_is_site_destinations_only(): void
    {
        $navigation = (string) file_get_contents(resource_path('js/Composables/useNavigation.js'));
        $navbar = (string) file_get_contents(resource_path('js/Layouts/Partials/FrontendNavbar.vue'));
        $sidebar = (string) file_get_contents(resource_path('js/Layouts/Partials/FrontendSidebar.vue'));
        $utilities = (string) file_get_contents(resource_path('js/Layouts/Partials/PublicTopbarUtilities.vue'));
        $layout = (string) file_get_contents(resource_path('js/Layouts/FrontendLayout.vue'));

        $this->assertStringContainsString('route: "welcome"', $navigation);
        $this->assertStringContainsString('route: "about"', $navigation);
        $this->assertStringContainsString('route: "publications.index"', $navigation);
        $this->assertStringContainsString('route: "knowledge.index"', $navigation);
        $this->assertStringContainsString('route: "lapak.index"', $navigation);
        $this->assertStringContainsString('route: "membership.index"', $navigation);
        $this->assertStringContainsString('route: "contact"', $navigation);

        $this->assertStringNotContainsString('Apply for membership', $navbar);
        $this->assertStringNotContainsString("route('dashboard')", $navbar);
        $this->assertStringNotContainsString("route('admin.dashboard')", $navbar);
        $this->assertStringNotContainsString("route('membership.apply')", $navbar);
        $this->assertStringContainsString('frontendMenus', $navbar);
        $this->assertStringContainsString('xl:flex', $navbar);

        $this->assertStringNotContainsString('Apply for membership', $sidebar);
        $this->assertStringContainsString('PublicTopbarUtilities', $sidebar);
        $this->assertStringContainsString('min-width: 1280px', $sidebar);

        $this->assertStringContainsString('ThemeToggle', $utilities);
        $this->assertStringContainsString('LanguageSwitcher', $utilities);
        $this->assertStringContainsString("route('admin.dashboard')", $utilities);
        $this->assertStringContainsString('can_access_admin', $utilities);
        $this->assertStringNotContainsString('Apply for membership', $utilities);

        $this->assertStringContainsString('PublicTopbarUtilities', $layout);
        $this->assertStringNotContainsString('Apply for membership', $layout);
    }

    public function test_guest_does_not_receive_admin_utility_and_keeps_membership_pathways(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.can_access_admin', false)
                ->where('auth.linked_to_member', false)
                ->where('membershipCta.learn_url', route('membership.index'))
                ->where('membershipCta.apply_url', route('membership.apply')));

        $this->get(route('membership.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Membership/Index')
                ->where('applyUrl', route('membership.apply'))
                ->where('auth.can_access_admin', false));
    }

    public function test_platform_staff_receive_admin_utility_and_ordinary_accounts_do_not(): void
    {
        $staff = $this->superAdmin();
        $ordinary = User::factory()->create();
        $linked = User::factory()->create();
        Member::factory()->linked($linked)->create();

        $this->actingAs($staff)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.can_access_admin', true));

        $this->actingAs($ordinary)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.can_access_admin', false)
                ->where('auth.linked_to_member', false));

        $this->actingAs($linked)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.can_access_admin', false)
                ->where('auth.linked_to_member', true));

        $this->actingAs($ordinary)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($linked)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_localization_keeps_canonical_membership_and_dashboard_keys(): void
    {
        $id = json_decode((string) file_get_contents(lang_path('id/sources/membership.json')), true);
        $en = json_decode((string) file_get_contents(lang_path('en/sources/membership.json')), true);
        $idNav = json_decode((string) file_get_contents(lang_path('id/sources/navigation.json')), true);
        $enNav = json_decode((string) file_get_contents(lang_path('en/sources/navigation.json')), true);

        $this->assertSame('Ajukan Keanggotaan', $id['Apply for membership']);
        $this->assertSame('Apply for membership', $en['Apply for membership']);
        $this->assertSame('Pelajari Keanggotaan', $id['Learn about membership']);
        $this->assertSame('Learn about membership', $en['Learn about membership']);
        $this->assertSame('Dasbor', $idNav['Dashboard']);
        $this->assertSame('Dashboard', $enNav['Dashboard']);
        $this->assertSame('Lapak Anggota', $idNav['Lapak Anggota']);
        $this->assertSame('Member marketplace', $enNav['Lapak Anggota']);
        $this->assertSame('Teknologi Tani', $idNav['Teknologi Tani']);
        $this->assertSame('Agricultural Technology', $enNav['Teknologi Tani']);

        $idLanding = json_decode((string) file_get_contents(lang_path('id/sources/landing.json')), true);
        $enLanding = json_decode((string) file_get_contents(lang_path('en/sources/landing.json')), true);
        $this->assertSame('Teknologi Tani', $idLanding['Teknologi Tani']);
        $this->assertSame('Agricultural Technology', $enLanding['Teknologi Tani']);

        $idCompiled = json_decode((string) file_get_contents(lang_path('id.json')), true);
        $enCompiled = json_decode((string) file_get_contents(lang_path('en.json')), true);
        $this->assertSame('Teknologi Tani', $idCompiled['Teknologi Tani']);
        $this->assertSame('Agricultural Technology', $enCompiled['Teknologi Tani']);
        $this->assertSame('Agricultural Technology article list', $enCompiled['Knowledge article list']);
        $this->assertSame('Back to Agricultural Technology', $enCompiled['Back to knowledge articles']);
        $this->assertSame('Change to:', $enCompiled['Change to']);
        $this->assertSame('Ganti ke:', $idCompiled['Change to']);
    }
}
