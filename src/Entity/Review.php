<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

final class Review
{
    private ?int $id = null;
    private int $userId;
    private int $contentId;
    private string $type;
    private int $rating;
    private string $comment;
    private DateTimeImmutable $createdAt;
    private ?string $authorName = null;

    public function __construct(int $userId, int $contentId, string $type, int $rating, string $comment)
    {
        $this->setUserId($userId);
        $this->setContentId($contentId);
        $this->setType($type);
        $this->setRating($rating);
        $this->setComment($comment);
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        if ($id !== null && $id < 0) {
            throw new InvalidArgumentException('Invalid review id');
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
        $allowed = [Inscription::TYPE_COURSE, Inscription::TYPE_WORKSHOP];
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException('Invalid type');
        }

        $this->type = $type;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Rating must be between 1 and 5');
        }

        $this->rating = $rating;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $comment = trim($comment);
        if ($comment === '') {
            throw new InvalidArgumentException('Comment required');
        }

        $this->comment = $comment;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'content_id' => $this->contentId,
            'type' => $this->type,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    public function setAuthorName(?string $author): void
    {
        $this->authorName = $author;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }
}
