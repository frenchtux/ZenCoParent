<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    private string $secret;
    private int $expiry;
    private string $algo = 'HS256';

    public function __construct(string $secret, int $expiry = 3600)
    {
        $this->secret = $secret;
        $this->expiry = $expiry;
    }

    public function generateAccessToken(string $userId, string $tenantId, string $role): string
    {
        $now = time();
        $payload = [
            'iss'       => 'zencoparent',
            'sub'       => $userId,
            'tenant_id' => $tenantId,
            'role'      => $role,
            'iat'       => $now,
            'exp'       => $now + $this->expiry,
            'jti'       => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        ];
        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(64));
    }

    public function validateAccessToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
        return (array) $decoded;
    }

    public function hashRefreshToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
