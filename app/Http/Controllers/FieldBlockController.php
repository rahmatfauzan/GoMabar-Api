<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\FieldBlock;
use App\Http\Resources\FieldBlockResource;
use Illuminate\Http\Request;
use Carbon\Carbon;

class FieldBlockController extends Controller
{
    /**
     * Menampilkan semua jadwal blokir untuk satu lapangan.
     * Rute: GET /api/admin/fields/{field}/blocks
     */
    public function index(Field $field)
    {
        $blocks = $field->blocks()->orderBy('start_datetime', 'desc')->get();
        return FieldBlockResource::collection($blocks);
    }

    /**
     * Menyimpan jadwal blokir baru.
     * Rute: POST /api/admin/fields/{field}/blocks
     */
    public function store(Request $request, Field $field)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
            'start_datetime' => 'required|date|after_or_equal:today',
            'end_datetime' => 'required|date|after:start_datetime',
        ]);

        // Cek overlap (opsional tapi bagus)
        // (Logika untuk mengecek apakah blokir baru ini tumpang tindih
        // dengan booking yang sudah 'active')
        // ...

        $block = $field->blocks()->create($validated);

        return response()->json(new FieldBlockResource($block), 201);
    }

    /**
     * Menghapus jadwal blokir.
     * Rute: DELETE /api/admin/blocks/{fieldBlock}
     */
    public function destroy(string $id) // <-- Menerima ID sebagai string
    {
        // 1. Cari modelnya secara manual
        $fieldBlock = FieldBlock::findOrFail($id);
        $fieldBlock->delete();

        return response()->json(null, 204); // 204 No Content
    }
}
