<?php
use Fleetbase\FleetOps\Models\Telematic;
use Fleetbase\FleetOps\Models\Position;
use Fleetbase\FleetOps\Models\Device;

echo Telematic::count() . " telematics\n";
echo Device::count() . " devices\n";
echo Position::count() . " positions\n";
$p = Position::orderByDesc('created_at')->first();
if ($p) {
    echo "latest position: " . $p->created_at . " subject=" . $p->subject_uuid . "\n";
} else {
    echo "no positions found\n";
}