<?php
declare(strict_types=1);

ini_set('display_errors', '0');
set_time_limit(180);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

use Orizon\Agent;
use Orizon\ApiException;
use Orizon\Config;
use Orizon\ConversationRepository;
use Orizon\CurlTransport;
use Orizon\EcoFreightClient;
use Orizon\GeminiClient;
use Orizon\Limits;

function respond(array $body, int $status = 200): void
{
    if (isset($body['messages'])) {
        $body['limits'] = Limits::publicValues();
    }
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    if (PHP_VERSION_ID < 80100) {
        respond(['ok' => false, 'error' => ['code' => 'PHP_VERSION', 'message' => 'Seleziona PHP 8.1 o successivo in WAMP.']], 503);
    }
    require dirname(__DIR__) . '/src/bootstrap.php';
    $config = Config::load();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        if (!in_array($origin, $config['allowed_origins'], true)) {
            throw new ApiException(403, 'ORIGIN_NOT_ALLOWED', 'L’origine della richiesta non è autorizzata. Verifica allowed_origins nella configurazione.');
        }
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if ($method === 'GET' && ($_GET['action'] ?? 'status') === 'status') {
        respond(Config::status($config));
    }
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST, OPTIONS');
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Metodo HTTP non supportato.');
    }
    $input = [];
    if ($method === 'POST') {
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            throw new ApiException(415, 'CONTENT_TYPE', 'Invia il corpo della richiesta come application/json.');
        }
        $raw = file_get_contents('php://input', false, null, 0, Limits::MAX_REQUEST_BYTES + 1);
        if ($raw === false) {
            throw new ApiException(400, 'INVALID_BODY', 'Il corpo della richiesta non è leggibile.');
        }
        if (strlen($raw) > Limits::MAX_REQUEST_BYTES) {
            throw new ApiException(413, 'BODY_TOO_LARGE', 'Il messaggio è troppo lungo.');
        }
        try {
            $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new ApiException(400, 'INVALID_JSON', 'Il corpo della richiesta non contiene JSON valido.');
        }
        if (!is_array($input) || array_is_list($input)) {
            throw new ApiException(400, 'INVALID_BODY', 'Il corpo della richiesta deve essere un oggetto JSON.');
        }
    }
    $action = $method === 'POST' ? ($input['action'] ?? '') : ($_GET['action'] ?? '');
    if (($method === 'GET' && $action !== 'history') || ($method === 'POST' && !in_array($action, ['start', 'send'], true))) {
        throw new ApiException(400, 'INVALID_ACTION', 'Azione non riconosciuta.');
    }
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($authorization === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $authorization = $value;
            }
        }
    }
    $token = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
    if ($action === 'send') {
        if (!is_string($input['message'] ?? null) || !is_string($input['request_id'] ?? null)) {
            throw new ApiException(422, 'MESSAGE_INVALID', 'Messaggio o identificativo della richiesta mancante.');
        }
        ConversationRepository::validateMessage(trim($input['message']), $input['request_id']);
    }
    $repository = new ConversationRepository(Config::connect($config), max(1, (int) $config['max_turns']));
    if ($action === 'history') {
        respond($repository->history($token));
    }
    $repository->limitRequests($_SERVER['REMOTE_ADDR'] ?? 'unknown', (int) $config['requests_per_hour']);
    if ($action === 'start') {
        respond($repository->create(), 201);
    }
    $transport = new CurlTransport();
    $agent = new Agent(new GeminiClient($transport, $config['gemini_api_key'], $config['gemini_model']), new EcoFreightClient($transport, $config['ecofreight_api_key']));
    respond($repository->send($token, $input['request_id'], trim($input['message']), $agent));
} catch (ApiException $error) {
    if ($error->httpStatus === 429) {
        header('Retry-After: 60');
    }
    respond(['ok' => false, 'error' => ['code' => $error->errorCode, 'message' => $error->getMessage()]], $error->httpStatus);
} catch (PDOException $error) {
    $code = $error->getCode();
    if ($code === '42S02') {
        respond(['ok' => false, 'error' => ['code' => 'DATABASE_SCHEMA', 'message' => 'Importa backend/database/schema.sql nel database orizon_ai tramite phpMyAdmin.']], 503);
    }
    if (in_array((int) ($error->errorInfo[1] ?? 0), [1205, 1213], true)) {
        respond(['ok' => false, 'error' => ['code' => 'CONVERSATION_BUSY', 'message' => 'Un’altra richiesta della chat è in corso. Attendi qualche secondo e riprova.']], 409);
    }
    error_log('[Orizon] database error; SQLSTATE=' . $code);
    respond(['ok' => false, 'error' => ['code' => 'DATABASE_ERROR', 'message' => 'Il salvataggio della conversazione non è riuscito. Riprova tra poco.']], 500);
} catch (Throwable $error) {
    // Non registrare chiavi, messaggi, token o risposte dei provider nei log pubblici.
    error_log('[Orizon] unexpected error; type=' . get_class($error));
    respond(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Il server non ha completato la richiesta. Verifica la configurazione PHP e riprova.']], 500);
}
