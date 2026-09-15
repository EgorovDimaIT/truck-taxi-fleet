<?php
require "/fleetbase/api/vendor/autoload.php";
$app = require "/fleetbase/api/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Fleetbase\FleetOps\Models\Place;

$p = Place::query()->whereNotNull('company_uuid')->first();
if (!$p) {
    echo "NO PLACES FOUND\n";
    exit;
}
echo "uuid: " . $p->uuid . PHP_EOL;
echo "company_uuid: " . $p->company_uuid . PHP_EOL;
echo "name: " . $p->name . PHP_EOL;
echo "type: " . var_export($p->type, true) . PHP_EOL;
echo "street1: " . $p->street1 . PHP_EOL;
echo "city: " . $p->city . PHP_EOL;
echo "province: " . $p->province . PHP_EOL;
echo "country: " . var_export($p->country, true) . PHP_EOL;
echo "postal_code: " . var_export($p->postal_code, true) . PHP_EOL;
echo "location_raw: " . var_export($p->getRawOriginal('location'), true) . PHP_EOL;
echo "location_cast: " . json_encode($p->location) . PHP_EOL;
