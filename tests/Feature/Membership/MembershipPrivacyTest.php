<?php

namespace Tests\Feature\Membership;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAcl;
use Tests\Concerns\SubmitsPublicMembershipApplication;
use Tests\TestCase;

class MembershipPrivacyTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;
    use SubmitsPublicMembershipApplication;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('membership_documents');

        config([
            'app.url' => 'https://ab2ti.test',
            'public_delivery.canonical_origin' => 'https://ab2ti.test',
            'public_delivery.site_name' => 'AB2TI 2.0',
            'public_delivery.default_title' => 'AB2TI 2.0',
            'public_delivery.default_og_image_path' => '/images/og/ab2ti-og-default-v1.jpg',
        ]);
    }

    public function test_intake_and_confirmation_are_not_indexable_and_do_not_put_pii_in_metadata(): void
    {
        $applyHtml = $this->get(route('membership.apply'))->assertOk()->getContent();

        $this->assertSame('noindex, nofollow', $this->metaContent($applyHtml, 'name', 'robots'));
        $this->assertStringNotContainsString('sari@example.com', $applyHtml);
        $this->assertSame(
            'https://ab2ti.test/images/og/ab2ti-og-default-v1.jpg',
            $this->metaContent($applyHtml, 'property', 'og:image'),
        );

        $this->postPublicMembership([
            'legal_name' => 'Sari Petani',
            'email' => 'sari@example.com',
            'phone' => '081234567890',
            'address' => 'Desa Sukamaju',
        ]);

        $confirmHtml = $this->get(route('membership.apply.submitted'))->assertOk()->getContent();
        $this->assertSame('noindex, nofollow', $this->metaContent($confirmHtml, 'name', 'robots'));
        $this->assertNotSame('Sari Petani', $this->metaContent($confirmHtml, 'property', 'og:title'));
        $this->assertStringNotContainsString('sari@example.com', $this->metaContent($confirmHtml, 'property', 'og:title') ?? '');
        $this->assertStringNotContainsString('sari@example.com', $this->metaContent($confirmHtml, 'property', 'og:description') ?? '');
    }

    public function test_informational_membership_landing_is_indexable_without_pii(): void
    {
        $html = $this->get(route('membership.index'))->assertOk()->getContent();

        $this->assertSame('index, follow', $this->metaContent($html, 'name', 'robots'));
        $this->assertSame('Membership', $this->metaContent($html, 'property', 'og:title'));
    }

    public function test_admin_membership_pages_are_noindex(): void
    {
        $user = $this->userWithPermissions([
            'membership-applications.view',
            'membership-members.view',
        ]);
        $application = MembershipApplication::factory()->create([
            'legal_name' => 'Rahasia Nama',
            'email' => 'rahasia@example.com',
        ]);
        $member = Member::factory()->create([
            'legal_name' => 'Rahasia Anggota',
            'email' => 'anggota-rahasia@example.com',
        ]);

        $html = $this->actingAs($user)
            ->get(route('admin.membership.applications.show', $application))
            ->assertOk()
            ->getContent();

        $this->assertSame('noindex, nofollow', $this->metaContent($html, 'name', 'robots'));
        $this->assertNotSame('Rahasia Nama', $this->metaContent($html, 'property', 'og:title'));
        $this->assertStringNotContainsString(
            'rahasia@example.com',
            $this->metaContent($html, 'property', 'og:description') ?? '',
        );

        $memberHtml = $this->actingAs($user)
            ->get(route('admin.membership.members.show', $member))
            ->assertOk()
            ->getContent();

        $this->assertSame('noindex, nofollow', $this->metaContent($memberHtml, 'name', 'robots'));
        $this->assertStringNotContainsString(
            'anggota-rahasia@example.com',
            $this->metaContent($memberHtml, 'property', 'og:description') ?? '',
        );
    }

    public function test_document_bytes_require_document_view_permission_not_media_view(): void
    {
        $application = MembershipApplication::factory()->create();
        $document = $this->attachPrivateDocument($application);

        $this->get(route('admin.membership.documents.show', $document))
            ->assertRedirect(route('login'));

        $mediaUser = $this->userWithPermissions(['media.view', 'membership-applications.view']);
        $this->actingAs($mediaUser)
            ->get(route('admin.membership.documents.show', $document))
            ->assertForbidden();

        $unprivileged = User::factory()->create();
        $this->actingAs($unprivileged)
            ->get(route('admin.membership.documents.show', $document))
            ->assertForbidden();

        $allowed = $this->userWithPermissions(['membership-documents.view']);
        $this->actingAs($allowed)
            ->get(route('admin.membership.documents.show', $document))
            ->assertOk();
    }

    public function test_admin_detail_does_not_serialize_document_storage_paths(): void
    {
        $user = $this->userWithPermissions([
            'membership-applications.view',
            'membership-documents.view',
        ]);
        $application = MembershipApplication::factory()->create();
        $this->attachPrivateDocument($application);

        $this->actingAs($user)
            ->get(route('admin.membership.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Membership/Applications/Show')
                ->has('application.documents.0.id')
                ->has('application.documents.0.original_filename')
                ->missing('application.documents.0.object_key')
                ->missing('application.documents.0.disk')
                ->missing('application.object_key'));
    }

    private function attachPrivateDocument(MembershipApplication $application): MembershipDocument
    {
        $file = UploadedFile::fake()->image('ktp.jpg');
        $key = $application->id.'/identity_document/test.jpg';
        Storage::disk('membership_documents')->put($key, $file->getContent());

        return $application->documents()->create([
            'document_type' => 'identity_document',
            'disk' => 'membership_documents',
            'object_key' => $key,
            'original_filename' => 'ktp.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12,
            'uploaded_at' => now(),
            'uploaded_by_context' => 'applicant',
        ]);
    }

    private function metaContent(string $html, string $attribute, string $name): ?string
    {
        $pattern = '/<meta '.$attribute.'="'.preg_quote($name, '/').'" content="([^"]*)"/';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
