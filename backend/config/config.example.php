<?php
declare(strict_types=1);

// File pubblico di base: deve rimanere nel repository e sul server.
// Copiarlo in config.php e inserire solo nella copia privata i valori reali.
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_password' => '',
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-3.5-flash-lite',
    'ecofreight_api_key' => '',
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],
    'requests_per_hour' => 60,
    'max_turns' => 40,
];
