<?php
declare(strict_types=1);

// Copia questo file come config.php e inserisci le due chiavi SOLO nella copia.
// Su WAMP, root e password vuota sono i valori locali abituali: verifica i tuoi.
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'orizon_ai',
    'db_user' => 'root',
    'db_password' => '',
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-3.5-flash-lite',
    'ecofreight_api_key' => '',
    'allowed_origins' => ['http://localhost:5173', 'http://127.0.0.1:5173'],
    'requests_per_hour' => 60,
    'max_turns' => 40,
];