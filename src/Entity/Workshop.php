<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

final class Workshop
{
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_VALIDE = 'valide';
    public const STATUT_ANNULE = 'annule';

    private ?int $id = null;
    private string $title;
    private string $description;
    private float $price;
    private string $status;
    private int $trainerId;
    private int $nbPlaces;
    private int $nbInscrits = 0;
    private DateTimeImmutable $scheduledAt;
    private int $durationMinutes;
    private string $location;
    private ?string $imageUrl = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $title,
        string $description,
        float $price,
        int $trainerId,
        DateTimeImmutable $scheduledAt,
        int $durationMinutes,
        int $nbPlaces,
        string $location,
        string $status = self::STATUT_EN_ATTENTE
    ) {
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setPrice($price);
        $this->setTrainerId($trainerId);
        $this->setScheduledAt($scheduledAt);
        $this->setDurationMinutes($durationMinutes);
        $this->setNbPlaces($nbPlaces);
        $this->setLocation($location);
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
            throw new InvalidArgumentException('Invalid workshop id');
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
        $allowed = [self::STATUT_BROUILLON, self::STATUT_EN_ATTENTE, self::STATUT_VALIDE, self::STATUT_ANNULE];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid workshop status');
        }

        $this->status = $status;
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

    public function getNbPlaces(): int
    {
        return $this->nbPlaces;
    }

    public function setNbPlaces(int $nbPlaces): void
    {
        if ($nbPlaces <= 0) {
            throw new InvalidArgumentException('Number of places must be positive');
        }

        $this->nbPlaces = $nbPlaces;
        $this->touch();
    }

    public function getNbInscrits(): int
    {
        return $this->nbInscrits;
    }

    public function setNbInscrits(int $nbInscrits): void
    {
        if ($nbInscrits < 0) {
            throw new InvalidArgumentException('Number of registrations cannot be negative');
        }

        if ($nbInscrits > $this->nbPlaces) {
            throw new InvalidArgumentException('Registrations exceed capacity');
        }

        $this->nbInscrits = $nbInscrits;
        $this->touch();
    }

    public function incrementInscription(): void
    {
        if ($this->nbInscrits >= $this->nbPlaces) {
            throw new InvalidArgumentException('Workshop is full');
        }

        $this->nbInscrits++;
        $this->touch();
    }

    public function decrementInscription(): void
    {
        if ($this->nbInscrits <= 0) {
            return;
        }

        $this->nbInscrits--;
        $this->touch();
    }

    public function getScheduledAt(): DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(DateTimeImmutable $scheduledAt): void
    {
        $this->scheduledAt = $scheduledAt;
        $this->touch();
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): void
    {
        if ($durationMinutes <= 0) {
            throw new InvalidArgumentException('Duration must be positive');
        }

        $this->durationMinutes = $durationMinutes;
        $this->touch();
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): void
    {
        $location = trim($location);
        if ($location === '') {
            throw new InvalidArgumentException('Location required');
        }

        $this->location = $location;
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
            'trainer_id' => $this->trainerId,
            'nb_places' => $this->nbPlaces,
            'nb_inscrits' => $this->nbInscrits,
            'scheduled_at' => $this->scheduledAt->format(DATE_ATOM),
            'duration_minutes' => $this->durationMinutes,
            'location' => $this->location,
            'image_url' => $this->imageUrl,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
