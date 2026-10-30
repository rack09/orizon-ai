<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';
echo json_encode(Orizon\Config::status(Orizon\Config::load()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
