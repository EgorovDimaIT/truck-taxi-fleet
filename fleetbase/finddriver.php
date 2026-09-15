<?php
require "/fleetbase/api/vendor/autoload.php";
$app = require "/fleetbase/api/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Vehicle;

$drivers = Driver::query()->where("name", "like", "%Львів%")->orWhere("name", "like", "%Олег%")->get(["uuid","name","vehicle_uuid"]);
foreach ($drivers as $d) {
  echo $d->uuid." | ".$d->name." | vehicle_uuid=".$d->vehicle_uuid.PHP_EOL;
}
echo "---vehicles---".PHP_EOL;
$vehicles = Vehicle::query()->get(["uuid","display_name","plate_number"]);
foreach ($vehicles as $v) {
  echo $v->uuid." | ".$v->display_name." | ".$v->plate_number.PHP_EOL;
}