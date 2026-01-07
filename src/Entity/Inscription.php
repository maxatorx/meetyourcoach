<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

final class Inscription
{
    public const TYPE_COURSE = 'cours';
    public const TYPE_WORKSHOP = 'atelier';

    private ?int $id = null;
    private int $userId;
    private int $contentId;
    private string $type;
    private DateTimeImmutable $createdAt;

    public function __construct(int $userId, int $contentId, string $type)
    {
        $this->setUserId($userId);
        $this->setContentId($contentId);
        $this->setType($type);
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        if ($id !== null && $id < 0) {
            throw new InvalidArgumentException('Invalid inscription id');
        }

        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user id');
        }

        $this->userId = $userId;
    }

    public function getContentId(): int
    {
        return $this->contentId;
    }

    public function setContentId(int $contentId): void
    {
        if ($contentId <= 0) {
            throw new InvalidArgumentException('Invalid content id');
        }

        $this->contentId = $contentId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $allowed = [self::TYPE_COURSE, self::TYPE_WORKSHOP];
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException('Invalid inscription type');
        }

        $this->type = $type;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'content_id' => $this->contentId,
            'type' => $this->type,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
