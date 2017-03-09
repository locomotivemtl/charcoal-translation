<?php

namespace Charcoal\Translation;

use \InvalidArgumentException;

// Dependency from 'charcoal-config'
use \Charcoal\Config\AbstractConfig;

// Dependencies from 'charcoal-translation'
use \Charcoal\Language\Language;
use \Charcoal\Language\LanguageRepository;
use \Charcoal\Polyglot\MultilingualAwareInterface;
use \Charcoal\Translation\TranslatableTrait;

/**
 * Configuration container for the {@see TranslatorServiceProvider}.
 *
 */
class TranslatorConfig extends AbstractConfig implements MultilingualAwareInterface
{
    use TranslatableTrait {
        TranslatableTrait::setCurrentLanguage as applyCurrentLanguage;
    }

    /**
     * Whether the translator is enabled.
     *
     * @var boolean $active
     */
    private $active;

    /**
     * Translation Driver
     *
     * @var array $types
     */
    private $types = [];

    /**
     * List of file and directory paths for loading language metadata.
     *
     * @var string[] $languagePaths
     */
    private $languagePaths;

    /**
     * A map of custom translations organized by domain (e.g., `$domain => [ $lang => [ $ident => $message ] ]`).
     *
     * @var array $translationMessages
     */
    private $translationMessages = [];

    /**
     * List of file and directory paths for loading translations.
     *
     * @var array $translationPaths
     */
    private $translationPaths = [];

    /**
     * @return array
     */
    public function defaults()
    {
        return [
            'active'           => true,
            'types'             => [ 'noop' ],
            'locales'          => [
                'repositories' => [
                    'vendor/locomotivemtl/charcoal-translation/config/languages.json'
                ],
                'languages' => [
                    'en'  => [
                        'name' => 'English'
                    ],
                    'fr'  => [
                        'name' => 'Français'
                    ]
                ],
                'current_language'   => '',
                'default_language'   => '',
                'fallback_languages' => [ 'en' ],
            ],
            'translations' => [
                'paths'    => [],
                'messages' => []
            ]
        ];
    }

    /**
     * Determine if the translator is enabled.
     *
     * @return boolean
     */
    public function active()
    {
        return $this->active;
    }

    /**
     * Set whether the translator is enabled.
     *
     * @param boolean $active The active flag.
     * @return TranslatorConfig Chainable
     */
    public function setActive($active)
    {
        $this->active = !!$active;

        return $this;
    }

    /**
     * @return array
     */
    public function types()
    {
        return $this->types;
    }

    /**
     * Alias of `self::setTypes([ $type ])`
     *
     * Set the driver type of translator to use.
     *
     * @todo   Implement translation drivers (Database, File, Yandex) similar to CacheServiceProvider.
     * @param  string $type A translation driver to try using.
     * @return self
     */
    public function setType($type)
    {
        if (!is_array($type)) {
            $type = [ $type ];
        }

        $this->setTypes($type);

        return $this;
    }

    /**
     * Set the driver types of translator to use.
     *
     * The first driver actually available will be the one used for translating and loading language metadata.
     *
     * @param  string[] $types An array of translation drivers to try using, in order of priority.
     * @return self
     */
    public function setTypes(array $types)
    {
        $this->types = [];

        foreach ($types as $type) {
            $this->addType($type);
        }

        return $this;
    }

    /**
     * @param string $type The cache type.
     * @throws InvalidArgumentException If the type is not a string.
     * @return CacheConfig Chainable
     */
    public function addType($type)
    {
        if (!in_array($type, $this->validTypes())) {
            throw new InvalidArgumentException(
                sprintf('Invalid translator driver: "%s"', $type)
            );
        }

        $this->types[] = $type;

        return $this;
    }

    /**
     * Get the valid driver types.
     *
     * @return array
     */
    public function validTypes()
    {
        return [
            'db',
            'file',
            'noop',
            'yandex',
        ];
    }

    /**
     * Retrieve the language configuration set.
     *
     * @return array
     */
    public function locales()
    {
        return [
            'repositories'     => $this->languagePaths(),
            'languages'        => $this->languages(),
            'current_language' => $this->currentLanguage(),
            'default_language' => $this->defaultLanguage()
        ];
    }

    /**
     * Define the translator's localization settings.
     *
     * @param  array $config An array of localization settings.
     * @return TranslatorConfig Chainable
     */
    public function setLocales(array $config)
    {
        if (isset($config['paths'])) {
            $config['repositories'] = $config['paths'];
        }

        if (isset($config['repositories'])) {
            $this->setLanguagePaths($config['repositories']);
        }

        if (isset($config['languages'])) {
            $languages = $this->languages();
            foreach ($config['languages'] as $langCode => &$langData) {
                if (isset($languages[$langCode])) {
                    $langData = array_replace($languages[$langCode], $langData);
                }
            }

            $this->setLanguages($config['languages']);
        }

        if (!empty($config['current_language'])) {
            $this->setCurrentLanguage($config['current_language']);
        }

        if (!empty($config['default_language'])) {
            $this->setDefaultLanguage($config['default_language']);
        }

        return $this;
    }

    /**
     * Retrieve the translations configuration set.
     *
     * @return array
     */
    public function translations()
    {
        return [
            'paths'    => $this->translationPaths(),
            'messages' => $this->translationMessages()
        ];
    }

    /**
     * Define the translator's translation settings.
     *
     * @param  array $config An array of translation settings.
     * @return TranslatorConfig Chainable
     */
    public function setTranslations(array $config)
    {
        if (isset($config['paths'])) {
            $this->setTranslationPaths($config['paths']);
        }

        if (isset($config['messages'])) {
            $this->setTranslationMessages($config['messages']);
        }

        return $this;
    }

    /**
     * Retrieve the list of file and directory paths for loading language metadata.
     *
     * @return string[]
     */
    public function languagePaths()
    {
        return $this->languagePaths;
    }

    /**
     * Assign a list of file and directory paths for loading language metadata.
     *
     * @param  string[] $paths The list of path to add.
     * @return self
     */
    private function setLanguagePaths(array $paths)
    {
        $this->languagePaths = [];

        foreach ($paths as $path) {
            if ($path && is_string($path)) {
                $this->languagePaths[] = $path;
            }
        }

        return $this;
    }

    /**
     * Retrieve the list of file and directory paths for loading translations.
     *
     * @return string[]
     */
    public function translationPaths()
    {
        return $this->translationPaths;
    }

    /**
     * Assign a list of file and directory paths for loading translations.
     *
     * @param  string[] $paths The list of path to add.
     * @return self
     */
    private function setTranslationPaths(array $paths)
    {
        $this->translationPaths = [];

        foreach ($paths as $path) {
            if ($path && is_string($path)) {
                $this->translationPaths[] = $path;
            }
        }

        return $this;
    }

    /**
     * Retrieve the map of custom translated messages.
     *
     * @return array
     */
    public function translationMessages()
    {
        return $this->translationMessages;
    }

    /**
     * Assign a map of custom translated messages.
     *
     * @param  array $messages An associative array of translations.
     * @return self
     */
    private function setTranslationMessages(array $messages)
    {
        $this->translationMessages = $messages;

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
     * @param  LanguageInterface|string|null $lang A language object or identifier.
     * @return MultilingualAwareInterface Chainable
     */
    public function setCurrentLanguage($lang = null)
    {
        $this->applyCurrentLanguage($lang);
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
                $locale = $language->locale();

                if (!$locale) {
                    $locales = $language->code();
                }

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
