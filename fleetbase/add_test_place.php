<?php
require "/fleetbase/api/vendor/autoload.php";
$app = require "/fleetbase/api/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Fleetbase\FleetOps\Models\Place;
use Fleetbase\LaravelMysqlSpatial\Types\Point;

$companyUuid = '4aa20fe8-f693-428f-816f-8cf91ac1ea48';

$place = Place::create([
    'company_uuid' => $companyUuid,
    'name'         => 'Тест API - Київ, Хрещатик 1',
    'street1'      => 'вулиця Хрещатик, 1',
    'city'         => 'Київ',
    'province'     => '',
    'country'      => 'UA',
    'location'     => new Point(50.4501, 30.5234),
]);

$place->refresh();

echo "OK created" . PHP_EOL;
echo "uuid: " . $place->uuid . PHP_EOL;
echo "public_id: " . $place->public_id . PHP_EOL;
echo "location: " . json_encode($place->location) . PHP_EOL;
