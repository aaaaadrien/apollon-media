<?php
declare(strict_types=1);

namespace Apollon;

final class Request
{
    public static function query(string $key, ?string $default = null): ?string
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Decode the raw JSON request body.
     *
     * @return array<string, mixed>
     */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw === false ? '' : $raw, true);
        return is_array($data) ? $data : [];
    }

    public static function page(): int
    {
        return isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    }

    public static function limitClause(): string
    {
        $offset = (self::page() - 1) * PAGE_SIZE;
        return 'LIMIT ' . PAGE_SIZE . ' OFFSET ' . $offset;
    }
}
