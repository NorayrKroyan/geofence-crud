<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Geofence::query()
            ->orderBy('name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Geofence $geofence) => $this->transform($geofence))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function show(Geofence $geofence): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->transform($geofence),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $geofence = Geofence::create($this->validated($request, true));

        return response()->json([
            'success' => true,
            'message' => 'Geofence created successfully.',
            'data' => $this->transform($geofence->fresh()),
        ], 201);
    }

    public function update(Request $request, Geofence $geofence): JsonResponse
    {
        $geofence->update($this->validated($request, false));

        return response()->json([
            'success' => true,
            'message' => 'Geofence updated successfully.',
            'data' => $this->transform($geofence->fresh()),
        ]);
    }

    public function destroy(Geofence $geofence): JsonResponse
    {
        $geofence->delete();

        return response()->json([
            'success' => true,
            'message' => 'Geofence deleted successfully.',
        ]);
    }

    protected function validated(Request $request, bool $isCreate): array
    {
        $data = $request->validate([
            'trigger_zone' => ['nullable'],
            'bounding_box' => ['nullable'],
            'bounding_box_center' => ['nullable'],
            'name' => ['required', 'string', 'max:255'],
            'center_point_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'center_point_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'speed_limit_kph' => ['nullable', 'integer', 'min:0'],
            'entry_action' => ['nullable', 'string', 'max:40'],
            'exit_action' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'is_delete' => ['nullable', 'boolean'],
            'expire_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'geometry_json' => [$isCreate ? 'required' : 'sometimes'],
            'polygon_points' => ['nullable'],
        ]);

        foreach (['trigger_zone', 'bounding_box', 'bounding_box_center', 'geometry_json', 'polygon_points'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizeJsonish($data[$field], $field === 'geometry_json');
            }
        }

        if (! array_key_exists('color', $data) || blank($data['color'])) {
            $data['color'] = '#2563eb';
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }

        if (array_key_exists('is_delete', $data)) {
            $data['is_delete'] = (bool) $data['is_delete'];
        }

        return $data;
    }

    protected function normalizeJsonish(mixed $value, bool $required = false): ?string
    {
        if ($value === null || $value === '') {
            return $required ? null : null;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function decodeJson(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    protected function transform(Geofence $geofence): array
    {
        return [
            'id' => $geofence->id,
            'trigger_zone' => $geofence->trigger_zone,
            'trigger_zone_data' => $this->decodeJson($geofence->trigger_zone),
            'bounding_box' => $geofence->bounding_box,
            'bounding_box_data' => $this->decodeJson($geofence->bounding_box),
            'bounding_box_center' => $geofence->bounding_box_center,
            'bounding_box_center_data' => $this->decodeJson($geofence->bounding_box_center),
            'name' => $geofence->name,
            'center_point_lat' => $geofence->center_point_lat !== null ? (float) $geofence->center_point_lat : null,
            'center_point_lng' => $geofence->center_point_lng !== null ? (float) $geofence->center_point_lng : null,
            'speed_limit_kph' => $geofence->speed_limit_kph,
            'entry_action' => $geofence->entry_action,
            'exit_action' => $geofence->exit_action,
            'color' => $geofence->color,
            'is_active' => (bool) $geofence->is_active,
            'is_delete' => (bool) $geofence->is_delete,
            'expire_date' => optional($geofence->expire_date)->toIso8601String(),
            'notes' => $geofence->notes,
            'geometry_json' => $geofence->geometry_json,
            'geometry' => $this->decodeJson($geofence->geometry_json),
            'polygon_points' => $geofence->polygon_points,
            'polygon_points_array' => $this->decodeJson($geofence->polygon_points),
            'created_at' => optional($geofence->created_at)->toIso8601String(),
            'updated_at' => optional($geofence->updated_at)->toIso8601String(),
        ];
    }
}
