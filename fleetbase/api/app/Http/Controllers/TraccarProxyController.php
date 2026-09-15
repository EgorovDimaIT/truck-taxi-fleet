<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only proxy that forwards live device positions from Traccar
 * to the Fleetbase console. Added as a standalone controller so it
 * does not touch any existing routes or controllers.
 */
class TraccarProxyController extends Controller
{
    /**
     * GET /int/v1/traccar/positions
     *
     * Returns a simplified list of current device positions:
     * [{ id, name, uniqueId, lat, lng, speed, course, fixTime, address }]
     */
    public function positions(): JsonResponse
    {
        $baseUrl = rtrim(config('services.traccar.base_url'), '/');
        $token = config('services.traccar.token');

        if (!$baseUrl || !$token) {
            return response()->json(['error' => 'Traccar is not configured'], 500);
        }

        // Cache devices list briefly to avoid hammering Traccar on every poll
        $devices = Cache::remember('traccar:devices', 30, function () use ($baseUrl, $token) {
            $response = Http::withToken($token)->timeout(10)->get("{$baseUrl}/api/devices");

            return $response->successful() ? $response->json() : [];
        });

        $devicesById = collect($devices)->keyBy('id');

        $response = Http::withToken($token)->timeout(10)->get("{$baseUrl}/api/positions");

        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to reach Traccar', 'status' => $response->status()], 502);
        }

        $positions = collect($response->json())->map(function ($position) use ($devicesById) {
            $device = $devicesById->get($position['deviceId'] ?? null);

            return [
                'id' => $position['deviceId'] ?? null,
                'name' => $device['name'] ?? ('Device #' . ($position['deviceId'] ?? '?')),
                'uniqueId' => $device['uniqueId'] ?? null,
                'lat' => $position['latitude'] ?? null,
                'lng' => $position['longitude'] ?? null,
                'speed' => isset($position['speed']) ? round($position['speed'] * 1.852, 1) : null, // knots -> km/h
                'course' => $position['course'] ?? null,
                'fixTime' => $position['fixTime'] ?? null,
                'valid' => $position['valid'] ?? true,
            ];
        })->filter(fn ($p) => $p['lat'] !== null && $p['lng'] !== null)->values();

        return response()->json($positions);
    }
}
