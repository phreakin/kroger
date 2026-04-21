<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OrderRepository extends BaseRepository
{
    public function forUser(int $userId, int $limit = 25): array
    {
        $stmt = $this->db->prepare('SELECT * FROM order_history WHERE user_id = :user_id ORDER BY purchased_at DESC LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
