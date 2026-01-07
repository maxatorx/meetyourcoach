<?php

declare(strict_types=1);

namespace App\Security;

final class CsrfTokenManager
{
    private const SESSION_KEY = 'myc_csrf';

    public function getToken(string $id = 'default'): string
    {
        if (!isset($_SESSION[self::SESSION_KEY][$id])) {
            $_SESSION[self::SESSION_KEY][$id] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY][$id];
    }

    public function validateToken(string $token, string $id = 'default'): bool
    {
        $stored = $_SESSION[self::SESSION_KEY][$id] ?? null;
        return is_string($stored) && hash_equals($stored, $token);
    }
}
