<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Ports\Clock;
use App\Application\Ports\TokenIssuer;
use App\Domain\Role;
use RuntimeException;

/** JWT HS256 sin dependencias externas (D-11): sub, role, iat y exp; sin datos personales. */
final class Hs256TokenIssuer implements TokenIssuer
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds,
        private readonly Clock $clock,
    ) {
        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET no está configurado.');
        }
    }

    public function issue(string $userId, Role $role): array
    {
        $now = $this->clock->now()->getTimestamp();
        $header = $this->encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->encode(json_encode([
            'sub' => $userId,
            'role' => $role->value,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ], JSON_THROW_ON_ERROR));

        return [
            'token' => $header . '.' . $payload . '.' . $this->sign($header . '.' . $payload),
            'expires_in' => $this->ttlSeconds,
        ];
    }

    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;

        if (!hash_equals($this->sign($header . '.' . $payload), $signature)) {
            return null;
        }

        $head = json_decode((string) $this->decode($header), true);
        if (!is_array($head) || ($head['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $claims = json_decode((string) $this->decode($payload), true);
        if (!is_array($claims) || !isset($claims['sub'], $claims['role'], $claims['exp'])) {
            return null;
        }
        if ((int) $claims['exp'] <= $this->clock->now()->getTimestamp()) {
            return null;
        }

        $role = Role::tryFrom((string) $claims['role']);
        if ($role === null) {
            return null;
        }

        return ['sub' => (string) $claims['sub'], 'role' => $role];
    }

    private function sign(string $data): string
    {
        return $this->encode(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function decode(string $encoded): string|false
    {
        return base64_decode(strtr($encoded, '-_', '+/'), true);
    }
}
