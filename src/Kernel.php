<?php

declare(strict_types=1);

namespace App;

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
use RuntimeException;
use Twig\Environment;

final class Kernel
{
    private Environment $twig;
    private FlashBag $flashBag;
    private Translator $translator;
    private AutoTranslator $autoTranslator;
    private ContentModel $contentModel;
    private CalendarModel $calendarModel;
    private CalendarEventModel $calendarEventModel;
    private CourseModel $courseModel;
    private WorkshopModel $workshopModel;
    private UserModel $userModel;
    private InscriptionModel $inscriptionModel;
    private ReviewModel $reviewModel;
    private string $basePath = '';
    private bool $useQueryRouting = false;
    private string $scriptName = '/index.php';

    /** @var array<string, array<string, array{controller:string, action:string, pass_method?:bool, defaults?:array}>> */
    private array $routes = [];

    public function __construct(
        private CsrfTokenManager $csrfTokenManager,
        private UserSession $userSession,
        Translator $translator,
        string $basePath = '',
        bool $useQueryRouting = false,
        string $scriptName = '/index.php'
    ) {
        $this->basePath = $basePath === '' ? '' : '/' . ltrim($basePath, '/');
        $this->useQueryRouting = $useQueryRouting;
        $this->scriptName = $scriptName !== '' ? $scriptName : '/index.php';
        $this->translator = $translator;
        $cacheDir = dirname(__DIR__) . '/var/cache';
        $autoTranslateEnabled = filter_var($_ENV['AUTO_TRANSLATE_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $this->autoTranslator = new AutoTranslator($cacheDir, $autoTranslateEnabled);
        $this->twig = TwigFactory::create($this->basePath, $translator, $this->useQueryRouting, $this->scriptName, $this->autoTranslator);
        $this->flashBag = new FlashBag();
        $this->contentModel = new ContentModel();
        $this->calendarModel = new CalendarModel();
        $this->calendarEventModel = new CalendarEventModel();
        $this->courseModel = new CourseModel();
        $this->workshopModel = new WorkshopModel();
        $this->userModel = new UserModel();
        $this->inscriptionModel = new InscriptionModel();
        $this->reviewModel = new ReviewModel();
        $this->registerRoutes();
    }

    public function handle(string $uri, string $method): array
    {
        $path = $this->stripBasePath(parse_url($uri, PHP_URL_PATH) ?: '/');
        $method = strtoupper($method);
        $route = $this->matchRoute($method, $path);

        if ($route === null) {
            return [
                'status' => 404,
                'content' => 'Page non trouvée',
            ];
        }

        $controller = $this->instantiateController($route['controller']);
        $params = $route['params'];

        try {
            $response = $controller->{$route['action']}(...$params);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'AUTH_REQUIRED') {
                return $this->redirectResponse('/login');
            }
            if ($exception->getMessage() === 'ACCESS_DENIED') {
                return $this->redirectResponse('/');
            }
            throw $exception;
        }

        if (isset($response['redirect'])) {
            header('Location: ' . $this->absolutePath($response['redirect']));
            return [
                'status' => $response['status'] ?? 302,
                'content' => '',
            ];
        }

        $template = $response['template'] ?? null;
        $context = $response['context'] ?? [];
        $status = $response['status'] ?? 200;
        $content = $response['content'] ?? '';

        $flashes = $this->flashBag->all();

        if ($template) {
            $content = $this->twig->render($template, array_merge(
                $context,
                $this->globalContext($flashes)
            ));
        }

        return [
            'status' => $status,
            'content' => $content,
        ];
    }

    private function globalContext(array $flashes): array
    {
        return [
            'current_user' => $this->userSession->getUser(),
            'is_logged_in' => $this->userSession->isLoggedIn(),
            'csrf_logout' => $this->csrfTokenManager->getToken('logout'),
            'flashes' => $flashes,
            'base_path' => $this->basePath,
        ];
    }

    private function stripBasePath(string $path): string
    {
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }

        if ($path === '' || $path === '/index.php') {
            return '/';
        }

        return $path;
    }

    private function matchRoute(string $method, string $path): ?array
    {
        foreach ($this->routes[$method] ?? [] as $pattern => $config) {
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#i', '(?P<$1>[a-zA-Z0-9_-]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                $params = $config['defaults'] ?? [];
                foreach ($this->extractParamNames($pattern) as $name) {
                    $value = $matches[$name] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    $params[] = ctype_digit($value) ? (int) $value : $value;
                }
                if (!empty($config['pass_method'])) {
                    $params[] = $method;
                }
                return [
                    'controller' => $config['controller'],
                    'action' => $config['action'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    private function extractParamNames(string $pattern): array
    {
        preg_match_all('#\{([a-z_]+)\}#i', $pattern, $matches);
        return $matches[1] ?? [];
    }

    private function registerRoutes(): void
    {
        $this->routes = [
            'GET' => [
                '/' => ['controller' => HomeController::class, 'action' => 'index'],
                '/login' => ['controller' => AuthController::class, 'action' => 'login', 'pass_method' => true],
                '/register' => ['controller' => AuthController::class, 'action' => 'register', 'pass_method' => true],
                '/cours/{id}' => ['controller' => ContentController::class, 'action' => 'show', 'pass_method' => true, 'defaults' => ['cours']],
                '/atelier/{id}' => ['controller' => ContentController::class, 'action' => 'show', 'pass_method' => true, 'defaults' => ['atelier']],
                '/recherche' => ['controller' => SearchController::class, 'action' => 'search'],
                '/calendrier' => ['controller' => CalendarController::class, 'action' => 'index'],
                '/calendrier/evenement/{id}' => ['controller' => CalendarController::class, 'action' => 'showEvent'],
                '/lang/{locale}' => ['controller' => LanguageController::class, 'action' => 'switch'],
                '/contact' => ['controller' => ContactController::class, 'action' => 'index', 'pass_method' => true],
                '/espace' => ['controller' => LearnerController::class, 'action' => 'dashboard', 'pass_method' => true],
                '/formateur' => ['controller' => TrainerController::class, 'action' => 'dashboard', 'pass_method' => true],
                '/formateur/{id}' => ['controller' => TrainerController::class, 'action' => 'profile'],
                '/admin' => ['controller' => AdminController::class, 'action' => 'index', 'pass_method' => true],
                '/profil' => ['controller' => ProfileController::class, 'action' => 'index', 'pass_method' => true],
                '/contact' => ['controller' => ContactController::class, 'action' => 'index', 'pass_method' => true],
            ],
            'POST' => [
                '/login' => ['controller' => AuthController::class, 'action' => 'login', 'pass_method' => true],
                '/register' => ['controller' => AuthController::class, 'action' => 'register', 'pass_method' => true],
                '/logout' => ['controller' => AuthController::class, 'action' => 'logout'],
                '/cours/{id}' => ['controller' => ContentController::class, 'action' => 'show', 'pass_method' => true, 'defaults' => ['cours']],
                '/atelier/{id}' => ['controller' => ContentController::class, 'action' => 'show', 'pass_method' => true, 'defaults' => ['atelier']],
                '/espace' => ['controller' => LearnerController::class, 'action' => 'dashboard', 'pass_method' => true],
                '/formateur' => ['controller' => TrainerController::class, 'action' => 'dashboard', 'pass_method' => true],
                '/admin' => ['controller' => AdminController::class, 'action' => 'index', 'pass_method' => true],
                '/profil' => ['controller' => ProfileController::class, 'action' => 'index', 'pass_method' => true],
                '/calendrier' => ['controller' => CalendarController::class, 'action' => 'createEvent'],
                '/calendrier/evenement/{id}/supprimer' => ['controller' => CalendarController::class, 'action' => 'deleteEvent'],
            ],
        ];
    }

    private function instantiateController(string $class): object
    {
        return match ($class) {
            AuthController::class => new AuthController($this->userModel, $this->userSession, $this->csrfTokenManager, $this->flashBag),
            HomeController::class => new HomeController($this->contentModel, $this->userSession, $this->csrfTokenManager, $this->flashBag),
            CalendarController::class => new CalendarController($this->calendarModel, $this->calendarEventModel, $this->userSession, $this->csrfTokenManager, $this->flashBag, $this->translator),
            LanguageController::class => new LanguageController($this->translator, $this->userSession, $this->csrfTokenManager, $this->flashBag),
            ContentController::class => new ContentController($this->courseModel, $this->workshopModel, $this->inscriptionModel, $this->reviewModel, $this->userModel, $this->userSession, $this->csrfTokenManager, $this->flashBag),
            SearchController::class => new SearchController($this->contentModel, $this->userSession, $this->csrfTokenManager, $this->flashBag),
            LearnerController::class => new LearnerController($this->userModel, $this->inscriptionModel, $this->reviewModel, $this->courseModel, $this->workshopModel, $this->userSession, $this->csrfTokenManager, $this->flashBag),
            TrainerController::class => new TrainerController($this->userModel, $this->courseModel, $this->workshopModel, $this->userSession, $this->csrfTokenManager, $this->flashBag),
            AdminController::class => new AdminController($this->userModel, $this->courseModel, $this->workshopModel, $this->userSession, $this->csrfTokenManager, $this->flashBag),
            ProfileController::class => new ProfileController($this->userModel, $this->userSession, $this->csrfTokenManager, $this->flashBag),
            ContactController::class => new ContactController($this->userSession, $this->csrfTokenManager, $this->flashBag),
            default => throw new RuntimeException('Controller not found'),
        };
    }

    private function redirectResponse(string $path): array
    {
        header('Location: ' . $this->absolutePath($path));
        return [
            'status' => 302,
            'content' => '',
        ];
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            $path = '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return ($this->basePath !== '' ? $this->basePath : '') . $path;
    }
}
