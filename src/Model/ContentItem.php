<?php

declare(strict_types=1);

namespace App\Model;

final class ContentItem
{
    public function __construct(
        public int $id,
        public string $type,
        public string $title,
        public string $trainer,
        public float $price,
        public ?string $imageUrl,
        public ?string $level,
        public ?string $status,
        public ?string $scheduledAt,
        public ?int $remainingSeats
    ) {
    }
}
