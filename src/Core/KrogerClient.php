<?php
class KrogerClient {
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;

    public function __construct(array $config) {
        $this->baseUrl = $config['base_url'];
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
    }

    private function getAccessToken(): string {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new RuntimeException('Missing Kroger API credentials in environment.');
        }

        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $ch = curl_init('https://api.kroger.com/v1/connect/oauth2/token');
        $data = http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => 'product.compact',
        ]);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('Kroger token request failed');
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $json = json_decode($response, true);
        $this->accessToken = $json['access_token'] ?? null;

        if (!$this->accessToken) {
            $message = $json['error_description'] ?? $json['error'] ?? 'No access token from Kroger';
            throw new RuntimeException($status >= 400 ? "Kroger auth failed: {$message}" : $message);
        }

        return $this->accessToken;
    }

    private function request(string $endpoint, array $params = []): array {
        $result = $this->requestWithMeta('GET', $endpoint, $params);
        return $result['body'];
    }

    private function requestWithMeta(
        string $method,
        string $endpoint,
        array $params = [],
        ?array $body = null,
        ?string $accessToken = null
    ): array {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params) && strtoupper($method) === 'GET') {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . ($accessToken ?: $this->getAccessToken()),
            'Accept: application/json',
        ];

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $curlOptions[CURLOPT_HTTPHEADER] = $headers;
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        if (!empty($params) && strtoupper($method) !== 'GET') {
            $url .= '?' . http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        curl_setopt_array($ch, [
            ...$curlOptions,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('Kroger API request failed');
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $json = json_decode($response, true) ?? [];
        if ($status >= 400) {
            $message = $json['errors'][0]['message']
                ?? $json['errors']['reason']
                ?? $json['error_description']
                ?? $json['error']
                ?? 'Kroger API request failed';
            throw new RuntimeException($message, $status);
        }

        return [
            'status' => $status,
            'body' => $json,
        ];
    }

    public function searchProducts(string $term, string $locationId, int $limit = 20): array {
        return $this->request('/products', [
            'filter.term' => $term,
            'filter.locationId' => $locationId,
            'filter.limit' => $limit,
        ]);
    }

    public function searchLocations(string $zipCode, int $limit = 8): array {
        return $this->request('/locations', [
            'filter.zipCode.near' => $zipCode,
            'filter.limit' => $limit,
        ]);
    }

    public function getProduct(string $productId, string $locationId): array {
        return $this->request('/products/' . rawurlencode($productId), [
            'filter.locationId' => $locationId,
        ]);
    }

    public function getCart(string $accessToken): array {
        $result = $this->requestWithMeta('GET', '/cart', [], null, $accessToken);
        return $result['body'];
    }

    public function addToCart(array $items, string $accessToken, ?string $modality = null): array {
        $payloadItems = array_map(static function (array $item) use ($modality): array {
            $payload = [
                'upc' => (string) ($item['upc'] ?? ''),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            ];
            if ($modality !== null && $modality !== '') {
                $payload['modality'] = $modality;
            }
            return $payload;
        }, $items);

        $result = $this->requestWithMeta('PUT', '/cart/add', [], ['items' => $payloadItems], $accessToken);
        return $result['body'];
    }

    public function updateCartItem(string $upc, int $quantity, string $accessToken, ?string $modality = null): array {
        $payload = [
            'items' => [[
                'upc' => $upc,
                'quantity' => max(0, $quantity),
            ]],
        ];
        if ($modality !== null && $modality !== '') {
            $payload['items'][0]['modality'] = $modality;
        }

        $result = $this->requestWithMeta('PUT', '/cart/change', [], $payload, $accessToken);
        return $result['body'];
    }

    public function removeFromCart(array $items, string $accessToken): array {
        $payloadItems = array_map(static function (array $item): array {
            return [
                'upc' => (string) ($item['upc'] ?? ''),
                'quantity' => 0,
            ];
        }, $items);
        $result = $this->requestWithMeta('PUT', '/cart/remove', [], ['items' => $payloadItems], $accessToken);
        return $result['body'];
    }
}
