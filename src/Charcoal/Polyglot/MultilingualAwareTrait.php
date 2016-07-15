<?php

namespace Charcoal\Polyglot;

use \InvalidArgumentException;

// Local Dependencies
use \Charcoal\Language\LanguageInterface;

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
 * Contains three additional methods:
 *
 * • `is_multilingual()`
 * • `resolveLanguage_ident()` (static)
 * • `resolve_specialLanguages()`
 */
trait MultilingualAwareTrait
{
    /**
     * Determine if the object has more than one available language.
     *
     * @return boolean Whether the object is multilingual (TRUE) or unilingual (FALSE).
     */
    public function isMultilingual()
    {
        return count($this->availableLanguages()) > 1;
    }

    /**
     * Resolve a language's identifier.
     *
     * @param  LanguageInterface|string $lang A language object or identifier.
     * @return string|mixed A language identifier.
     */
    public static function resolveLanguageIdent($lang)
    {
        if ($lang instanceof LanguageInterface) {
            return (string)$lang->ident();
        } elseif (is_array($lang) && isset($lang['ident'])) {
            return (string)$lang['ident'];
        } else {
            return $lang;
        }
    }
}
