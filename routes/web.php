<?php

use App\Http\Controllers\Admin\BlockTemplateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FileManagerController;
use App\Http\Controllers\Admin\HeroController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WelcomeSectionController;
use App\Http\Controllers\PageController;
use App\Support\CmsPath;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

// language switch
Route::get('/language/{language}', function ($language) {
    // Validasi input agar hanya 'id' atau 'en' yang diterima
    if (! in_array($language, ['en', 'id'])) {
        abort(400);
    }
    // Simpan ke session
    Session::put('locale', $language);

    // Kembali ke halaman sebelumnya
    return back()->with('success', $language === 'id' ? 'beralih ke bahasa Indonesia' : 'switch to English');
})->name('language.switch');

Route::get('/', function () {
    $hero = \App\Models\Hero::active()->with('media')->inRandomOrder()->first();
    $heroData = null;

    if ($hero) {
        $heroData = [
            'title' => $hero->title,
            'primary_text' => $hero->primary_text,
            'secondary_text' => $hero->secondary_text,
            'button_text' => $hero->button_text,
            'button_url' => $hero->button_url,
            'images' => $hero->getMedia('hero-backgrounds')->map(function ($media) {
                return $media->getUrl('large');
            }
            )->values(),
        ];
    }

    $welcome = \App\Models\WelcomeSection::query()
        ->active()
        ->latest()
        ->first();

    $welcomeSection = null;
    if ($welcome) {
        $welcomeSection = [
            'title' => $welcome->title,
            'content' => $welcome->content,
            'secondary_content' => $welcome->secondary_content,
            'image_url' => $welcome->image_url,
            'cta_label' => $welcome->cta_label,
            'cta_url' => $welcome->cta_url,
        ];
    }

    return Inertia::render('Frontend/Landing/Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'hero' => $heroData,
        'welcomeSection' => $welcomeSection,
    ]);
})->name('welcome');

// Frontend CMS shortcuts (mapped to dynamic pages)
Route::get('/about', function () {
    return app(PageController::class)->show('about');
})->name('about');
Route::get('/sermons', function () {
    return app(PageController::class)->show('sermons');
})->name('sermons');
Route::get('/events', function () {
    return app(PageController::class)->show('events');
})->name('events');
Route::get('/contact', function () {
    return app(PageController::class)->show('contact');
})->name('contact');

$authenticate = [
    'auth:sanctum',
    config('jetstream.auth_session'),
];

Route::middleware($authenticate)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware($authenticate)
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::middleware('permission:users.view')->group(function () {
            Route::resource('users', UserController::class)->only(['index', 'show']);
        });
        Route::middleware('permission:users.create')->group(function () {
            Route::resource('users', UserController::class)->only(['create', 'store']);
        });
        Route::middleware('permission:users.update')->group(function () {
            Route::resource('users', UserController::class)->only(['edit', 'update']);
        });
        Route::middleware('permission:users.delete')->group(function () {
            Route::resource('users', UserController::class)->only(['destroy']);
        });

        Route::middleware('permission:roles.manage')->group(function () {
            Route::resource('roles', RoleController::class);
        });

        Route::middleware('permission:media.view')->group(function () {
            Route::get('files/json', [FileManagerController::class, 'getJson'])->name('files.json');
            Route::resource('files', FileManagerController::class)->only(['index', 'show']);
        });
        Route::middleware('permission:media.create')->group(function () {
            Route::post('files/upload-json', [FileManagerController::class, 'storeJson'])->name('files.upload-json');
            Route::resource('files', FileManagerController::class)->only(['create', 'store']);
        });
        Route::middleware('permission:media.update')->group(function () {
            Route::post('files/{file}/restore', [FileManagerController::class, 'restore'])
                ->whereNumber('file')
                ->name('files.restore');
            Route::resource('files', FileManagerController::class)->only(['edit', 'update']);
        });
        Route::middleware('permission:media.delete')->group(function () {
            Route::delete('files/{file}/force', [FileManagerController::class, 'forceDelete'])
                ->whereNumber('file')
                ->name('files.force-delete');
            Route::resource('files', FileManagerController::class)->only(['destroy']);
        });

        Route::middleware('permission:heroes.view')->group(function () {
            Route::resource('heroes', HeroController::class)->only(['index', 'show']);
        });
        Route::middleware('permission:heroes.create')->group(function () {
            Route::resource('heroes', HeroController::class)->only(['create', 'store']);
        });
        Route::middleware('permission:heroes.update')->group(function () {
            Route::resource('heroes', HeroController::class)->only(['edit', 'update']);
        });
        Route::middleware('permission:heroes.delete')->group(function () {
            Route::resource('heroes', HeroController::class)->only(['destroy']);
        });

        Route::middleware('permission:cms.view')->group(function () {
            Route::get('welcome-section', [WelcomeSectionController::class, 'edit'])->name('welcome-section.edit');
            Route::get('block-templates/active', [BlockTemplateController::class, 'getActiveBlocks'])->name('block-templates.active');
            Route::resource('pages', AdminPageController::class)->only(['index', 'show']);
            Route::resource('block-templates', BlockTemplateController::class)->only(['index', 'show']);
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
    });

// Frontend CMS catch-all (keep last). Nested slugs remain allowed; reserved
// first segments are excluded via config/cms.php.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', CmsPath::catchAllConstraint())
    ->name('pages.show');
