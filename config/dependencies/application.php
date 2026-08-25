<?php

declare(strict_types=1);

use Backendbase\Shared\Options\System\Environment;
use Backendbase\Shared\Services\ObjectMapper;
use Backendbase\Shared\Services\Translator;
use Backendbase\Shared\Settings;
use Backendbase\Utility\Pipeline\Pipeline;
use Backendbase\Utility\Pipeline\PipelineInterface;
use CuyZ\Valinor\Cache\FileSystemCache;
use CuyZ\Valinor\Cache\FileWatchingCache;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        PipelineInterface::class => static fn (ContainerInterface $container) => Pipeline::withContainer($container),
        ObjectMapper::class => static function (ContainerInterface $container) {
            $settings    = $container->get(Settings::class);
            $cache       = new FileSystemCache('var/cache/valinor');
            $environment = $settings->get('env');
            if ($environment instanceof Environment) {
                $environment = $environment->value;
            }

            if ($environment === Environment::DEV->value) {
                $cache = new FileWatchingCache($cache);
            }

            return new ObjectMapper($cache);
        },
        Translator::class => static function () {
            $translationFiles = glob('resources/i18n/*.php', GLOB_NOSORT);
            $locales          = [];
            if ($translationFiles === false) {
                throw new RuntimeException('Cannot discover translation files.');
            }

            foreach ($translationFiles as $translationFile) {
                $localeName           = basename($translationFile, '.php');
                $locales[$localeName] = require $translationFile;
            }

            $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'tr-TR';
            if ($acceptLanguage === '') {
                $acceptLanguage = 'tr-TR';
            }

            $preferredLocale = explode(';', explode(',', $acceptLanguage, 2)[0], 2)[0];
            if (! array_key_exists($preferredLocale, $locales)) {
                $preferredLocale = 'tr-TR';
            }

            return new Translator($preferredLocale, $locales);
        },
    ]);
};
