<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\AutoTranslator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class TwigFactory
{
    public static function create(string $basePath = '', ?Translator $translator = null, bool $useQueryRouting = false, string $scriptName = '/index.php', ?AutoTranslator $autoTranslator = null): Environment
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new Environment($loader, [
            'cache' => false,
            'auto_reload' => true,
        ]);

        $normalizedBase = $basePath === '' ? '' : rtrim($basePath, '/');
        $scriptPath = $scriptName !== '' ? $scriptName : '/index.php';
        $twig->addGlobal('base_path', $normalizedBase);

        $twig->addFunction(new TwigFunction(
            'asset',
            static function (string $path = '') use ($normalizedBase, $useQueryRouting): string {
                if (preg_match('#^(?:https?:)?//#', $path)) {
                    return $path;
                }

                $cleanPath = '/' . ltrim($path, '/');
                if ($useQueryRouting && !str_starts_with($cleanPath, '/public/')) {
                    $cleanPath = '/public' . $cleanPath;
                }

                $prefix = $normalizedBase !== '' ? $normalizedBase : '';
                if ($prefix === '') {
                    return $cleanPath;
                }

                return $prefix . $cleanPath;
            }
        ));

        $twig->addFunction(new TwigFunction(
            'path',
            static function (string $path = '/', array $query = []) use ($normalizedBase, $useQueryRouting, $scriptPath): string {
                if ($useQueryRouting) {
                    $action = $path === '' || $path === '/' ? null : ltrim($path, '/');
                    $queryParams = $query;
                    if ($action !== null) {
                        $queryParams = array_merge(['action' => $action], $queryParams);
                    }
                    $queryString = '';
                    if (!empty($queryParams)) {
                        $queryString = '?' . http_build_query($queryParams);
                    }

                    return $scriptPath . $queryString;
                }

                $cleanPath = $path === '' || $path === '/' ? '/' : '/' . ltrim($path, '/');
                $url = ($normalizedBase !== '' ? $normalizedBase : '') . $cleanPath;
                if (!empty($query)) {
                    $queryString = http_build_query($query);
                    if ($queryString !== '') {
                        $url .= '?' . $queryString;
                    }
                }

                return $url;
            }
        ));

        $twig->addFunction(new TwigFunction('trans', static function (string $key, array $params = []) use ($translator): string {
            if ($translator === null) {
                return $key;
            }
            return $translator->trans($key, $params);
        }));

        if ($translator !== null) {
            $twig->addGlobal('current_locale', $translator->getLocale());
            $twig->addGlobal('available_locales', $translator->getAvailableLocales());
        } else {
            $twig->addGlobal('current_locale', 'fr');
            $twig->addGlobal('available_locales', ['fr']);
        }

        if ($autoTranslator !== null && $translator !== null) {
            $twig->addFilter(new \Twig\TwigFilter('autotrans', static function (string $text, ?string $target = null) use ($autoTranslator, $translator): string {
                $targetLocale = $target ?? $translator->getLocale();
                return $autoTranslator->translate($text, $targetLocale);
            }));
        }

        return $twig;
    }
}
