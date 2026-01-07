<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use App\Security\CsrfTokenManager;
use App\Security\UserSession;
use App\Service\Translator;

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
    $scriptDir = '';
}
$basePath = rtrim($scriptDir, '/');
if ($basePath === '' && isset($_SERVER['REQUEST_URI'])) {
    $requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    $projectSegment = basename(__DIR__);
    if ($projectSegment !== '' && $projectSegment !== '/' && str_starts_with($requestPath, '/' . $projectSegment)) {
        $basePath = '/' . $projectSegment;
    }
}

$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$htaccessRoot = is_file(__DIR__ . '/.htaccess');
$htaccessPublic = is_file(__DIR__ . '/public/.htaccess');
$scriptInPath = $scriptName !== '' && str_contains($requestPath, $scriptName);
$useQueryRouting = (!$htaccessRoot && !$htaccessPublic) || $scriptInPath;

$translationsDir = __DIR__ . '/translations';
$initialLocale = $_SESSION['locale'] ?? 'fr';
$translator = new Translator($translationsDir, $initialLocale);
$_SESSION['locale'] = $translator->getLocale();

$kernel = new Kernel(
    new CsrfTokenManager(),
    new UserSession(),
    $translator,
    $basePath,
    $useQueryRouting,
    $scriptName ?: '/index.php'
);
$response = $kernel->handle($requestUri, $_SERVER['REQUEST_METHOD']);

http_response_code($response['status']);
echo $response['content'];
