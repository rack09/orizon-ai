<?php
declare(strict_types=1);
namespace Orizon;

use PDO;
use PDOException;

final class Config
{
    public static function load(): array
    {
        $directory = dirname(__DIR__) . '/config/';
        $values = require $directory . 'config.example.php';
        if (is_file($directory . 'config.php')) {
            $local = require $directory . 'config.php';
            if (!is_array($local)) {
                throw new ApiException(503, 'CONFIG_INVALID', 'Il file config.php deve restituire un array PHP.');
            }
            $values = array_replace($values, $local);
        }
        // Le variabili del server hanno precedenza sulla configurazione locale.
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'GEMINI_API_KEY', 'GEMINI_MODEL', 'ECOFREIGHT_API_KEY', 'REQUESTS_PER_HOUR', 'MAX_TURNS'] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $values[strtolower($key)] = $value;
            }
        }
        if (getenv('ALLOWED_ORIGINS') !== false) {
            $values['allowed_origins'] = array_filter(array_map('trim', explode(',', (string) getenv('ALLOWED_ORIGINS'))));
        }
        return $values;
    }

    public static function connect(array $config): PDO
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new ApiException(503, 'PDO_MYSQL_MISSING', 'Abilita l’estensione pdo_mysql nella versione PHP usata da Apache.');
        }
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['db_host'], (int) $config['db_port'], $config['db_name']), $config['db_user'], $config['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->exec('SET SESSION innodb_lock_wait_timeout = 5');
            return $pdo;
        } catch (PDOException $error) {
            throw new ApiException(503, 'DATABASE_CONNECTION', 'Connessione MySQL non disponibile. Avvia WAMP, crea orizon_ai e verifica host, porta, utente e password in config.php.');
        }
    }

    public static function status(array $config): array
    {
        $checks = [
            'php' => PHP_VERSION_ID >= 80100,
            'curl' => extension_loaded('curl'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'gemini_key' => trim((string) $config['gemini_api_key']) !== '',
            'ecofreight_key' => trim((string) $config['ecofreight_api_key']) !== '',
            'mysql' => false,
            'schema' => false,
        ];
        $note = 'Le chiavi sono controllate solo come presenza; la loro validità si verifica con il primo messaggio.';
        try {
            $pdo = self::connect($config);
            $checks['mysql'] = true;
            foreach (['conversations', 'messages', 'tool_calls', 'turn_requests', 'rate_limits'] as $table) {
                $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 0');
            }
            $checks['schema'] = true;
        } catch (ApiException $error) {
            $note = $error->getMessage();
        } catch (PDOException $error) {
            $note = 'Seleziona il database orizon_ai in phpMyAdmin e importa backend/database/schema.sql.';
        }
        return ['ok' => true, 'ready' => !in_array(false, $checks, true), 'checks' => $checks, 'note' => $note, 'model' => $config['gemini_model']];
    }
}
