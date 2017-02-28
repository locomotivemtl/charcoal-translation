<?php

namespace Charcoal\Translation;

use \ArrayAccess;
use \Traversable;
use \Exception;
use \InvalidArgumentException;
use \JsonSerializable;
use \Serializable;

// Dependencies from 'charcoal-config'
use \Charcoal\Config\ConfigurableInterface;
use \Charcoal\Config\ConfigurableTrait;

// Dependencies from 'charcoal-translator'
use \Charcoal\Translator\Translation;

// Local Dependencies
use \Charcoal\Polyglot\MultilingualAwareInterface;
use \Charcoal\Translation\ConfigurableTranslationTrait;
use \Charcoal\Translation\TranslationConfig;
use \Charcoal\Translation\TranslationStringInterface;

/**
 * Translation String Object
 *
 * Allow a string to be translatable, transparently.
 *
 * Except for `self::$currentLanguage`, this configurable object delegates
 * most of its multi-language handling to TranslationConfig.
 *
 * The TranslationString class provides it's own independant `self::$currentLanguage`.
 * The class redefines the following methods from ConfigurableTranslationTrait:
 *
 * • `self::defaultLanguage()` → `ConfigurableTranslationTrait::currentLanguage()`
 * • `self::currentLanguage()` → `self::$currentLanguage`
 * • `self::setCurrentLanguage()` → `self::$currentLanguage`
 *
 * The {@see \Charcoal\Translation\Catalog\Catalog} class features a similar special-use for the current language.
 */
class TranslationString implements
    JsonSerializable,
    Serializable,
    MultilingualAwareInterface,
    TranslationStringInterface,
    ConfigurableInterface,
    ArrayAccess
{
    use ConfigurableTrait;
    use ConfigurableTranslationTrait;

    /**
     * The object's translations
     *
     * Stored as a `[ $lang => $val ]` hash.
     *
     * @var array $val
     */
    private $val = [];

    /**
     * Current language identifier.
     *
     * @var string
     */
    private $currentLanguage;

    /**
     * Calling the constructor with a parameter should force setting it up as value.
     *
     * @param  mixed                   $val    One or more strings (as an array).
     * @param  TranslationConfig|array $config An existing TranslationConfig or settings to apply to this instance.
     * @return self
     */
    public function __construct($val = null, $config = null)
    {
        if (isset($config)) {
            $this->setConfig($config);
        }

        if (isset($val)) {
            $this->setVal($val);
        }

        return $this;
    }

    /**
     * Magic caller.
     *
     * Accepts language as a method:
     *
     * ```php
     * $str = new TranslationString([ 'en' => 'foo', 'fr' => 'bar' ]);
     * // Because "fr" is an available language, this will output "bar".
     * echo $str->fr();
     * ```
     *
     * @param  string $method A language for an available translation.
     * @param  mixed  $args   Extra arguments (unused).
     * @return string A translated string.
     * @throws Exception If language isn't available.
     */
    public function __call($method, $args = null)
    {
        unset($args);

        if (in_array($method, $this->availableLanguages())) {
            return $this->val($method);
        } else {
            throw new Exception(
                sprintf('Invalid language: "%s"', (string)$method)
            );
        }
    }

    /**
     * Magic string getter, when the object is cast as a string.
     *
     * This allows, amongst other things, to use the `TranslationString`
     * object directly in a mustache template.
     *
     * @return string The translated string, in current language.
     */
    public function __toString()
    {
        return $this->val();
    }

    /**
     * Assign the current translation value(s).
     *
     * @param TranslationStringInterface|array|string $val The translation value(s).
     *     Add one or more translation values.
     *
     *     Accept 3 types of arguments:
     *     - object (TranslationStringInterface): The data will be copied from the object's.
     *     - array: All languages available in the array. The format of the array should
     *       be a hash in the `lang` => `string` format.
     *     - string: The value will be assigned to the current language.
     * @return self
     * @throws InvalidArgumentException If value is invalid.
     */
    public function setVal($val)
    {
        if ($val instanceof TranslationStringInterface) {
            $this->val = $val->all();
        } elseif (class_exists('\Charcoal\Translator\Translation') && ($val instanceof Translation)) {
            $this->val = $val->data();
        } elseif (is_array($val) || is_a($val, 'Traversable')) {
            $this->val = [];

            foreach ($val as $lang => $l10n) {
                $this->addVal($lang, (string)$l10n);
            }
        } elseif (is_string($val)) {
            $lang = $this->currentLanguage();

            $this->val[$lang] = $val;
        } else {
            throw new InvalidArgumentException(
                'Invalid localized value.'
            );
        }
        return $this;
    }

    /**
     * Add a translation value to a specified and available language.
     *
     * @param  LanguageInterface|string $lang A language object or identifier.
     * @param  string                   $val  The translation to be added.
     * @return self
     * @throws InvalidArgumentException If the language or value is invalid.
     */
    public function addVal($lang, $val)
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

        if (!is_string($val)) {
            throw new InvalidArgumentException('Localized value must be a string.');
        }

        $this->val[$lang] = $val;

        return $this;
    }

    /**
     * Remove a translation value specified by an available language.
     *
     * @param  LanguageInterface|string $lang A language object or identifier.
     * @return self
     * @throws InvalidArgumentException If language is invalid.
     */
    public function removeVal($lang)
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

        if ($this->hasVal($lang)) {
            unset($this->val[$lang]);
        }
        return $this;
    }

    /**
     * Get a translation value.
     *
     * If $lang is provided, that language's translation is returned.
     * If $lang isn't a supported language or the translation is unavailable,
     * the translation in the default language is returned.
     * If $lang isn't provided, the translation in the current language is returned.
     *
     * @param  LanguageInterface|string|null $lang Optional supported language to retrieve a translation in.
     * @return string
     * @throws InvalidArgumentException If language is invalid.
     * @todo   When the language is invalid, should we fallback to the default language
     *         or throw an InvalidArgumentException.
     */
    public function val($lang = null)
    {
        if ($lang === null) {
            $lang = $this->currentLanguage();
        } elseif (!$this->hasLanguage($lang)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid language, received %s',
                    (is_object($lang) ? get_class($lang) : gettype($lang))
                )
            );
        }

        if ($this->hasVal($lang)) {
            return $this->val[$lang];
        } else {
            $lang = $this->defaultLanguage();

            if ($this->hasVal($lang)) {
                return $this->val[$lang];
            } else {
                return '';
            }
        }
    }

    /**
     * Determine if a translation exists.
     *
     * If $lang is provided, that language's translation is tested.
     * If $lang isn't provided, the translation in the current language is tested.
     *
     * @param  LanguageInterface|string $lang Optional supported language to lookup.
     * @return boolean
     * @throws InvalidArgumentException If language is invalid.
     */
    public function hasVal($lang = null)
    {
        $lang = $this->parseLanguageIdent($lang);

        return !empty($this->val[$lang]);
    }

    /**
     * Alias of `self::hasVal()`.
     *
     * @param  LanguageInterface|string $lang Optional supported language to lookup.
     * @return boolean
     */
    public function hasTranslation($lang = null)
    {
        return $this->hasVal($lang);
    }

    /**
     * Get a translation value or the first one available.
     *
     * Behaves like {@see self::val()} except that if there's no translation in
     * the default language, it returns the first non-empty translation.
     *
     * @param  LanguageInterface|string|null $lang Optional supported language to retrieve a translation in.
     * @throws InvalidArgumentException If language is invalid.
     * @return string
     */
    public function fallback($lang = null)
    {
        $lang = $this->parseLanguageIdent($lang);
        $val  = $this->val($lang);
        if (empty($val)) {
            $available = array_diff_key($this->val, array_flip([ $lang, $this->defaultLanguage() ]));
            foreach ($available as $code => $l10n) {
                if (!empty($l10n)) {
                    return $l10n;
                }
            }
        }

        return $val;
    }

    /**
     * Get the array of translations in all languages.
     *
     * @return string[]
     *
     * @todo Add support for retrieving a subset of translations.
     */
    public function all()
    {
        return $this->val;
    }

    /**
     * Get an array of translations in either all languages or a select few.
     *
     * If an array of one or more lanagues is provided, the metahod returns
     * a subset of the object's available languages (if any).
     *
     * @param  (LanguageInterface|string)[] $langs Optional language(s) filters.
     * @return (LanguageInterface|string)[] An array of available languages.
     */
    public function translations(array $langs = [])
    {
        if (count($langs)) {
            array_walk($langs, function (&$val, $key) {
                $val = self::resolveLanguageIdent($val);
            });

            return array_intersect_key($this->all(), array_flip($langs));
        }

        return $this->all();
    }

    /**
     * Alias of `ConfigurableTranslationTrait::hasLanguage()`.
     *
     * Called when using the objects as `isset($obj['foo'])`.
     *
     * @see    ArrayAccess::offsetExists()
     * @param  string $lang A language identifier.
     * @return boolean
     * @throws InvalidArgumentException If array key isn't a string.
     */
    public function offsetExists($lang)
    {
        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                'Array key must be a string.'
            );
        }

        return $this->hasVal($lang);
    }

    /**
     * Alias of `self::val()`.
     *
     * @see    ArrayAccess::offsetGet()
     * @param  string $lang A language identifier.
     * @return string A translated string.
     * @throws InvalidArgumentException If array key isn't a string.
     */
    public function offsetGet($lang)
    {
        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                'Array key must be a string.'
            );
        }

        return $this->val($lang);
    }

    /**
     * Alias of `self::addVal()`.
     *
     * @see    ArrayAccess::offsetSet()
     * @param  string $lang A language identifier.
     * @param  string $val  A translation value.
     * @return void
     * @throws InvalidArgumentException If array key isn't a string.
     */
    public function offsetSet($lang, $val)
    {
        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                'Array key must be a string.'
            );
        }

        $this->addVal($lang, $val);
    }

    /**
     * Alias of `self::removeVal()`.
     *
     * Called when using `unset($obj['foo']);`.
     *
     * @see    ArrayAccess::offsetUnset()
     * @param  string $lang A language identifier.
     * @return void
     * @throws InvalidArgumentException If array key isn't a string.
     */
    public function offsetUnset($lang)
    {
        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                'Array key must be a string.'
            );
        }

        $this->removeVal($lang);
    }

    /**
     * Get the config's default language.
     *
     * @uses   ConfigurableInterface::config()
     * @return string A language identifier.
     */
    public function defaultLanguage()
    {
        return $this->config()->defaultLanguage();
    }

    /**
     * Get the object's current language.
     *
     * The current language acts as the first to be used when interacting
     * with data in a context where the language isn't explicitly specified.
     *
     * @see    TranslatableTrait::currentLanguage()
     * @return string A language identifier.
     */
    public function currentLanguage()
    {
        if (!isset($this->currentLanguage)) {
            return $this->config()->currentLanguage();
        }

        return $this->currentLanguage;
    }

    /**
     * Set the object's current language.
     *
     * Must be one of the available languages assigned to the object.
     *
     * Defaults to resetting the object's current language to the config's,
     * (which might fall onto the default language).
     *
     * @see    TranslatableTrait::setCurrentLanguage()
     * @param  LanguageInterface|string|null $lang A language object or identifier.
     * @return self
     * @throws InvalidArgumentException If language isn't available.
     */
    public function setCurrentLanguage($lang = null)
    {
        if (isset($lang)) {
            $lang = self::resolveLanguageIdent($lang);

            if ($this->hasLanguage($lang)) {
                $this->currentLanguage = $lang;
            } else {
                throw new InvalidArgumentException(
                    sprintf('Invalid language: "%s"', (string)$lang)
                );
            }
        } else {
            $this->currentLanguage = null;
        }

        return $this;
    }

    /**
     * Retrieve a string representation of translations.
     *
     * @see    Serializable::serialize()
     * @return string
     */
    public function serialize()
    {
        $data = $this->all();
        return serialize($data);
    }

    /**
     * Assign translation value(s) from a
     * string representation of translations.
     *
     * @see    Serializable::unsierialize()
     * @param  string $data The string representation of the translations.
     * @return void
     */
    public function unserialize($data)
    {
        $data = unserialize($data);
        $this->setVal($data);
    }

    /**
     * Retrieve translations that can be serialized natively by json_encode().
     *
     * @see    JsonSerializable::jsonSerialize()
     * @return string[]
     */
    public function jsonSerialize()
    {
        return $this->all();
    }

    /**
     * Retrieve a Charcoal application's instance or a new instance of self.
     *
     * @see    ConfigurableInterface::create_config() For abstract definition of this method.
     * @uses   TranslationConfig::instance()
     * @param  array|string|null $data Optional data to pass to the new TranslationConfig instance.
     * @return TranslationConfig
     */
    protected function createConfig($data = null)
    {
        $config = TranslationConfig::instance($data);
        return $config;
    }

    /**
     * Resolve the given language's identifier or return the current language.
     *
     * Behaves like {@see self::resolveLanguageIdent()} except it returns
     * the current language if the value is NULL.
     *
     * @param  mixed $lang A language object or identifier.
     * @throws InvalidArgumentException If language is invalid.
     * @return string|mixed A language identifier.
     */
    protected function parseLanguageIdent($lang)
    {
        if ($lang === null) {
            $lang = $this->currentLanguage();
        } else {
            $lang = self::resolveLanguageIdent($lang);

            if (!is_string($lang)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid language, received %s',
                        (is_object($lang) ? get_class($lang) : gettype($lang))
                    )
                );
            }
        }

        return $lang;
    }

    /**
     * Determine whether a variable is translatable.
     *
     * Useful for ignoring empty-like values.
     *
     * @param  mixed $var The value to be checked.
     * @return boolean
     */
    public static function isTranslatable($var)
    {
        if ($var === null) {
            return false;
        }

        if (is_string($var)) {
            return !!strlen(trim($var));
        }

        if ($var instanceof TranslationStringInterface) {
            return true;
        }

        if (class_exists('\Charcoal\Translator\Translation') && ($val instanceof Translation)) {
            return true;
        }

        if ($var instanceof Traversable) {
            $var = iterator_to_array($var);
        }

        if (is_array($var)) {
            return !!array_filter(
                $var,
                function ($v, $k) {
                    if (is_string($k) && is_string($v)) {
                        if (strlen($k) && mb_strlen($v)) {
                            return true;
                        }
                    }

                    return false;
                },
                ARRAY_FILTER_USE_BOTH
            );
        }

        return false;
    }
}
