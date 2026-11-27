<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('units', 'name')],
        ]);

        $unit = Unit::create([
            'name' => strtolower(trim($validated['name'])),
            'is_active' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['unit' => $unit], 201);
        }

        return back()->with('status', "Satuan '{$unit->name}' berhasil ditambahkan.");
    }
}
