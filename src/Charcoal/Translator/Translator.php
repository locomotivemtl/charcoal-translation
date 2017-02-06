<?php

namespace Charcoal\Translator;

use Symfony\Component\Translation\Translator as SymfonyTranslator;

use Charcoal\Translator\LanguageManager;
use Charcoal\Translator\Translation;

/**
 * Extends the symfony translator to allow returned values in a "Translation" oject,
 * containing localizations for all languages.
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
     * Get a translation object from a value.
     * @param TranslationInterface|array|string $val The string or translation-object to retrieve.
     * @return Translation
     */
    public function translation($val)
    {
        $translation = new Translation($val, $this->languageManager);
        foreach ($this->languageManager->availableLanguages() as $lang) {
            if (!isset($translation[$lang]) || $translation[$lang] == $val) {
                $translation[$lang] = $this->trans($val, [], null, $lang);
            }
        }
        return $translation;
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
}
