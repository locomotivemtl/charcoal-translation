<?php

namespace Charcoal\Polyglot;

use \InvalidArgumentException;

// Local Dependencies
use \Charcoal\Language\LanguageInterface;

/**
 * An implementation of the `UnilingualAwareInterface`.
 *
 * A basic trait for objects that simply need to track a single language value.
 */
trait UnilingualAwareTrait
{
    /**
     * Current language identifier.
     *
     * @var string|LanguageInterface
     */
    private $language;

    /**
     * Retrieve the object's current language.
     *
     * @return string|LanguageInterface A language identifier.
     */
    public function language()
    {
        return $this->language;
    }

    /**
     * Determine if the object has a language.
     *
     * @return bool Whether a language has been assigned.
     */
    public function hasLanguage()
    {
        return !!$this->language;
    }

    /**
     * Assign a language to the object.
     *
     * @param  string|LanguageInterface $lang A language object or identifier.
     * @throws InvalidArgumentException If language is invalid.
     * @return self
     */
    public function setLanguage($lang)
    {
        if (is_array($lang) && isset($lang['ident'])) {
            $this->language = (string)$lang['ident'];
        } elseif ($lang instanceof LanguageInterface) {
            $this->language = $lang;
        } elseif (is_string((string)$lang)) {
            $this->language = (string)$lang;
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid language, received %s',
                    (is_object($lang) ? get_class($lang) : gettype($lang))
                )
            );
        }

        return $this;
    }
}
