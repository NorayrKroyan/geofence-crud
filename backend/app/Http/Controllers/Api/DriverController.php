<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Driver::query()
            ->orderBy('name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Driver $driver) => $this->transform($driver))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function show(Driver $driver): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->transform($driver),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $driver = Driver::create($this->validated($request));

        return response()->json([
            'success' => true,
            'message' => 'Driver created successfully.',
            'data' => $this->transform($driver->fresh()),
        ], 201);
    }

    public function update(Request $request, Driver $driver): JsonResponse
    {
        $driver->update($this->validated($request));

        return response()->json([
            'success' => true,
            'message' => 'Driver updated successfully.',
            'data' => $this->transform($driver->fresh()),
        ]);
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $driver->delete();

        return response()->json([
            'success' => true,
            'message' => 'Driver deleted successfully.',
        ]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'string', 'max:60'],
        ]);
    }

    protected function transform(Driver $driver): array
    {
        return [
            'id' => $driver->id,
            'name' => $driver->name,
            'mobile_number' => $driver->mobile_number,
            'status' => $driver->status,
            'created_at' => optional($driver->created_at)->toIso8601String(),
            'updated_at' => optional($driver->updated_at)->toIso8601String(),
        ];
    }
}
