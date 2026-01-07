<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../vendor/autoload.php';

use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Controller\CalendarController;
use App\Controller\ContactController;
use App\Controller\ContentController;
use App\Controller\HomeController;
use App\Controller\LanguageController;
use App\Controller\LearnerController;
use App\Controller\ProfileController;
use App\Controller\SearchController;
use App\Controller\TrainerController;
use App\Model\CalendarEventModel;
use App\Model\CalendarModel;
use App\Model\ContentModel;
use App\Model\CourseModel;
use App\Model\InscriptionModel;
use App\Model\ReviewModel;
use App\Model\UserModel;
use App\Model\WorkshopModel;
use App\Security\CsrfTokenManager;
use App\Security\UserSession;
use App\Service\AutoTranslator;
use App\Service\FlashBag;
use App\Service\TwigFactory;
use App\Service\Translator;

// Services de base.
$translationsDir = __DIR__ . '/../translations';
$initialLocale = $_SESSION['locale'] ?? 'fr';
$translator = new Translator($translationsDir, $initialLocale);
$_SESSION['locale'] = $translator->getLocale();

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$httpMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Détection du sous-dossier pour les URLs.
$scriptDir = str_replace('\\', '/', dirname($scriptName));
if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
    $scriptDir = '';
}
$basePath = rtrim($scriptDir, '/');

// Dépendances partagées.
$csrfTokenManager = new CsrfTokenManager();
$userSession = new UserSession();
$flashBag = new FlashBag();
$cacheDir = dirname(__DIR__) . '/var/cache';
$autoTranslateEnabled = filter_var($_ENV['AUTO_TRANSLATE_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
$autoTranslator = new AutoTranslator($cacheDir, $autoTranslateEnabled);
$twig = TwigFactory::create($basePath, $translator, false, $scriptName ?: '/index.php', $autoTranslator);

// Modèles (accès base de données).
$contentModel = new ContentModel();
$calendarModel = new CalendarModel();
$calendarEventModel = new CalendarEventModel();
$courseModel = new CourseModel();
$workshopModel = new WorkshopModel();
$userModel = new UserModel();
$inscriptionModel = new InscriptionModel();
$reviewModel = new ReviewModel();

// Controllers.
$authController = new AuthController($userModel, $userSession, $csrfTokenManager, $flashBag);
$homeController = new HomeController($contentModel, $userSession, $csrfTokenManager, $flashBag);
$calendarController = new CalendarController($calendarModel, $calendarEventModel, $userSession, $csrfTokenManager, $flashBag, $translator);
$languageController = new LanguageController($translator, $userSession, $csrfTokenManager, $flashBag);
$contentController = new ContentController($courseModel, $workshopModel, $inscriptionModel, $reviewModel, $userModel, $userSession, $csrfTokenManager, $flashBag);
$searchController = new SearchController($contentModel, $userSession, $csrfTokenManager, $flashBag);
$learnerController = new LearnerController($userModel, $inscriptionModel, $reviewModel, $courseModel, $workshopModel, $userSession, $csrfTokenManager, $flashBag);
$trainerController = new TrainerController($userModel, $courseModel, $workshopModel, $userSession, $csrfTokenManager, $flashBag);
$adminController = new AdminController($userModel, $courseModel, $workshopModel, $userSession, $csrfTokenManager, $flashBag);
$profileController = new ProfileController($userModel, $userSession, $csrfTokenManager, $flashBag);
$contactController = new ContactController($userSession, $csrfTokenManager, $flashBag);

// Routage simple base sur l'URL.
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$path = stripBasePath($path, $basePath);

$response = null;

// Connexion / inscription / deconnexion.
if ($path === '/login') {
    $response = $authController->login($httpMethod);
} elseif ($path === '/register') {
    $response = $authController->register($httpMethod);
} elseif ($path === '/logout' && $httpMethod === 'POST') {
    $response = $authController->logout();
} elseif ($path === '/lang' || str_starts_with($path, '/lang/')) {
    $locale = trim(str_replace('/lang', '', $path), '/');
    $response = $languageController->switch($locale);
}

// Catalogue / recherche.
if ($response === null && $path === '/') {
    $response = $homeController->index();
} elseif ($response === null && $path === '/recherche') {
    $response = $searchController->search();
}

// Cours et ateliers (fiche + inscriptions/avis).
if ($response === null && preg_match('#^/cours/(\d+)$#', $path, $matches)) {
    $response = $contentController->show('cours', (int) $matches[1], $httpMethod);
} elseif ($response === null && preg_match('#^/atelier/(\d+)$#', $path, $matches)) {
    $response = $contentController->show('atelier', (int) $matches[1], $httpMethod);
}

// Calendrier.
if ($response === null && $path === '/calendrier') {
    $response = $httpMethod === 'POST'
        ? $calendarController->createEvent()
        : $calendarController->index();
} elseif ($response === null && preg_match('#^/calendrier/evenement/(\d+)$#', $path, $matches)) {
    $response = $calendarController->showEvent((int) $matches[1]);
} elseif ($response === null && preg_match('#^/calendrier/evenement/(\d+)/supprimer$#', $path, $matches) && $httpMethod === 'POST') {
    $response = $calendarController->deleteEvent((int) $matches[1]);
}

// Espaces utilisateurs.
if ($response === null && $path === '/espace') {
    $response = $learnerController->dashboard($httpMethod);
} elseif ($response === null && $path === '/formateur') {
    $response = $trainerController->dashboard($httpMethod);
} elseif ($response === null && preg_match('#^/formateur/(\d+)$#', $path, $matches)) {
    $response = $trainerController->profile((int) $matches[1]);
} elseif ($response === null && $path === '/admin') {
    $response = $adminController->index($httpMethod);
} elseif ($response === null && $path === '/profil') {
    $response = $profileController->index($httpMethod);
}

// Contact.
if ($response === null && $path === '/contact') {
    $response = $contactController->index($httpMethod);
}

// Page non trouvée.
if ($response === null) {
    http_response_code(404);
    echo 'Page non trouvée';
    return;
}

try {
    if (isset($response['redirect'])) {
        header('Location: ' . buildPath($basePath, $response['redirect']));
        http_response_code($response['status'] ?? 302);
        return;
    }

    $template = $response['template'] ?? null;
    $context = $response['context'] ?? [];
    $status = $response['status'] ?? 200;
    $content = $response['content'] ?? '';

    $flashes = $flashBag->all();
    if ($template) {
        $content = $twig->render($template, array_merge($context, [
            'current_user' => $userSession->getUser(),
            'is_logged_in' => $userSession->isLoggedIn(),
            'csrf_logout' => $csrfTokenManager->getToken('logout'),
            'flashes' => $flashes,
            'base_path' => $basePath,
        ]));
    }

    http_response_code($status);
    echo $content;
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'AUTH_REQUIRED') {
        header('Location: ' . buildPath($basePath, '/login'));
        http_response_code(302);
        return;
    }
    if ($exception->getMessage() === 'ACCESS_DENIED') {
        header('Location: ' . buildPath($basePath, '/'));
        http_response_code(302);
        return;
    }
    throw $exception;
}

// Helpers simples pour le routage.
function buildPath(string $basePath, string $path): string
{
    if ($path === '') {
        $path = '/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return ($basePath !== '' ? $basePath : '') . $path;
}

function stripBasePath(string $path, string $basePath): string
{
    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath)) ?: '/';
    }
    if ($path === '' || $path === '/index.php') {
        return '/';
    }
    return $path;
}
