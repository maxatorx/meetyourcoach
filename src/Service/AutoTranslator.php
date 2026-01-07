<?php

declare(strict_types=1);

namespace App\Service;

final class AutoTranslator
{
    private bool $enabled;
    private string $cacheFile;
    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(string $cacheDir, bool $enabled = true)
    {
        $this->enabled = $enabled;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        $this->cacheFile = rtrim($cacheDir, '/') . '/auto_translation_cache.json';
        if (is_file($this->cacheFile)) {
            $decoded = json_decode((string) file_get_contents($this->cacheFile), true);
            if (is_array($decoded)) {
                $this->cache = $decoded;
            }
        }
    }

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        $target = strtolower($targetLocale);
        if (!$this->enabled || $target === '' || $target === 'fr') {
            return $text;
        }
        $original = trim($text);
        if ($original === '') {
            return $text;
        }

        $key = md5($target . '|' . $original);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $translated = $this->fetchFromApi($original, $target, $sourceLocale ?? 'auto');
        if ($translated === null || trim($translated) === '') {
            return $text;
        }

        $this->cache[$key] = $translated;
        $this->persistCache();

        return $translated;
    }

    private function fetchFromApi(string $text, string $target, string $source): ?string
    {
        $endpoint = 'https://translate.googleapis.com/translate_a/single';
        $params = http_build_query([
            'client' => 'gtx',
            'sl' => $source,
            'tl' => $target,
            'dt' => 't',
            'q' => $text,
        ]);
        $url = $endpoint . '?' . $params;

        $response = $this->httpGet($url);
        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded[0])) {
            return null;
        }

        $segments = $decoded[0];
        $translated = '';
        if (is_array($segments)) {
            foreach ($segments as $segment) {
                if (is_array($segment) && isset($segment[0])) {
                    $translated .= (string) $segment[0];
                }
            }
        }

        return $translated !== '' ? $translated : null;
    }

    private function httpGet(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => [
                    'User-Agent: MeetYourCoach-AutoTranslator/1.0',
                ],
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        return $result === false ? null : $result;
    }

    private function persistCache(): void
    {
        file_put_contents($this->cacheFile, json_encode($this->cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
