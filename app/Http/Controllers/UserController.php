<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        return $request->user();
    }


    public function index(Request $request)
    {
        $query = User::query()
            // Muat relasi roles untuk ditampilkan di tabel
            ->with('roles:id,name')
            ->latest();

        // Implementasi filter search (jika ada)
        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . $request->name . '%');
        }

        $users = $query->paginate(10);
        return UserResource::collection($users);
    }

    public function update(Request $request, User $user)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Email harus unik, tapi abaikan user yang sedang diedit
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            // Wajib ada role_id dan harus ada di tabel 'roles'
            'role_id' => 'required|exists:roles,id',
        ]);

        try {
            DB::beginTransaction();

            // 2. Update Detail User
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
            ]);

            // 3. Update Peran (Role)
            // Asumsi: Setiap user hanya punya satu peran utama
            $user->roles()->sync([$validated['role_id']]);

            DB::commit();

            return new UserResource($user);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengupdate pengguna.'], 500);
        }
    }

    /**
     * [ADMIN] Menghapus pengguna.
     * Rute: DELETE /api/admin/users/{user}
     */
    public function destroy(User $user)
    {
        //@ var $user
        // Periksa apakah user mencoba menghapus dirinya sendiri (self-delete prevention)
        if (auth()->check() && auth()->id() === $user->id) {
            return response()->json(['message' => 'Anda tidak bisa menghapus akun Anda sendiri.'], 403);
        }

        $user->delete();

        return response()->json(null, 204); // 204 No Content
    }

    public function store(Request $request)
    {
        // 1. Validasi Input (Wajib ada password)
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            // Password wajib dan harus diconfirm (karena create)
            'password' => 'required|string|min:6|confirmed',
            // role_id wajib untuk menentukan peran awal
            'role_id' => 'required|exists:roles,id',
        ]);

        try {
            DB::beginTransaction();

            // 2. Buat User Baru
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'password' => Hash::make($validated['password']), // <-- Enkripsi password
            ]);

            // 3. Tetapkan Peran (Role)
            $user->roles()->sync([$validated['role_id']]);

            DB::commit();

            // Load relasi roles sebelum mengembalikan resource
            $user->load('roles');

            return response()->json(new UserResource($user), 201); // 201 Created

        } catch (\Exception $e) {
            DB::rollBack();
            // Tangkap error jika terjadi kesalahan database/server
            return response()->json(['message' => 'Gagal membuat pengguna baru.'], 500);
        }
    }
}
