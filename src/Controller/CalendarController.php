<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CalendarEvent;
use App\Model\CalendarEventModel;
use App\Model\CalendarModel;
use App\Service\Translator;
use DateInterval;
use DateTimeImmutable;
use Twig\Environment;

/**
 * Calendrier des evenements (publics + personnels).
 */
final class CalendarController extends AbstractController
{
    public function __construct(
        private CalendarModel $calendarModel,
        private CalendarEventModel $calendarEventModel,
        Environment $twig,
        \App\Security\UserSession $userSession,
        \App\Security\CsrfTokenManager $csrfTokenManager,
        \App\Service\FlashBag $flashBag,
        private Translator $translator,
        string $basePath = ''
    ) {
        parent::__construct($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
    }

    public function index(): void
    {
        // Vue calendrier mensuelle avec evenements publics + personnels.
        $month = (int) ($_GET['month'] ?? date('n'));
        $year = (int) ($_GET['year'] ?? date('Y'));

        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $current = (new DateTimeImmutable())
            ->setDate($year, $month, 1)
            ->setTime(0, 0);
        $start = $current;
        $end = $current->modify('last day of this month')->setTime(23, 59, 59);

        $user = $this->userSession->getUser();
        $events = $this->calendarModel->getEventsForContext($user, $start, $end);
        $customEvents = $this->calendarEventModel->findForUserWithinPeriod(
            isset($user['id']) ? (int) $user['id'] : null,
            $start,
            $end
        );
        $formattedCustom = $this->formatCustomEvents($customEvents);
        $events = $this->mergeEvents($events, $formattedCustom);
        $weeks = $this->buildCalendarMatrix($current, $events);

        $prev = $current->sub(new DateInterval('P1M'));
        $next = $current->add(new DateInterval('P1M'));

        $this->render('calendar/index.html.twig', [
            'calendar_weeks' => $weeks,
            'current_month_label' => $this->formatMonthLabel($current),
            'current_month' => (int) $current->format('n'),
            'current_year' => (int) $current->format('Y'),
            'prev' => ['month' => (int) $prev->format('n'), 'year' => (int) $prev->format('Y')],
            'next' => ['month' => (int) $next->format('n'), 'year' => (int) $next->format('Y')],
            'events' => $events,
            'viewer_role' => $user['role'] ?? null,
            'calendar_intro_key' => $this->buildIntroKey($user),
            'is_empty' => count($events) === 0,
            'can_add_event' => $this->userSession->isLoggedIn(),
            'add_event_csrf' => $this->csrfTokenManager->getToken('calendar_add_event'),
            'custom_events' => array_values($formattedCustom),
        ]);
    }

    public function createEvent(): void
    {
        $this->requireLogin();
        // Creation d'un evenement personnel dans le calendrier.
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'calendar_add_event')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            $this->redirect('/calendrier');
            return;
        }

        $title = trim($_POST['titre'] ?? '');
        $datetimeInput = $_POST['date'] ?? '';
        $location = trim($_POST['lieu'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scheduledAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $datetimeInput);

        if ($scheduledAt === false) {
            $this->flashBag->add('danger', 'Date invalide.');
            $this->redirect('/calendrier');
            return;
        }

        try {
            $event = new CalendarEvent(
                $title,
                $scheduledAt,
                (int) $this->userSession->getUser()['id'],
                $description ?: null,
                $location ?: null
            );
            $this->calendarEventModel->create($event);
            $this->flashBag->add('success', 'Événement ajouté au calendrier.');
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
        }

        $this->redirect(sprintf(
            '/calendrier?month=%d&year=%d',
            (int) $scheduledAt->format('n'),
            (int) $scheduledAt->format('Y')
        ));
    }

    public function showEvent(int $id): void
    {
        // Detail d'un evenement du calendrier.
        $event = $this->calendarEventModel->findById($id);
        if ($event === null) {
            $this->render('calendar/show_event.html.twig', [
                'event' => null,
            ], 404);
            return;
        }

        $user = $this->userSession->getUser();
        $ownerId = $event->getUserId();
        if ($ownerId !== null) {
            // Evenements personnels visibles par leur proprietaire ou l'admin.
            if ($user === null) {
                $this->redirect('/login');
                return;
            }
            if (!$this->isOwnerOrAdmin($user, $ownerId)) {
                $this->flashBag->add('danger', 'Cet événement est privé.');
                $this->redirect('/calendrier');
                return;
            }
        }

        $this->render('calendar/show_event.html.twig', [
            'event' => $event,
        ]);
    }

    public function deleteEvent(int $id): void
    {
        $this->requireLogin();
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'calendar_delete_' . $id)) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            $this->redirect('/calendrier');
            return;
        }

        $event = $this->calendarEventModel->findById($id);
        if ($event === null) {
            $this->flashBag->add('danger', 'Événement introuvable.');
            $this->redirect('/calendrier');
            return;
        }

        $user = $this->userSession->getUser();
        $ownerId = $event->getUserId();
        // Suppression autorisee pour le proprietaire ou l'admin.
        if ($ownerId !== null && !$this->isOwnerOrAdmin($user, $ownerId)) {
            $this->flashBag->add('danger', 'Vous ne pouvez pas supprimer cet événement.');
            $this->redirect('/calendrier');
            return;
        }

        $month = (int) $event->getScheduledAt()->format('n');
        $year = (int) $event->getScheduledAt()->format('Y');

        $this->calendarEventModel->delete($id);
        $this->flashBag->add('info', 'Événement supprimé.');

        $this->redirect(sprintf('/calendrier?month=%d&year=%d', $month, $year));
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<int, array<string, mixed>|null>>
     */
    private function buildCalendarMatrix(DateTimeImmutable $current, array $events): array
    {
        $eventsByDay = [];
        foreach ($events as $event) {
            if (!isset($event['date'])) {
                continue;
            }
            $key = $event['date']->format('Y-m-d');
            $eventsByDay[$key][] = $event;
        }

        $weeks = [];
        $week = [];
        $firstDayOfMonth = $current;
        $gridStart = $firstDayOfMonth->modify('monday this week');
        if ((int) $firstDayOfMonth->format('N') === 1) {
            $gridStart = $firstDayOfMonth;
        }

        for ($i = 0; $i < 42; $i++) {
            $date = $gridStart->add(new DateInterval('P' . $i . 'D'));
            $key = $date->format('Y-m-d');
            $week[] = [
                'day' => (int) $date->format('j'),
                'date' => $date,
                'events' => $eventsByDay[$key] ?? [],
                'is_current_month' => $date->format('Y-m') === $current->format('Y-m'),
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    /**
     * @param CalendarEvent[] $events
     * @return array<int, array<string, mixed>>
     */
    private function formatCustomEvents(array $events): array
    {
        $formatted = [];
        foreach ($events as $event) {
            $token = $this->csrfTokenManager->getToken('calendar_delete_' . $event->getId());
            $formatted[] = [
                'id' => 'custom-' . $event->getId(),
                'content_key' => 'custom_' . $event->getId(),
                'date' => $event->getScheduledAt(),
                'title' => $event->getTitle(),
                'type' => 'custom',
                'context' => 'custom',
                'location' => $event->getLocation(),
                'description' => $event->getDescription() ?? '',
                'price' => null,
                'capacity' => null,
                'status' => 'Ajout personnel',
                'subtitle' => 'Événement ajouté',
                'delete_token' => $token,
                'delete_path' => 'calendrier/evenement/' . $event->getId() . '/supprimer',
            ];
        }

        return $formatted;
    }

    /**
     * @param array<int, array<string, mixed>> $base
     * @param array<int, array<string, mixed>> $custom
     * @return array<int, array<string, mixed>>
     */
    private function mergeEvents(array $base, array $custom): array
    {
        $indexed = [];
        foreach (array_merge($base, $custom) as $event) {
            $key = $event['content_key'] ?? $event['id'];
            $indexed[$key] = $event;
        }

        return array_values($indexed);
    }

    private function formatMonthLabel(DateTimeImmutable $date): string
    {
        $month = (int) $date->format('n');
        $translationKey = 'calendar.months.' . $month;
        $monthLabel = $this->translator->trans($translationKey);
        if ($monthLabel === $translationKey) {
            $monthLabel = $date->format('F');
        }

        return $monthLabel . ' ' . $date->format('Y');
    }

    private function buildIntroKey(?array $user): string
    {
        return match ($user['role'] ?? null) {
            'apprenant' => 'calendar.intro.apprenant',
            'formateur' => 'calendar.intro.formateur',
            default => 'calendar.intro.default',
        };
    }

    private function isOwnerOrAdmin(?array $user, int $ownerId): bool
    {
        if ($user === null) {
            return false;
        }
        if ((int) ($user['id'] ?? 0) === $ownerId) {
            return true;
        }
        return ($user['role'] ?? null) === 'admin';
    }
}
