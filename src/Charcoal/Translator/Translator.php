<?php

namespace Charcoal\Translator;

use Symfony\Component\Translation\Translator as SymfonyTranslator;

use Charcoal\Translator\LanguageManager;
use Charcoal\Translator\Translation;

/**
 * Extends the symfony translator to allow returned values in a "Translation" oject,
 * containing localizations for all languages.
 *
 */
class Translator extends SymfonyTranslator
{
    /**
     * @var LanguageManager
     */
    private $languageManager;

    /**
     * @param array $data Constructor data.
     * @return
     */
    public function __construct(array $data)
    {
        $this->languageManager = $data['language_manager'];
        parent::__construct($data['locale'], $data['message_selector'], $data['cache_dir'], $data['debug']);
    }

    /**
     * Get a translation object from a (mixed) value.
     *
     * @param mixed $val The string or translation-object to retrieve.
     * @return Translation|null
     */
    public function translation($val)
    {
        if ($this->isValidTranslation($val) === false) {
            return null;
        }
        $translation = new Translation($val, $this->languageManager);
        foreach ($this->languageManager->availableLanguages() as $lang) {
            if (!isset($translation[$lang]) || $translation[$lang] == $val) {
                $translation[$lang] = $this->trans($val, [], null, $lang);
            }
        }
        return $translation;
    }

    /**
     * Get a translated string from a (mixed) value.
     *
     * @param mixed $val The string or translation-object to retrieve.
     * @return string
     */
    public function translate($val)
    {
        if (is_string($val)) {
            return $this->trans($val);
        } else {
            $translation = $this->translation($val);
            return (string)$translation;
        }
    }

    /**
     * Ensure that the `setLocale()` method also changes the language manager's language.
     *
     * @param string $locale The language (locale) to set.
     * @return void
     */
    public function setLocale($locale)
    {
        parent::setLocale($locale);
        $this->languageManager->setCurrentLanguage($locale);
    }

    /**
     * @param mixed $val The value to be checked.
     * @return boolean
     */
    private function isValidTranslation($val)
    {
        if ($val === null) {
            return false;
        }

        if (is_string($val)) {
            return !empty(trim($val));
        }

        if ($val instanceof Translation) {
            return true;
        }

        if (is_array($val)) {
            return !!array_filter(
                $val,
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
