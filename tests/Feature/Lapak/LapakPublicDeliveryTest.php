<?php

namespace Tests\Feature\Lapak;

use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Enums\LapakPriceMode;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LapakPublicDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://ab2ti.test',
            'app.name' => 'AB2TI 2.0',
            'public_delivery.canonical_origin' => 'https://ab2ti.test',
            'public_delivery.site_name' => 'AB2TI 2.0',
            'public_delivery.default_title' => 'AB2TI 2.0',
            'public_delivery.default_description' => 'Asosiasi Bank Benih dan Teknologi Tani Indonesia (AB2TI).',
            'public_delivery.default_og_image_path' => '/images/og/ab2ti-og-default-v1.jpg',
        ]);
    }

    public function test_directory_lists_only_public_eligible_listings_and_active_categories(): void
    {
        $lapak = $this->publishedLapak();
        $benih = LapakCategory::factory()->create(['name' => 'Benih', 'slug' => 'benih']);
        $inactive = LapakCategory::factory()->inactive()->create(['name' => 'Arsip', 'slug' => 'arsip']);

        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $benih->id,
            'title' => 'Benih jagung tayang',
            'slug' => 'benih-jagung-tayang',
        ]);
        LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $benih->id,
            'title' => 'Draf rahasia',
            'slug' => 'draf-rahasia',
        ]);
        LapakListing::factory()->published()->unavailable()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $benih->id,
            'title' => 'Tidak tersedia',
            'slug' => 'tidak-tersedia',
        ]);
        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $inactive->id,
            'title' => 'Kategori nonaktif',
            'slug' => 'kategori-nonaktif',
        ]);

        $this->get('/lapak')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Lapak/Index')
                ->has('listings.data', 1)
                ->where('listings.data.0.title', 'Benih jagung tayang')
                ->where('listings.data.0.url', '/lapak/'.$lapak->slug.'/produk/benih-jagung-tayang')
                ->missing('listings.data.0.moderation_status')
                ->missing('listings.data.0.moderation_note')
                ->missing('listings.data.0.created_by')
                ->missing('listings.data.0.member_name')
                ->has('categories', 1)
                ->where('categories.0.slug', 'benih'));
    }

    public function test_directory_search_is_server_side_and_ignores_private_member_fields(): void
    {
        $member = Member::factory()->create([
            'legal_name' => 'Rahasia Nama Pribadi',
            'email' => 'rahasia-anggota@example.com',
            'phone' => '081299999999',
        ]);
        $lapak = $this->publishedLapak($member, [
            'name' => 'Lapak Tani Sejahtera',
            'slug' => 'lapak-tani-sejahtera',
        ]);
        $category = LapakCategory::factory()->create(['name' => 'Pangan', 'slug' => 'pangan']);

        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Beras organik',
            'slug' => 'beras-organik',
            'summary' => 'Beras dari sawah anggota.',
        ]);
        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Pupuk kompos',
            'slug' => 'pupuk-kompos',
        ]);

        $this->get('/lapak?q=beras')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('listings.data', 1)
                ->where('listings.data.0.title', 'Beras organik')
                ->where('filters.q', 'beras'));

        $this->get('/lapak?q=rahasia-anggota@example.com')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('listings.data', []));

        $this->get('/lapak?q=081299999999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('listings.data', []));
    }

    public function test_active_category_filter_and_invalid_category_are_safe(): void
    {
        $lapak = $this->publishedLapak();
        $benih = LapakCategory::factory()->create(['name' => 'Benih', 'slug' => 'benih']);
        $pupuk = LapakCategory::factory()->create(['name' => 'Pupuk', 'slug' => 'pupuk']);
        LapakCategory::factory()->inactive()->create(['name' => 'Arsip', 'slug' => 'arsip']);

        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $benih->id,
            'title' => 'Benih jagung',
            'slug' => 'benih-jagung',
        ]);
        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $pupuk->id,
            'title' => 'Pupuk kandang',
            'slug' => 'pupuk-kandang',
        ]);

        $this->get('/lapak?kategori=benih')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('listings.data', 1)
                ->where('listings.data.0.title', 'Benih jagung')
                ->where('filters.kategori', 'benih'));

        $this->get('/lapak?kategori=arsip')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listings.data', [])
                ->where('filters.kategori', 'arsip'));

        $this->get('/lapak?kategori=tidak-ada')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('listings.data', []));
    }

    public function test_pagination_preserves_search_and_category_query(): void
    {
        $lapak = $this->publishedLapak();
        $category = LapakCategory::factory()->create(['name' => 'Benih', 'slug' => 'benih']);

        foreach (range(1, 13) as $index) {
            LapakListing::factory()->published()->create([
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'title' => 'Beras paket '.$index,
                'slug' => 'beras-paket-'.$index,
            ]);
        }

        $this->get('/lapak?q=beras&kategori=benih&page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'beras')
                ->where('filters.kategori', 'benih')
                ->has('listings.data', 1)
                ->where('listings.current_page', 2));
    }

    public function test_directory_empty_state_does_not_error(): void
    {
        $this->get('/lapak')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Lapak/Index')
                ->where('listings.data', [])
                ->where('categories', []));
    }

    public function test_published_storefront_is_public_and_omits_private_member_data(): void
    {
        $account = User::factory()->create(['email' => 'akun-pemilik@example.com']);
        $member = Member::factory()->linked($account)->create([
            'legal_name' => 'Rahasia Nama Pribadi',
            'email' => 'rahasia-anggota@example.com',
            'phone' => '081288888888',
        ]);
        $lapak = $this->publishedLapak($member, [
            'name' => 'Lapak Tani Sejahtera',
            'slug' => 'lapak-tani-sejahtera',
            'summary' => 'Etalase benih petani.',
            'contact_phone' => '081234567890',
            'contact_whatsapp' => '081234567890',
            'contact_email' => 'lapak-publik@example.com',
            'moderation_note' => 'Catatan staf rahasia',
        ]);
        $category = LapakCategory::factory()->create();
        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Benih jagung',
            'slug' => 'benih-jagung',
        ]);
        LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Draf diam',
            'slug' => 'draf-diam',
        ]);

        $html = $this->get('/lapak/lapak-tani-sejahtera')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Lapak/Show')
                ->where('lapak.name', 'Lapak Tani Sejahtera')
                ->where('lapak.slug', 'lapak-tani-sejahtera')
                ->missing('lapak.member')
                ->missing('lapak.member_name')
                ->missing('lapak.member_id')
                ->missing('lapak.user_id')
                ->missing('lapak.moderation_note')
                ->missing('lapak.moderation_status')
                ->missing('lapak.created_by')
                ->missing('lapak.contact_phone')
                ->has('listings.data', 1)
                ->where('listings.data.0.title', 'Benih jagung'))
            ->getContent();

        $this->assertStringNotContainsString('Rahasia Nama Pribadi', $html);
        $this->assertStringNotContainsString('rahasia-anggota@example.com', $html);
        $this->assertStringNotContainsString('akun-pemilik@example.com', $html);
        $this->assertStringNotContainsString('081288888888', $html);
        $this->assertStringNotContainsString('Catatan staf rahasia', $html);
        $this->assertStringNotContainsString('Draf diam', $html);
        $this->assertStringContainsString('lapak-publik@example.com', $html);
    }

    public function test_unpublished_storefronts_return_branded_404_without_moderation_leak(): void
    {
        $cases = [
            [LapakModerationStatus::Draft, 'toko-tertutup-satu'],
            [LapakModerationStatus::PendingReview, 'toko-tertutup-dua'],
            [LapakModerationStatus::Rejected, 'toko-tertutup-tiga'],
            [LapakModerationStatus::Suspended, 'toko-tertutup-empat'],
        ];

        foreach ($cases as [$status, $slug]) {
            Lapak::factory()->create([
                'slug' => $slug,
                'moderation_status' => $status,
                'moderation_note' => 'Alasan internal staf',
            ]);

            $html = $this->get('/lapak/'.$slug)
                ->assertNotFound()
                ->assertInertia(fn ($page) => $page->component('Errors/Show')->where('status', 404))
                ->getContent();

            $this->assertStringNotContainsString('Alasan internal staf', $html);
            $this->assertStringNotContainsString('Menunggu tinjauan', $html);
            $this->assertStringNotContainsString('This listing is pending review', $html);
        }
    }

    public function test_listing_detail_is_public_only_when_fully_eligible(): void
    {
        $lapak = $this->publishedLapak(['slug' => 'lapak-publik']);
        $category = LapakCategory::factory()->create(['name' => 'Benih', 'slug' => 'benih']);
        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Benih Jagung Hibrida',
            'slug' => 'benih-jagung-hibrida',
            'summary' => 'Benih musim tanam ini.',
            'price_mode' => LapakPriceMode::Fixed,
            'price_amount' => 25000,
            'price_unit' => 'kg',
        ]);

        $this->get('/lapak/lapak-publik/produk/benih-jagung-hibrida')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Lapak/Listing')
                ->where('listing.title', 'Benih Jagung Hibrida')
                ->where('listing.price_label', 'Rp 25.000 / kg')
                ->where('listing.category_name', 'Benih')
                ->where('listing.lapak.name', $lapak->name)
                ->missing('listing.moderation_note')
                ->missing('listing.moderation_status')
                ->missing('listing.created_by')
                ->missing('listing.member_name')
                ->missing('listing.id')
                ->missing('listing.lapak.member_name'));

        $listing->forceFill(['moderation_status' => LapakModerationStatus::Draft])->save();
        $this->get('/lapak/lapak-publik/produk/benih-jagung-hibrida')->assertNotFound();
    }

    public function test_ineligible_listings_return_404(): void
    {
        $lapak = $this->publishedLapak(['slug' => 'lapak-induk']);
        $category = LapakCategory::factory()->create();

        $cases = [
            ['slug' => 'masih-draf', 'moderation_status' => LapakModerationStatus::Draft],
            ['slug' => 'menunggu', 'moderation_status' => LapakModerationStatus::PendingReview],
            ['slug' => 'ditolak', 'moderation_status' => LapakModerationStatus::Rejected],
            ['slug' => 'ditangguhkan', 'moderation_status' => LapakModerationStatus::Suspended],
        ];

        foreach ($cases as $case) {
            LapakListing::factory()->create([
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'slug' => $case['slug'],
                'moderation_status' => $case['moderation_status'],
                'availability' => LapakListingAvailability::Available,
            ]);

            $this->get('/lapak/lapak-induk/produk/'.$case['slug'])->assertNotFound();
        }

        LapakListing::factory()->published()->unavailable()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'slug' => 'tidak-tersedia',
        ]);
        $this->get('/lapak/lapak-induk/produk/tidak-tersedia')->assertNotFound();

        $inactive = LapakCategory::factory()->inactive()->create();
        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $inactive->id,
            'slug' => 'kategori-mati',
        ]);
        $this->get('/lapak/lapak-induk/produk/kategori-mati')->assertNotFound();

        $draftLapak = Lapak::factory()->create(['slug' => 'lapak-draf']);
        LapakListing::factory()->published()->create([
            'lapak_id' => $draftLapak->id,
            'category_id' => $category->id,
            'slug' => 'induk-belum-tayang',
        ]);
        $this->get('/lapak/lapak-draf/produk/induk-belum-tayang')->assertNotFound();
        $this->get('/lapak/lapak-draf')->assertNotFound();
    }

    public function test_nested_listing_must_belong_to_the_named_lapak(): void
    {
        $category = LapakCategory::factory()->create();
        $lapakA = $this->publishedLapak(['slug' => 'lapak-a', 'name' => 'Lapak A']);
        $lapakB = $this->publishedLapak(['slug' => 'lapak-b', 'name' => 'Lapak B']);

        LapakListing::factory()->published()->create([
            'lapak_id' => $lapakB->id,
            'category_id' => $category->id,
            'title' => 'Milik B',
            'slug' => 'milik-b',
        ]);

        $this->get('/lapak/lapak-a/produk/milik-b')->assertNotFound();
        $this->get('/lapak/lapak-b/produk/milik-b')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('listing.title', 'Milik B'));
    }

    public function test_public_price_labels_do_not_expose_raw_enums(): void
    {
        $lapak = $this->publishedLapak(['slug' => 'lapak-harga']);
        $category = LapakCategory::factory()->create();

        $cases = [
            ['slug' => 'harga-tetap', 'mode' => LapakPriceMode::Fixed, 'amount' => 25000, 'unit' => 'kg', 'label' => 'Rp 25.000 / kg'],
            ['slug' => 'harga-mulai', 'mode' => LapakPriceMode::From, 'amount' => 25000, 'unit' => null, 'label' => 'Mulai Rp 25.000'],
            ['slug' => 'harga-nego', 'mode' => LapakPriceMode::Negotiable, 'amount' => null, 'unit' => null, 'label' => 'Harga dapat ditanyakan'],
            ['slug' => 'harga-tersembunyi', 'mode' => LapakPriceMode::Hidden, 'amount' => null, 'unit' => null, 'label' => 'Hubungi penjual untuk harga'],
        ];

        foreach ($cases as $case) {
            LapakListing::factory()->published()->create([
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'slug' => $case['slug'],
                'price_mode' => $case['mode'],
                'price_amount' => $case['amount'],
                'price_unit' => $case['unit'],
            ]);

            $this->get('/lapak/lapak-harga/produk/'.$case['slug'])
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('listing.price_label', $case['label'])
                    ->missing('listing.price_mode'));
        }
    }

    public function test_contact_uses_only_lapak_fields_and_safe_hrefs(): void
    {
        $member = Member::factory()->create([
            'email' => 'anggota@example.com',
            'phone' => '081211111111',
        ]);
        $lapak = $this->publishedLapak($member, [
            'slug' => 'lapak-kontak',
            'name' => 'Lapak Kontak',
            'contact_phone' => '081234567890',
            'contact_whatsapp' => '081234567890',
            'contact_email' => 'penjual@example.com',
        ]);
        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => LapakCategory::factory(),
            'title' => 'Benih Jagung',
            'slug' => 'benih-jagung',
        ]);

        $this->get('/lapak/lapak-kontak/produk/benih-jagung')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listing.lapak.contact.0.type', 'whatsapp')
                ->where('listing.lapak.contact.0.href', fn ($href) => is_string($href)
                    && str_starts_with($href, 'https://wa.me/6281234567890')
                    && str_contains($href, rawurlencode('Halo, saya melihat produk "Benih Jagung" di Lapak Anggota AB2TI.')))
                ->where('listing.lapak.contact.1.type', 'phone')
                ->where('listing.lapak.contact.1.href', 'tel:+6281234567890')
                ->where('listing.lapak.contact.2.type', 'email')
                ->where('listing.lapak.contact.2.href', 'mailto:penjual@example.com')
                ->where('listing.lapak.contact', fn ($channels) => collect($channels)->every(
                    fn ($channel) => ! str_contains((string) ($channel['href'] ?? ''), 'javascript:')
                        && ! str_contains((string) ($channel['href'] ?? ''), 'anggota@example.com')
                        && ! str_contains((string) ($channel['href'] ?? ''), '081211111111')
                )));

        $unsafe = $this->publishedLapak(['slug' => 'lapak-tidak-aman']);
        $unsafe->forceFill([
            'contact_phone' => 'javascript:alert(1)',
            'contact_whatsapp' => 'https://evil.example/phish',
            'contact_email' => 'not-an-email',
        ])->save();
        LapakListing::factory()->published()->create([
            'lapak_id' => $unsafe->id,
            'category_id' => LapakCategory::factory(),
            'slug' => 'produk-aman',
        ]);

        $this->get('/lapak/lapak-tidak-aman/produk/produk-aman')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listing.lapak.contact', []));
    }

    public function test_directory_storefront_and_listing_receive_server_side_seo(): void
    {
        Storage::fake('public');

        $lapak = $this->publishedLapak([
            'name' => 'Lapak Tani Sejahtera',
            'slug' => 'lapak-tani-sejahtera',
            'summary' => 'Etalase benih petani.',
        ]);
        $lapak->addMedia(UploadedFile::fake()->image('cover.jpg', 1200, 630))
            ->toMediaCollection('lapak-cover');

        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => LapakCategory::factory(),
            'title' => 'Benih Jagung Hibrida',
            'slug' => 'benih-jagung-hibrida',
            'summary' => 'Benih musim tanam ini.',
        ]);
        $listing->addMedia(UploadedFile::fake()->image('produk.jpg', 800, 800))
            ->toMediaCollection('listing-images');

        $directory = $this->get('/lapak?q=beras&kategori=benih')->assertOk()->getContent();
        $this->assertSame('https://ab2ti.test/lapak', $this->linkHref($directory, 'canonical'));
        $this->assertSame('Member marketplace', $this->metaContent($directory, 'property', 'og:title'));
        $this->assertStringContainsString('AB2TI members', (string) $this->metaContent($directory, 'name', 'description'));
        $this->assertSame('index, follow', $this->metaContent($directory, 'name', 'robots'));
        $this->assertSame('website', $this->metaContent($directory, 'property', 'og:type'));
        $this->assertSame('summary_large_image', $this->metaContent($directory, 'name', 'twitter:card'));
        $this->assertSame(
            'https://ab2ti.test/images/og/ab2ti-og-default-v1.jpg',
            $this->metaContent($directory, 'property', 'og:image'),
        );

        $storefront = $this->get('/lapak/lapak-tani-sejahtera')->assertOk()->getContent();
        $this->assertSame('https://ab2ti.test/lapak/lapak-tani-sejahtera', $this->linkHref($storefront, 'canonical'));
        $this->assertSame('Lapak Tani Sejahtera', $this->metaContent($storefront, 'property', 'og:title'));
        $this->assertSame('Etalase benih petani.', $this->metaContent($storefront, 'name', 'description'));
        $this->assertStringStartsWith('https://ab2ti.test/', (string) $this->metaContent($storefront, 'property', 'og:image'));
        $this->assertStringNotContainsString('ab2ti-og-default-v1.jpg', (string) $this->metaContent($storefront, 'property', 'og:image'));

        $detail = $this->get('/lapak/lapak-tani-sejahtera/produk/benih-jagung-hibrida')->assertOk()->getContent();
        $this->assertSame(
            'https://ab2ti.test/lapak/lapak-tani-sejahtera/produk/benih-jagung-hibrida',
            $this->linkHref($detail, 'canonical'),
        );
        $this->assertSame('Benih Jagung Hibrida — Lapak Tani Sejahtera', $this->metaContent($detail, 'property', 'og:title'));
        $this->assertSame('Benih musim tanam ini.', $this->metaContent($detail, 'name', 'description'));
        $this->assertStringStartsWith('https://ab2ti.test/', (string) $this->metaContent($detail, 'property', 'og:image'));
        $this->assertStringNotContainsString('ab2ti-og-default-v1.jpg', (string) $this->metaContent($detail, 'property', 'og:image'));
    }

    public function test_public_navigation_owns_lapak_prefix_and_cms_does_not_shadow_it(): void
    {
        $this->assertTrue(Route::has('lapak.index'));
        $this->assertTrue(Route::has('lapak.show'));
        $this->assertTrue(Route::has('lapak.listings.show'));
        $this->assertTrue(Route::has('account.lapak.index'));
        $this->assertTrue(Route::has('admin.lapak.storefronts.index'));

        $navigation = (string) file_get_contents(resource_path('js/Composables/useNavigation.js'));
        $this->assertStringContainsString('route: "lapak.index"', $navigation);
        $this->assertStringContainsString('label: "Lapak Anggota"', $navigation);

        $this->get('/lapak')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Frontend/Lapak/Index'));
        $this->get('/lapak/tidak-ada')->assertNotFound();
    }

    public function test_homepage_shows_only_public_eligible_lapak_listings(): void
    {
        $lapak = $this->publishedLapak();
        $category = LapakCategory::factory()->create();
        LapakListing::factory()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Draf homepage',
            'slug' => 'draf-homepage',
        ]);
        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Produk homepage',
            'slug' => 'produk-homepage',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Landing/Welcome')
                ->has('latestLapakListings', 1)
                ->where('latestLapakListings.0.title', 'Produk homepage')
                ->where('latestLapakListings.0.url', '/lapak/'.$lapak->slug.'/produk/produk-homepage')
                ->missing('latestLapakListings.0.created_by')
                ->missing('latestLapakListings.0.moderation_note')
                ->missing('latestLapakListings.0.member')
                ->missing('latestLapakListings.0.user_id'));
    }

    public function test_search_edge_queries_stay_parameterized_and_public_field_only(): void
    {
        $lapak = $this->publishedLapak(['name' => 'Lapak Benih']);
        $category = LapakCategory::factory()->create(['name' => 'Benih', 'slug' => 'benih']);
        LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Benih Jagung',
            'slug' => 'benih-jagung',
            'summary' => 'Unggul musim tanam',
        ]);

        $this->get('/lapak?q=')->assertOk();
        $this->get('/lapak?q=%20%20%20')->assertOk()->assertInertia(fn ($page) => $page
            ->has('listings.data', 1)
            ->where('filters.q', ''));
        $this->get('/lapak?q='.str_repeat('jagung', 40))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', fn ($term) => is_string($term) && mb_strlen($term) === 100));
        $this->get('/lapak?q=%25')->assertOk()->assertInertia(fn ($page) => $page->where('listings.data', []));
        $this->get('/lapak?q=_')->assertOk()->assertInertia(fn ($page) => $page->where('listings.data', []));
        $this->get('/lapak?q=%27')->assertOk();
        $this->get('/lapak?q=%22')->assertOk();
        $this->get('/lapak?q=JAGUNG')->assertOk()->assertInertia(fn ($page) => $page->has('listings.data', 1));
        $this->get('/lapak?q=jagung&kategori=benih')->assertOk()->assertInertia(fn ($page) => $page
            ->has('listings.data', 1)
            ->where('filters.kategori', 'benih'));
        $this->get('/lapak?q=jagung&kategori=tidak-ada')->assertOk()->assertInertia(fn ($page) => $page
            ->where('listings.data', []));
        $this->get('/lapak?q=jagung&kategori=arsip-nonaktif')->assertOk()->assertInertia(fn ($page) => $page
            ->where('listings.data', []));
        $this->get('/lapak?q=tidak-ada-hasil-xyz')->assertOk()->assertInertia(fn ($page) => $page
            ->where('listings.data', []));
        $this->get('/lapak?q=benih-jagung-ユニコード')->assertOk();
    }

    public function test_pagination_edge_pages_are_safe_and_keep_filters(): void
    {
        $lapak = $this->publishedLapak();
        $category = LapakCategory::factory()->create(['name' => 'Benih', 'slug' => 'benih']);

        foreach (range(1, 13) as $index) {
            LapakListing::factory()->published()->create([
                'lapak_id' => $lapak->id,
                'category_id' => $category->id,
                'title' => 'Beras paket '.$index,
                'slug' => 'beras-paket-'.$index,
            ]);
        }

        $this->get('/lapak?q=beras&kategori=benih&page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'beras')
                ->where('filters.kategori', 'benih')
                ->where('listings.current_page', 1)
                ->has('listings.data', 12));
        $this->get('/lapak?page=0')->assertOk()->assertInertia(fn ($page) => $page->where('listings.current_page', 1));
        $this->get('/lapak?page=-1')->assertOk()->assertInertia(fn ($page) => $page->where('listings.current_page', 1));
        $this->get('/lapak?page=abc')->assertOk()->assertInertia(fn ($page) => $page->where('listings.current_page', 1));
        $this->get('/lapak?q=beras&kategori=benih&page=999999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'beras')
                ->where('filters.kategori', 'benih')
                ->where('listings.data', []));
    }

    public function test_directory_sort_is_published_then_id_descending(): void
    {
        $lapak = $this->publishedLapak();
        $category = LapakCategory::factory()->create();
        $older = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Lebih lama',
            'slug' => 'lebih-lama',
            'published_at' => now()->subDay(),
        ]);
        $newerA = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Baru A',
            'slug' => 'baru-a',
            'published_at' => now(),
        ]);
        $newerB = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => $category->id,
            'title' => 'Baru B',
            'slug' => 'baru-b',
            'published_at' => $newerA->published_at,
        ]);

        $this->get('/lapak')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listings.data.0.title', 'Baru B')
                ->where('listings.data.1.title', 'Baru A')
                ->where('listings.data.2.title', 'Lebih lama'));

        $this->assertTrue($newerB->id > $newerA->id);
        $this->assertTrue($newerA->id > $older->id);
    }

    public function test_malformed_optional_public_data_does_not_error(): void
    {
        $member = Member::factory()->create([
            'legal_name' => 'Nama Pribadi Rahasia',
            'email' => 'pribadi@example.com',
        ]);
        $lapak = $this->publishedLapak($member, [
            'name' => str_repeat('Toko Panjang 漢字 ', 12),
            'slug' => 'lapak-rapuh',
            'summary' => null,
            'description' => null,
            'location_text' => null,
            'contact_phone' => null,
            'contact_whatsapp' => null,
            'contact_email' => null,
        ]);
        $listing = LapakListing::factory()->published()->create([
            'lapak_id' => $lapak->id,
            'category_id' => LapakCategory::factory(),
            'title' => str_repeat('Judul ユニコード ', 10),
            'slug' => 'produk-rapuh',
            'summary' => null,
            'description' => null,
            'price_mode' => LapakPriceMode::Fixed,
            'price_amount' => null,
            'price_unit' => '???',
        ]);

        $this->get('/lapak/lapak-rapuh')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Lapak/Show')
                ->where('lapak.contact', [])
                ->missing('lapak.member_name')
                ->missing('lapak.email')
                ->missing('lapak.user_id'));

        $this->get('/lapak/lapak-rapuh/produk/produk-rapuh')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listing.price_label', '— / ???')
                ->where('listing.images', [])
                ->missing('listing.moderation_status')
                ->missing('listing.created_by')
                ->missing('listing.user_id'));

        $html = $this->get('/lapak/lapak-rapuh/produk/produk-rapuh')->assertOk()->getContent();
        $this->assertSame(
            'https://ab2ti.test/images/og/ab2ti-og-default-v1.jpg',
            $this->metaContent($html, 'property', 'og:image'),
        );
        $this->assertStringStartsWith('Judul', (string) $this->metaContent($html, 'property', 'og:title'));
        $this->assertLessThanOrEqual(160, mb_strlen((string) $this->metaContent($html, 'property', 'og:title')));
    }

    /**
     * @param  array<string, mixed>|Member|null  $member
     * @param  array<string, mixed>  $overrides
     */
    private function publishedLapak(Member|array|null $member = null, array $overrides = []): Lapak
    {
        if (is_array($member)) {
            $overrides = $member;
            $member = null;
        }

        return Lapak::factory()->published()->create(array_merge([
            'member_id' => $member?->id ?? Member::factory(),
            'contact_phone' => '081234567890',
        ], $overrides));
    }

    private function metaContent(string $html, string $attribute, string $name): ?string
    {
        $pattern = '/<meta '.$attribute.'="'.preg_quote($name, '/').'" content="([^"]*)"/';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function linkHref(string $html, string $rel): ?string
    {
        $pattern = '/<link rel="'.preg_quote($rel, '/').'" href="([^"]*)"/';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
