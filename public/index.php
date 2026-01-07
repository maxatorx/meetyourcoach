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
$authController = new AuthController($userModel, $twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
$homeController = new HomeController($contentModel, $twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
$calendarController = new CalendarController($calendarModel, $calendarEventModel, $twig, $userSession, $csrfTokenManager, $flashBag, $translator, $basePath);
$languageController = new LanguageController($translator, $twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
$contentController = new ContentController($courseModel, $workshopModel, $inscriptionModel, $reviewModel, $userModel, $twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
$searchController = new SearchController($contentModel, $twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
$learnerController = new LearnerController($userModel, $inscriptionModel, $reviewModel, $courseModel, $workshopModel, $twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
$trainerController = new TrainerController($userModel, $courseModel, $workshopModel, $twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
$adminController = new AdminController($userModel, $courseModel, $workshopModel, $twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
$profileController = new ProfileController($userModel, $twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
$contactController = new ContactController($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);

// Routage simple base sur l'URL.
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$path = stripBasePath($path, $basePath);

try {
    // Connexion / inscription / deconnexion.
    if ($path === '/login') {
        $authController->login($httpMethod);
        return;
    }
    if ($path === '/register') {
        $authController->register($httpMethod);
        return;
    }
    if ($path === '/logout' && $httpMethod === 'POST') {
        $authController->logout();
        return;
    }
    if ($path === '/lang' || str_starts_with($path, '/lang/')) {
        $locale = trim(str_replace('/lang', '', $path), '/');
        $languageController->switch($locale);
        return;
    }

    // Catalogue / recherche.
    if ($path === '/') {
        $homeController->index();
        return;
    }
    if ($path === '/recherche') {
        $searchController->search();
        return;
    }

    // Cours et ateliers (fiche + inscriptions/avis).
    if (preg_match('#^/cours/(\d+)$#', $path, $matches)) {
        $contentController->show('cours', (int) $matches[1], $httpMethod);
        return;
    }
    if (preg_match('#^/atelier/(\d+)$#', $path, $matches)) {
        $contentController->show('atelier', (int) $matches[1], $httpMethod);
        return;
    }

    // Calendrier.
    if ($path === '/calendrier') {
        if ($httpMethod === 'POST') {
            $calendarController->createEvent();
        } else {
            $calendarController->index();
        }
        return;
    }
    if (preg_match('#^/calendrier/evenement/(\d+)$#', $path, $matches)) {
        $calendarController->showEvent((int) $matches[1]);
        return;
    }
    if (preg_match('#^/calendrier/evenement/(\d+)/supprimer$#', $path, $matches) && $httpMethod === 'POST') {
        $calendarController->deleteEvent((int) $matches[1]);
        return;
    }

    // Espaces utilisateurs.
    if ($path === '/espace') {
        $learnerController->dashboard($httpMethod);
        return;
    }
    if ($path === '/formateur') {
        $trainerController->dashboard($httpMethod);
        return;
    }
    if (preg_match('#^/formateur/(\d+)$#', $path, $matches)) {
        $trainerController->profile((int) $matches[1]);
        return;
    }
    if ($path === '/admin') {
        $adminController->index($httpMethod);
        return;
    }
    if ($path === '/profil') {
        $profileController->index($httpMethod);
        return;
    }

    // Contact.
    if ($path === '/contact') {
        $contactController->index($httpMethod);
        return;
    }
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

// Page non trouvée.
http_response_code(404);
echo 'Page non trouvée';

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
