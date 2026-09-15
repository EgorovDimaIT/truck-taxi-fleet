<?php

namespace Fleetbase\FleetOps\Support\Telematics\Providers;

use Illuminate\Support\Carbon;

/**
 * Class TraccarProvider.
 *
 * Traccar telematics connector for Fleetbase.
 *
 * Traccar (traccar.org) and its official "Traccar Client" mobile app make up a
 * self-hosted GPS tracking platform. Unlike the other native providers in this
 * directory, Traccar is primarily a *push* integration: the Traccar Server (or
 * the Traccar Client app directly) sends position updates to Fleetbase, rather
 * than Fleetbase polling a cloud API for them.
 *
 * This connector is additive - it plugs into Fleetbase's existing generic
 * telematics webhook route (POST /webhooks/telematics/traccar), which is
 * already wired up by Fleetbase\FleetOps\Http\Controllers\TelematicWebhookController.
 * No routes, controllers, or core files were modified to add this connector.
 *
 * It accepts three wire formats on that shared endpoint:
 *
 *  1. Traccar Server "Position Forwarding" JSON - produced when a Traccar
 *     Server instance is configured to forward positions using the `json`
 *     format: {"position": {...}, "device": {...}}. This is the exact
 *     contract GeoPulse (github.com/tess1o/geopulse) uses for its own
 *     TRACCAR GPS source type, so a Traccar Server already forwarding to
 *     GeoPulse can be pointed at Fleetbase using the same payload shape.
 *  2. A flat OsmAnd-style payload, as sent directly by the Traccar Client
 *     app or many generic GPS trackers: id/deviceId, lat/latitude,
 *     lon/longitude, timestamp, speed, bearing/course, altitude, accuracy,
 *     batt/battery.
 *  3. A batch (array) of either of the above, for forwarders that buffer
 *     and flush multiple positions per request.
 *
 * Optional discovery: if a `server_url` (plus `username`/`password`) is
 * supplied, the connector can also read the device list from a Traccar
 * Server's own REST API (GET /api/devices) for device linking.
 *
 * https://www.traccar.org/
 */
class TraccarProvider extends AbstractProvider
{
    protected int $requestsPerMinute = 120;

    /**
     * {@inheritdoc}
     */
    protected function prepareAuthentication(): void
    {
        $this->baseUrl = rtrim((string) ($this->credentials['server_url'] ?? ''), '/');
        $this->headers = ['Accept' => 'application/json'];

        if (!empty($this->credentials['username']) && !empty($this->credentials['password'])) {
            $this->headers['Authorization'] = 'Basic ' . base64_encode($this->credentials['username'] . ':' . $this->credentials['password']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(array $credentials): array
    {
        try {
            $this->credentials = $credentials;
            $this->prepareAuthentication();

            if (empty($this->baseUrl)) {
                // Traccar is push-based: without a server URL there is nothing to dial
                // out to. Confirm the connector is configured and ready to receive data.
                return [
                    'success'  => true,
                    'message'  => 'Webhook endpoint ready. Point your Traccar Server "Position Forwarding" (or the Traccar Client app) at the Fleetbase webhook URL to start receiving positions.',
                    'metadata' => [
                        'mode' => 'push_only',
                    ],
                ];
            }

            $response = $this->request('GET', '/api/server');

            return [
                'success'  => true,
                'message'  => 'Connected to Traccar Server successfully.',
                'metadata' => [
                    'server_version' => $response['version'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success'  => false,
                'message'  => $e->getMessage(),
                'metadata' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchDevices(array $options = []): array
    {
        if (empty($this->baseUrl)) {
            return [
                'devices'     => [],
                'next_cursor' => null,
                'has_more'    => false,
            ];
        }

        $response = $this->request('GET', '/api/devices');

        return [
            'devices'     => is_array($response) ? $response : [],
            'next_cursor' => null,
            'has_more'    => false,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function fetchDeviceDetails(string $externalId): array
    {
        if (empty($this->baseUrl)) {
            return [];
        }

        $devices = $this->request('GET', '/api/devices', ['id' => $externalId]);

        return $devices[0] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeDevice(array $payload): array
    {
        $device     = $payload['device'] ?? [];
        $position   = $payload['position'] ?? null;
        $externalId = $this->resolveExternalId($payload);

        [$lat, $lng, $speed, $heading, $altitude, $accuracy, $occurredAt, $battery] = $this->extractTelemetry($payload);

        return [
            'device_id'    => $externalId,
            'external_id'  => $externalId,
            'name'         => $device['name'] ?? ($externalId ? ('Traccar Device ' . $externalId) : 'Unknown Device'),
            'provider'     => 'traccar',
            'model'        => $device['model'] ?? $device['category'] ?? null,
            'imei'         => $externalId && $this->looksLikeImei($externalId) ? $externalId : null,
            'phone'        => $device['phone'] ?? null,
            'status'       => ($device['disabled'] ?? false) ? 'inactive' : 'active',
            'online'       => isset($device['status']) ? $device['status'] === 'online' : ($occurredAt !== null),
            'last_seen_at' => $occurredAt,
            'location'     => ['lat' => $lat, 'lng' => $lng],
            'speed'        => $speed,
            'heading'      => $heading,
            'altitude'     => $altitude,
            'fuel_level'   => $this->extractAttribute($position, ['fuel', 'fuelLevel']),
            'ignition'     => $this->extractIgnition($position),
            'odometer'     => $this->extractAttribute($position, ['odometer', 'totalDistance']),
            'meta'         => array_filter([
                'accuracy' => $accuracy,
                'battery'  => $battery,
                'protocol' => $position['protocol'] ?? null,
                'valid'    => $position['valid'] ?? null,
            ], fn ($value) => $value !== null),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeEvent(array $payload): array
    {
        $position   = $payload['position'] ?? null;
        $externalId = $this->resolveExternalId($payload);

        [$lat, $lng, $speed, $heading, $altitude, $accuracy, $occurredAt, $battery] = $this->extractTelemetry($payload);

        return [
            'external_id' => $externalId,
            'device_id'   => $externalId,
            'event_type'  => 'position_update',
            'occurred_at' => $occurredAt ?? now(),
            'online'      => true,
            'location'    => ['lat' => $lat, 'lng' => $lng],
            'speed'       => $speed,
            'heading'     => $heading,
            'altitude'    => $altitude,
            'odometer'    => $this->extractAttribute($position, ['odometer', 'totalDistance']),
            'ignition'    => $this->extractIgnition($position),
            'fuel_level'  => $this->extractAttribute($position, ['fuel', 'fuelLevel']),
            'meta'        => array_filter([
                'accuracy' => $accuracy,
                'battery'  => $battery,
                'valid'    => $position['valid'] ?? null,
                'protocol' => $position['protocol'] ?? null,
            ], fn ($value) => $value !== null),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeSensor(array $payload): array
    {
        return [
            'sensor_type' => $payload['sensor_type'] ?? 'generic',
            'value'       => $payload['value'] ?? null,
            'unit'        => $payload['unit'] ?? null,
            'recorded_at' => now(),
            'meta'        => $payload,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Traccar itself does not sign webhook payloads. If a `shared_secret` is
     * configured, Fleetbase expects it to be relayed via the standard
     * `X-Webhook-Signature` header - e.g. by setting a custom header on a
     * Traccar Server "Position Forwarding" configuration, or via a thin
     * reverse-proxy rule in front of the Traccar Client's target URL.
     */
    public function validateWebhookSignature(string $payload, string $signature, array $credentials): bool
    {
        $secret = $credentials['shared_secret'] ?? null;

        if (!$secret) {
            return true;
        }

        return hash_equals((string) $secret, (string) $signature);
    }

    /**
     * {@inheritdoc}
     */
    public function processWebhook(array $payload, array $headers = []): array
    {
        // Shape: batch of messages (array of positions/devices flushed together).
        if ($this->isSequentialArray($payload)) {
            $devices = [];
            $events  = [];

            foreach ($payload as $message) {
                if (!is_array($message)) {
                    continue;
                }

                $result  = $this->buildWebhookResult($message);
                $devices = [...$devices, ...$result['devices']];
                $events  = [...$events, ...$result['events']];
            }

            return ['devices' => $devices, 'events' => $events, 'sensors' => []];
        }

        // Shape: single Traccar Server forward object ({"position":..., "device":...})
        // or a flat OsmAnd-style payload sent directly by a Traccar Client / tracker.
        return $this->buildWebhookResult($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentialSchema(): array
    {
        return [
            [
                'name'        => 'shared_secret',
                'label'       => 'Shared Secret (Optional)',
                'type'        => 'password',
                'placeholder' => 'Generate a secret and send it as the X-Webhook-Signature header',
                'required'    => false,
                'help_text'   => 'If set, incoming positions must carry this value in the X-Webhook-Signature header. Leave blank if your network path (or reverse proxy) already restricts who can reach the webhook.',
            ],
            [
                'name'        => 'server_url',
                'label'       => 'Traccar Server URL (Optional)',
                'type'        => 'text',
                'placeholder' => 'https://your-traccar-server.example.com',
                'required'    => false,
                'advanced'    => true,
                'is_endpoint' => true,
                'help_text'   => 'Only needed to pull your existing device list from a Traccar Server for linking. Leave blank if you only want to receive pushed positions.',
                'validation'  => 'nullable|url',
            ],
            [
                'name'     => 'username',
                'label'    => 'Traccar Server Username (Optional)',
                'type'     => 'text',
                'required' => false,
            ],
            [
                'name'     => 'password',
                'label'    => 'Traccar Server Password (Optional)',
                'type'     => 'password',
                'required' => false,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsWebhooks(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDiscovery(): bool
    {
        return true;
    }

    protected function buildWebhookResult(array $payload): array
    {
        if (!$this->resolveExternalId($payload)) {
            return ['devices' => [], 'events' => [], 'sensors' => []];
        }

        return [
            'devices' => [$this->normalizeDevice($payload)],
            'events'  => [$this->normalizeEvent($payload)],
            'sensors' => [],
        ];
    }

    /**
     * Extracts lat/lng/speed/heading/altitude/accuracy/occurredAt/battery from
     * either wire shape in one pass. Speed is normalized from Traccar's native
     * knots to km/h to match how Fleetbase stores vehicle speed elsewhere.
     *
     * @return array{0:?float,1:?float,2:?float,3:?float,4:?float,5:?float,6:?string,7:?float}
     */
    protected function extractTelemetry(array $payload): array
    {
        $position = $payload['position'] ?? $payload;

        $lat = $position['latitude'] ?? $position['lat'] ?? null;
        $lng = $position['longitude'] ?? $position['lon'] ?? $position['lng'] ?? null;

        $speedKnots = $position['speed'] ?? null;
        $speedFlat  = $payload['speed'] ?? null;
        $speed      = $speedKnots !== null
            ? round(((float) $speedKnots) * 1.852, 2)
            : ($speedFlat !== null ? (float) $speedFlat : null);

        $heading  = $position['course'] ?? $position['bearing'] ?? $payload['bearing'] ?? $payload['course'] ?? null;
        $altitude = $position['altitude'] ?? $payload['altitude'] ?? null;
        $accuracy = $position['accuracy'] ?? $payload['accuracy'] ?? null;

        $occurredAt = $this->resolveTimestamp($payload);
        $battery    = $this->extractAttribute($position, ['batteryLevel', 'battery']) ?? $payload['batt'] ?? $payload['battery'] ?? null;

        return [
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
            $speed,
            $heading !== null ? (float) $heading : null,
            $altitude !== null ? (float) $altitude : null,
            $accuracy !== null ? (float) $accuracy : null,
            $occurredAt?->toDateTimeString(),
            $battery !== null ? (float) $battery : null,
        ];
    }

    protected function resolveTimestamp(array $payload): ?Carbon
    {
        $position = $payload['position'] ?? null;

        $candidates = [
            $position['fixTime'] ?? null,
            $position['deviceTime'] ?? null,
            $position['serverTime'] ?? null,
            $payload['fixTime'] ?? null,
            $payload['timestamp'] ?? null,
            $payload['dateTime'] ?? null,
            $payload['lastUpdate'] ?? null,
        ];

        foreach ($candidates as $value) {
            $parsed = $this->parseFlexibleTimestamp($value);
            if ($parsed) {
                return $parsed;
            }
        }

        return null;
    }

    protected function parseFlexibleTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $number = (float) $value;

            return Carbon::createFromTimestamp($number > 10_000_000_000 ? $number / 1000 : $number);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveExternalId(array $payload): ?string
    {
        $device = $payload['device'] ?? [];

        $value = $device['uniqueId']
            ?? $device['id']
            ?? $payload['uniqueId']
            ?? $payload['id']
            ?? $payload['deviceId']
            ?? $payload['device_id']
            ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    protected function extractAttribute(mixed $position, array $keys): mixed
    {
        if (!is_array($position)) {
            return null;
        }

        foreach ($keys as $key) {
            if (isset($position['attributes'][$key])) {
                return $position['attributes'][$key];
            }

            if (isset($position[$key])) {
                return $position[$key];
            }
        }

        return null;
    }

    protected function extractIgnition(mixed $position): ?bool
    {
        $value = $this->extractAttribute($position, ['ignition']);

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    protected function looksLikeImei(string $value): bool
    {
        return preg_match('/^\d{14,17}$/', $value) === 1;
    }

    /**
     * PHP 8.0-safe equivalent of array_is_list() (introduced in 8.1), which
     * this package (composer.json: "php": "^8.0") cannot rely on.
     */
    protected function isSequentialArray(array $payload): bool
    {
        if ($payload === []) {
            return false;
        }

        return array_keys($payload) === range(0, count($payload) - 1) && isset($payload[0]) && is_array($payload[0]);
    }
}
