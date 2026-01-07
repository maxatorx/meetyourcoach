<?php

declare(strict_types=1);

namespace App\Model;

use App\Config\Database;
use App\Entity\CalendarEvent;
use DateTimeImmutable;
use PDO;

final class CalendarEventModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(CalendarEvent $event): CalendarEvent
    {
        // Evenement personnel (agenda utilisateur).
        $statement = $this->pdo->prepare(
            'INSERT INTO calendrier_evenement (utilisateur_id, titre, description, lieu, scheduled_at, created_at, updated_at)
             VALUES (:user, :titre, :description, :lieu, :scheduled, :created, :updated)'
        );
        $statement->execute([
            'user' => $event->getUserId(),
            'titre' => $event->getTitle(),
            'description' => $event->getDescription(),
            'lieu' => $event->getLocation(),
            'scheduled' => $event->getScheduledAt()->format('Y-m-d H:i:s'),
            'created' => $event->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated' => $event->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        $event->setId((int) $this->pdo->lastInsertId());

        return $event;
    }

    /**
     * @return CalendarEvent[]
     */
    public function findForUserWithinPeriod(?int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $sql = 'SELECT * FROM calendrier_evenement WHERE scheduled_at BETWEEN :start AND :end';
        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ];

        if ($userId !== null) {
            $sql .= ' AND (utilisateur_id = :user OR utilisateur_id IS NULL)';
            $params['user'] = $userId;
        } else {
            $sql .= ' AND utilisateur_id IS NULL';
        }

        $sql .= ' ORDER BY scheduled_at ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $events = [];
        while ($row = $statement->fetch()) {
            $events[] = $this->hydrate($row);
        }

        return $events;
    }

    public function findById(int $id): ?CalendarEvent
    {
        $statement = $this->pdo->prepare('SELECT * FROM calendrier_evenement WHERE id = :id');
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();

        return $data ? $this->hydrate($data) : null;
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM calendrier_evenement WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function hydrate(array $data): CalendarEvent
    {
        $event = new CalendarEvent(
            $data['titre'],
            new DateTimeImmutable($data['scheduled_at']),
            isset($data['utilisateur_id']) ? (int) $data['utilisateur_id'] : null,
            $data['description'],
            $data['lieu']
        );
        $event->setId((int) $data['id']);
        $event->setCreatedAt(new DateTimeImmutable($data['created_at']));
        $event->setUpdatedAt(new DateTimeImmutable($data['updated_at']));

        return $event;
    }
}
