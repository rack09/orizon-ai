<?php
declare(strict_types=1);
namespace Orizon;

final class Agent
{
    public function __construct(private GeminiClient $model, private EcoFreightClient $emissions) {}

    public function respond(array $history, string $message): array
    {
        $history[] = ['type' => 'user_input', 'content' => [['type' => 'text', 'text' => $message]]];
        $calculations = [];
        $records = [];
        $toolCount = 0;
        $lastError = '';

        for ($round = 0; $round < 3; $round++) {
            try {
                $interaction = $this->model->next($history);
            } catch (ApiException $error) {
                if (!$records) {
                    throw $error;
                }
                // Conserviamo un risultato EcoFreight già ottenuto anche se il riepilogo AI fallisce.
                return $this->fallback($history, $calculations, $records, $lastError ?: $error->getMessage());
            }

            $steps = $interaction['steps'];
            // Tutti i passaggi opachi del modello vengono conservati esattamente per il replay.
            array_push($history, ...$steps);
            $calls = array_values(array_filter($steps, static fn ($step) => is_array($step) && ($step['type'] ?? '') === 'function_call'));
            if (!$calls) {
                $text = GeminiClient::text($steps);
                if ($text === '') {
                    if ($records) {
                        return $this->fallback($history, $calculations, $records, $lastError);
                    }
                    throw new ApiException(502, 'GEMINI_EMPTY', 'L’assistente non ha restituito testo. Prova a riformulare il messaggio.');
                }
                return ['history' => $history, 'reply' => $text, 'calculations' => $calculations, 'tool_calls' => $records, 'response_kind' => 'model'];
            }
            if (count($calls) > 8) {
                throw new ApiException(502, 'TOOL_CALL_LIMIT', 'L’assistente ha richiesto troppi calcoli. Descrivi una tratta alla volta.');
            }
            foreach ($calls as $call) {
                $id = $call['id'] ?? '';
                $name = $call['name'] ?? '';
                if (!is_string($id) || $id === '' || strlen($id) > 200 || !is_string($name) || strlen($name) > 100) {
                    throw new ApiException(502, 'TOOL_PROTOCOL', 'L’assistente ha restituito una chiamata non valida. Riprova.');
                }
                $arguments = $call['arguments'] ?? [];
                $raw = null;
                try {
                    if (is_string($arguments)) {
                        $arguments = json_decode($arguments, true, 16, JSON_THROW_ON_ERROR);
                    }
                    if (!is_array($arguments)) {
                        throw new ApiException(422, 'TOOL_ARGUMENTS', 'Gli argomenti del calcolo non sono validi. Chiedi i dati mancanti.');
                    }
                    if ($name !== 'calcola_emissioni') {
                        throw new ApiException(422, 'TOOL_UNKNOWN', 'Questa funzione non è disponibile. Usa soltanto calcola_emissioni.');
                    }
                    if (++$toolCount > 3) {
                        throw new ApiException(422, 'TOOL_BUDGET', 'Sono consentiti al massimo tre calcoli per messaggio. Chiedi di separare le tratte.');
                    }
                    $calculation = $this->emissions->calculate($arguments);
                    $raw = $calculation['raw'];
                    $calculations[] = $calculation['summary'];
                    $result = ['ok' => true, 'data' => $calculation['summary']];
                } catch (\JsonException $error) {
                    $lastError = 'Gli argomenti della funzione non sono JSON valido. Chiedi di riformulare il tragitto.';
                    $result = ['ok' => false, 'error' => ['code' => 'TOOL_JSON', 'message' => $lastError]];
                } catch (ApiException $error) {
                    $lastError = $error->getMessage();
                    $result = ['ok' => false, 'error' => ['code' => $error->errorCode, 'message' => $lastError]];
                }
                $history[] = ['type' => 'function_result', 'name' => $name, 'call_id' => $id, 'result' => [['type' => 'text', 'text' => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]]];
                $records[] = ['call_id' => $id, 'name' => $name, 'arguments' => $arguments, 'result' => $result, 'raw_response' => $raw];
            }
        }
        return $this->fallback($history, $calculations, $records, $lastError);
    }

    private function fallback(array $history, array $calculations, array $records, string $error): array
    {
        $reply = $calculations
            ? 'EcoFreight ha restituito la stima riportata nella scheda. Il riepilogo dell’assistente non è disponibile in questo momento. La stima riguarda il peso trasportato, non l’intera impronta del passeggero.'
            : 'Non è stato possibile ottenere una stima. ' . ($error ?: 'Prova a descrivere una sola tratta con origine, destinazione, mezzo e peso.');
        $history[] = ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $reply]]];
        return ['history' => $history, 'reply' => $reply, 'calculations' => $calculations, 'tool_calls' => $records, 'response_kind' => 'fallback'];
    }
}
