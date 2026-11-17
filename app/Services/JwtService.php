<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Throwable;

class JwtService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly int $ttlSeconds = 3600,
    ) {}

    /** @return array{access_token: string, token_type: string, expires_in: int} */
    public function issue(User $user): array
    {
        $now = time();

        $token = JWT::encode([
            'iss' => $this->issuer,
            'sub' => (string) $user->getKey(),
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
            'jti' => (string) Str::uuid(),
        ], $this->secret, self::ALGORITHM);

        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $this->ttlSeconds];
    }

    /** Returns the user id, or null for expired / tampered / malformed / wrong-issuer tokens. */
    public function userIdFrom(string $token): ?int
    {
        try {
            // Key pins the algorithm: tokens signed with anything but HS256 (incl. "none") are rejected.
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable) {
            return null;
        }

        return ($claims->iss ?? null) === $this->issuer && isset($claims->sub) ? (int) $claims->sub : null;
    }
}
