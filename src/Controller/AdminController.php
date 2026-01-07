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
use DateTimeImmutable;

/**
 * Administration : roles + validation + edition.
 */
final class AdminController extends AbstractController
{
    public function __construct(
        private UserModel $userModel,
        private CourseModel $courseModel,
        private WorkshopModel $workshopModel,
        UserSession $userSession,
        CsrfTokenManager $csrfTokenManager,
        FlashBag $flashBag
    ) {
        parent::__construct($userSession, $csrfTokenManager, $flashBag);
    }

    public function index(string $httpMethod): array
    {
        $this->requireRole('admin');

        if ($httpMethod === 'POST') {
            $this->handlePost();
        }

        $users = $this->userModel->findAll();
        $courses = $this->courseModel->findAll();
        $workshops = $this->workshopModel->findAll();

        $pendingCourses = array_filter($courses, static fn (Course $course) => $course->getStatus() !== Course::STATUT_PUBLIE);
        $pendingWorkshops = array_filter($workshops, static fn (Workshop $workshop) => $workshop->getStatus() !== Workshop::STATUT_VALIDE);

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'courses' => $courses,
            'workshops' => $workshops,
            'pending_courses' => $pendingCourses,
            'pending_workshops' => $pendingWorkshops,
            'csrf_token' => $this->csrfTokenManager->getToken('admin_panel'),
        ]);
    }

    private function handlePost(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'admin_panel')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return;
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'validate_course') {
            // Validation admin des cours.
            $id = (int) ($_POST['course_id'] ?? 0);
            $status = $_POST['status'] ?? Course::STATUT_PUBLIE;
            $this->courseModel->updateStatus($id, $status);
            $this->flashBag->add('success', 'Statut du cours mis à jour.');
        } elseif ($action === 'validate_workshop') {
            // Validation admin des ateliers.
            $id = (int) ($_POST['workshop_id'] ?? 0);
            $status = $_POST['status'] ?? Workshop::STATUT_VALIDE;
            $this->workshopModel->updateStatus($id, $status);
            $this->flashBag->add('success', 'Statut de l\'atelier mis à jour.');
        } elseif ($action === 'update_course') {
            $this->updateCourse((int) ($_POST['course_id'] ?? 0));
        } elseif ($action === 'update_workshop') {
            $this->updateWorkshop((int) ($_POST['workshop_id'] ?? 0));
        } elseif ($action === 'delete_course') {
            $this->deleteCourse((int) ($_POST['course_id'] ?? 0));
        } elseif ($action === 'delete_workshop') {
            $this->deleteWorkshop((int) ($_POST['workshop_id'] ?? 0));
        } elseif ($action === 'update_role') {
            $id = (int) ($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? 'apprenant';
            $this->userModel->updateRole($id, $role);
            $this->flashBag->add('success', 'Rôle utilisateur mis à jour.');
        }
    }

    private function updateCourse(int $courseId): void
    {
        if ($courseId <= 0) {
            return;
        }

        $course = $this->courseModel->findById($courseId);
        if ($course === null) {
            $this->flashBag->add('danger', 'Cours introuvable.');
            return;
        }

        try {
            $course->setTitle(trim($_POST['titre'] ?? $course->getTitle()));
            $course->setDescription(trim($_POST['description'] ?? $course->getDescription()));
            $course->setPrice((float) ($_POST['prix'] ?? $course->getPrice()));
            $course->setLevel($_POST['niveau'] ?? $course->getLevel());
            $this->courseModel->update($course);
            $this->flashBag->add('success', 'Cours mis à jour.');
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
        }
    }

    private function updateWorkshop(int $workshopId): void
    {
        if ($workshopId <= 0) {
            return;
        }

        $workshop = $this->workshopModel->findById($workshopId);
        if ($workshop === null) {
            $this->flashBag->add('danger', 'Atelier introuvable.');
            return;
        }

        $dateInput = $_POST['date'] ?? null;
        $scheduledAt = null;
        if ($dateInput) {
            $scheduledAt = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $dateInput);
            if ($scheduledAt === false) {
                $this->flashBag->add('danger', 'Date invalide.');
                return;
            }
        }

        try {
            $workshop->setTitle(trim($_POST['titre'] ?? $workshop->getTitle()));
            $workshop->setDescription(trim($_POST['description'] ?? $workshop->getDescription()));
            $workshop->setPrice((float) ($_POST['prix'] ?? $workshop->getPrice()));
            if ($scheduledAt) {
                $workshop->setScheduledAt($scheduledAt);
            }
            $workshop->setDurationMinutes((int) ($_POST['duree'] ?? $workshop->getDurationMinutes()));
            $workshop->setNbPlaces((int) ($_POST['places'] ?? $workshop->getNbPlaces()));
            $workshop->setLocation(trim($_POST['lieu'] ?? $workshop->getLocation()));
            $this->workshopModel->update($workshop);
            $this->flashBag->add('success', 'Atelier mis à jour.');
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
        }
    }

    private function deleteCourse(int $courseId): void
    {
        if ($courseId <= 0) {
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

        $this->workshopModel->delete($workshopId);
        $this->flashBag->add('info', 'Atelier supprimé.');
    }
}
