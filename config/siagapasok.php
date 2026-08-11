<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Controlled Demo Environment
    |--------------------------------------------------------------------------
    |
    | Demo utilities harus opt-in secara eksplisit.
    |
    | Jangan menggunakan APP_ENV === 'local' sebagai satu-satunya guard karena
    | development environment tidak otomatis berarti controlled demo mode.
    |
    */

    'demo' => [
        'enabled' => env(
            'SIAGAPASOK_DEMO_MODE',
            false
        ),

        'label' => 'SIMULASI',
    ],
];