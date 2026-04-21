<?php
declare(strict_types=1);

namespace App\Repositories;

final class OAuthTokenRepository extends BaseRepository
{
    public function save(array $token): void
    {
        $sql = 'INSERT INTO oauth_tokens (provider, user_id, access_token, refresh_token, expires_at, scope, created_at, updated_at)
                VALUES (:provider, :user_id, :access_token, :refresh_token, :expires_at, :scope, NOW(), NOW())
                ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), expires_at = VALUES(expires_at), scope = VALUES(scope), updated_at = NOW()';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':provider' => $token['provider'] ?? 'kroger',
            ':user_id' => (int) ($token['user_id'] ?? 0),
            ':access_token' => $token['access_token'] ?? '',
            ':refresh_token' => $token['refresh_token'] ?? null,
            ':expires_at' => $token['expires_at'] ?? null,
            ':scope' => $token['scope'] ?? null,
        ]);
    }
}
