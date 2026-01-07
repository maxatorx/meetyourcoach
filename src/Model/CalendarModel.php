<?php

declare(strict_types=1);

namespace App\Model;

use App\Config\Database;
use App\Entity\Workshop;
use DateTimeImmutable;
use PDO;

final class CalendarModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * @param array<string, mixed>|null $user
     * @return array<int, array<string, mixed>>
     */
    public function getEventsForContext(?array $user, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        // Evenements visibles selon le role (apprenant, formateur, admin).
        $indexed = [];
        if (($user['role'] ?? null) === 'formateur' && isset($user['id'])) {
            $indexed = $this->appendEvents($indexed, $this->getTrainerEvents((int) $user['id'], $start, $end));
        } elseif (($user['role'] ?? null) === 'apprenant' && isset($user['id'])) {
            $indexed = $this->appendEvents($indexed, $this->getLearnerEvents((int) $user['id'], $start, $end));
        } elseif (($user['role'] ?? null) === 'admin') {
            $indexed = $this->appendEvents($indexed, $this->getAllTrainerEvents($start, $end));
        }

        return array_values($indexed);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getLearnerEvents(int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $sql = <<<SQL
            SELECT i.id,
                   i.type AS content_type,
                   i.contenu_id AS content_id,
                   i.created_at AS inscription_date,
                   a.scheduled_at,
                   a.titre AS workshop_title,
                   a.lieu,
                   a.description AS workshop_description,
                   a.prix AS workshop_price,
                   a.nb_places,
                   a.nb_inscrits,
                   c.titre AS course_title,
                   c.description AS course_description,
                   c.prix AS course_price
            FROM inscription i
            LEFT JOIN cours c ON i.type = 'cours' AND c.id = i.contenu_id
            LEFT JOIN atelier a ON i.type = 'atelier' AND a.id = i.contenu_id
            WHERE i.utilisateur_id = :user
            ORDER BY COALESCE(a.scheduled_at, i.created_at) ASC
        SQL;
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'user' => $userId,
        ]);

        $events = [];
        while ($row = $statement->fetch()) {
            $eventDate = null;
            if ($row['content_type'] === 'atelier' && !empty($row['scheduled_at'])) {
                $eventDate = new DateTimeImmutable($row['scheduled_at']);
            } else {
                $eventDate = new DateTimeImmutable($row['inscription_date']);
            }

            if ($eventDate < $start || $eventDate > $end) {
                continue;
            }

            $title = $row['content_type'] === 'atelier' ? $row['workshop_title'] : $row['course_title'];
            if (empty($title)) {
                continue;
            }

            $events[] = [
                'id' => 'registration-' . $row['id'],
                'content_key' => $this->buildContentKey($row['content_type'], (int) $row['content_id']),
                'date' => $eventDate,
                'title' => $title,
                'type' => $row['content_type'],
                'context' => 'inscription',
                'location' => $row['content_type'] === 'atelier' ? ($row['lieu'] ?? null) : null,
                'description' => $row['content_type'] === 'atelier' ? ($row['workshop_description'] ?? '') : ($row['course_description'] ?? ''),
                'price' => $row['content_type'] === 'atelier' ? $row['workshop_price'] : $row['course_price'],
                'capacity' => ($row['content_type'] === 'atelier' && $row['nb_places'] !== null)
                    ? ($row['nb_inscrits'] . '/' . $row['nb_places'])
                    : null,
                'cta' => $this->buildContentUrl($row['content_type'], (int) $row['content_id']),
                'cta_label' => 'Voir le détail',
                'status' => $row['content_type'] === 'atelier' ? 'Participation confirmée' : 'Cours en autonomie',
                'subtitle' => $row['content_type'] === 'atelier' ? 'Atelier réservé' : 'Cours suivi',
            ];
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTrainerEvents(int $trainerId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, titre, scheduled_at, lieu, nb_places, nb_inscrits, statut, description, prix
             FROM atelier
             WHERE formateur_id = :trainer
               AND statut != :annule
               AND scheduled_at BETWEEN :start AND :end
             ORDER BY scheduled_at ASC'
        );
        $statement->execute([
            'trainer' => $trainerId,
            'annule' => Workshop::STATUT_ANNULE,
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);

        $events = [];
        while ($row = $statement->fetch()) {
            if (empty($row['scheduled_at'])) {
                continue;
            }
            $events[] = [
                'id' => 'workshop-' . $row['id'],
                'content_key' => $this->buildContentKey('atelier', (int) $row['id']),
                'date' => new DateTimeImmutable($row['scheduled_at']),
                'title' => $row['titre'],
                'type' => 'atelier',
                'context' => 'workshop',
                'location' => $row['lieu'],
                'description' => $row['description'] ?? '',
                'price' => $row['prix'],
                'capacity' => $row['nb_inscrits'] . '/' . $row['nb_places'],
                'cta' => 'atelier/' . $row['id'],
                'cta_label' => 'Gérer l\'atelier',
                'status' => ucfirst($row['statut']),
                'subtitle' => 'Atelier planifié',
            ];
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPublicEvents(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, titre, scheduled_at, lieu, nb_places, nb_inscrits, statut, description, prix
             FROM atelier
             WHERE statut IN (:valide, :en_attente)
               AND scheduled_at BETWEEN :start AND :end
             ORDER BY scheduled_at ASC'
        );
        $statement->execute([
            'valide' => Workshop::STATUT_VALIDE,
            'en_attente' => Workshop::STATUT_EN_ATTENTE,
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);

        $events = [];
        while ($row = $statement->fetch()) {
            if (empty($row['scheduled_at'])) {
                continue;
            }
            $events[] = [
                'id' => 'public-' . $row['id'],
                'content_key' => $this->buildContentKey('atelier', (int) $row['id']),
                'date' => new DateTimeImmutable($row['scheduled_at']),
                'title' => $row['titre'],
                'type' => 'atelier',
                'context' => 'public',
                'location' => $row['lieu'],
                'description' => $row['description'] ?? '',
                'price' => $row['prix'],
                'capacity' => $row['nb_inscrits'] . '/' . $row['nb_places'],
                'cta' => 'atelier/' . $row['id'],
                'cta_label' => 'Découvrir l\'atelier',
                'status' => ucfirst($row['statut']),
                'subtitle' => 'Ouvert aux inscriptions',
            ];
        }

        return $events;
    }

    private function buildContentUrl(string $type, int $contentId): ?string
    {
        if ($contentId <= 0) {
            return null;
        }

        if ($type === 'atelier') {
            return 'atelier/' . $contentId;
        }

        if ($type === 'cours') {
            return 'cours/' . $contentId;
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $indexed
     * @param array<int, array<string, mixed>> $events
     * @return array<string, array<string, mixed>>
     */
    private function appendEvents(array $indexed, array $events): array
    {
        foreach ($events as $event) {
            $key = $event['content_key'] ?? $event['id'];
            $indexed[$key] = $event;
        }

        return $indexed;
    }

    private function buildContentKey(string $type, int $id): string
    {
        return $type . '_' . $id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAllTrainerEvents(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, titre, scheduled_at, lieu, nb_places, nb_inscrits, statut, description, prix, formateur_id
             FROM atelier
             WHERE statut != :annule AND scheduled_at BETWEEN :start AND :end
             ORDER BY scheduled_at ASC'
        );
        $statement->execute([
            'annule' => Workshop::STATUT_ANNULE,
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);

        $events = [];
        while ($row = $statement->fetch()) {
            $events[] = [
                'id' => 'workshop-' . $row['id'],
                'content_key' => $this->buildContentKey('atelier', (int) $row['id']),
                'date' => new DateTimeImmutable($row['scheduled_at']),
                'title' => $row['titre'],
                'type' => 'atelier',
                'context' => 'workshop',
                'location' => $row['lieu'],
                'description' => $row['description'] ?? '',
                'price' => $row['prix'],
                'capacity' => $row['nb_inscrits'] . '/' . $row['nb_places'],
                'cta' => 'atelier/' . $row['id'],
                'cta_label' => 'Voir l\'atelier',
                'status' => ucfirst($row['statut']),
                'subtitle' => 'Atelier planifié',
            ];
        }

        return $events;
    }
}
