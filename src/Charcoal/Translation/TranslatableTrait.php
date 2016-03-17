<?php

namespace Charcoal\Translation;

use \InvalidArgumentException;

// Local Dependencies
use \Charcoal\Language\Language;
use \Charcoal\Language\LanguageInterface;
use \Charcoal\Polyglot\MultilingualAwareTrait;

/**
 * An implementation of the `MultilingualAwareInterface`.
 *
 * A basic trait for objects needed to interact with languages defined for itself.
 *
 * @see \Charcoal\Translation\ConfigurableTranslationTrait
 *     For objects that use ConfigurableTrait. Useful for sharing a single
 *     instance of TranslationString.
 *
 *     Provides a working exampel of how to delegate language-handling to
 *     a separate object.
 *
 * Contains one additional method:
 *
 * • `self::resolve_specialLanguages()`
 */
trait TranslatableTrait
{
    use MultilingualAwareTrait;

    /**
     * List of available languages.
     *
     * @var (LanguageInterface|string)[] $languages
     */
    protected $languages = [];

    /**
     * List of language codes to use as fallbacks for the current language.
     *
     * @var  string[]
     */
    protected $fallbackLanguages = [];

    /**
     * Fallback language identifier.
     *
     * @var string
     */
    protected $defaultLanguage;

    /**
     * Current language identifier.
     *
     * @var string
     */
    protected $currentLanguage;

    /**
     * Resolve the default and current languages.
     *
     * Utility to be called after altering the self::$languages list.
     *
     * 1. Retrieve special language directly; mitigates validating value twice.
     * 2. Validate existence of special language; if missing, reset value.
     *
     * @used-by self::setLanguages()
     * @used-by self::removeLanguage()
     * @return  MultilingualAwareInterface Chainable
     */
    public function resolveSpecialLanguages()
    {
        if (count($this->languages)) {
            if (!isset($this->defaultLanguage) || !array_key_exists($this->defaultLanguage, $this->languages)) {
                $this->setDefaultLanguage();
            }

            if (!isset($this->currentLanguage) || !array_key_exists($this->currentLanguage, $this->languages)) {
                $this->setCurrentLanguage();
            }
        }

        return $this;
    }

    /**
     * Retrieve the object's list of available languages.
     *
     * @param  (LanguageInterface|string)[] $langs Optional language(s) filters.
     *     If an array of one or more lanagues is provided, the method returns
     *     a subset of the object's available languages (if any).
     * @return array An array of available languages
     */
    public function languages(array $langs = [])
    {
        if (count($langs)) {
            array_walk($langs, function (&$val, $key) {
                $val = self::resolveLanguage_ident($val);
            });

            return array_intersect_key($this->languages, array_flip($langs));
        }

        return $this->languages;
    }

    /**
     * Retrieve the object's list of available language identifiers.
     *
     * @param  (LanguageInterface|string)[] $langs Optional language(s) filters.
     *     If an array of one or more lanagues is provided, the method returns
     *     a subset of the object's available languages (if any).
     * @return array An array of available language identifiers
     */
    public function availableLanguages(array $langs = [])
    {
        $available = array_keys($this->languages);

        if (count($langs)) {
            array_walk($langs, function (&$val, $key) {
                $val = self::resolveLanguage_ident($val);
            });

            return array_intersect($available, $langs);
        }

        return $available;
    }

    /**
     * Assign a list of languages to the object.
     *
     * When updating the list of available languages, the default and current language
     * is checked against the new list. If the either doesn't exist in the new list,
     * the first of the new set is used as the default language and the current language
     * is reset to NULL (which falls onto the default language).
     *
     * @param  (LanguageInterface|string)[] $langs Optional language(s) filters.
     *     An array of zero or more language objects or language identifiers
     *     to set on the object.
     *
     *     If an empty array is provided, the method should consider this a request
     *     to clear the languages store.
     * @return MultilingualAwareInterface Chainable
     */
    public function setLanguages(array $langs = [])
    {
        $this->languages = [];

        if (count($langs)) {
            $this->addLanguages($langs);
        }

        $this->resolveSpecialLanguages();

        return $this;
    }

    /**
     * Append a list of languages to the object.
     *
     * @param  (LanguageInterface|string)[] $langs The languages to set.
     * @return MultilingualAwareInterface Chainable
     */
    public function addLanguages(array $langs)
    {
        foreach ($langs as $ident => $lang) {
            /** Make sure arrays are acceptable */
            if (is_array($lang) && !isset($lang['ident'])) {
                $lang['ident'] = $ident;
            }

            $this->addLanguage($lang);
        }

        return $this;
    }

    /**
     * Add an available language to the object.
     *
     * If adding a LanguageInterface object that is already available,
     * this method will replace the existing one.
     *
     * @param  LanguageInterface|array|string $lang A language object or identifier.
     * @return MultilingualAwareInterface Chainable
     *
     * @throws InvalidArgumentException If the language is invalid.
     */
    public function addLanguage($lang)
    {
        if (is_string($lang)) {
            $this->languages[$lang] = $lang;
        } elseif (is_array($lang) && isset($lang['ident'])) {
            $this->languages[$lang['ident']] = $lang;
        } elseif ($lang instanceof LanguageInterface) {
            $this->languages[$lang->ident()] = $lang;
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Must be a string-cast language code or an instance of LanguageInterface, received %s',
                    (is_object($lang) ? get_class($lang) : gettype($lang))
                )
            );
        }

        return $this;
    }

    /**
     * Remove an available language from the object.
     *
     * @uses   self::resolve_specialLanguages()
     * @param  LanguageInterface|string $lang A language object or identifier.
     * @return MultilingualAwareInterface Chainable
     *
     * @throws InvalidArgumentException If an array member isn't a string or instance of LanguageInterface.
     */
    public function removeLanguage($lang)
    {
        $lang = self::resolveLanguageIdent($lang);

        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid language, received %s',
                    (is_object($lang) ? get_class($lang) : gettype($lang))
                )
            );
        }

        if (isset($this->languages[$lang])) {
            unset($this->languages[$lang]);
        }

        $this->resolve_specialLanguages();

        return $this;
    }

    /**
     * Retrieve an available language from the object.
     *
     * @param  LanguageInterface|string $lang A language object or identifier.
     * @return LanguageInterface|string|null A language object or identifier.
     *
     * @throws InvalidArgumentException If an array member isn't a string or instance of LanguageInterface.
     */
    public function language($lang)
    {
        if ($this->hasLanguage($lang)) {
            return $this->languages[$lang];
        }

        return null;
    }

    /**
     * Determine if the object has a specified language.
     *
     * @param  LanguageInterface|string $lang A language object or identifier.
     * @throws InvalidArgumentException If language is invalid.
     * @return boolean Whether the language is available
     */
    public function hasLanguage($lang)
    {
        $lang = self::resolveLanguageIdent($lang);

        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid language, received %s',
                    (is_object($lang) ? get_class($lang) : gettype($lang))
                )
            );
        }

        return array_key_exists($lang, $this->languages);
    }

    /**
     * Retrieve the object's default language identifier.
     *
     * The default language acts as a fallback when the current language
     * is not available. This is especially useful when dealing with translations.
     *
     * @todo   Replace with `self::fallbackLanguages()`
     * @return string A language identifier.
     */
    public function defaultLanguage()
    {
        if (!isset($this->defaultLanguage)) {
            $this->setDefaultLanguage();
        }

        return $this->defaultLanguage;
    }

    /**
     * Set the object's default language.
     *
     * Must be one of the available languages assigned to the object.
     *
     * @param  LanguageInterface|string|null $lang A language object or identifier.
     * @throws InvalidArgumentException If language is invalid.
     * @return MultilingualAwareInterface Chainable
     */
    public function setDefaultLanguage($lang = null)
    {
        if (isset($lang)) {
            $lang = self::resolveLanguageIdent($lang);

            if ($this->hasLanguage($lang)) {
                $this->defaultLanguage = $lang;
            } else {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid language, received %s',
                        (is_object($lang) ? get_class($lang) : gettype($lang))
                    )
                );
            }
        } else {
            $languages = $this->availableLanguages();
            $this->defaultLanguage = reset($languages);
        }

        return $this;
    }

    /**
     * Retrieve the object's current language identifier.
     *
     * The current language acts as the first to be used when interacting
     * with data in a context where the language isn't explicitly specified.
     *
     * @return string A language identifier.
     */
    public function currentLanguage()
    {
        if (!isset($this->currentLanguage)) {
            return $this->defaultLanguage();
        }

        return $this->currentLanguage;
    }

    /**
     * Set the object's current language.
     *
     * Must be one of the available languages assigned to the object.
     *
     * Defaults to resetting the object's current language to NULL,
     * (which falls onto the default language).
     *
     * @param  LanguageInterface|string|null $lang A language object or identifier.
     * @throws InvalidArgumentException If language is invalid.
     * @return MultilingualAwareInterface Chainable
     */
    public function setCurrentLanguage($lang = null)
    {
        if (isset($lang)) {
            $lang = self::resolveLanguageIdent($lang);

            if ($this->hasLanguage($lang)) {
                $this->currentLanguage = $lang;
            } else {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid language, received %s',
                        (is_object($lang) ? get_class($lang) : gettype($lang))
                    )
                );
            }
        } else {
            $this->currentLanguage = null;
        }

        return $this;
    }
}
