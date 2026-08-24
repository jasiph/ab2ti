<?php

use App\Enums\PublicationType;
use App\Http\Controllers\Account\MemberAccountLinkController;
use App\Http\Controllers\Account\OwnerLapakController;
use App\Http\Controllers\Account\OwnerLapakListingController;
use App\Http\Controllers\Admin\BlockTemplateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FileManagerController;
use App\Http\Controllers\Admin\HeroController;
use App\Http\Controllers\Admin\Knowledge\KnowledgeArticleController as AdminKnowledgeArticleController;
use App\Http\Controllers\Admin\Lapak\LapakCategoryController;
use App\Http\Controllers\Admin\Lapak\LapakController;
use App\Http\Controllers\Admin\Lapak\LapakListingController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\MembershipApplicationController;
use App\Http\Controllers\Admin\MembershipDocumentController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PublicationController as AdminPublicationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WelcomeSectionController;
use App\Http\Controllers\Knowledge\PublicKnowledgeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Lapak\PublicLapakController;
use App\Http\Controllers\Membership\MembershipRevisionController;
use App\Http\Controllers\Membership\PublicMembershipController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PhpunitErrorController;
use App\Http\Controllers\PublicDiscoveryController;
use App\Http\Controllers\Publishing\PublicPublicationController;
use App\Http\Controllers\SecureCapability\CapabilityRedeemController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\ApplyCapabilitySecurityHeaders;
use App\Http\Middleware\RequireMembershipRevisionCapability;
use App\Support\CmsPath;
use Illuminate\Support\Facades\Route;

Route::get('/language/{language}', LanguageController::class)
    ->whereIn('language', ['en', 'id'])
    ->name('language.switch');

if (app()->runningUnitTests()) {
    Route::get('/__phpunit/expired', [PhpunitErrorController::class, 'expired']);
    Route::get('/__phpunit/server-error', [PhpunitErrorController::class, 'serverError']);
}

Route::get('/robots.txt', [PublicDiscoveryController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [PublicDiscoveryController::class, 'sitemap'])->name('sitemap');

Route::get('/', WelcomeController::class)->name('welcome');

Route::get('/keanggotaan', [PublicMembershipController::class, 'index'])->name('membership.index');
Route::get('/keanggotaan/daftar', [PublicMembershipController::class, 'create'])->name('membership.apply');
Route::post('/keanggotaan/daftar', [PublicMembershipController::class, 'store'])
    ->middleware('throttle:membership-intake')
    ->name('membership.apply.store');
Route::get('/keanggotaan/daftar/terkirim', [PublicMembershipController::class, 'submitted'])
    ->name('membership.apply.submitted');

Route::middleware(ApplyCapabilitySecurityHeaders::class)->group(function () {
    Route::get('/akses/revisi/terkirim', [MembershipRevisionController::class, 'submitted'])
        ->name('capability.membership.revision.submitted');

    Route::middleware(['throttle:capability-revision', RequireMembershipRevisionCapability::class])
        ->group(function () {
            Route::get('/akses/revisi', [MembershipRevisionController::class, 'show'])
                ->name('capability.membership.revision.show');
            Route::post('/akses/revisi', [MembershipRevisionController::class, 'update'])
                ->name('capability.membership.revision.update');
        });

    Route::get('/akses/{secret}', CapabilityRedeemController::class)
        ->middleware('throttle:capability-redeem')
        ->where('secret', '[A-Za-z0-9_-]{20,128}')
        ->name('capability.redeem');
});

$publicationTypeConstraint = implode('|', PublicationType::publicPathConstraint());

Route::get('/publikasi', [PublicPublicationController::class, 'index'])->name('publications.index');
Route::get('/{type}', [PublicPublicationController::class, 'typeIndex'])
    ->where('type', $publicationTypeConstraint)
    ->name('publications.type');
Route::get('/{type}/{slug}', [PublicPublicationController::class, 'show'])
    ->where([
        'type' => $publicationTypeConstraint,
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])
    ->name('publications.show');

Route::get('/teknologi-tani', [PublicKnowledgeController::class, 'index'])->name('knowledge.index');
Route::get('/teknologi-tani/{slug}', [PublicKnowledgeController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('knowledge.show');

Route::get('/lapak', [PublicLapakController::class, 'index'])->name('lapak.index');
Route::get('/lapak/{lapak}', [PublicLapakController::class, 'show'])
    ->where('lapak', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('lapak.show');
Route::get('/lapak/{lapak}/produk/{listing}', [PublicLapakController::class, 'listing'])
    ->where([
        'lapak' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'listing' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])
    ->name('lapak.listings.show');

Route::get('/about', [PageController::class, 'show'])
    ->defaults('slug', 'about')
    ->name('about');
Route::get('/contact', [PageController::class, 'show'])
    ->defaults('slug', 'contact')
    ->name('contact');

$authenticate = [
    'auth:sanctum',
    config('jetstream.auth_session'),
];

$authenticateStaff = [...$authenticate, 'platform-staff'];

Route::middleware($authenticateStaff)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware('throttle:capability-redeem')->group(function () {
    Route::get('/akun/tautkan/{secret}', [MemberAccountLinkController::class, 'show'])
        ->where('secret', '[A-Za-z0-9_-]{20,128}')
        ->name('account.member-link.show');
    Route::post('/akun/tautkan/{secret}/aktivasi', [MemberAccountLinkController::class, 'activate'])
        ->where('secret', '[A-Za-z0-9_-]{20,128}')
        ->name('account.member-link.activate');
});

Route::middleware([...$authenticate, 'throttle:capability-redeem'])->group(function () {
    Route::post('/akun/tautkan/{secret}', [MemberAccountLinkController::class, 'confirm'])
        ->where('secret', '[A-Za-z0-9_-]{20,128}')
        ->name('account.member-link.confirm');
});

Route::middleware($authenticate)
    ->prefix('akun/lapak')
    ->name('account.lapak.')
    ->group(function () {
        Route::get('/', [OwnerLapakController::class, 'index'])->name('index');
        Route::get('/buat', [OwnerLapakController::class, 'create'])->name('create');
        Route::post('/', [OwnerLapakController::class, 'store'])->name('store');

        Route::get('/produk', [OwnerLapakListingController::class, 'index'])->name('listings.index');
        Route::get('/produk/buat', [OwnerLapakListingController::class, 'create'])->name('listings.create');
        Route::post('/produk', [OwnerLapakListingController::class, 'store'])->name('listings.store');
        Route::get('/produk/{listing}/ubah', [OwnerLapakListingController::class, 'edit'])->name('listings.edit');
        Route::match(['put', 'post'], '/produk/{listing}', [OwnerLapakListingController::class, 'update'])->name('listings.update');
        Route::post('/produk/{listing}/kirim', [OwnerLapakListingController::class, 'submit'])->name('listings.submit');
        Route::post('/produk/{listing}/ketersediaan', [OwnerLapakListingController::class, 'availability'])->name('listings.availability');

        Route::get('/{lapak}/ubah', [OwnerLapakController::class, 'edit'])->name('edit');
        Route::match(['put', 'post'], '/{lapak}', [OwnerLapakController::class, 'update'])->name('update');
        Route::post('/{lapak}/kirim', [OwnerLapakController::class, 'submit'])->name('submit');
    });

Route::middleware($authenticateStaff)
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::middleware('permission:users.view')->group(function () {
            Route::resource('users', UserController::class)->only(['index']);
        });
        Route::middleware('permission:users.create')->group(function () {
            Route::resource('users', UserController::class)->only(['store']);
        });
        Route::middleware('permission:users.update')->group(function () {
            Route::resource('users', UserController::class)->only(['update']);
        });
        Route::middleware('permission:users.delete')->group(function () {
            Route::resource('users', UserController::class)->only(['destroy']);
        });

        Route::middleware('permission:roles.manage')->group(function () {
            Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        Route::middleware('permission:media.view')->group(function () {
            Route::get('files/json', [FileManagerController::class, 'getJson'])->name('files.json');
            Route::resource('files', FileManagerController::class)->only(['index']);
        });
        Route::middleware('permission:media.create')->group(function () {
            Route::post('files/upload-json', [FileManagerController::class, 'storeJson'])->name('files.upload-json');
            Route::resource('files', FileManagerController::class)->only(['create', 'store']);
        });
        Route::middleware('permission:media.update')->group(function () {
            Route::post('files/{file}/restore', [FileManagerController::class, 'restore'])
                ->whereNumber('file')
                ->name('files.restore');
        });
        Route::middleware('permission:media.delete')->group(function () {
            Route::delete('files/{file}/force', [FileManagerController::class, 'forceDelete'])
                ->whereNumber('file')
                ->name('files.force-delete');
            Route::resource('files', FileManagerController::class)->only(['destroy']);
        });

        Route::middleware('permission:heroes.view')->group(function () {
            Route::resource('heroes', HeroController::class)->only(['index']);
        });
        Route::middleware('permission:heroes.create')->group(function () {
            Route::resource('heroes', HeroController::class)->only(['create', 'store']);
        });
        Route::middleware('permission:heroes.update')->group(function () {
            Route::patch('heroes/{hero}/toggle', [HeroController::class, 'toggle'])->name('heroes.toggle');
            Route::resource('heroes', HeroController::class)->only(['edit', 'update']);
        });
        Route::middleware('permission:heroes.delete')->group(function () {
            Route::resource('heroes', HeroController::class)->only(['destroy']);
        });

        Route::middleware('permission:cms.view')->group(function () {
            Route::get('welcome-section', [WelcomeSectionController::class, 'edit'])->name('welcome-section.edit');
            Route::get('block-templates/active', [BlockTemplateController::class, 'getActiveBlocks'])->name('block-templates.active');
            Route::resource('pages', AdminPageController::class)->only(['index']);
            Route::resource('block-templates', BlockTemplateController::class)->only(['index']);
        });
        Route::middleware('permission:cms.create')->group(function () {
            Route::resource('pages', AdminPageController::class)->only(['create', 'store']);
            Route::resource('block-templates', BlockTemplateController::class)->only(['create', 'store']);
        });
        Route::middleware('permission:cms.update')->group(function () {
            Route::put('welcome-section', [WelcomeSectionController::class, 'update'])->name('welcome-section.update');
            Route::patch('pages/{page}/toggle', [AdminPageController::class, 'toggle'])->name('pages.toggle');
            Route::patch('block-templates/{block_template}/toggle', [BlockTemplateController::class, 'toggle'])->name('block-templates.toggle');
            Route::resource('pages', AdminPageController::class)->only(['edit', 'update']);
            Route::resource('block-templates', BlockTemplateController::class)->only(['edit', 'update']);
        });
        Route::middleware('permission:cms.delete')->group(function () {
            Route::resource('pages', AdminPageController::class)->only(['destroy']);
            Route::resource('block-templates', BlockTemplateController::class)->only(['destroy']);
        });

        Route::middleware('permission:membership-applications.view')->group(function () {
            Route::get('membership/applications', [MembershipApplicationController::class, 'index'])
                ->name('membership.applications.index');
            Route::get('membership/applications/{application}', [MembershipApplicationController::class, 'show'])
                ->name('membership.applications.show');
        });
        Route::middleware('permission:membership-applications.review')->group(function () {
            Route::post('membership/applications/{application}/review', [MembershipApplicationController::class, 'review'])
                ->name('membership.applications.review');
        });
        Route::middleware('permission:membership-applications.approve')->group(function () {
            Route::post('membership/applications/{application}/approve', [MembershipApplicationController::class, 'approve'])
                ->name('membership.applications.approve');
        });
        Route::middleware('permission:membership-applications.reject')->group(function () {
            Route::post('membership/applications/{application}/reject', [MembershipApplicationController::class, 'reject'])
                ->name('membership.applications.reject');
        });
        Route::middleware('permission:membership-applications.request-revision')->group(function () {
            Route::post('membership/applications/{application}/revision', [MembershipApplicationController::class, 'requestRevision'])
                ->name('membership.applications.revision');
        });

        Route::middleware('permission:membership-members.view')->group(function () {
            Route::get('membership/members', [MemberController::class, 'index'])
                ->name('membership.members.index');
            Route::get('membership/members/{member}', [MemberController::class, 'show'])
                ->name('membership.members.show');
        });
        Route::middleware('permission:membership-members.update')->group(function () {
            Route::patch('membership/members/{member}', [MemberController::class, 'update'])
                ->name('membership.members.update');
            Route::post('membership/members/{member}/account-link', [MemberController::class, 'issueAccountLink'])
                ->name('membership.members.account-link');
            Route::delete('membership/members/{member}/account-link', [MemberController::class, 'revokeAccountLink'])
                ->name('membership.members.account-link.revoke');
        });

        Route::middleware('permission:membership-documents.view')->group(function () {
            Route::get('membership/documents/{document}', [MembershipDocumentController::class, 'show'])
                ->name('membership.documents.show');
        });

        Route::middleware('permission:publishing-publications.create')->group(function () {
            Route::get('publishing/publications/create', [AdminPublicationController::class, 'create'])
                ->name('publishing.publications.create');
            Route::post('publishing/publications', [AdminPublicationController::class, 'store'])
                ->name('publishing.publications.store');
        });
        Route::middleware('permission:publishing-publications.view')->group(function () {
            Route::get('publishing/publications', [AdminPublicationController::class, 'index'])
                ->name('publishing.publications.index');
            Route::get('publishing/publications/{publication}/edit', [AdminPublicationController::class, 'edit'])
                ->name('publishing.publications.edit');
        });
        Route::middleware('permission:publishing-publications.update')->group(function () {
            Route::match(['put', 'post'], 'publishing/publications/{publication}', [AdminPublicationController::class, 'update'])
                ->name('publishing.publications.update');
        });
        Route::middleware('permission:publishing-publications.publish')->group(function () {
            Route::post('publishing/publications/{publication}/publish', [AdminPublicationController::class, 'publish'])
                ->name('publishing.publications.publish');
            Route::post('publishing/publications/{publication}/unpublish', [AdminPublicationController::class, 'unpublish'])
                ->name('publishing.publications.unpublish');
        });
        Route::middleware('permission:publishing-publications.delete')->group(function () {
            Route::delete('publishing/publications/{publication}', [AdminPublicationController::class, 'destroy'])
                ->name('publishing.publications.destroy');
        });

        Route::middleware('permission:lapak.manage')->group(function () {
            Route::get('lapak/storefronts/create', [LapakController::class, 'create'])
                ->name('lapak.storefronts.create');
            Route::post('lapak/storefronts', [LapakController::class, 'store'])
                ->name('lapak.storefronts.store');
            Route::get('lapak/storefronts/{lapak}/edit', [LapakController::class, 'edit'])
                ->name('lapak.storefronts.edit');
            Route::match(['put', 'post'], 'lapak/storefronts/{lapak}', [LapakController::class, 'update'])
                ->name('lapak.storefronts.update');

            Route::get('lapak/listings/create', [LapakListingController::class, 'create'])
                ->name('lapak.listings.create');
            Route::post('lapak/listings', [LapakListingController::class, 'store'])
                ->name('lapak.listings.store');
            Route::get('lapak/listings/{listing}/edit', [LapakListingController::class, 'edit'])
                ->name('lapak.listings.edit');
            Route::match(['put', 'post'], 'lapak/listings/{listing}', [LapakListingController::class, 'update'])
                ->name('lapak.listings.update');
            Route::post('lapak/listings/{listing}/availability', [LapakListingController::class, 'availability'])
                ->name('lapak.listings.availability');

            Route::post('lapak/categories', [LapakCategoryController::class, 'store'])
                ->name('lapak.categories.store');
            Route::put('lapak/categories/{category}', [LapakCategoryController::class, 'update'])
                ->name('lapak.categories.update');
            Route::patch('lapak/categories/{category}/toggle', [LapakCategoryController::class, 'toggle'])
                ->name('lapak.categories.toggle');
        });

        Route::middleware('permission:lapak.moderate')->group(function () {
            Route::post('lapak/storefronts/{lapak}/moderate', [LapakController::class, 'moderate'])
                ->name('lapak.storefronts.moderate');
            Route::post('lapak/listings/{listing}/moderate', [LapakListingController::class, 'moderate'])
                ->name('lapak.listings.moderate');
        });

        Route::middleware('permission:lapak.view')->group(function () {
            Route::get('lapak/storefronts', [LapakController::class, 'index'])
                ->name('lapak.storefronts.index');
            Route::get('lapak/storefronts/{lapak}', [LapakController::class, 'show'])
                ->name('lapak.storefronts.show');
            Route::get('lapak/listings', [LapakListingController::class, 'index'])
                ->name('lapak.listings.index');
            Route::get('lapak/listings/{listing}', [LapakListingController::class, 'show'])
                ->name('lapak.listings.show');
            Route::get('lapak/categories', [LapakCategoryController::class, 'index'])
                ->name('lapak.categories.index');
        });

        Route::middleware('permission:knowledge-articles.create')->group(function () {
            Route::get('knowledge/articles/create', [AdminKnowledgeArticleController::class, 'create'])
                ->name('knowledge.articles.create');
            Route::post('knowledge/articles', [AdminKnowledgeArticleController::class, 'store'])
                ->name('knowledge.articles.store');
        });
        Route::middleware('permission:knowledge-articles.view')->group(function () {
            Route::get('knowledge/articles', [AdminKnowledgeArticleController::class, 'index'])
                ->name('knowledge.articles.index');
            Route::get('knowledge/articles/{article}/edit', [AdminKnowledgeArticleController::class, 'edit'])
                ->name('knowledge.articles.edit');
        });
        Route::middleware('permission:knowledge-articles.update')->group(function () {
            Route::match(['put', 'post'], 'knowledge/articles/{article}', [AdminKnowledgeArticleController::class, 'update'])
                ->name('knowledge.articles.update');
        });
        Route::middleware('permission:knowledge-articles.publish')->group(function () {
            Route::post('knowledge/articles/{article}/publish', [AdminKnowledgeArticleController::class, 'publish'])
                ->name('knowledge.articles.publish');
            Route::post('knowledge/articles/{article}/unpublish', [AdminKnowledgeArticleController::class, 'unpublish'])
                ->name('knowledge.articles.unpublish');
        });
        Route::middleware('permission:knowledge-articles.delete')->group(function () {
            Route::delete('knowledge/articles/{article}', [AdminKnowledgeArticleController::class, 'destroy'])
                ->name('knowledge.articles.destroy');
        });
    });

// Frontend CMS catch-all (keep last). Nested slugs remain allowed; reserved
// first segments are excluded via config/cms.php.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', CmsPath::catchAllConstraint())
    ->name('pages.show');
