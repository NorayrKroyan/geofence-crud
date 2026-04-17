<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use App\Models\EventLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'min:9', 'max:255'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
        ]);

        $deviceId = trim($validated['device_id']);
        $startedAt = CarbonImmutable::parse($validated['started_at']);
        $endedAt = CarbonImmutable::parse($validated['ended_at']);

        $driverContext = DriverLocation::query()
            ->leftJoin('drivers', 'drivers.id', '=', 'driver_locations.user_id')
            ->select('driver_locations.user_id')
            ->selectRaw('MAX(drivers.name) AS driver_name')
            ->selectRaw('MAX(drivers.mobile_number) AS driver_mobile_number')
            ->where('driver_locations.device_id', $deviceId)
            ->where(function ($query) use ($startedAt, $endedAt) {
                $query
                    ->whereBetween('driver_locations.timestamp', [$startedAt, $endedAt])
                    ->orWhere(function ($subQuery) use ($startedAt, $endedAt) {
                        $subQuery
                            ->whereNotNull('driver_locations.device_dtm')
                            ->whereBetween('driver_locations.device_dtm', [$startedAt, $endedAt]);
                    });
            })
            ->groupBy('driver_locations.user_id')
            ->get();

        $driverIds = $driverContext
            ->pluck('user_id')
            ->filter(fn ($value) => $value !== null && (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $driverNames = $driverContext
            ->pluck('driver_name')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        $mobileNumbers = $driverContext
            ->pluck('driver_mobile_number')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        if ($driverIds->isEmpty() && $mobileNumbers->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'device_id' => $deviceId,
                    'started_at' => $startedAt->toIso8601String(),
                    'ended_at' => $endedAt->toIso8601String(),
                    'count' => 0,
                    'driver_ids' => [],
                    'driver_names' => [],
                    'mobile_numbers' => [],
                    'rows' => [],
                ],
            ]);
        }

        $query = EventLog::query()
            ->leftJoin('geofences', 'geofences.id', '=', 'event_logs.geofence_id')
            ->select('event_logs.*')
            ->selectRaw('geofences.name AS geofence_name')
            ->selectRaw('geofences.speed_limit_kph AS geofence_speed_limit_kph')
            ->whereBetween('event_logs.created_at', [$startedAt, $endedAt])
            ->where(function ($scopedQuery) use ($driverIds, $mobileNumbers) {
                if ($driverIds->isNotEmpty()) {
                    $scopedQuery->whereIn('event_logs.user_id', $driverIds->all());
                }

                if ($mobileNumbers->isNotEmpty()) {
                    if ($driverIds->isNotEmpty()) {
                        $scopedQuery->orWhereIn('event_logs.mobile_number', $mobileNumbers->all());
                    } else {
                        $scopedQuery->whereIn('event_logs.mobile_number', $mobileNumbers->all());
                    }
                }
            })
            ->orderByDesc('event_logs.created_at')
            ->orderByDesc('event_logs.id');

        $rows = $query->get()->map(function (EventLog $eventLog) {
            return [
                'id' => (int) $eventLog->id,
                'user_id' => $eventLog->user_id !== null ? (int) $eventLog->user_id : null,
                'mobile_number' => $eventLog->mobile_number !== null ? trim((string) $eventLog->mobile_number) : null,
                'geofence_id' => $eventLog->geofence_id !== null ? (int) $eventLog->geofence_id : null,
                'geofence_name' => ($eventLog->getAttribute('geofence_name') !== null && trim((string) $eventLog->getAttribute('geofence_name')) !== '')
                    ? trim((string) $eventLog->getAttribute('geofence_name'))
                    : null,
                'geofence_speed_limit_kph' => $eventLog->getAttribute('geofence_speed_limit_kph') !== null
                    ? (int) $eventLog->getAttribute('geofence_speed_limit_kph')
                    : null,
                'action' => $eventLog->action !== null ? trim((string) $eventLog->action) : null,
                'acknowledged' => $eventLog->acknowledged,
                'attachments_uploaded' => $eventLog->attachments_uploaded,
                'created_at' => optional($eventLog->created_at)->toIso8601String(),
                'updated_at' => optional($eventLog->updated_at)->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'device_id' => $deviceId,
                'started_at' => $startedAt->toIso8601String(),
                'ended_at' => $endedAt->toIso8601String(),
                'count' => $rows->count(),
                'driver_ids' => $driverIds->all(),
                'driver_names' => $driverNames->all(),
                'mobile_numbers' => $mobileNumbers->all(),
                'rows' => $rows,
            ],
        ]);
    }
}
