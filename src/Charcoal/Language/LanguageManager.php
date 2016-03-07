<?php

namespace Charcoal\Language;

use \InvalidArgumentException;
use \RuntimeException;

// Dependency from 'charcoal-config'
use \Charcoal\Config\GenericConfig;

// Dependencies from 'charcoal-app'
use \Charcoal\App\App;
use \Charcoal\App\AbstractManager;

// Local Dependencies
use \Charcoal\Language\Language;
use \Charcoal\Language\LanguageInterface;
use \Charcoal\Polyglot\MultilingualAwareInterface;
use \Charcoal\Translation\Catalog\Catalog;
use \Charcoal\Translation\Catalog\CatalogInterface;
use \Charcoal\Translation\Catalog\FileLoader;
use \Charcoal\Translation\ConfigurableTranslationTrait;
use \Charcoal\Translation\TranslationConfig;
use \Charcoal\Translation\TranslationString;
use \Charcoal\Translation\TranslationStringInterface;

/**
 * Manage a collection of LanguageInterface objects and a unique TranslationConfig object.
 *
 * Not implementing ConfigurableInterface because the AbstractManager shouldn't permit
 * it's properties to be modified from outside the manager.
 */
class LanguageManager extends AbstractManager implements
    MultilingualAwareInterface
{
    use ConfigurableTranslationTrait;

    /**
     * A static cache of language information, raw data
     * used to create fill-up Language objects.
     *
     * @var GenericConfig
     */
    private static $languageIndex;

    /**
     * Set up the available languages, defaults, and active
     *
     * @return self
     */
    public function setup()
    {
        /*$config = $this->config();

        if (!($config instanceof TranslationConfig)) {
            $this->setupLanguages($config);
            $this->setupTranslations($config);
        }*/

        return $this;
    }

    /**
     * Set up the available languages, defaults, and active
     *
     * Settings:
     * - languages
     * - defaultLanguage
     * - currentLanguage
     *
     * @param  array $config The raw configuration array provided upon instantiation.
     * @return void
     * @throws RuntimeException If the languages passed to the manager isn't an associative array.
     * @todo   Implement cache get/set of JSON data based on languages.
     */
    public function setupLanguages(array $config)
    {
        $locales    = [];
        $translator = new TranslationConfig();

        if (isset($config['default_language'])) {
            $locales['default_language'] = $config['default_language'];
        }

        /** Not recommended; allow the current language to be determined by the client. */
        if (isset($config['current_language'])) {
            $locales['current_language'] = $config['current_language'];
        }

        if (empty($config['languages'])) {
            $config['languages'] = $translator->defaults()['languages'];
        }

        /**
         * Build the array of Language objects from the `TranslationConfig`-filtered list
         * to prevent any bad apples from slipping through.
         */
        if (is_array($config['languages'])) {
            $config['languages'] = array_filter($config['languages'], function ($lang) {
                return (!isset($lang['active']) || $lang['active']);
            });

            if (count($config['languages'])) {
                $index = $this->getLanguageIndex(array_keys($config['languages']));

                $locales['languages'] = [];
                foreach ($config['languages'] as $ident => $data) {
                    $lang = new Language();

                    if (!is_array($data)) {
                        $data = [];
                    }

                    if (isset($index[$ident])) {
                        $data = array_merge($index[$ident], $data);
                    }

                    if (!isset($data['ident'])) {
                        $lang->setIdent($ident);
                    }

                    $lang->setData($data);

                    $locales['languages'][$lang->ident()] = $lang;
                }
            }
        } else {
            throw new RuntimeException('Languages must be an associative array (e.g., `$langCode => $langInfo`).');
        }

        $translator->setData($locales);

        $this->setConfig($translator);
    }

    /**
     * Set up global string translations
     *
     * Settings:
     * - translations
     *
     * @param  array $config The raw configuration array provided upon instantiation.
     * @return void
     * @throws RuntimeException If the translations passed to the manager isn't an associative array.
     * @todo   Move catalog setup to {@see \Charcoal\App\Provider\TranslatorServiceProvider}
     */
    private function setupTranslations(array $config)
    {
        $translations = [];

        /**
         * Build the array of Language objects from the `TranslationConfig`-filtered list
         * to prevent any bad apples from slipping through.
         */
        if (isset($config['translations'])) {
            if (is_array($config['translations'])) {
                $translations = $config['translations'];
            } else {
                throw new RuntimeException(
                    'Global translations must be an associative array (e.g., `$ident => $translations`).'
                );
            }
        }

        $catalog = new Catalog($translations);

        if (isset($config['paths'])) {
            $paths  = $config['paths'];
            $loader = new GenericConfig();

            if (!is_array($paths)) {
                $paths = [ $paths ];
            }

            foreach ($paths as &$path) {
                $path = rtrim($path, '/\\').DIRECTORY_SEPARATOR;

                if (class_exists('\Charcoal\App\App')) {
                    $basePath = App::instance()->config()->get('ROOT');
                    $basePath = rtrim($basePath, '/\\').DIRECTORY_SEPARATOR;

                    if (false === strpos($path, $basePath)) {
                        $path = rtrim($basePath.$path, '/');
                    }
                }

                foreach ($this->availableLanguages() as $langCode) {
                    $exts = [ 'ini', 'json', 'php' ];
                    while ($exts) {
                        $ext = array_pop($exts);
                        $cfg = sprintf('%1$s/messages.%2$s.%3$s', $path, $langCode, $ext);

                        if (file_exists($cfg)) {
                            $loader->addFile($cfg);
                        }
                    }

                    foreach ($loader as $ident => $message) {
                        $catalog->addEntryTranslation($ident, $langCode, $message);
                    }
                }
            }
        }

        $this->setCatalog($catalog);
    }

    /**
     * Get the manager's translation catalog
     *
     * @param  CatalogInterface $catalog A catalog to hold translations for the manager.
     * @return self
     */
    protected function setCatalog(CatalogInterface $catalog)
    {
        $this->catalog = $catalog;
        return $this;
    }

    /**
     * Get the manager's translation catalog
     *
     * @return CatalogInterface The manager's catalog of translations.
     */
    public function catalog()
    {
        return $this->catalog;
    }

    /**
     * Alias of `ConfigurableInterface::config()`
     *
     * @return TranslationConfig The manager's translation configuration object.
     * @throws RuntimeException If the manager hasn't been set up.
     */
    public function translation()
    {
        $config = $this->config();

        if (!($config instanceof TranslationConfig)) {
            throw new RuntimeException('Manager hasn’t been set up.');
        }

        return $config;
    }

    /**
     * Get a list of existing languages, their names and translations,
     * codes in various standards, and directionality.
     *
     * @param  string[] $subset If provided, returns a subset of language information.
     *     Defaults to returning all language data.
     * @return GenericConfig
     */
    public function getLanguageIndex(array $subset = [])
    {
        $container = $this->app()->getContainer();
        $index     = [];

        if ($container['cache']) {
            if (count($subset)) {
                sort($subset);
                $key = implode(',', $subset);
            } else {
                $key = 'all';
            }

            $cache_item = $container['cache']->getItem('languages', $key, 'index');

            if ($cache_item->isMiss()) {
                $cache_item->lock();

                $index = self::getCompleteLanguageIndex();

                if ('all' !== $key) {
                    $languages = [];

                    foreach ($subset as $langCode) {
                        if (isset($index[$langCode])) {
                            $languages[$langCode] = $index[$langCode];
                        }
                    }

                    $index = $languages;
                }

                $cache_item->set($index);
            } else {
                return $cache_item->get();
            }
        } else {
            $index = self::getCompleteLanguageIndex();
        }

        return $index;
    }

    /**
     * Get a list of all existing languages, their names and translations,
     * codes in various standards, and directionality.
     *
     * @return GenericConfig
     */
    public function getCompleteLanguageIndex()
    {
        $index = new GenericConfig(__DIR__.'/../../../../config/languages.json');

        if (isset($index['languages'])) {
            return new GenericConfig($index['languages']);
        }

        return [];
    }

    /**
     * Set the application's current language.
     *
     * Must be one of the available languages assigned to the config.
     *
     * This method sets the environment's locale.
     *
     * @uses   ConfigurableTranslationTrait::setCurrentLanguage()
     * @param  LanguageInterface|string|null $lang A language object or identifier.
     * @return MultilingualAwareInterface Chainable
     */
    public function setCurrentLanguage($lang = null)
    {
        $this->config()->setCurrentLanguage($lang);
        $this->setCurrentLocale();

        return $this;
    }

    /**
     * Set the application's current language.
     *
     * Must be one of the available languages assigned to the config.
     *
     * This method sets the environment's locale.
     *
     * @uses   ConfigurableTranslationTrait::setCurrentLanguage()
     * @return MultilingualAwareInterface Chainable
     */
    public function setCurrentLocale()
    {
        $current  = $this->currentLanguage();
        $fallback = $this->defaultLanguage();

        $locales   = [ LC_ALL ];
        $languages = [ $current ];

        if ($current !== $fallback) {
            $languages[] = $fallback;
        }

        foreach ($languages as $code) {
            $language = $this->language($code);

            if ($language instanceof Language) {
                $locale = ($language->locale() ?: $language->code());

                if ($locale) {
                    $locales[] = $locale;
                }
            }
        }

        if (count($locales) > 1) {
            call_user_func_array('setlocale', $locales);
        }

        return $this;
    }
}
