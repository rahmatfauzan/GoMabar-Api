<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Http\Resources\FieldResource;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FieldController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService
    ) {}

    // app/Http/Controllers/Api/FieldController.php
    // app/Http/Controllers/Api/FieldController.php (atau Admin/FieldController)
    public function index(Request $request)
    {
        $query = Field::query()->with('sportCategory');

        // ... (Filter status) ...
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ... (Filter search 'name') ...
        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . $request->query('name') . '%');
        }

        if ($request->filled('sport_category_id')) {
            $query->where('sport_category_id', $request->query('sport_category_id'));
        }

        // --- TAMBAHKAN LOGIKA SORTING INI ---
        if ($request->filled('sort_by') && $request->filled('sort_dir')) {
            $sortBy = $request->query('sort_by');
            $sortDir = $request->query('sort_dir') === 'desc' ? 'desc' : 'asc';

            // (Opsional: Cek keamanan, pastikan $sortBy ada di whitelist)
            $allowedSorts = ['name', 'price_weekday', 'price_weekend', 'status'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDir);
            }
        } else {
            // Default sorting
            $query->latest(); // (Misal: created_at desc)
        }
        // ------------------------------------

        $fields = $query->paginate(10);
        return FieldResource::collection($fields);
    }

    public function show(Field $field)
    {
        // Player hanya bisa lihat lapangan yang 'active'
        if ($field->status !== 'active') {
            // Cek apakah user adalah admin? (jika auth:sanctum ada)
            if (!auth('sanctum')->check() || auth('sanctum')->user()->role !== 'admin') {
                return response()->json(['message' => 'Lapangan tidak ditemukan'], 404);
            }
        }

        // Load relasi yang dibutuhkan di detail
        $field->load('sportCategory', 'operatingHours');

        return new FieldResource($field);
    }

    public function getAvailability(Request $request, Field $field)
    {
        // 1. Validasi tetap di Controller
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d'
        ]);

        $requestedDate = Carbon::parse($validated['date']);

        // 2. Panggil Service untuk melakukan pekerjaan berat
        $slots = $this->availabilityService->getSlotsForField($field, $requestedDate);

        // 3. Kembalikan hasil dari Service sebagai JSON
        return response()->json($slots);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sport_category_id' => 'required|exists:sport_categories,id',
            'name' => 'required|string|max:255|unique:fields',
            'description' => 'nullable|string',
            'field_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'price_weekday' => 'required|integer|min:0',
            'price_weekend' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        $imagePath = null;

        try {
            DB::beginTransaction();

            if ($request->hasFile('field_photo')) {
                $imagePath = $request->file('field_photo')->store('field_photos', 'public');
            }

            // Buat lapangan
            $field = Field::create([
                'sport_category_id' => $validated['sport_category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price_weekday' => $validated['price_weekday'],
                'price_weekend' => $validated['price_weekend'],
                'status' => $validated['status'],
                'field_photo' => $imagePath,
            ]);

            // Buat Jam Operasional Default (PENTING!)
            for ($day = 1; $day <= 7; $day++) {
                $isWeekend = in_array($day, [6, 7]);
                $field->operatingHours()->create([
                    'day_of_week' => $day,
                    'start_time' => $isWeekend ? '07:00:00' : '08:00:00',
                    'end_time'   => $isWeekend ? '23:00:00' : '22:00:00',
                ]);
            }

            DB::commit();

            return response()->json(new FieldResource($field), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($imagePath) Storage::disk('public')->delete($imagePath);
            return response()->json(['message' => 'Gagal membuat lapangan.', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Field $field)
    {

        $validated = $request->validate([
            'sport_category_id' => 'sometimes|required|exists:sport_categories,id',
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('fields')->ignore($field->id)],
            'description' => 'nullable|string',
            'field_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Cek jika ada file BARU
            'price_weekday' => 'sometimes|required|integer|min:0',
            'price_weekend' => 'sometimes|required|integer|min:0',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        $imagePath = $field->field_photo;

        if ($request->hasFile('field_photo')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('field_photo')->store('field_photos', 'public');
            $validated['field_photo'] = $imagePath; // Set path baru untuk di-update
        }

        $field->update($validated);

        return new FieldResource($field);
    }


    public function destroy(Field $field)
    {
        // Hapus file gambar dari storage
        if ($field->field_photo) {
            Storage::disk('public')->delete($field->field_photo);
        }

        $field->delete();

        return response()->json(null, 204); // 204 No Content
    }
}
