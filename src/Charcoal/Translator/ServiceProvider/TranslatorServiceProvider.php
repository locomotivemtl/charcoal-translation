<?php

namespace Charcoal\Translator\ServiceProvider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\CsvFileLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;

use Charcoal\Translator\Translator;
use Charcoal\Translator\LanguageConfig;
use Charcoal\Translator\LanguageManager;

/**
 *
 */
class TranslatorServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container Pimple DI container.
     * @return void
     */
    public function register(Container$container)
    {
        $this->registerLanguages($container);
        $this->registerTranslator($container);
        //$this->registerTranslationFactory($container);
    }

    /**
     * @param Container $container Pimple DI container.
     * @return void
     */
    private function registerLanguages(Container $container)
    {
        /**
         * @param Container $container Pimple DI container.
         * @return array
         */
        $container['languages'] = function(Container $container) {
            $config = $container['language/config'];
            return $config['languages'];
        };

        /**
         * @param Container $container Pimple DI container.
         * @return LanguageConfig
         */
        $container['language/config'] = function(Container $container) {
            $config = isset($container['config']) ? $container['config'] : null;
            return new LanguageConfig($config['locales']);
        };

        /**
         * @param Container $container Pimple DI container.
         * @return string
         */
        $container['language/default'] = function(Container $container) {
            $config = $container['language/config'];
            if (isset($config['auto_detect']) && $config['auto_detect']) {
                if ($container['language/browser'] !== null) {
                    return $container['language/browser'];
                }
            }
            return $config['default_language'];
        };

        /**
         * @param Container $container Pimple DI container.
         * @return string|null
         */
        $container['language/browser'] = function(Container $container) {
            if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                return null;
            }
            $availableLanguages = array_keys($container['languages']);
            $acceptedLanguages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($acceptedLanguages as $acceptedLang) {
                $lang = explode(';', $acceptedLang);
                if (in_array($lang[0], $availableLanguages)) {
                    return $lang[0];
                }
            }
            return null;
        };

        /**
         * @param Container $container Pimple DI container.
         * @return array
         */
        $container['language/fallbacks'] = function(Container $container) {
            $config = $container['language/config'];
            return $config['fallback_languages'];
        };

        /**
         * @param Container $container Pimple DI container.
         * @return array
         */
        $container['language/manager'] = function (Container $container) {
            return new LanguageManager([
                'languages'            => array_keys($container['languages']),
                'default_language'     => $container['language/default'],
                'fallback_languages'   => $container['language/fallbacks']
            ]);
        };
    }

    /**
     * @param Container $container Pimple DI container.
     * @return void
     */
    private function registerTranslator(Container $container)
    {

        /**
         * @param Container $container Pimple DI container.
         * @return array
         */
        $container['translator/resources/array'] = function (Container $container) {
            return [];
        };

        /**
         * @param Container $container Pimple DI container.
         * @return array
         */
        $container['translator/resources/csv'] = function (Container $container) {
            $config = $container['config'];
            $paths = isset($config['translations']['paths']) ? $config['translations']['paths'] : [];
            $languages = array_keys($container['languages']);
            $csvs = [];
            foreach ($paths as $path) {
                foreach ($languages as $lang) {
                    $file = $path.$lang.'.csv';
                    if (file_exists($file)) {
                        $csvs[$lang][] = $files;
                    }
                }
            }

            return $csvs;
        };

        /**
         * @param Container $container Pimple DI container.
         * @return Translator
         */
        $container['translator'] = function (Container $container) {
            $locale = $container['language/manager']->currentLanguage();

            $translator = new Translator([
                'locale'            => $locale,
                'message_selector'  => new MessageSelector(),
                'cache_dir'         => null,
                'debug'             => false,
                'language_manager'  => $container['language/manager']
            ]);

            $translator->setFallbackLocales(['en']);
            $translator->addLoader('array', new ArrayLoader());
            $translator->addLoader('csv', new CsvFileLoader());
            $translator->addLoader('xliff', new XliffFileLoader());

            foreach ($container['translator/resources/array'] as $locale => $translations) {
                $translator->addResource('array', $translations, $locale);
            }

            foreach ($container['translator/resources/csv'] as $locale => $files) {
                foreach ($files as $file) {
                    $translator->addResource('csv', $file, $locale);
                }
            }

            return $translator;
        };
    }
}
