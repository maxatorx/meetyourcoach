<?php

declare(strict_types=1);

namespace App\Model;

use App\Config\Database;
use App\Entity\Workshop;
use DateTimeImmutable;
use PDO;

final class WorkshopModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * @return Workshop[]
     */
    public function findPublished(int $page): array
    {
        // Catalogue public : uniquement les ateliers valides.
        $limit = 10;
        $offset = max(0, $page - 1) * $limit;
        $statement = $this->pdo->prepare(
            'SELECT * FROM atelier WHERE statut = :valide ORDER BY scheduled_at ASC LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':valide', Workshop::STATUT_VALIDE);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $workshops = [];
        while ($data = $statement->fetch()) {
            $workshops[] = $this->hydrateWorkshop($data);
        }

        return $workshops;
    }

    public function findById(int $id): ?Workshop
    {
        $statement = $this->pdo->prepare('SELECT * FROM atelier WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();

        return $data ? $this->hydrateWorkshop($data) : null;
    }

    /**
     * @return Workshop[]
     */
    public function findByTrainer(int $trainerId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM atelier WHERE formateur_id = :trainer ORDER BY scheduled_at DESC');
        $statement->execute(['trainer' => $trainerId]);
        $items = [];
        while ($row = $statement->fetch()) {
            $items[] = $this->hydrateWorkshop($row);
        }

        return $items;
    }

    public function create(Workshop $workshop): Workshop
    {
        $statement = $this->pdo->prepare('INSERT INTO atelier (titre, description, prix, statut, formateur_id, nb_places, nb_inscrits, scheduled_at, duree_minutes, lieu, image_url, created_at, updated_at) VALUES (:titre, :description, :prix, :statut, :formateur, :places, :inscrits, :scheduled, :duree, :lieu, :image, :created_at, :updated_at)');
        $statement->execute([
            'titre' => $workshop->getTitle(),
            'description' => $workshop->getDescription(),
            'prix' => $workshop->getPrice(),
            'statut' => $workshop->getStatus(),
            'formateur' => $workshop->getTrainerId(),
            'places' => $workshop->getNbPlaces(),
            'inscrits' => $workshop->getNbInscrits(),
            'scheduled' => $workshop->getScheduledAt()->format('Y-m-d H:i:s'),
            'duree' => $workshop->getDurationMinutes(),
            'lieu' => $workshop->getLocation(),
            'image' => $workshop->getImageUrl(),
            'created_at' => $workshop->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $workshop->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
        $workshop->setId((int) $this->pdo->lastInsertId());

        return $workshop;
    }

    public function updateStatus(int $id, string $status): void
    {
        // Mise a jour du statut par l'admin.
        $statement = $this->pdo->prepare('UPDATE atelier SET statut = :statut, updated_at = :updated WHERE id = :id');
        $statement->execute([
            'statut' => $status,
            'updated' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM atelier WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function update(Workshop $workshop): void
    {
        $statement = $this->pdo->prepare('UPDATE atelier SET titre = :titre, description = :description, prix = :prix, statut = :statut, nb_places = :places, nb_inscrits = :inscrits, scheduled_at = :scheduled, duree_minutes = :duree, lieu = :lieu, image_url = :image, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'titre' => $workshop->getTitle(),
            'description' => $workshop->getDescription(),
            'prix' => $workshop->getPrice(),
            'statut' => $workshop->getStatus(),
            'places' => $workshop->getNbPlaces(),
            'inscrits' => $workshop->getNbInscrits(),
            'scheduled' => $workshop->getScheduledAt()->format('Y-m-d H:i:s'),
            'duree' => $workshop->getDurationMinutes(),
            'lieu' => $workshop->getLocation(),
            'image' => $workshop->getImageUrl(),
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $workshop->getId(),
        ]);
    }

    /**
     * @return Workshop[]
     */
    public function findAll(): array
    {
        // Liste complete pour l'administration.
        $statement = $this->pdo->query('SELECT * FROM atelier ORDER BY updated_at DESC');
        $items = [];
        while ($row = $statement->fetch()) {
            $items[] = $this->hydrateWorkshop($row);
        }

        return $items;
    }

    public function incrementRegistrations(int $id): bool
    {
        $statement = $this->pdo->prepare('UPDATE atelier SET nb_inscrits = nb_inscrits + 1 WHERE id = :id AND nb_inscrits < nb_places');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() === 1;
    }

    public function decrementRegistrations(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE atelier SET nb_inscrits = GREATEST(nb_inscrits - 1, 0) WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function hydrateWorkshop(array $data): Workshop
    {
        $workshop = new Workshop(
            $data['titre'],
            $data['description'],
            (float) $data['prix'],
            (int) $data['formateur_id'],
            new DateTimeImmutable($data['scheduled_at']),
            (int) $data['duree_minutes'],
            (int) $data['nb_places'],
            $data['lieu'],
            $data['statut']
        );
        $workshop->setId((int) $data['id']);
        $workshop->setImageUrl($data['image_url']);
        $workshop->setNbInscrits((int) $data['nb_inscrits']);
        $workshop->setCreatedAt(new DateTimeImmutable($data['created_at']));
        $workshop->setUpdatedAt(new DateTimeImmutable($data['updated_at']));

        return $workshop;
    }
}
