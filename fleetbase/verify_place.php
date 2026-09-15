<?php
require "/fleetbase/api/vendor/autoload.php";
$app = require "/fleetbase/api/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Fleetbase\FleetOps\Models\Place;

$companyUuid = '4aa20fe8-f693-428f-816f-8cf91ac1ea48';

$total = Place::query()->where('company_uuid', $companyUuid)->count();
echo "total places for company: " . $total . PHP_EOL;

$mine = Place::query()->where('public_id', 'place_tze4xfdfpu')->first();
echo "mine exists: " . ($mine ? 'YES' : 'NO') . PHP_EOL;
if ($mine) {
    echo "mine created_at: " . $mine->created_at . PHP_EOL;
    echo "mine deleted_at: " . var_export($mine->deleted_at, true) . PHP_EOL;
}

echo "--- ordered by created_at asc, all uuids/names ---" . PHP_EOL;
$all = Place::query()->where('company_uuid', $companyUuid)->orderBy('created_at', 'asc')->get(['public_id','name','created_at']);
foreach ($all as $p) {
    echo $p->created_at . " | " . $p->public_id . " | " . $p->name . PHP_EOL;
}
