<?php
require "/fleetbase/api/vendor/autoload.php";
$app = require "/fleetbase/api/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Fleetbase\FleetOps\Models\Place;
use Fleetbase\LaravelMysqlSpatial\Types\Point;

$place = Place::where('public_id', 'place_srytijhhrj')->first();
if (!$place) {
    echo "NOT FOUND" . PHP_EOL;
    exit;
}

echo "Before: " . json_encode($place->location) . PHP_EOL;

$place->location = new Point(49.4125360, 32.0366862);
$place->save();
$place->refresh();

echo "After: " . json_encode($place->location) . PHP_EOL;
