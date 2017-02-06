<?php

namespace Charcoal\Translator;

use InvalidArgumentException;

/**
 *
 */
class LanguageManager
{
    /**
     * @var array
     */
    private $languages;

    /**
     * @var string
     */
    private $defaultLanguage;

    /**
     * @var string[]
     */
    private $fallbackLanguages;

    /**
     * @var string|null
     */
    private $currentLanguage;

    /**
     * @param array $data Constructor dependencies.
     */
    public function __construct(array $data)
    {
        $this->languages = $data['languages'];
        $this->defaultLanguage = $data['default_language'];
        $this->fallbackLanguages = $data['fallback_languages'];
    }

    /**
     * @return string[]
     */
    public function availableLanguages()
    {
        return array_keys($this->languages);
    }

    /**
     * @param string|null $lang The current language (ident).
     * @throws InvalidArgumentException If the language is invalid.
     * @return void
     */
    public function setCurrentLanguage($lang)
    {
        if ($lang === null) {
            $this->currentLanguage = null;
            return;
        }
        if (!$this->hasLanguage($lang)) {
            throw new InvalidArgumentException(
                'Invalid language.'
            );
        }
        $this->currentLanguage = $lang;
//        setlocale('todo')
    }

    /**
     * @return string
     */
    public function currentLanguage()
    {
        if ($this->currentLanguage === null) {
            return $this->defaultLanguage;
        }
        return $this->currentLanguage;
    }

    /**
     * @param string $lang The language to check.
     * @return boolean
     */
    public function hasLanguage($lang)
    {
        return in_array($lang, $this->availableLanguages());
    }
}
