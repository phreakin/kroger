<?php
declare(strict_types=1);

namespace App\Services\Cart;

use PDO;
use RuntimeException;

class KrogerCartService
{
    public function __construct(
        private PDO $db,
        private string $baseUrl,
        private ?string $accessToken = null
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function pushAddItem($cart, $upc, $quantity): array
    {
        $upc = trim((string) $upc);
        $quantity = max(1, (int) $quantity);
        if ($upc === '') {
            throw new RuntimeException('upc is required.');
        }

        $cart = $this->createKrogerCartIfMissing($cart);
        $payload = [
            'items' => [[
                'upc' => $upc,
                'quantity' => $quantity,
                'modality' => (string) ($cart['fulfillment_mode'] ?? 'instore'),
            ]],
        ];

        $response = $this->curlJsonRequest('PUT', '/v1/cart/add', $payload);
        $this->logSync((int) $cart['id'], 'add', $payload, $response['body'], $response['status']);
        $this->persistKrogerCartId((int) $cart['id'], $response['body']);
        return $response['body'];
    }

    public function pushRemoveItem($cart, $upc): array
    {
        $upc = trim((string) $upc);
        if ($upc === '') {
            throw new RuntimeException('upc is required.');
        }

        $cart = $this->createKrogerCartIfMissing($cart);
        $payload = [
            'items' => [[
                'upc' => $upc,
                'quantity' => 0,
            ]],
        ];

        $response = $this->curlJsonRequest('PUT', '/v1/cart/remove', $payload);
        $this->logSync((int) $cart['id'], 'remove', $payload, $response['body'], $response['status']);
        $this->persistKrogerCartId((int) $cart['id'], $response['body']);
        return $response['body'];
    }

    public function pushFullSync($cart): array
    {
        $cart = $this->createKrogerCartIfMissing($cart);
        $stmt = $this->db->prepare('
            SELECT upc, quantity
            FROM shopping_cart_items
            WHERE cart_id = :cart_id
            ORDER BY id ASC
        ');
        $stmt->execute([':cart_id' => (int) $cart['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $payload = [
            'items' => array_map(static function (array $item) use ($cart): array {
                return [
                    'upc' => (string) $item['upc'],
                    'quantity' => max(1, (int) $item['quantity']),
                    'modality' => (string) ($cart['fulfillment_mode'] ?? 'instore'),
                ];
            }, $items),
        ];

        $response = $this->curlJsonRequest('PUT', '/v1/cart/add', $payload);
        $this->logSync((int) $cart['id'], 'sync', $payload, $response['body'], $response['status']);
        $this->persistKrogerCartId((int) $cart['id'], $response['body']);
        return $response['body'];
    }

    public function createKrogerCartIfMissing($cart): array
    {
        if (!is_array($cart) || empty($cart['id'])) {
            throw new RuntimeException('Valid cart is required.');
        }
        if (!empty($cart['kroger_cart_id'])) {
            return $cart;
        }

        $payload = ['items' => []];
        $response = $this->curlJsonRequest('PUT', '/v1/cart/add', $payload);
        $this->logSync((int) $cart['id'], 'sync', $payload, $response['body'], $response['status']);
        $krogerCartId = $this->extractKrogerCartId($response['body']);

        if ($krogerCartId !== null) {
            $stmt = $this->db->prepare('
                UPDATE shopping_cart
                SET kroger_cart_id = :kroger_cart_id, last_synced_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ');
            $stmt->execute([
                ':kroger_cart_id' => $krogerCartId,
                ':id' => (int) $cart['id'],
            ]);
            $cart['kroger_cart_id'] = $krogerCartId;
        }

        return $cart;
    }

    public function logSync($cartId, $action, $request, $response, $status): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO shopping_cart_sync_log (
                cart_id,
                action,
                request_json,
                response_json,
                http_status
            ) VALUES (
                :cart_id,
                :action,
                :request_json,
                :response_json,
                :http_status
            )
        ');
        $stmt->execute([
            ':cart_id' => (int) $cartId,
            ':action' => (string) $action,
            ':request_json' => is_string($request) ? $request : json_encode($request, JSON_UNESCAPED_SLASHES),
            ':response_json' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_SLASHES),
            ':http_status' => (int) $status,
        ]);
    }

    private function curlJsonRequest(string $method, string $path, array $payload): array
    {
        if ($this->accessToken === null || trim($this->accessToken) === '') {
            throw new RuntimeException('Kroger access token is required.');
        }

        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('Kroger cart request failed: ' . curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = ['raw' => $raw];
        }

        if ($status >= 400) {
            $message = $body['errors'][0]['message']
                ?? $body['error_description']
                ?? $body['error']
                ?? 'Kroger cart request failed';
            throw new RuntimeException($message, $status);
        }

        return [
            'status' => $status > 0 ? $status : 200,
            'body' => $body,
        ];
    }

    private function persistKrogerCartId(int $cartId, array $response): void
    {
        $krogerCartId = $this->extractKrogerCartId($response);
        if ($krogerCartId === null) {
            $stmt = $this->db->prepare('UPDATE shopping_cart SET last_synced_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([':id' => $cartId]);
            return;
        }

        $stmt = $this->db->prepare('
            UPDATE shopping_cart
            SET kroger_cart_id = :kroger_cart_id, last_synced_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            ':kroger_cart_id' => $krogerCartId,
            ':id' => $cartId,
        ]);
    }

    private function extractKrogerCartId(array $response): ?string
    {
        $value = $response['data']['cartId']
            ?? $response['data']['id']
            ?? $response['cartId']
            ?? $response['id']
            ?? null;
        return $value !== null ? (string) $value : null;
    }
}
