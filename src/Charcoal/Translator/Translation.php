<?php

namespace Charcoal\Translator;

use ArrayAccess;
use Exception;
use InvalidArgumentException;
use JsonSerializable;

/**
 *
 */
class Translation implements
    ArrayAccess,
    JsonSerializable
{
    /**
     * The object's translations
     * Stored as a `[ $lang => $val ]` hash.
     *
     * @var string[]
     */
    private $val = [];

    /**
     * @var LanguageManager
     */
    private $languageManager;

    /**
     * @param TranslationInterface|array|string $val             The translation values.
     * @param LanguageManager                   $languageManager A LanguageManager instance.
     */
    public function __construct($val, LanguageManager $languageManager)
    {
        $this->languageManager = $languageManager;

        $this->setVal($val);
    }

    /**
     * Output the current language's value, when cast to string.
     *
     * @return string
     */
    public function __toString()
    {
        $lang = $this->languageManager->currentLanguage();
        return $this->val[$lang];
    }

    /**
     * Get the array of translations in all languages.
     *
     * @return string[]
     *
     * @todo Add support for retrieving a subset of translations.
     */
    public function data()
    {
        return $this->val;
    }

    /**
     * Assign the current translation value(s).
     *
     * @param TranslationInterface|array|Traversable|string $val The translation value(s).
     *     Add one or more translation values.
     *
     *     Accept 3 types of arguments:
     *     - object (TranslationInterface): The data will be copied from the object's.
     *     - array: All languages available in the array. The format of the array should
     *       be a hash in the `lang` => `string` format.
     *     - string: The value will be assigned to the current language.
     * @return self
     * @throws InvalidArgumentException If value is invalid.
     */
    private function setVal($val)
    {
        if ($val instanceof Translation) {
            $this->val = $val->data();
        } elseif (is_array($val)) {
            $this->val = [];
            foreach ($val as $lang => $l10n) {
                $this->addVal($lang, (string)$l10n);
            }
        } elseif (is_string($val)) {
            $lang = $this->languageManager->currentLanguage();

            $this->val[$lang] = $val;
        } else {
            throw new InvalidArgumentException(
                'Invalid localized value.'
            );
        }
        return $this;
    }

    /**
     * @param  string $lang A language identifier.
     * @return boolean
     * @see    ArrayAccess::offsetExists()
     * @throws InvalidArgumentException If array key isn't a string.
     */
    public function offsetExists($lang)
    {
        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                sprintf('Translation key must be a string. (%s given)', gettype($lang))
            );
        }

        return !empty($this->val[$lang]);
    }

    /**
     * @see    ArrayAccess::offsetGet()
     * @param  string $lang A language identifier.
     * @return string A translated string.
     * @see    ArrayAccess::offsetGet()
     * @throws InvalidArgumentException If array key isn't a string.
     */
    public function offsetGet($lang)
    {
        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                sprintf('Translation key must be a string. (%s given)', gettype($lang))
            );
        }

        return $this->val[$lang];
    }

    /**
     * @param  string $lang A language identifier.
     * @param  string $val  A translation value.
     * @throws InvalidArgumentException If array key isn't a string.
     * @see    ArrayAccess::offsetSet()
     * @return void
     */
    public function offsetSet($lang, $val)
    {
        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                sprintf('Translation key must be a string. (%s given)', gettype($lang))
            );
        }

        if (!is_string($val)) {
            throw new InvalidArgumentException(
                'Localized value must be a string.'
            );
        }

        $this->val[$lang] = $val;
    }

    /**
     * @param  string $lang A language identifier.
     * @throws InvalidArgumentException If array key isn't a string.
     * @see    ArrayAccess::offsetUnset()
     * @return void
     */
    public function offsetUnset($lang)
    {
        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                sprintf('Translation key must be a string. (%s given)', gettype($lang))
            );
        }

        unset($this->val[$lang]);
    }

    /**
     * Retrieve translations that can be serialized natively by json_encode().
     *
     * @see    JsonSerializable::jsonSerialize()
     * @return string[]
     */
    public function jsonSerialize()
    {
        return $this->data();
    }

    /**
     * Add a translation value to a specified and available language.
     *
     * @param  LanguageInterface|string $lang A language object or identifier.
     * @param  string                   $val  The translation to be added.
     * @return self
     * @throws InvalidArgumentException If the language or value is invalid.
     */
    private function addVal($lang, $val)
    {
        if (!is_string($lang)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid language, received %s',
                    (is_object($lang) ? get_class($lang) : gettype($lang))
                )
            );
        }

        $this->val[$lang] = $val;

        return $this;
    }
}
