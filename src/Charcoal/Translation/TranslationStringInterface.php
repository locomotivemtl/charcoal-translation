<?php

namespace Charcoal\Translation;

/**
 * Defines the translatable string object
 */
interface TranslationStringInterface
{
    /**
     * Set one or more translation values
     *
     * @param  TranslationStringInterface|array|string $val The value(s) to translate or as translated array.
     * @return TranslationStringInterface Chainable
     */
    public function setVal($val);

    /**
     * Add a translation value to a specified, and available, language
     *
     * @param  string $lang An available language identifier.
     * @param  string $val  The translation to be added.
     * @return TranslationStringInterface Chainable
     */
    public function addVal($lang, $val);

    /**
     * Remove a translation value specified by an available language
     *
     * @param  string $lang The language to remove.
     * @return TranslationStringInterface Chainable
     */
    public function removeVal($lang);

    /**
     * Get a translation value
     *
     * @param  string|null $lang Optional supported language to retrieve a translation in.
     * @return string
     */
    public function val($lang = null);

    /**
     * Determine if the object has a specified translation
     *
     * @param  string $lang The lang to check for.
     * @return boolean
     */
    public function hasVal($lang);
}
