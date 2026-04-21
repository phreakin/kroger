<?php
declare(strict_types=1);

namespace App\Services\Kroger;

use RuntimeException;

final class KrogerApiClient
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function request(string $method, string $endpoint, array $query = [], ?array $body = null): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token(),
        ];

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Kroger API call failed.');
        }

        $decoded = json_decode($response, true) ?? [];
        if ($status >= 400) {
            throw new RuntimeException((string) ($decoded['error'] ?? $decoded['message'] ?? 'Kroger API error'), $status);
        }

        return $decoded;
    }

    private function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $ch = curl_init('https://api.kroger.com/v1/connect/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials',
                'scope' => getenv('KROGER_SCOPE_PRODUCTS') ?: 'product.compact',
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode((string) $response, true) ?: [];
        $token = (string) ($decoded['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Unable to retrieve Kroger access token.');
        }

        return $this->accessToken = $token;
    }
}
