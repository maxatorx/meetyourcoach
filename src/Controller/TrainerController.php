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

/**
 * Espace formateur : CRUD cours/ateliers.
 */
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
        parent::__construct($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
    }

    public function profile(int $id): void
    {
        $trainer = $this->userModel->findById($id);
        $courses = $this->courseModel->findByTrainer($id);
        $workshops = $this->workshopModel->findByTrainer($id);

        if ($trainer === null) {
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
        $this->requireRole('formateur');

        if ($httpMethod === 'POST') {
            $this->handleDashboardPost();
        }

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
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'trainer_dashboard')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return;
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'delete_course') {
            $this->deleteCourse((int) ($_POST['course_id'] ?? 0));
            return;
        }
        if ($action === 'delete_workshop') {
            $this->deleteWorkshop((int) ($_POST['workshop_id'] ?? 0));
            return;
        }

        $formType = $_POST['form_type'] ?? '';
        if ($formType === 'course') {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            if ($courseId > 0) {
                $this->updateCourse($courseId);
            } else {
                $this->createCourse();
            }
        } elseif ($formType === 'workshop') {
            $workshopId = (int) ($_POST['workshop_id'] ?? 0);
            if ($workshopId > 0) {
                $this->updateWorkshop($workshopId);
            } else {
                $this->createWorkshop();
            }
        }
    }

    private function createCourse(): void
    {
        $trainerId = (int) $this->userSession->getUser()['id'];
        try {
            $course = new Course(
                trim($_POST['titre'] ?? ''),
                trim($_POST['description'] ?? ''),
                (float) ($_POST['prix'] ?? 0),
                $trainerId,
                $_POST['niveau'] ?? Course::NIVEAU_DEBUTANT,
                // Soumis à validation admin avant publication.
                Course::STATUT_BROUILLON
            );
            if ($imagePath = $this->uploadImage('image')) {
                $course->setImageUrl($imagePath);
            }
            $this->courseModel->create($course);
            $this->flashBag->add('success', 'Cours soumis pour validation.');
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
        }
    }

    private function createWorkshop(): void
    {
        $trainerId = (int) $this->userSession->getUser()['id'];
        try {
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
            if ($imagePath = $this->uploadImage('image')) {
                $workshop->setImageUrl($imagePath);
            }
            $this->workshopModel->create($workshop);
            $this->flashBag->add('success', 'Atelier soumis pour validation.');
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
        }
    }

    private function uploadImage(string $field): ?string
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $this->flashBag->add('danger', 'Erreur lors du téléchargement de l\'image.');
            return null;
        }

        $tmpName = $_FILES[$field]['tmp_name'];
        if (!is_uploaded_file($tmpName)) {
            $this->flashBag->add('danger', 'Fichier d\'image invalide.');
            return null;
        }

        $extension = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if ($extension && !in_array($extension, $allowed, true)) {
            $this->flashBag->add('danger', 'Format d\'image non supporté.');
            return null;
        }

        $uploadDir = __DIR__ . '/../../public/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

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
        if ($courseId <= 0) {
            return;
        }

        $course = $this->courseModel->findById($courseId);
        $userId = (int) $this->userSession->getUser()['id'];
        if ($course === null || $course->getTrainerId() !== $userId) {
            $this->flashBag->add('danger', 'Action non autorisée.');
            return;
        }

        $this->courseModel->delete($courseId);
        $this->flashBag->add('info', 'Cours supprimé.');
    }

    private function deleteWorkshop(int $workshopId): void
    {
        if ($workshopId <= 0) {
            return;
        }

        $workshop = $this->workshopModel->findById($workshopId);
        $userId = (int) $this->userSession->getUser()['id'];
        if ($workshop === null || $workshop->getTrainerId() !== $userId) {
            $this->flashBag->add('danger', 'Action non autorisée.');
            return;
        }

        $this->workshopModel->delete($workshopId);
        $this->flashBag->add('info', 'Atelier supprimé.');
    }

    private function prepareCourseEdit(int $trainerId): ?Course
    {
        $courseId = isset($_GET['edit_course']) ? (int) $_GET['edit_course'] : 0;
        if ($courseId <= 0) {
            return null;
        }

        $course = $this->courseModel->findById($courseId);
        if ($course === null || $course->getTrainerId() !== $trainerId) {
            return null;
        }

        return $course;
    }

    private function prepareWorkshopEdit(int $trainerId): ?Workshop
    {
        $workshopId = isset($_GET['edit_workshop']) ? (int) $_GET['edit_workshop'] : 0;
        if ($workshopId <= 0) {
            return null;
        }

        $workshop = $this->workshopModel->findById($workshopId);
        if ($workshop === null || $workshop->getTrainerId() !== $trainerId) {
            return null;
        }

        return $workshop;
    }

    private function updateCourse(int $courseId): void
    {
        $course = $this->courseModel->findById($courseId);
        $trainerId = (int) $this->userSession->getUser()['id'];
        if ($course === null || $course->getTrainerId() !== $trainerId) {
            $this->flashBag->add('danger', 'Modification non autorisée.');
            return;
        }

        try {
            $course->setTitle(trim($_POST['titre'] ?? $course->getTitle()));
            $course->setDescription(trim($_POST['description'] ?? $course->getDescription()));
            $course->setPrice((float) ($_POST['prix'] ?? $course->getPrice()));
            $course->setLevel($_POST['niveau'] ?? $course->getLevel());
            // Toute modification repasse en validation admin.
            $course->setStatus(Course::STATUT_BROUILLON);
            if ($imagePath = $this->uploadImage('image')) {
                $course->setImageUrl($imagePath);
            }
            $this->courseModel->update($course);
            $this->flashBag->add('success', 'Cours mis à jour et soumis pour validation.');
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
        }
    }

    private function updateWorkshop(int $workshopId): void
    {
        $workshop = $this->workshopModel->findById($workshopId);
        $trainerId = (int) $this->userSession->getUser()['id'];
        if ($workshop === null || $workshop->getTrainerId() !== $trainerId) {
            $this->flashBag->add('danger', 'Modification non autorisée.');
            return;
        }

        try {
            $workshop->setTitle(trim($_POST['titre'] ?? $workshop->getTitle()));
            $workshop->setDescription(trim($_POST['description'] ?? $workshop->getDescription()));
            $workshop->setPrice((float) ($_POST['prix'] ?? $workshop->getPrice()));
            $dateInput = $_POST['date'] ?? null;
            if ($dateInput) {
                $workshop->setScheduledAt(new \DateTimeImmutable($dateInput));
            }
            $workshop->setDurationMinutes((int) ($_POST['duree'] ?? $workshop->getDurationMinutes()));
            $workshop->setNbPlaces((int) ($_POST['places'] ?? $workshop->getNbPlaces()));
            $workshop->setLocation(trim($_POST['lieu'] ?? $workshop->getLocation()));
            // Toute modification repasse en validation admin.
            $workshop->setStatus(Workshop::STATUT_EN_ATTENTE);
            if ($imagePath = $this->uploadImage('image')) {
                $workshop->setImageUrl($imagePath);
            }
            $this->workshopModel->update($workshop);
            $this->flashBag->add('success', 'Atelier mis à jour et soumis pour validation.');
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
        }
    }
}
