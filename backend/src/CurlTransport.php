<?php
declare(strict_types=1);
namespace Orizon;

final class CurlTransport implements HttpTransport
{
    public function post(string $url, array $headers, array $payload): array
    {
        if (!extension_loaded('curl')) {
            throw new ApiException(503, 'CURL_MISSING', 'Abilita l’estensione curl nella versione PHP usata da Apache.');
        }
        // Questi indirizzi sono definiti dal codice, mai da messaggi o argomenti del modello.
        $allowed = ['https://generativelanguage.googleapis.com/v1beta/interactions', 'https://api.ecofreight.co/api/v1/calculate'];
        if (!in_array($url, $allowed, true)) {
            throw new ApiException(500, 'UPSTREAM_URL', 'Indirizzo del servizio non consentito.');
        }
        $body = '';
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 2097152) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $success = curl_exec($handle);
        $errno = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlDetail = curl_error($handle);
        curl_close($handle);
        if ($success === false) {
            error_log('[Orizon] host=' . parse_url($url, PHP_URL_HOST) . '; curl=' . $errno . '; ' . $curlDetail);
            if ($errno === 60) {
                throw new ApiException(502, 'TLS_CERTIFICATE', 'PHP non riesce a verificare il certificato HTTPS. Configura curl.cainfo in php.ini mantenendo attiva la verifica TLS.');
            }
            throw new ApiException(502, 'NETWORK_ERROR', 'Il servizio esterno non è raggiungibile o ha impiegato troppo tempo. Riprova tra poco.');
        }
        try {
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            if ($status >= 400) {
                return ['status' => $status, 'body' => []];
            }
            throw new ApiException(502, 'UPSTREAM_JSON', 'Il servizio esterno ha restituito una risposta non leggibile.');
        }
        return ['status' => $status, 'body' => is_array($data) ? $data : []];
    }
}
