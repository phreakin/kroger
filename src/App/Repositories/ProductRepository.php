<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProductRepository extends BaseRepository
{
    public function latest(int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM products ORDER BY updated_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(string $query, int $limit = 24): array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE description LIKE :q OR brand LIKE :q ORDER BY updated_at DESC LIMIT :limit');
        $stmt->bindValue(':q', '%' . $query . '%');
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
