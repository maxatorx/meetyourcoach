<?php

declare(strict_types=1);

namespace App\Service;

final class Translator
{
    private string $translationsPath;
    private string $locale;
    private string $defaultLocale;
    /** @var array<string, array<string, mixed>> */
    private array $catalogues = [];

    /**
     * @param string $translationsPath absolute path to directory containing locale JSON files
     */
    public function __construct(string $translationsPath, string $locale = 'fr', string $defaultLocale = 'fr')
    {
        $this->translationsPath = rtrim($translationsPath, '/');
        $this->defaultLocale = $defaultLocale;
        $this->setLocale($locale);
    }

    public function setLocale(string $locale): void
    {
        $locale = strtolower($locale);
        if (!in_array($locale, $this->getAvailableLocales(), true)) {
            $locale = $this->defaultLocale;
        }
        $this->locale = $locale;
        $this->ensureCatalogueLoaded($locale);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return string[]
     */
    public function getAvailableLocales(): array
    {
        static $locales = null;
        if ($locales !== null) {
            return $locales;
        }

        $locales = [];
        if (!is_dir($this->translationsPath)) {
            return [$this->defaultLocale];
        }

        foreach (glob($this->translationsPath . '/*.json') as $file) {
            $locales[] = strtolower(pathinfo($file, PATHINFO_FILENAME));
        }

        if (!in_array($this->defaultLocale, $locales, true)) {
            $locales[] = $this->defaultLocale;
        }

        sort($locales);
        return $locales;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function trans(string $key, array $parameters = []): string
    {
        $value = $this->getValue($this->locale, $key)
            ?? $this->getValue($this->defaultLocale, $key)
            ?? $key;

        foreach ($parameters as $param => $replacement) {
            $value = str_replace('%' . $param . '%', (string) $replacement, $value);
        }

        return $value;
    }

    private function ensureCatalogueLoaded(string $locale): void
    {
        if (isset($this->catalogues[$locale])) {
            return;
        }

        $file = $this->translationsPath . '/' . $locale . '.json';
        if (!is_file($file)) {
            $this->catalogues[$locale] = [];
            return;
        }

        $content = file_get_contents($file);
        $decoded = json_decode($content ?: '', true);
        $this->catalogues[$locale] = is_array($decoded) ? $decoded : [];
    }

    private function getValue(string $locale, string $key): mixed
    {
        $this->ensureCatalogueLoaded($locale);
        $segments = explode('.', $key);
        $value = $this->catalogues[$locale];

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }
}
