<?php

// App-level override of Fleetbase's SmsService defaults.
// Does NOT touch vendor code. Only the test phone number below
// is routed to the custom_http (Telegram) provider — every other
// number keeps using the default_provider (twilio) unchanged.

return [
    'default_provider' => env('SMS_DEFAULT_PROVIDER', 'twilio'),

    'routing_rules' => [
        '+976' => 'callpro', // keep Fleetbase's original Mongolia rule
        env('SMS_TEST_ROUTE_PHONE', '+380674105077') => 'custom_http',
    ],

    'providers' => [
        'custom_http' => [
            'url'    => env('SMS_CUSTOM_HTTP_URL', 'http://telegram-relay:9000/relay'),
            'method' => 'POST',
            'body'   => [
                'to'   => '{{to}}',
                'text' => '{{text}}',
            ],
        ],
    ],
];
