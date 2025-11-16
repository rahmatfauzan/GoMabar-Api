<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SportCategory;
use App\Http\Resources\SportCategoryResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SportCategoryController extends Controller
{
    /**
     * Menampilkan semua kategori. (Publik)
     */
    // app/Http/Controllers/Api/Admin/SportCategoryController.php
    public function index(Request $request) // Tambahkan Request
    {
        $query = SportCategory::query()->withCount('fields');

        // (Tambahkan filter server-side)
        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . $request->query('name') . '%');
        }

        // Ganti .get() menjadi .paginate()
        $categories = $query->orderBy('name')->paginate(15);

        return SportCategoryResource::collection($categories);
    }

    /**
     * Menyimpan kategori baru. (Admin)
     */
    // app/Http/Controllers/Api/Admin/SportCategoryController.php

    public function store(Request $request)
    {
        // --- PERBAIKI VALIDASI INI ---
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:sport_categories',
            // Ganti 'image' menjadi 'string'
            'icon' => 'required|string|max:100',
        ]);
        // ----------------------------

        $category = SportCategory::create($validated);

        return response()->json(new SportCategoryResource($category), 201);
    }
    /**
     * Menampilkan satu kategori. (Publik)
     */
    public function show(SportCategory $sportCategory)
    {
        return new SportCategoryResource($sportCategory);
    }

    /**
     * Meng-update kategori. (Admin)
     */
    // app/Http/Controllers/Api/Admin/SportCategoryController.php

    public function update(Request $request, SportCategory $sportCategory)
    {
        // --- PERBAIKI VALIDASI INI ---
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('sport_categories')->ignore($sportCategory->id)],
            // Ganti 'image' menjadi 'string'
            'icon' => 'sometimes|required|string|max:100',
        ]);
        // ----------------------------

        $sportCategory->update($validated);

        return new SportCategoryResource($sportCategory);
    }

    /**
     * Menghapus kategori. (Admin)
     */
    public function destroy(SportCategory $sportCategory)
    {
        // Hapus file ikon dari storage
        if ($sportCategory->icon) {
            Storage::disk('public')->delete($sportCategory->icon);
        }

        // Hapus record dari database
        $sportCategory->delete();

        return response()->json(null, 204); // 204 No Content
    }
}
