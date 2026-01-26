<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Workshop;
use App\Model\CourseModel;
use App\Model\UserModel;
use App\Model\WorkshopModel;
use App\Security\CsrfTokenManager;
use App\Security\UserSession;
use App\Service\FlashBag;
use Twig\Environment;

/** Espace formateur : cours/ateliers. */
final class TrainerController extends AbstractController
{
    public function __construct(
        private UserModel $userModel,
        private CourseModel $courseModel,
        private WorkshopModel $workshopModel,
        Environment $twig,
        UserSession $userSession,
        CsrfTokenManager $csrfTokenManager,
        FlashBag $flashBag,
        string $basePath = ''
    ) {
        // injection des dependances
        parent::__construct($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
    }

    public function profile(int $id): void
    {
        // Profil formateur: infos + contenus
        $trainer = $this->userModel->findById($id);
        $courses = $this->courseModel->findByTrainer($id);
        $workshops = $this->workshopModel->findByTrainer($id);

        if ($trainer === null) {
            // 404 si l'utilisateur n'existe pas
            $this->render('trainer/profile.html.twig', [
                'trainer' => null,
            ], 404);
            return;
        }

        $this->render('trainer/profile.html.twig', [
            'trainer' => $trainer,
            'courses' => $courses,
            'workshops' => $workshops,
        ]);
    }

    public function dashboard(string $httpMethod): void
    {
        // Espace prive: uniquement les utilisateurs avec le role "formateur".
        $this->requireRole('formateur');

        if ($httpMethod === 'POST') {
            // Toute action d'ecriture passe par le handler POST (create/update/delete).
            $this->handleDashboardPost();
        }

        // Donnees du formateur connecte pour l'affichage du dashboard.
        $trainerId = (int) $this->userSession->getUser()['id'];
        $courses = $this->courseModel->findByTrainer($trainerId);
        $workshops = $this->workshopModel->findByTrainer($trainerId);
        $editCourse = $this->prepareCourseEdit($trainerId);
        $editWorkshop = $this->prepareWorkshopEdit($trainerId);

        $this->render('trainer/dashboard.html.twig', [
            'courses' => $courses,
            'workshops' => $workshops,
            'csrf_token' => $this->csrfTokenManager->getToken('trainer_dashboard'),
            'edit_course' => $editCourse,
            'edit_workshop' => $editWorkshop,
        ]);
    }

    private function handleDashboardPost(): void
    {
        // Protection CSRF pour toutes les actions du dashboard.
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'trainer_dashboard')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return;
        }

        // Actions rapides (suppression) basees sur le champ "action".
        $action = $_POST['action'] ?? '';
        if ($action === 'delete_course') {
            $this->deleteCourse((int) ($_POST['course_id'] ?? 0));
            return;
        }
        if ($action === 'delete_workshop') {
            $this->deleteWorkshop((int) ($_POST['workshop_id'] ?? 0));
            return;
        }

        // Actions d'ajout/modification selon le type de formulaire.
        $formType = $_POST['form_type'] ?? '';
        if ($formType === 'course') {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $this->saveCourse($courseId);
        } elseif ($formType === 'workshop') {
            $workshopId = (int) ($_POST['workshop_id'] ?? 0);
            $this->saveWorkshop($workshopId);
        }
    }

    private function saveCourse(int $courseId): void
    {
        // Controle d'appartenance: un formateur ne peut modifier que ses cours.
        $trainerId = (int) $this->userSession->getUser()['id'];
        $course = $courseId > 0 ? $this->courseModel->findById($courseId) : null;
        if ($courseId > 0 && ($course === null || $course->getTrainerId() !== $trainerId)) {
            $this->flashBag->add('danger', 'Modification non autorisée.');
            return;
        }

        try {
            if ($course === null) {
                // Creation d'un nouveau cours (statut brouillon en attente de validation).
                $course = new Course(
                    trim($_POST['titre'] ?? ''),
                    trim($_POST['description'] ?? ''),
                    (float) ($_POST['prix'] ?? 0),
                    $trainerId,
                    $_POST['niveau'] ?? Course::NIVEAU_DEBUTANT,
                    // Soumis à validation admin avant publication.
                    Course::STATUT_BROUILLON
                );
            } else {
                // Mise a jour d'un cours existant: on repasse en statut brouillon.
                $course->setTitle(trim($_POST['titre'] ?? $course->getTitle()));
                $course->setDescription(trim($_POST['description'] ?? $course->getDescription()));
                $course->setPrice((float) ($_POST['prix'] ?? $course->getPrice()));
                $course->setLevel($_POST['niveau'] ?? $course->getLevel());
                // Toute modification repasse en validation admin.
                $course->setStatus(Course::STATUT_BROUILLON);
            }
            // Upload optionnel d'une image.
            if ($imagePath = $this->uploadImage('image')) {
                $course->setImageUrl($imagePath);
            }
            if ($courseId > 0) {
                $this->courseModel->update($course);
                $this->flashBag->add('success', 'Cours mis à jour et soumis pour validation.');
            } else {
                $this->courseModel->create($course);
                $this->flashBag->add('success', 'Cours soumis pour validation.');
            }
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
        }
    }

    private function saveWorkshop(int $workshopId): void
    {
        // Controle d'appartenance: un formateur ne peut modifier que ses ateliers.
        $trainerId = (int) $this->userSession->getUser()['id'];
        $workshop = $workshopId > 0 ? $this->workshopModel->findById($workshopId) : null;
        if ($workshopId > 0 && ($workshop === null || $workshop->getTrainerId() !== $trainerId)) {
            $this->flashBag->add('danger', 'Modification non autorisée.');
            return;
        }

        try {
            if ($workshop === null) {
                // Creation d'un nouvel atelier (statut en attente de validation).
                $workshop = new Workshop(
                    trim($_POST['titre'] ?? ''),
                    trim($_POST['description'] ?? ''),
                    (float) ($_POST['prix'] ?? 0),
                    $trainerId,
                    new \DateTimeImmutable($_POST['date'] ?? 'now'),
                    (int) ($_POST['duree'] ?? 60),
                    (int) ($_POST['places'] ?? 10),
                    trim($_POST['lieu'] ?? ''),
                    // Soumis à validation admin avant mise en ligne.
                    Workshop::STATUT_EN_ATTENTE
                );
            } else {
                // Mise a jour d'un atelier existant: on repasse en statut en attente.
                $workshop->setTitle(trim($_POST['titre'] ?? $workshop->getTitle()));
                $workshop->setDescription(trim($_POST['description'] ?? $workshop->getDescription()));
                $workshop->setPrice((float) ($_POST['prix'] ?? $workshop->getPrice()));
                $dateInput = $_POST['date'] ?? null;
                if ($dateInput) {
                    // On ne met a jour la date que si le champ est present.
                    $workshop->setScheduledAt(new \DateTimeImmutable($dateInput));
                }
                $workshop->setDurationMinutes((int) ($_POST['duree'] ?? $workshop->getDurationMinutes()));
                $workshop->setNbPlaces((int) ($_POST['places'] ?? $workshop->getNbPlaces()));
                $workshop->setLocation(trim($_POST['lieu'] ?? $workshop->getLocation()));
                // Toute modification repasse en validation admin.
                $workshop->setStatus(Workshop::STATUT_EN_ATTENTE);
            }
            // Upload optionnel d'une image.
            if ($imagePath = $this->uploadImage('image')) {
                $workshop->setImageUrl($imagePath);
            }
            if ($workshopId > 0) {
                $this->workshopModel->update($workshop);
                $this->flashBag->add('success', 'Atelier mis à jour et soumis pour validation.');
            } else {
                $this->workshopModel->create($workshop);
                $this->flashBag->add('success', 'Atelier soumis pour validation.');
            }
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
        }
    }

    private function uploadImage(string $field): ?string
    {
        // Aucun fichier envoye pour ce champ.
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        // Erreur PHP native pendant l'upload.
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $this->flashBag->add('danger', 'Erreur lors du téléchargement de l\'image.');
            return null;
        }

        // Verification basique que le fichier vient bien d'un upload HTTP.
        $tmpName = $_FILES[$field]['tmp_name'];
        if (!is_uploaded_file($tmpName)) {
            $this->flashBag->add('danger', 'Fichier d\'image invalide.');
            return null;
        }

        // Filtrage par extension autorisee.
        $extension = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if ($extension && !in_array($extension, $allowed, true)) {
            $this->flashBag->add('danger', 'Format d\'image non supporté.');
            return null;
        }

        // Dossier de destination dans public/uploads.
        $uploadDir = __DIR__ . '/../../public/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Nom de fichier unique pour eviter les collisions.
        $filename = uniqid('content_', true) . ($extension ? '.' . $extension : '');
        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            $this->flashBag->add('danger', 'Impossible de sauvegarder l\'image.');
            return null;
        }

        return '/uploads/' . $filename;
    }

    private function deleteCourse(int $courseId): void
    {
        // On ignore les identifiants invalides.
        if ($courseId <= 0) {
            return;
        }

        $course = $this->courseModel->findById($courseId);
        $userId = (int) $this->userSession->getUser()['id'];
        // Un formateur ne peut supprimer que ses propres cours.
        if ($course === null || $course->getTrainerId() !== $userId) {
            $this->flashBag->add('danger', 'Action non autorisée.');
            return;
        }

        $this->courseModel->delete($courseId);
        $this->flashBag->add('info', 'Cours supprimé.');
    }

    private function deleteWorkshop(int $workshopId): void
    {
        // On ignore les identifiants invalides.
        if ($workshopId <= 0) {
            return;
        }

        $workshop = $this->workshopModel->findById($workshopId);
        $userId = (int) $this->userSession->getUser()['id'];
        // Un formateur ne peut supprimer que ses propres ateliers.
        if ($workshop === null || $workshop->getTrainerId() !== $userId) {
            $this->flashBag->add('danger', 'Action non autorisée.');
            return;
        }

        $this->workshopModel->delete($workshopId);
        $this->flashBag->add('info', 'Atelier supprimé.');
    }

    private function prepareCourseEdit(int $trainerId): ?Course
    {
        // Activation du mode edition via query string (?edit_course=ID).
        $courseId = isset($_GET['edit_course']) ? (int) $_GET['edit_course'] : 0;
        if ($courseId <= 0) {
            return null;
        }

        $course = $this->courseModel->findById($courseId);
        // On ne laisse pas editer un cours qui n'appartient pas au formateur connecte.
        if ($course === null || $course->getTrainerId() !== $trainerId) {
            return null;
        }

        return $course;
    }

    private function prepareWorkshopEdit(int $trainerId): ?Workshop
    {
        // Activation du mode edition via query string (?edit_workshop=ID).
        $workshopId = isset($_GET['edit_workshop']) ? (int) $_GET['edit_workshop'] : 0;
        if ($workshopId <= 0) {
            return null;
        }

        $workshop = $this->workshopModel->findById($workshopId);
        // On ne laisse pas editer un atelier qui n'appartient pas au formateur connecte.
        if ($workshop === null || $workshop->getTrainerId() !== $trainerId) {
            return null;
        }

        return $workshop;
    }

}
