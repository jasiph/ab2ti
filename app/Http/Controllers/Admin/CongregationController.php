<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Congregation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CongregationController extends Controller
{
    public function index(Request $request)
    {
        $query = Congregation::query()
            ->when($request->search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('unit_number', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                }
                );
            })
            ->when($request->status, function ($query, $status) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        })
            ->when($request->gender, function ($query, $gender) {
            if ($gender !== 'all') {
                $query->where('gender', $gender);
            }
        });

        $congregations = $query->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Congregation/Index', [
            'congregations' => $congregations,
            'filters' => $request->only(['search', 'status', 'gender']),
        ]);
    }

    public function apiSearch(Request $request)
    {
        $search = $request->query('search');

        if (!$search) {
            return response()->json([]);
        }

        $congregations = Congregation::query()
            ->search($search) // Using the scopeSearch defined in the model
            ->limit(10)
            ->get(['id', 'name', 'unit_number']);

        return response()->json($congregations);
    }

    public function create()
    {
        return Inertia::render('Admin/Congregation/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_number' => 'required|unique:congregations,unit_number',
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string',
            'status' => 'required|in:active,inactive,moved,deceased',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'father_id' => 'nullable|exists:congregations,id',
            'mother_id' => 'nullable|exists:congregations,id',
            'spouse_id' => 'nullable|exists:congregations,id',
            'head_of_family_id' => 'nullable|exists:congregations,id',
            // Add other fields as needed
        ]);

        Congregation::create($validated);

        return redirect()->route('admin.congregations.index')
            ->with('success', 'Congregation member created successfully.');
    }

    public function show(Congregation $congregation)
    {
        $congregation->load(['father', 'mother', 'spouse', 'familyMembers']);
        $congregation->append('children');

        return Inertia::render('Admin/Congregation/Show', [
            'congregation' => $congregation
        ]);
    }

    public function edit(Congregation $congregation)
    {
        $congregation->load(['father', 'mother', 'spouse', 'headOfFamily']);

        return Inertia::render('Admin/Congregation/Form', [
            'congregation' => $congregation
        ]);
    }

    public function update(Request $request, Congregation $congregation)
    {
        $validated = $request->validate([
            'unit_number' => 'required|unique:congregations,unit_number,' . $congregation->id,
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string',
            'status' => 'required|in:active,inactive,moved,deceased',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'father_id' => 'nullable|exists:congregations,id',
            'mother_id' => 'nullable|exists:congregations,id',
            'spouse_id' => 'nullable|exists:congregations,id',
            'head_of_family_id' => 'nullable|exists:congregations,id',
        ]);

        $congregation->update($validated);

        return redirect()->route('admin.congregations.index')
            ->with('success', 'Congregation member updated successfully.');
    }

    public function destroy(Congregation $congregation)
    {
        $congregation->delete();

        return redirect()->route('admin.congregations.index')
            ->with('success', 'Congregation member deleted successfully.');
    }

    public function upcomingBirthdays()
    {
        $currentMonth = now()->month;

        $birthdays = Congregation::query()
            ->active()
            ->whereMonth('date_of_birth', $currentMonth)
            ->orderByRaw('DAY(date_of_birth) ASC')
            ->get(['id', 'name', 'date_of_birth', 'photo_path']);

        return response()->json($birthdays);
    }
}
