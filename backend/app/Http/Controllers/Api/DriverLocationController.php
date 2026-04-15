<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function devices(): JsonResponse
    {
        $devices = DriverLocation::query()
            ->join('drivers', 'drivers.id', '=', 'driver_locations.user_id')
            ->select('driver_locations.device_id')
            ->selectRaw('MAX(COALESCE(driver_locations.device_dtm, driver_locations.`timestamp`)) AS last_seen_at')
            ->selectRaw('MAX(drivers.name) AS driver_name')
            ->selectRaw('MAX(drivers.mobile_number) AS driver_mobile_number')
            ->whereNotNull('driver_locations.device_id')
            ->whereRaw("TRIM(driver_locations.device_id) <> ''")
            ->whereRaw("CHAR_LENGTH(TRIM(driver_locations.device_id)) >= 9")
            ->whereRaw("LOWER(TRIM(drivers.status)) = 'active'")
            ->groupBy('driver_locations.device_id')
            ->orderBy('driver_locations.device_id')
            ->get()
            ->map(function (DriverLocation $row) {
                $lastSeenAt = $row->getAttribute('last_seen_at');

                $name = trim((string) ($row->getAttribute('driver_name') ?? ''));
                $number = trim((string) ($row->getAttribute('driver_mobile_number') ?? ''));

                $labelParts = array_values(array_filter([
                    $name,
                    $number,
                ], fn ($value) => $value !== ''));

                return [
                    'device_id' => (string) $row->device_id,
                    'label' => count($labelParts)
                        ? implode(' - ', $labelParts)
                        : (string) $row->device_id,
                    'name' => $name !== '' ? $name : null,
                    'mobile_number' => $number !== '' ? $number : null,
                    'last_seen_at' => $lastSeenAt
                        ? CarbonImmutable::parse($lastSeenAt)->toIso8601String()
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'min:9', 'max:255'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
        ]);

        $deviceId = trim($validated['device_id']);

        $startedAt = CarbonImmutable::parse($validated['started_at']);

        $endedAt = CarbonImmutable::parse($validated['ended_at']);

        $rows = DriverLocation::query()
            ->where('device_id', $deviceId)
            ->where(function ($query) use ($startedAt, $endedAt) {
                $query
                    ->whereBetween('timestamp', [$startedAt, $endedAt])
                    ->orWhere(function ($subQuery) use ($startedAt, $endedAt) {
                        $subQuery
                            ->whereNotNull('device_dtm')
                            ->whereBetween('device_dtm', [$startedAt, $endedAt]);
                    });
            })
            ->orderByRaw('COALESCE(device_dtm, `timestamp`) ASC')
            ->orderBy('id')
            ->get();

        $points = [];

        $previous = null;

        foreach ($rows as $row) {
            $points[] = $this->transformRow($row, $previous);

            $previous = $row;
        }

        return response()->json([
            'success' => true,

            'data' => [
                'device_id' => $deviceId,

                'started_at' => $startedAt->toIso8601String(),

                'ended_at' => $endedAt->toIso8601String(),

                'count' => count($points),

                'rows' => $points,
            ],
        ]);
    }

    protected function transformRow(DriverLocation $row, ?DriverLocation $previous): array
    {
        $calculated = $this->calculateDerivedMetrics($previous, $row);
        $tableSpeed = $row->speed !== null ? round((float) $row->speed, 1) : null;

        $tableBearing = $row->direction !== null ? $this->normalizeBearing((float) $row->direction) : null;

        $displaySpeed = $tableSpeed ?? $calculated['speed'];

        $displayBearing = $tableBearing ?? $calculated['bearing'];

        $displayTime = $row->device_dtm ?? $row->timestamp;

        $latitude = $row->latitude !== null ? (float) $row->latitude : null;

        $longitude = $row->longitude !== null ? (float) $row->longitude : null;

        return [
            'id' => $row->id,

            'user_id' => $row->user_id,

            'device_id' => $row->device_id,

            'timestamp' => optional($row->timestamp)->toIso8601String(),

            'device_dtm' => optional($row->device_dtm)->toIso8601String(),

            'display_time' => optional($displayTime)->toIso8601String(),

            'latitude' => $latitude,

            'longitude' => $longitude,

            'speed' => $tableSpeed,

            'bearing' => $tableBearing,

            'calculated_speed' => $calculated['speed'],

            'calculated_bearing' => $calculated['bearing'],

            'display_speed' => $displaySpeed,

            'display_bearing' => $displayBearing,

            'display_bearing_cardinal' => $displayBearing !== null ? $this->bearingToCardinal($displayBearing) : null,

            'speed_source' => $tableSpeed !== null
                ? 'table'
                : ($calculated['speed'] !== null ? 'calculated' : null),

            'bearing_source' => $tableBearing !== null
                ? 'table'
                : ($calculated['bearing'] !== null ? 'calculated' : null),

            'google_maps_url' => ($latitude !== null && $longitude !== null)
                ? sprintf('https://www.google.com/maps?q=%s,%s', $latitude, $longitude)
                : null,
        ];
    }

    protected function calculateDerivedMetrics(?DriverLocation $previous, DriverLocation $current): array
    {
        if (! $previous) {
            return [
                'speed' => null,

                'bearing' => null,
            ];
        }

        $prevLat = $previous->latitude !== null ? (float) $previous->latitude : null;

        $prevLng = $previous->longitude !== null ? (float) $previous->longitude : null;
        $currLat = $current->latitude !== null ? (float) $current->latitude : null;

        $currLng = $current->longitude !== null ? (float) $current->longitude : null;

        if (
            $prevLat === null || $prevLng === null ||
            $currLat === null || $currLng === null
        ) {
            return [
                'speed' => null,

                'bearing' => null,
            ];
        }

        $bearing = $this->bearingDegrees($prevLat, $prevLng, $currLat, $currLng);

        $previousTime = $previous->device_dtm ?? $previous->timestamp;
        $currentTime = $current->device_dtm ?? $current->timestamp;

        if (! $previousTime || ! $currentTime) {
            return [
                'speed' => null,

                'bearing' => $bearing,
            ];
        }

        $diffSeconds = $currentTime->diffInSeconds($previousTime, false);

        if ($diffSeconds <= 0) {
            return [
                'speed' => null,

                'bearing' => $bearing,
            ];
        }

        $distanceMiles = $this->distanceMiles($prevLat, $prevLng, $currLat, $currLng);

        $speed = round(($distanceMiles / $diffSeconds) * 3600, 1);

        return [
            'speed' => is_finite($speed) ? $speed : null,

            'bearing' => $bearing,
        ];
    }

    protected function distanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3958.7613;

        $lat1Rad = deg2rad($lat1);

        $lat2Rad = deg2rad($lat2);

        $deltaLat = deg2rad($lat2 - $lat1);

        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusMiles * $c;
    }

    protected function bearingDegrees(float $lat1, float $lng1, float $lat2, float $lng2): ?float
    {
        if ($lat1 === $lat2 && $lng1 === $lng2) {
            return null;
        }

        $lat1Rad = deg2rad($lat1);

        $lat2Rad = deg2rad($lat2);

        $deltaLngRad = deg2rad($lng2 - $lng1);

        $y = sin($deltaLngRad) * cos($lat2Rad);

        $x = cos($lat1Rad) * sin($lat2Rad)
            - sin($lat1Rad) * cos($lat2Rad) * cos($deltaLngRad);

        $bearing = rad2deg(atan2($y, $x));
        return $this->normalizeBearing($bearing);
    }

    protected function normalizeBearing(float $bearing): float
    {
        $normalized = fmod($bearing, 360.0);

        if ($normalized < 0) {
            $normalized += 360.0;
        }

        return round($normalized, 1);
    }

    protected function bearingToCardinal(float $bearing): string
    {
        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];

        $index = (int) round($bearing / 45) % 8;

        return $directions[$index];
    }
}
