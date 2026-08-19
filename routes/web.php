<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Session;

//language switch
Route::get('/language/{language}', function ($language) {
    // Validasi input agar hanya 'id' atau 'en' yang diterima
    if (!in_array($language, ['en', 'id'])) {
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
    return app(\App\Http\Controllers\PageController::class)->show('about');
})->name('about');
Route::get('/sermons', function () {
    return app(\App\Http\Controllers\PageController::class)->show('sermons');
})->name('sermons');
Route::get('/events', function () {
    return app(\App\Http\Controllers\PageController::class)->show('events');
})->name('events');
Route::get('/contact', function () {
    return app(\App\Http\Controllers\PageController::class)->show('contact');
})->name('contact');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class , 'index'])->name('dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->prefix('admin') // URL: /admin/users
    ->name('admin.') // Route Name: admin.users.index
    ->group(function () {

        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class , 'index'])->name('dashboard');

        // User Management Routes
        Route::resource('users', UserController::class);
        Route::resource('roles', RoleController::class);

        // File Manager
        Route::get('files/json', [\App\Http\Controllers\Admin\FileManagerController::class , 'getJson'])->name('files.json');
        Route::post('files/upload-json', [\App\Http\Controllers\Admin\FileManagerController::class , 'storeJson'])->name('files.upload-json');
        Route::post('files/{file}/restore', [\App\Http\Controllers\Admin\FileManagerController::class , 'restore'])->name('files.restore');
        Route::delete('files/{file}/force', [\App\Http\Controllers\Admin\FileManagerController::class , 'forceDelete'])->name('files.force-delete');
        Route::resource('files', \App\Http\Controllers\Admin\FileManagerController::class);

        // Hero Management
        Route::resource('heroes', \App\Http\Controllers\Admin\HeroController::class);

        Route::get('welcome-section', [\App\Http\Controllers\Admin\WelcomeSectionController::class, 'edit'])->name('welcome-section.edit');
        Route::put('welcome-section', [\App\Http\Controllers\Admin\WelcomeSectionController::class, 'update'])->name('welcome-section.update');
        Route::resource('pages', \App\Http\Controllers\Admin\PageController::class);
        Route::patch('/pages/{page}/toggle', [\App\Http\Controllers\Admin\PageController::class, 'toggle'])->name('pages.toggle');
        Route::get('/block-templates/active', [\App\Http\Controllers\Admin\BlockTemplateController::class, 'getActiveBlocks'])->name('block-templates.active');
        Route::resource('block-templates', \App\Http\Controllers\Admin\BlockTemplateController::class);
        Route::patch('/block-templates/{block_template}/toggle', [\App\Http\Controllers\Admin\BlockTemplateController::class, 'toggle'])->name('block-templates.toggle');

        // Song Management
        Route::resource('songs', \App\Http\Controllers\Admin\SongController::class);
        Route::resource('song-categories', \App\Http\Controllers\Admin\SongCategoryController::class)->except(['create', 'edit', 'show']);

        // Church Administration
        Route::get('congregations/birthdays', [\App\Http\Controllers\Admin\CongregationController::class , 'upcomingBirthdays'])->name('congregations.birthdays');
        Route::get('congregations/search', [\App\Http\Controllers\Admin\CongregationController::class , 'apiSearch'])->name('congregations.api.search');
        Route::resource('congregations', \App\Http\Controllers\Admin\CongregationController::class);
        Route::resource('letter-templates', \App\Http\Controllers\Admin\LetterTemplateController::class);
        Route::resource('documents', \App\Http\Controllers\Admin\DocumentController::class);
        Route::resource('letters', \App\Http\Controllers\Admin\LetterController::class);
        Route::post('letters/{letter}/approve', [\App\Http\Controllers\Admin\LetterController::class , 'approve'])->name('letters.approve');
        Route::get('letters/{letter}/print', [\App\Http\Controllers\Admin\LetterController::class , 'print'])->name('letters.print');
    });

// Public Verification
Route::get('/verify/doc/{letter}', [\App\Http\Controllers\Admin\LetterController::class , 'verify'])->name('letter.verify');

// Frontend CMS catch-all route (keep at bottom to avoid conflicts)
Route::get('/{slug}', [\App\Http\Controllers\PageController::class, 'show'])
    ->where('slug', '.*')
    ->name('pages.show');
