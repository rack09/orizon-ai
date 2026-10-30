<?php
declare(strict_types=1);
namespace Orizon;

final class EcoFreightClient
{
    public function __construct(private HttpTransport $http, private string $apiKey) {}

    public function calculate(array $arguments): array
    {
        $values = self::validate($arguments);
        if (trim($this->apiKey) === '') {
            throw new ApiException(503, 'ECOFREIGHT_KEY_MISSING', 'Inserisci la chiave EcoFreight in backend/config/config.php.');
        }
        $response = $this->http->post('https://api.ecofreight.co/api/v1/calculate', ['Authorization: Bearer ' . trim($this->apiKey)], [
            'origin' => ['query' => $values['origin']],
            'destination' => ['query' => $values['destination']],
            'cargo' => ['weight' => $values['weight_kg'], 'type' => 'general'],
            'transport_mode' => $values['transport_mode'],
        ]);
        $status = $response['status'];
        if ($status < 200 || $status >= 300) {
            $messages = [
                400 => 'EcoFreight non ha accettato il tragitto. Verifica luoghi, mezzo e peso.',
                401 => 'La chiave EcoFreight non è valida. Controllala in config.php.',
                403 => 'Il progetto EcoFreight non è autorizzato a eseguire questo calcolo.',
                404 => 'EcoFreight non riconosce una delle località. Specifica città e Paese.',
                422 => 'La combinazione di mezzo e tragitto non è supportata da EcoFreight.',
                429 => 'La quota EcoFreight è esaurita. Controlla il pannello del servizio.',
            ];
            throw new ApiException(502, 'ECOFREIGHT_RESPONSE', $messages[$status] ?? 'EcoFreight non è disponibile in questo momento. Riprova più tardi.');
        }
        $raw = $response['body'];
        $emissions = $raw['emissions']['wtw'] ?? $raw['emissions']['total'] ?? null;
        if ((!is_int($emissions) && !is_float($emissions)) || !is_finite((float) $emissions) || $emissions < 0) {
            throw new ApiException(502, 'ECOFREIGHT_DATA', 'EcoFreight non ha restituito una quantità valida di emissioni. Nessuna stima è stata calcolata.');
        }
        $distance = $raw['calculation']['distance'] ?? null;
        $summary = array_merge($values, [
            'emissions_kg_co2e' => $emissions,
            'distance_km' => (is_int($distance) || is_float($distance)) && is_finite((float) $distance) && $distance >= 0 ? $distance : null,
            'source' => 'EcoFreight',
            'methodology' => is_string($raw['methodology']['framework'] ?? null) ? $raw['methodology']['framework'] : null,
            'calculation_id' => is_string($raw['calculation_id'] ?? null) ? $raw['calculation_id'] : null,
        ]);
        return ['summary' => $summary, 'raw' => $raw];
    }

    public static function validate(array $arguments): array
    {
        $values = [];
        foreach (['origin', 'destination'] as $field) {
            $value = $arguments[$field] ?? null;
            if (!is_string($value) || strlen(trim($value)) < 2 || strlen($value) > 300 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                throw new ApiException(422, 'TRIP_LOCATION', 'Specifica origine e destinazione come città e Paese, senza dati mancanti.');
            }
            $values[$field] = trim($value);
        }
        if (strcasecmp($values['origin'], $values['destination']) === 0) {
            throw new ApiException(422, 'TRIP_ROUTE', 'Origine e destinazione coincidono. Chiedi una tratta con località diverse.');
        }
        $mode = $arguments['transport_mode'] ?? null;
        if (!is_string($mode) || !in_array($mode, ['road', 'rail', 'sea', 'air'], true)) {
            throw new ApiException(422, 'TRIP_MODE', 'Mezzo non supportato: scegli trasporto su strada, ferrovia, mare o aereo.');
        }
        $weight = $arguments['weight_kg'] ?? null;
        if ((!is_int($weight) && !is_float($weight)) || !is_finite((float) $weight) || $weight < 1 || $weight > 100000) {
            throw new ApiException(422, 'TRIP_WEIGHT', 'EcoFreight richiede un peso compreso tra 1 e 100000 kg. Chiedi un peso valido e non inventarlo.');
        }
        $values['transport_mode'] = $mode;
        $values['weight_kg'] = $weight;
        return $values;
    }
}
