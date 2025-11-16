<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Field;
use Illuminate\Http\Request;
use App\Http\Resources\FieldOperatingHoursResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FieldOperatingHoursController extends Controller
{
    /**
     * Menampilkan 7 hari jam operasional untuk satu lapangan.
     * Rute: GET /api/admin/fields/{field}/operating-hours
     */
    public function index(Field $field)
    {
        // Ambil 7 record, urutkan berdasarkan hari (1=Senin, 7=Minggu)
        $operatingHours = $field->operatingHours()
            ->orderBy('day_of_week', 'asc')
            ->get();

        return FieldOperatingHoursResource::collection($operatingHours);
    }

    /**
     * Mengupdate 7 hari jam operasional untuk satu lapangan.
     * Rute: PUT /api/admin/fields/{field}/operating-hours
     */
    public function update(Request $request, Field $field)
    {
        $validated = $request->validate([
            'hours' => 'required|array|size:7',
            'hours.*.day_of_week' => 'required|integer|between:1,7',
            'hours.*.is_open' => 'required|boolean', // <-- TAMBAHKAN VALIDASI
            'hours.*.start_time' => 'required|date_format:H:i',
            'hours.*.end_time' => 'required|date_format:H:i',
        ]);

        try {
            DB::transaction(function () use ($field, $validated) {
                foreach ($validated['hours'] as $hourData) {
                    $field->operatingHours()->updateOrCreate(
                        ['day_of_week' => $hourData['day_of_week']],
                        [
                            'start_time' => $hourData['start_time'],
                            'end_time' => $hourData['end_time'],
                            'is_open' => $hourData['is_open'], // <-- TAMBAHKAN UPDATE
                        ]
                    );
                }
            });
            return $this->index($field);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengupdate jam operasional.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function name()
    {
        return Field::select('id', 'name')->get();
    }
}
