<?php
require "/fleetbase/api/vendor/autoload.php";
$app = require "/fleetbase/api/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Fleetbase\Models\ApiCredential;
use Fleetbase\Models\Company;

$creds = ApiCredential::query()->get(['uuid','company_uuid','name','key','test_mode','expires_at']);
foreach ($creds as $c) {
    echo $c->uuid . " | company=" . $c->company_uuid . " | name=" . $c->name . " | key=" . $c->key . " | test_mode=" . var_export($c->test_mode, true) . " | expires=" . var_export($c->expires_at, true) . PHP_EOL;
}
echo "--- companies ---" . PHP_EOL;
$companies = Company::query()->get(['uuid','name']);
foreach ($companies as $co) {
    echo $co->uuid . " | " . $co->name . PHP_EOL;
}
