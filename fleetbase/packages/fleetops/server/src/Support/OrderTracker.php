<?php

namespace Fleetbase\FleetOps\Support;

use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Tracking\TrackingIntelligenceService;
use Fleetbase\FleetOps\Tracking\TrackingOptions;
use Illuminate\Support\Carbon;

class OrderTracker
{
    public function __construct(protected Order $order)
    {
    }

    public function eta(array $options = []): array
    {
        return app(TrackingIntelligenceService::class)->eta($this->order, TrackingOptions::fromArray($options));
    }

    public function toArray(array $options = []): array
    {
        $data = app(TrackingIntelligenceService::class)->track($this->order, TrackingOptions::fromArray($options));

        // Merge in legacy flat fields for backwards compatibility with mobile apps (Navigator)
        // that still consume the pre-TrackingIntelligenceService response shape.
        return array_merge($data, $this->legacyCompatFields($data));
    }

    /**
     * Builds the legacy flat field shape (total_distance, progress_percentage, etc.)
     * expected by the Navigator mobile app, derived from the new nested tracker payload.
     *
     * @see https://github.com/fleetbase/navigator-app src/screens/OrderScreen.tsx
     */
    protected function legacyCompatFields(array $data): array
    {
        $order              = $this->order;
        $progressPercentage = (float) data_get($data, 'progress.percentage', 0);
        $totalDistance      = data_get($data, 'route.distance_m') ?? data_get($data, 'progress.remaining_distance_m') ?? -1;
        $completedDistance  = data_get($data, 'progress.completed_distance_m') ?? 0;
        $currentDestEta     = data_get($data, 'eta.active_stop_seconds') ?? -1;
        $completionEta      = data_get($data, 'eta.completion_seconds') ?? -1;
        $completionAt       = data_get($data, 'eta.completion_at');
        $isCompleted        = $order->status === 'completed';

        return [
            'driver_current_location'             => data_get($data, 'driver.location'),
            'progress_percentage'                 => $progressPercentage,
            'total_distance'                      => $totalDistance,
            'completed_distance'                  => $completedDistance,
            'current_destination_eta'             => $currentDestEta,
            'completion_eta'                      => $completionEta,
            'estimated_completion_time'           => $completionAt,
            'estimated_completion_time_formatted' => $completionAt ? Carbon::parse($completionAt)->format('M jS, Y H:i') : null,
            'start_time'                          => $order->started_at,
            'completion_time'                     => $order->completed_at,
            'current_destination'                 => data_get($data, 'active_stop'),
            'next_destination'                    => data_get($data, 'next_stop'),
            'first_waypoint_completed'             => $progressPercentage > 10,
            'last_waypoint_completed'              => $progressPercentage === 100.0 || $isCompleted,
        ];
    }
}