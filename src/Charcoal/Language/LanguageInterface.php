<?php

namespace Charcoal\Language;

/**
 * Defines a language.
 */
interface LanguageInterface
{
    /**
     * Special language code used when the language does not have a code to be identified by.
     */
    const LANGUAGE_NOT_CODED = 'mis';

    /**
     * Special language code used when the language is not identified.
     *
     * @link http://www.w3.org/International/questions/qa-no-language#undetermined
     */
    const LANGUAGE_NOT_SPECIFIED = 'und';

    /**
     * Special language code used when the marked object has no linguistic content.
     *
     * @link http://www.w3.org/International/questions/qa-no-language#nonlinguistic
     */
    const LANGUAGE_NOT_APPLICABLE = 'zxx';

    /**
     * Language written left to right.
     *
     * @see self::direction()
     */
    const DIRECTION_LTR = 'ltr';

    /**
     * Language written right to left.
     *
     * @see self::direction()
     */
    const DIRECTION_RTL = 'rtl';

    /**
     * @param array $data The data to set.
     * @return LanguageInterface Chainable
     */
    public function setData(array $data);

    /**
     * Set the language identifier (language code)
     *
     * @param  string $ident Language identifier.
     * @return LanguageInterface Chainable
     */
    public function setIdent($ident);

    /**
     * Get the language identifier (language code)
     *
     * @return string Language identifier.
     */
    public function ident();

    /**
     * Set the name of the language
     *
     * Optionally, set the name in other languages.
     *
     * @param  TranslationString|array|string $name Language's name in one or more languages.
     * @return LanguageInterface Chainable
     */
    public function setName($name);

    /**
     * Get the name of the language
     *
     * @return TranslationString|string Language's name.
     */
    public function name();

    /**
     * Set the text direction (left-to-right or right-to-left)
     *
     * Either {@see self::DIRECTION_LTR} or {@see self::DIRECTION_RTL}.
     *
     * @param  string $dir Language's directionality.
     * @return LanguageInterface Chainable
     */
    public function setDirection($dir);

    /**
     * Get the text direction
     *
     * @return string Language's directionality.
     */
    public function direction();

    /**
     * Set the language's locale
     *
     * @param  LocaleInterface|string $locale Regional identifier.
     * @return LanguageInterface Chainable
     */
    public function setLocale($locale);

    /**
     * Get the language's locale
     *
     * @return LocaleInterface|string Regional identifier
     */
    public function locale();
}
