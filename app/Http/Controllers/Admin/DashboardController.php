<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Congregation;
use App\Models\Letter;
use App\Models\Song;
use App\Models\ArchivedDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Congregation Stats
        $totalCongregation = Congregation::active()->count();
        $totalMale = Congregation::active()->where('gender', 'male')->count();
        $totalFemale = Congregation::active()->where('gender', 'female')->count();
        $totalFamilies = Congregation::whereNotNull('head_of_family_id')
            ->distinct('head_of_family_id')
            ->count('head_of_family_id');

        // Menu/Feature Stats
        $totalSongs = Song::count();
        $pendingLetters = Letter::where('status', '!=', 'approved')->count(); // Assuming 'approved' is the final status
        $totalDocuments = ArchivedDocument::count();

        // Recent Members
        $recentMembers = Congregation::active()
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'status', 'created_at', 'photo_path']);

        // Upcoming Birthdays (Logic from CongregationController)
        $currentMonth = now()->month;
        $upcomingBirthdays = Congregation::active()
            ->whereMonth('date_of_birth', $currentMonth)
            ->orderByRaw('DAY(date_of_birth) ASC')
            ->limit(5)
            ->get(['id', 'name', 'date_of_birth', 'photo_path']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalCongregation' => $totalCongregation,
                'totalMale' => $totalMale,
                'totalFemale' => $totalFemale,
                'totalFamilies' => $totalFamilies,
                'totalSongs' => $totalSongs,
                'pendingLetters' => $pendingLetters,
                'totalDocuments' => $totalDocuments,
            ],
            'recentMembers' => $recentMembers,
            'upcomingBirthdays' => $upcomingBirthdays,
        ]);
    }
}
