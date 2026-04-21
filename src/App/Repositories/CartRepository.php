<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CartRepository extends BaseRepository
{
    public function listByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM grocery_list_items WHERE user_id = :user_id ORDER BY is_checked ASC, created_at DESC');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(int $userId, array $payload): int
    {
        $stmt = $this->db->prepare('INSERT INTO grocery_list_items (user_id, product_id, custom_name, quantity, is_checked) VALUES (:user_id, :product_id, :custom_name, :quantity, 0)');
        $stmt->execute([
            ':user_id' => $userId,
            ':product_id' => isset($payload['product_id']) ? (int) $payload['product_id'] : null,
            ':custom_name' => $payload['custom_name'] ?? null,
            ':quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
        ]);

        return (int) $this->db->lastInsertId();
    }
}
