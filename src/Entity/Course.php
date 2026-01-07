<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

final class Course
{
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_PUBLIE = 'publie';
    public const STATUT_ARCHIVE = 'archive';

    public const NIVEAU_DEBUTANT = 'debutant';
    public const NIVEAU_INTERMEDIAIRE = 'intermediaire';
    public const NIVEAU_AVANCE = 'avance';

    private ?int $id = null;
    private string $title;
    private string $description;
    private float $price;
    private string $status;
    private string $level;
    private int $trainerId;
    private ?string $imageUrl = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(string $title, string $description, float $price, int $trainerId, string $level = self::NIVEAU_DEBUTANT, string $status = self::STATUT_BROUILLON)
    {
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setPrice($price);
        $this->setTrainerId($trainerId);
        $this->setLevel($level);
        $this->setStatus($status);
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        if ($id !== null && $id < 0) {
            throw new InvalidArgumentException('Invalid course id');
        }

        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Title required');
        }

        $this->title = $title;
        $this->touch();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $description = trim($description);
        if ($description === '') {
            throw new InvalidArgumentException('Description required');
        }

        $this->description = $description;
        $this->touch();
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        if ($price < 0) {
            throw new InvalidArgumentException('Price must be positive');
        }

        $this->price = round($price, 2);
        $this->touch();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $allowed = [self::STATUT_BROUILLON, self::STATUT_PUBLIE, self::STATUT_ARCHIVE];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid course status');
        }

        $this->status = $status;
        $this->touch();
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): void
    {
        $allowed = [self::NIVEAU_DEBUTANT, self::NIVEAU_INTERMEDIAIRE, self::NIVEAU_AVANCE];
        if (!in_array($level, $allowed, true)) {
            throw new InvalidArgumentException('Invalid level');
        }

        $this->level = $level;
        $this->touch();
    }

    public function getTrainerId(): int
    {
        return $this->trainerId;
    }

    public function setTrainerId(int $trainerId): void
    {
        if ($trainerId <= 0) {
            throw new InvalidArgumentException('Trainer id required');
        }

        $this->trainerId = $trainerId;
        $this->touch();
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): void
    {
        $this->imageUrl = $imageUrl;
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'status' => $this->status,
            'level' => $this->level,
            'trainer_id' => $this->trainerId,
            'image_url' => $this->imageUrl,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
