<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // 1. Ambil data user dengan pagination & search
        $users = User::with('roles') // Eager load roles agar tidak N+1 Query
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->latest() // Urutkan dari yang terbaru
            ->paginate(10)
            ->withQueryString(); // Agar parameter search tetap ada saat pindah halaman

        // 2. Render ke Vue (Folder Admin)
        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search']),
            // Kirim list role untuk dropdown di Modal Tambah/Edit nanti
            'availableRoles' => Role::all()->pluck('name'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|exists:roles,name', // Pastikan role ada di database
        ]);

        // 2. Buat User Baru
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // 3. Assign Role (Spatie)
        $user->assignRole($validated['role']);

        // 4. Redirect kembali dengan pesan sukses (SystemToast)
        return redirect()->back()->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // Proteksi: Jangan biarkan user biasa mengedit Super Admin
        if ($user->hasRole('super-admin') && !auth()->user()->hasRole('super-admin')) {
            return back()->with('error', 'Anda tidak memiliki akses untuk mengedit Super Admin.');
        }

        // 1. Validasi (Email harus unique kecuali punya user ini sendiri)
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|exists:roles,name',
        ]);

        // 2. Update Data Dasar
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        // 3. Sync Role (Ganti role lama dengan yang baru)
        $user->syncRoles([$validated['role']]);

        return redirect()->back()->with('success', 'Data user berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Proteksi Self-Delete
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri saat sedang login.');
        }

        // Proteksi Super Admin
        if ($user->hasRole('super-admin')) {
            return back()->with('error', 'Super Admin tidak dapat dihapus.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User berhasil dihapus.');
    }
}
