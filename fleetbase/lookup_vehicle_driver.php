<?php
require "/fleetbase/api/vendor/autoload.php";
$app = require "/fleetbase/api/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\Models\User;
use Fleetbase\Models\Company;

$companyUuid = '4aa20fe8-f693-428f-816f-8cf91ac1ea48';

echo "--- vehicles (plate or name like АА1111 / Nissan) ---" . PHP_EOL;
$vehicles = Vehicle::query()->where('company_uuid', $companyUuid)->get(['uuid','public_id','display_name','plate_number','make','model']);
foreach ($vehicles as $v) {
    echo $v->public_id . " | " . $v->display_name . " | plate=" . $v->plate_number . " | make=" . $v->make . " | model=" . $v->model . PHP_EOL;
}

echo "--- drivers ---" . PHP_EOL;
$drivers = Driver::query()->where('company_uuid', $companyUuid)->get(['uuid','public_id','name','user_uuid','vehicle_uuid']);
foreach ($drivers as $d) {
    echo $d->public_id . " | " . $d->name . " | user_uuid=" . $d->user_uuid . " | vehicle_uuid=" . $d->vehicle_uuid . PHP_EOL;
}

echo "--- users in company ---" . PHP_EOL;
$company = Company::find($companyUuid);
$users = $company ? $company->users()->get(['uuid','name','email']) : collect();
foreach ($users as $u) {
    echo $u->uuid . " | " . $u->name . " | " . $u->email . PHP_EOL;
}
