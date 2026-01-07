<?php

declare(strict_types=1);

namespace App\Service;

final class FlashBag
{
    private const SESSION_KEY = 'myc_flash';

    public function add(string $type, string $message): void
    {
        $_SESSION[self::SESSION_KEY][$type][] = $message;
    }

    public function all(): array
    {
        $flashes = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        return $flashes;
    }
}
