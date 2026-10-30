<?php
declare(strict_types=1);
namespace Orizon;

use PDO;

final class ConversationRepository
{
    public function __construct(private PDO $pdo, private int $maxTurns = 40) {}

    public static function welcome(): string
    {
        return 'Ciao! Sono l’assistente di Orizon.' . "\n\n" . 'Descrivi il tuo tragitto: da dove parti, dove vuoi arrivare, il mezzo di trasporto e il peso dei bagagli o del carico in kg.';
    }

    public function create(): array
    {
        $token = bin2hex(random_bytes(32));
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('INSERT INTO conversations (token_hash, model_history) VALUES (?, ?)');
            $statement->execute([hash('sha256', $token), '[]']);
            $id = (int) $this->pdo->lastInsertId();
            $this->insertMessage($id, 'assistant', self::welcome(), [], 'welcome');
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->rollback();
            throw $error;
        }
        return ['ok' => true, 'conversation_token' => $token, 'messages' => $this->messages($id)];
    }

    public function history(string $token): array
    {
        $conversation = $this->find($token);
        return ['ok' => true, 'messages' => $this->messages((int) $conversation['id'])];
    }

    public function send(string $token, string $requestId, string $message, Agent $agent): array
    {
        self::validateMessage($message, $requestId);
        $this->pdo->beginTransaction();
        try {
            // Il blocco serializza due invii concorrenti della stessa conversazione.
            $conversation = $this->find($token, true);
            $id = (int) $conversation['id'];
            $statement = $this->pdo->prepare('SELECT content_hash FROM turn_requests WHERE conversation_id = ? AND request_id = ?');
            $statement->execute([$id, $requestId]);
            $previous = $statement->fetch();
            if ($previous) {
                if (!hash_equals($previous['content_hash'], hash('sha256', $message))) {
                    throw new ApiException(409, 'REQUEST_CONFLICT', 'Questo identificativo è già stato usato per un messaggio diverso. Riprova con un nuovo invio.');
                }
                $this->pdo->commit();
                return ['ok' => true, 'messages' => $this->messages($id), 'replayed' => true];
            }
            if ((int) $conversation['turn_count'] >= $this->maxTurns || strlen($conversation['model_history']) > 1000000) {
                throw new ApiException(422, 'CONVERSATION_LIMIT', 'Questa conversazione ha raggiunto il limite. Premi Nuova chat per continuare.');
            }
            $history = json_decode($conversation['model_history'], true, 64, JSON_THROW_ON_ERROR);
            $answer = $agent->respond($history, $message);
            $this->insertMessage($id, 'user', $message, [], 'user');
            $this->insertMessage($id, 'assistant', $answer['reply'], $answer['calculations'], $answer['response_kind']);
            $statement = $this->pdo->prepare('INSERT INTO tool_calls (conversation_id, request_id, call_id, function_name, arguments_json, result_json) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($answer['tool_calls'] as $call) {
                $statement->execute([$id, $requestId, $call['call_id'], $call['name'], self::json($call['arguments']), self::json(['result' => $call['result'], 'raw_response' => $call['raw_response']])]);
            }
            $statement = $this->pdo->prepare('UPDATE conversations SET model_history = ?, turn_count = turn_count + 1 WHERE id = ?');
            $statement->execute([self::json($answer['history']), $id]);
            $statement = $this->pdo->prepare('INSERT INTO turn_requests (conversation_id, request_id, content_hash) VALUES (?, ?, ?)');
            $statement->execute([$id, $requestId, hash('sha256', $message)]);
            $this->pdo->commit();
            return ['ok' => true, 'messages' => $this->messages($id), 'replayed' => false];
        } catch (\Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    public function limitRequests(string $client, int $limit): void
    {
        $hash = hash('sha256', $client);
        $window = intdiv(time(), 3600) * 3600;
        $statement = $this->pdo->prepare('INSERT INTO rate_limits (client_hash, window_start, request_count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE request_count = request_count + 1');
        $statement->execute([$hash, $window]);
        $statement = $this->pdo->prepare('SELECT request_count FROM rate_limits WHERE client_hash = ? AND window_start = ?');
        $statement->execute([$hash, $window]);
        if ((int) $statement->fetchColumn() > max(1, $limit)) {
            throw new ApiException(429, 'RATE_LIMIT', 'Hai raggiunto il limite di richieste dell’applicazione per quest’ora. Riprova più tardi.');
        }
        $statement = $this->pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?');
        $statement->execute([$window - 86400]);
    }

    public static function validateMessage(string $message, string $requestId): void
    {
        $length = preg_match_all('/./us', $message);
        if ($length === false || $length > 1200 || !preg_match('/\S/u', $message)) {
            throw new ApiException(422, 'MESSAGE_INVALID', 'Scrivi un messaggio non vuoto di massimo 1200 caratteri.');
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $requestId)) {
            throw new ApiException(422, 'REQUEST_ID', 'L’identificativo della richiesta non è valido. Ricarica la pagina.');
        }
    }

    private function find(string $token, bool $lock = false): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new ApiException(401, 'CONVERSATION_TOKEN', 'La conversazione non è riconosciuta. Premi Nuova chat.');
        }
        $statement = $this->pdo->prepare('SELECT * FROM conversations WHERE token_hash = ?' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([hash('sha256', $token)]);
        $conversation = $statement->fetch();
        if (!$conversation) {
            throw new ApiException(404, 'CONVERSATION_NOT_FOUND', 'Questa conversazione non è più disponibile. Premi Nuova chat.');
        }
        return $conversation;
    }

    private function messages(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT id, role, content, calculations, response_kind FROM messages WHERE conversation_id = ? ORDER BY id');
        $statement->execute([$id]);
        return array_map(static fn ($row) => [
            'id' => (string) $row['id'], 'role' => $row['role'], 'content' => $row['content'],
            'calculations' => $row['calculations'] ? json_decode($row['calculations'], true, 64, JSON_THROW_ON_ERROR) : [],
            'response_kind' => $row['response_kind'],
        ], $statement->fetchAll());
    }

    private function insertMessage(int $id, string $role, string $text, array $calculations, string $kind): void
    {
        $statement = $this->pdo->prepare('INSERT INTO messages (conversation_id, role, content, calculations, response_kind) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$id, $role, $text, self::json($calculations), $kind]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
