<?php

return [
    'tax' => [
        'iva_rate' => env('COMMERCE_IVA_RATE', 0.15),
    ],
    'stock' => [
        'low_threshold' => (int) env('COMMERCE_STOCK_LOW_THRESHOLD', 5),
    ],
    'reservation' => [
        'window_minutes' => (int) env('COMMERCE_RESERVATION_MINUTES', 15),
    ],
];
