<?php
declare(strict_types=1);

namespace Apollon;

// Signs and verifies single-song share tokens. When ALLOW_GUEST_LISTEN is
// false, a guest who opens a song share link receives a short-lived, signed
// token that authorizes streaming of that one track (and only that track).
final class ShareToken
{
    private const PLACEHOLDER = 'CHANGE_ME_64_HEX_CHARS';

    public static function create(int $songId): string
    {
        $ttl = defined('SHARE_TOKEN_TTL') ? (int)SHARE_TOKEN_TTL : 0;
        $expiresAt = $ttl > 0 ? time() + $ttl : 0;
        $payload = $songId . '.' . $expiresAt;
        return self::b64encode($payload . '.' . self::sign($payload));
    }

    public static function verify(int $songId, string $token): bool
    {
        if ($songId <= 0 || $token === '') {
            return false;
        }
        $secret = defined('SHARE_SECRET') ? (string)SHARE_SECRET : '';
        if ($secret === '' || $secret === self::PLACEHOLDER) {
            return false; // feature not configured
        }
        $decoded = self::b64decode($token);
        if ($decoded === '') {
            return false;
        }
        $parts = explode('.', $decoded);
        if (count($parts) !== 3) {
            return false;
        }
        [$id, $expiresAt, $signature] = $parts;
        if ((int)$id !== $songId) {
            return false;
        }
        if (!hash_equals(self::sign($id . '.' . $expiresAt), $signature)) {
            return false;
        }
        if ((int)$expiresAt > 0 && (int)$expiresAt < time()) {
            return false;
        }
        return true;
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string)SHARE_SECRET);
    }

    private static function b64encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64decode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}
