<?php

return [
    // Database datetimes use UTC. Calendar input and presentation use this zone.
    'timezone' => 'Asia/Tokyo',
    'name' => 'パーソナルトレーニング',
    'duration' => (int) env('BOOKING_DURATION_MINUTES', 60),
    'price' => (int) env('BOOKING_PRICE', 8800),
    'slot_minutes' => 30,
    'horizon_days' => 90,
];
