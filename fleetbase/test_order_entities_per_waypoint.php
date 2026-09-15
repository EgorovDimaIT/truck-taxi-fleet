<?php
/**
 * test_order_entities_per_waypoint.php
 *
 * Реальный тест против локального Fleetbase (docker compose, application/httpd),
 * подтверждающий официально задокументированную схему:
 * https://docs.fleetbase.io/developers/api/
 *
 * Каждая entity (товар/позиция) привязывается к конкретному waypoint через
 * числовой индекс в поле 'destination', а её 'description' — это инструкция,
 * относящаяся именно к этой точке маршрута (а не ко всему заказу).
 *
 * Запуск (внутри контейнера application, где есть curl/php и доступ к
 * внутренней docker-сети):
 *   docker compose exec -T application php /tmp/test_order_entities_per_waypoint.php
 */

// ---- Конфигурация ----
const API_BASE = 'http://httpd/v1';           // сервис httpd внутри docker-сети
const API_KEY  = 'flb_live_6VgYxtR2TUwXamvAXKg1'; // ключ компании "Dima", взят из БД (api_credentials)

/**
 * Простой HTTP-клиент на curl, повторяющий �URL-примеры из документации:
 * Authorization: Bearer <api key>
 */
function fleetbaseRequest(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init(API_BASE . $path);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . API_KEY,
    ];

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw     = curl_exec($ch);
    $errno   = curl_errno($ch);
    $error   = curl_error($ch);
    $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        fwrite(STDERR, "cURL error ($errno): $error\n");
        exit(1);
    }

    $decoded = json_decode($raw, true);

    return [
        'status' => $status,
        'body'   => $decoded,
        'raw'    => $raw,
    ];
}

echo "=== 1. Создание заказа с 3 точками и entities, привязанными к waypoint через 'destination' ===\n\n";

// pickup = точка 0, waypoints (dropoff по факту тоже waypoint под индексом, зависящим от количества)
// Официальная схема из документации (PHP SDK / REST):
//   entities[].destination = числовой индекс waypoint в payload
//   entities[].description = инструкция именно для этой точки
$orderPayload = [
    'pickup'  => 'Kyiv, Khreshchatyk St, 1',
    'dropoff' => 'Kyiv, Peremohy Ave, 37',
    'waypoints' => [
        'Kyiv, Velyka Vasylkivska St, 100',
    ],
    'entities' => [
        [
            'destination'  => 0, // pickup
            'name'         => 'Паллета А',
            'description'  => 'Забор со склада, ворота №3, погрузка вилочным погрузчиком.',
        ],
        [
            'destination'  => 1, // промежуточный waypoint
            'name'         => 'Груз для точки 2 (Velyka Vasylkivska)',
            'description'  => 'Занос на 4-й этаж, лифт не работает, звонить за 15 минут.',
        ],
        [
            'destination'  => 2, // dropoff
            'name'         => 'Груз для точки 3 (Peremohy Ave)',
            'description'  => 'Разгрузка со стороны двора, охрана требует паспорт.',
        ],
    ],
];

$createResult = fleetbaseRequest('POST', '/orders', $orderPayload);

echo "HTTP status: {$createResult['status']}\n";
echo "Response:\n";
echo json_encode($createResult['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($createResult['status'] < 200 || $createResult['status'] >= 300) {
    fwrite(STDERR, "Заказ не создан, дальнейшая проверка невозможна.\n");
    exit(1);
}

$orderId = $createResult['body']['id'] ?? $createResult['body']['order']['id'] ?? null;

if (!$orderId) {
    fwrite(STDERR, "Не удалось извлечь ID заказа из ответа.\n");
    exit(1);
}

echo "=== 2. Получение заказа обратно, чтобы проверить, что каждая entity сохранила свой destination ===\n\n";

$getResult = fleetbaseRequest('GET', "/orders/{$orderId}?with[]=payload.entities&with[]=payload.waypoints");

echo "HTTP status: {$getResult['status']}\n";
echo json_encode($getResult['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== Готово. Order ID: {$orderId} ===\n";
