<?php
/**
 * test_order_entities_fixed.php
 *
 * ИСПРАВЛЕННАЯ версия по итогам реального теста против локального Fleetbase.
 *
 * РЕАЛЬНОЕ ПОВЕДЕНИЕ (подтверждено кодом и тестом), отличается от того,
 * что предполагает пример из документации:
 *
 *   Payload::waypoints() — это ТОЛЬКО промежуточные точки маршрута
 *   ("Waypoints between start and end" — комментарий в коде,
 *   packages/fleetops/server/src/Models/Payload.php:235).
 *   pickup и dropoff в эту коллекцию НЕ входят — они отдельные поля.
 *
 *   Payload::findDestinationFromKey() резолвит entities[].destination так:
 *     - число (0,1,2...)  -> позиция ИМЕННО в массиве 'waypoints' (без pickup/dropoff)
 *     - 'pickup'          -> явно pickup
 *     - 'dropoff'         -> явно dropoff
 *     - public_id / uuid  -> конкретная точка по её id
 *
 * ПОЭТОМУ пример из docs.fleetbase.io ('destination' => 0 в заказе только
 * с pickup+dropoff, без явного 'waypoints') на практике не значит "pickup" —
 * если 'waypoints' пуст, индекс 0 не найдёт совпадений и destination
 * останется null. Правильно указывать 'pickup' / 'dropoff' явно, а числовой
 * индекс использовать только для точек из массива 'waypoints'.
 */

const API_BASE = 'http://httpd/v1';
const API_KEY  = 'flb_live_6VgYxtR2TUwXamvAXKg1';

function fleetbaseRequest(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init(API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($raw, true)];
}

echo "=== Создание заказа: pickup + 1 промежуточный waypoint + dropoff, ===\n";
echo "=== каждая entity привязана ПРАВИЛЬНЫМ ключом destination ===\n\n";

$orderPayload = [
    'pickup'  => 'Kyiv, Khreshchatyk St, 1',
    'dropoff' => 'Kyiv, Peremohy Ave, 37',
    'waypoints' => [
        'Kyiv, Velyka Vasylkivska St, 100',
    ],
    'entities' => [
        [
            'destination'  => 'pickup', // явно pickup, а не 0
            'name'         => 'Паллета А',
            'description'  => 'Забор со склада, ворота №3, погрузка вилочным погрузчиком.',
        ],
        [
            'destination'  => 0, // позиция 0 в массиве 'waypoints' (промежуточная точка)
            'name'         => 'Груз для точки 2 (Velyka Vasylkivska)',
            'description'  => 'Занос на 4-й этаж, лифт не работает, звонить за 15 минут.',
        ],
        [
            'destination'  => 'dropoff', // явно dropoff, а не 2
            'name'         => 'Груз для точки 3 (Peremohy Ave)',
            'description'  => 'Разгрузка со стороны двора, охрана требует паспорт.',
        ],
    ],
];

$createResult = fleetbaseRequest('POST', '/orders', $orderPayload);
echo "HTTP status: {$createResult['status']}\n\n";

$payload = $createResult['body']['payload'] ?? [];
$pickupId  = $payload['pickup']['id'] ?? null;
$dropoffId = $payload['dropoff']['id'] ?? null;
$waypointIds = array_map(fn ($w) => $w['id'], $payload['waypoints'] ?? []);

echo "pickup id:    {$pickupId}\n";
echo "dropoff id:   {$dropoffId}\n";
echo "waypoint ids: " . implode(', ', $waypointIds) . "\n\n";

echo "=== Проверка: destination каждой entity vs ожидаемая точка ===\n\n";
foreach ($payload['entities'] ?? [] as $entity) {
    $dest = $entity['destination'] ?? null;
    $match = 'НЕ ОПРЕДЕЛЕНО';
    if ($dest === $pickupId) {
        $match = 'PICKUP ✔';
    } elseif ($dest === $dropoffId) {
        $match = 'DROPOFF ✔';
    } elseif (in_array($dest, $waypointIds, true)) {
        $match = 'WAYPOINT ✔ (' . array_search($dest, $waypointIds) . ')';
    } elseif ($dest === null) {
        $match = 'NULL ✘ — НЕ ПРИВЯЗАНО';
    }

    printf(
        "- %-45s destination=%-25s => %s\n  description: %s\n\n",
        $entity['name'],
        $dest ?? 'null',
        $match,
        $entity['description']
    );
}
