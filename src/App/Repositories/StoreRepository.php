<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class StoreRepository extends BaseRepository
{
    public function latest(int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT * FROM stores ORDER BY updated_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
