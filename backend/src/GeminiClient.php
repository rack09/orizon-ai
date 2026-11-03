<?php
declare(strict_types=1);
namespace Orizon;

final class GeminiClient
{
    private string $systemInstruction;

    public function __construct(private HttpTransport $http, private string $apiKey, private string $model, ?string $promptPath = null)
    {
        // Un'unica lettura per istanza, anche quando il tool richiede più round.
        $prompt = @file_get_contents($promptPath ?? dirname(__DIR__) . '/config/system-prompt.txt');
        if ($prompt === false || trim($prompt) === '') {
            throw new ApiException(503, 'SYSTEM_PROMPT_MISSING', 'Le istruzioni dell’assistente non sono disponibili. Verifica backend/config/system-prompt.txt.');
        }
        $this->systemInstruction = $prompt;
    }

    public function next(array $history): array
    {
        if (trim($this->apiKey) === '') {
            throw new ApiException(503, 'GEMINI_KEY_MISSING', 'Inserisci la chiave Gemini in backend/config/config.php.');
        }
        $response = $this->http->post('https://generativelanguage.googleapis.com/v1beta/interactions', ['x-goog-api-key: ' . trim($this->apiKey)], [
            'model' => $this->model,
            'store' => false,
            'system_instruction' => $this->systemInstruction,
            'input' => $history,
            'tools' => [self::tool()],
            'generation_config' => ['max_output_tokens' => 2048, 'thinking_level' => 'low', 'thinking_summaries' => 'none'],
        ]);
        $status = $response['status'];
        if ($status < 200 || $status >= 300) {
            if ($status === 429) {
                throw new ApiException(429, 'GEMINI_QUOTA', 'Hai raggiunto una quota Gemini. Controlla i limiti del tuo progetto in AI Studio e riprova dopo il ripristino della quota.');
            }
            if (in_array($status, [401, 403], true)) {
                throw new ApiException(502, 'GEMINI_AUTH', 'La chiave Gemini non è valida o il progetto non ha accesso al modello. Controlla API Keys e quote in AI Studio.');
            }
            if ($status === 404) {
                throw new ApiException(502, 'GEMINI_MODEL', 'Il modello Gemini configurato non è disponibile per questo progetto. Verifica gemini_model in config.php e i modelli disponibili in AI Studio.');
            }
            throw new ApiException(502, 'GEMINI_RESPONSE', 'Gemini non ha completato la richiesta (HTTP ' . $status . '). Riprova; se persiste, verifica modello e accesso API in AI Studio.');
        }
        $data = $response['body'];
        if (!isset($data['steps']) || !is_array($data['steps']) || in_array($data['status'] ?? '', ['failed', 'cancelled'], true)) {
            throw new ApiException(502, 'GEMINI_EMPTY', 'Gemini non ha restituito una risposta utilizzabile. Prova a riformulare la richiesta.');
        }
        return $data;
    }

    public static function text(array $steps): string
    {
        $texts = [];
        foreach ($steps as $step) {
            if (($step['type'] ?? '') !== 'model_output') {
                continue;
            }
            foreach ((array) ($step['content'] ?? []) as $part) {
                if (is_array($part) && ($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) {
                    $texts[] = $part['text'];
                }
            }
        }
        return trim(implode("\n", $texts));
    }

    private static function tool(): array
    {
        return [
            'type' => 'function',
            'name' => 'calcola_emissioni',
            'description' => 'Calcola tramite EcoFreight le emissioni del trasporto di un peso su una singola tratta. Usala solo quando origine, destinazione, mezzo e peso sono noti. Non calcola l’impronta completa di un passeggero.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'origin' => ['type' => 'string', 'description' => 'Città di partenza con Paese, fornita o confermata dall’utente.'],
                    'destination' => ['type' => 'string', 'description' => 'Città di arrivo con Paese, fornita o confermata dall’utente.'],
                    'transport_mode' => ['type' => 'string', 'enum' => ['road', 'rail', 'sea', 'air']],
                    'weight_kg' => ['type' => 'number', 'minimum' => 1, 'maximum' => 100000, 'description' => 'Peso trasportato in chilogrammi, non peso dei passeggeri.'],
                ],
                'required' => ['origin', 'destination', 'transport_mode', 'weight_kg'],
                'additionalProperties' => false,
            ],
        ];
    }
}
