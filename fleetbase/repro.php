<?php
use Illuminate\Http\Request;
use Fleetbase\Models\User;

$admin = User::where('email', 'logisticstoukraine@gmail.com')->first();
if (!$admin) { $admin = User::where('type', 'admin')->first(); }
$companyUuid = $admin->company_uuid;

session(['company' => $companyUuid]);
auth()->login($admin);

$request = Request::create('/int/v1/users', 'GET', [
    'is_customer' => 1,
    'page' => 1,
    'limit' => 20,
    'sort' => 'created',
    'query' => '',
    'status' => '',
    'role' => '',
    'name' => '',
    'phone' => '',
    'email' => '',
]);
$request->headers->set('Accept', 'application/json');
$request->headers->set('X-Fleetbase-Api-Internal-Request', '1');

try {
    $controller = app(\Fleetbase\Http\Controllers\Internal\v1\UserController::class);
    $response = $controller->queryRecord($request);
    echo "SUCCESS\n";
    print_r($response instanceof \Illuminate\Http\JsonResponse ? $response->getContent() : $response);
} catch (\Throwable $e) {
    echo "EXCEPTION: " . get_class($e) . "\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
