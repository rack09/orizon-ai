<?php
declare(strict_types=1);

// Test deterministici: non vengono chiamati servizi esterni e non servono chiavi reali.
// Per i test MySQL opzionali, usare SOLO un database dedicato orizon_test_*.
require dirname(__DIR__) . '/src/bootstrap.php';

use Orizon\Agent;
use Orizon\ApiException;
use Orizon\ConversationRepository;
use Orizon\EcoFreightClient;
use Orizon\GeminiClient;
use Orizon\HttpTransport;
use Orizon\Limits;

final class FixtureTransport implements HttpTransport
{
    public array $requests = [];
    public function __construct(public array $queue) {}
    public function post(string $url, array $headers, array $payload): array
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'payload' => $payload];
        $entry = array_shift($this->queue);
        if ($entry === null) throw new RuntimeException('Chiamata esterna inattesa nel test.');
        check(str_contains($url, $entry['service']), 'Servizio chiamato nell’ordine previsto');
        return ['status' => $entry['status'] ?? 200, 'body' => $entry['body']];
    }
}

function check(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
}
function expectError(callable $call, string $code): void
{
    try { $call(); } catch (ApiException $error) { check($error->errorCode === $code, 'Codice atteso: ' . $code); return; }
    throw new RuntimeException('Eccezione attesa: ' . $code);
}
function modelText(string $text): array
{
    return ['service' => 'googleapis.com', 'body' => ['status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]]]];
}
function toolStep(array $arguments, string $name = 'calcola_emissioni'): array
{
    return ['service' => 'googleapis.com', 'body' => ['status' => 'requires_action', 'steps' => [
        ['type' => 'thought', 'signature' => 'opaque-test-signature'],
        ['type' => 'function_call', 'id' => 'call_test_1', 'name' => $name, 'arguments' => $arguments],
    ]]];
}
function freightResponse(mixed $amount = 0.568): array
{
    return ['service' => 'ecofreight.co', 'body' => ['emissions' => ['wtw' => $amount], 'calculation' => ['distance' => 573], 'methodology' => ['framework' => 'Fixture - solo test'], 'calculation_id' => 'fixture-not-a-real-calculation']];
}
function fixtureAgent(array $queue): array
{
    $http = new FixtureTransport($queue);
    return [new Agent(new GeminiClient($http, 'fixture-gemini-key', 'gemini-3.8-flash'), new EcoFreightClient($http, 'fixture-ecofreight-key')), $http];
}

$trip = ['origin' => 'Milano, Italia', 'destination' => 'Roma, Italia', 'transport_mode' => 'rail', 'weight_kg' => 20];
$tests = 0;

[$agent, $http] = fixtureAgent([modelText('Quanto pesano i bagagli?')]);
$answer = $agent->respond([], 'Da Milano a Roma in treno.');
check(count($http->requests) === 1 && !$answer['calculations'], 'Nessun calcolo se il modello chiede il dato mancante');
check($http->requests[0]['payload']['store'] === false, 'Memoria del provider disattivata');
$previousHistory = $answer['history'];
$tests++;

[$agent, $http] = fixtureAgent([toolStep($trip), freightResponse(), modelText('Ecco la stima del trasporto indicato.')]);
$answer = $agent->respond($previousHistory, '20 kg');
check($answer['calculations'][0]['emissions_kg_co2e'] === 0.568, 'Valore copiato dalla risposta EcoFreight');
check($http->requests[1]['payload']['cargo']['weight'] === 20, 'Peso in kg nel contratto EcoFreight');
check($http->requests[1]['payload']['origin'] === ['query' => 'Milano, Italia'], 'Località nel contratto EcoFreight');
check($http->requests[2]['payload']['input'][0] === $previousHistory[0], 'Contesto precedente riprodotto');
check($http->requests[2]['payload']['input'][3]['signature'] === 'opaque-test-signature', 'Passaggi opachi preservati');
check(end($http->requests[2]['payload']['input'])['type'] === 'function_result', 'Risultato tool restituito a Gemini');
check($answer['tool_calls'][0]['raw_response']['calculation_id'] === 'fixture-not-a-real-calculation', 'Risposta originale conservata');
$tests++;

foreach ([['weight_kg' => 0], ['weight_kg' => -2], ['weight_kg' => '20'], ['weight_kg' => INF], ['transport_mode' => 'car'], ['origin' => ''], ['destination' => $trip['origin']]] as $invalid) {
    [$agent, $http] = fixtureAgent([toolStep(array_replace($trip, $invalid)), modelText('Correggi i dati del tragitto.')]);
    $answer = $agent->respond([], 'Descrizione con dati non validi');
    check(count($http->requests) === 2 && !$answer['calculations'], 'Dati tool non validi bloccati prima di EcoFreight');
}
$tests++;

[$agent, $http] = fixtureAgent([toolStep($trip, 'esegui_comando'), modelText('Posso aiutarti con il calcolo del trasporto.')]);
$answer = $agent->respond([], 'Una richiesta fuori ambito');
check(count($http->requests) === 2 && !$answer['calculations'], 'Nessuna esecuzione di funzioni arbitrarie');
$tests++;

[$agent, $http] = fixtureAgent([toolStep($trip), freightResponse('non valido'), modelText('Il servizio non ha restituito una stima valida.')]);
$answer = $agent->respond([], 'Un viaggio completo');
check(!$answer['calculations'] && $answer['tool_calls'][0]['result']['ok'] === false, 'Nessun falso zero quando manca un valore numerico');
$tests++;

[$agent, $http] = fixtureAgent([toolStep($trip), freightResponse(), ['service' => 'googleapis.com', 'status' => 429, 'body' => []]]);
$answer = $agent->respond([], 'Un viaggio completo');
check($answer['response_kind'] === 'fallback' && $answer['calculations'][0]['emissions_kg_co2e'] === 0.568, 'Risultato EcoFreight preservato se il riepilogo Gemini fallisce');
$tests++;

[$agent, $http] = fixtureAgent([['service' => 'googleapis.com', 'status' => 429, 'body' => []]]);
expectError(static fn () => $agent->respond([], 'Ciao'), 'GEMINI_QUOTA');
expectError(static fn () => ConversationRepository::validateMessage('   ', str_repeat('a', 32)), 'MESSAGE_INVALID');
expectError(static fn () => ConversationRepository::validateMessage(str_repeat('é', Limits::MAX_MESSAGE_CHARACTERS + 1), str_repeat('a', 32)), 'MESSAGE_INVALID');
expectError(static fn () => ConversationRepository::validateMessage('Ciao', 'bad'), 'REQUEST_ID');
$tests++;

ConversationRepository::validateMessage(str_repeat('é', Limits::MAX_MESSAGE_CHARACTERS), str_repeat('a', 32));
check(Limits::publicValues()['message_characters'] === Limits::MAX_MESSAGE_CHARACTERS, 'Limite pubblico condiviso con il frontend');
$tests++;

$step = toolStep($trip);
$call = $step['body']['steps'][1];
$step['body']['steps'] = array_fill(0, Limits::MAX_TOOL_CALLS_PER_RESPONSE + 1, $call);
[$agent, $http] = fixtureAgent([$step]);
expectError(static fn () => $agent->respond([], 'Troppi calcoli insieme'), 'TOOL_CALL_LIMIT');
check(count($http->requests) === 1, 'Blocco prima di qualunque chiamata EcoFreight');
$tests++;

$step = toolStep($trip);
$step['body']['steps'] = [];
for ($i = 0; $i <= Limits::MAX_CALCULATIONS_PER_MESSAGE; $i++) {
    $step['body']['steps'][] = array_replace($call, ['id' => 'budget_' . $i]);
}
$queue = [$step];
for ($i = 0; $i < Limits::MAX_CALCULATIONS_PER_MESSAGE; $i++) $queue[] = freightResponse();
$queue[] = modelText('Ecco le stime disponibili.');
[$agent, $http] = fixtureAgent($queue);
$answer = $agent->respond([], 'Più tratte');
check(count($answer['calculations']) === Limits::MAX_CALCULATIONS_PER_MESSAGE, 'Budget del calcolatore rispettato');
check(end($answer['tool_calls'])['result']['error']['code'] === 'TOOL_BUDGET', 'Chiamata eccedente respinta');
$tests++;

$queue = [];
for ($i = 0; $i < Limits::MAX_MODEL_ROUNDS; $i++) {
    $queue[] = toolStep($trip);
    $queue[] = freightResponse();
}
[$agent, $http] = fixtureAgent($queue);
$answer = $agent->respond([], 'Continua a richiedere il tool');
check($answer['response_kind'] === 'fallback' && count($http->requests) === Limits::MAX_MODEL_ROUNDS * 2, 'Numero di round limitato con risultato preservato');
$tests++;

$promptPath = tempnam(sys_get_temp_dir(), 'orizon-prompt-');
try {
    file_put_contents($promptPath, 'Prima versione del prompt.');
    $http = new FixtureTransport([modelText('Uno'), modelText('Due'), modelText('Tre')]);
    $client = new GeminiClient($http, 'fixture-key', 'fixture-model', $promptPath);
    $client->next([]);
    file_put_contents($promptPath, 'Seconda versione del prompt.');
    $client->next([]);
    check($http->requests[1]['payload']['system_instruction'] === 'Prima versione del prompt.', 'Prompt letto una volta per istanza');
    (new GeminiClient($http, 'fixture-key', 'fixture-model', $promptPath))->next([]);
    check($http->requests[2]['payload']['system_instruction'] === 'Seconda versione del prompt.', 'Nuova istanza carica il prompt aggiornato');
    $tests++;
    file_put_contents($promptPath, '');
    expectError(static fn () => new GeminiClient($http, 'fixture-key', 'fixture-model', $promptPath), 'SYSTEM_PROMPT_MISSING');
} finally {
    unlink($promptPath);
}
expectError(static fn () => new GeminiClient(new FixtureTransport([]), 'fixture-key', 'fixture-model', $promptPath), 'SYSTEM_PROMPT_MISSING');
$tests++;

echo 'PASS: ' . $tests . ' scenari unitari con trasporto simulato, nessuna chiamata reale.' . PHP_EOL;

$dbName = getenv('ORIZON_TEST_DB');
if ($dbName === false || $dbName === '') {
    echo 'SKIP MySQL: imposta ORIZON_TEST_DB con il nome di un database dedicato orizon_test_* per attivare i test di persistenza.' . PHP_EOL;
    exit;
}
if (!preg_match('/^orizon_test_[a-zA-Z0-9_]+$/D', $dbName)) throw new RuntimeException('I test MySQL richiedono un database dedicato con prefisso orizon_test_.');
$pdo = new PDO('mysql:host=' . (getenv('ORIZON_TEST_HOST') ?: '127.0.0.1') . ';port=' . (getenv('ORIZON_TEST_PORT') ?: '3306') . ';dbname=' . $dbName . ';charset=utf8mb4', getenv('ORIZON_TEST_USER') ?: 'root', getenv('ORIZON_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$pdo->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
$repository = new ConversationRepository($pdo);
$one = $repository->create();
$two = $repository->create();
check($one['conversation_token'] !== $two['conversation_token'], 'Token distinti per conversazioni distinte');
try {
    [$agent, $http] = fixtureAgent([modelText('Quanto pesano i bagagli?')]);
    $requestId = bin2hex(random_bytes(16));
    $sent = $repository->send($one['conversation_token'], $requestId, 'Milano Roma in treno', $agent);
    check(count($sent['messages']) === 3, 'Messaggi salvati in MySQL');
    $restored = (new ConversationRepository($pdo))->history($one['conversation_token']);
    check($restored['messages'] === $sent['messages'], 'Recupero completo da una nuova istanza');
    $replay = $repository->send($one['conversation_token'], $requestId, 'Milano Roma in treno', $agent);
    check($replay['replayed'] === true && count($http->requests) === 1, 'Retry senza duplicati o nuove chiamate AI');
    expectError(static fn () => $repository->send($one['conversation_token'], $requestId, 'Testo diverso', $agent), 'REQUEST_CONFLICT');
    check(count($repository->history($two['conversation_token'])['messages']) === 1, 'Nessun accesso alla chat altrui');
    expectError(static fn () => $repository->history("' OR 1=1 --"), 'CONVERSATION_TOKEN');
    expectError(static fn () => $repository->history(str_repeat('a', 64)), 'CONVERSATION_NOT_FOUND');
    [$agent, $http] = fixtureAgent([toolStep($trip), freightResponse(), modelText('Ecco il risultato.')]);
    $sent = $repository->send($one['conversation_token'], bin2hex(random_bytes(16)), '20 kg', $agent);
    check(count($sent['messages']) === 5 && $sent['messages'][4]['calculations'][0]['weight_kg'] === 20, 'Risultato strutturato persistito');
    check($http->requests[0]['payload']['input'][0]['content'][0]['text'] === 'Milano Roma in treno', 'Memoria MySQL inviata al modello nel turno successivo');
    [$agent, $http] = fixtureAgent([['service' => 'googleapis.com', 'status' => 429, 'body' => []]]);
    expectError(static fn () => $repository->send($one['conversation_token'], bin2hex(random_bytes(16)), 'Un altro messaggio', $agent), 'GEMINI_QUOTA');
    check(count($repository->history($one['conversation_token'])['messages']) === 5, 'Rollback del turno fallito');
    $client = 'test-' . bin2hex(random_bytes(16));
    $repository->limitRequests($client, 2);
    $repository->limitRequests($client, 2);
    expectError(static fn () => $repository->limitRequests($client, 2), 'RATE_LIMIT');
    $short = new ConversationRepository($pdo, 1);
    [$agent, $http] = fixtureAgent([]);
    expectError(static fn () => $short->send($one['conversation_token'], bin2hex(random_bytes(16)), 'Altro', $agent), 'CONVERSATION_LIMIT');
    echo 'PASS: MySQL/MariaDB - persistenza, contesto, isolamento, idempotenza, rollback, quote e limite conversazione.' . PHP_EOL;
} finally {
    $statement = $pdo->prepare('DELETE FROM conversations WHERE token_hash IN (?, ?)');
    $statement->execute([hash('sha256', $one['conversation_token']), hash('sha256', $two['conversation_token'])]);
}
