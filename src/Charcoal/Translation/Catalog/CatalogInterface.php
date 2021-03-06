<?php

namespace Charcoal\Translation\Catalog;

use Traversable;

/**
 * Defines a collector of TranslationStringInterface objects
 */
interface CatalogInterface
{
    /**
     * Get the array of entries and their translations
     *
     * If a language code is provided, the method returns
     * a subset of entries in the specified language.
     *
     * @param  LanguageInterface|string $lang Optional language code.
     * @return array
     */
    public function entries($lang = null);

    /**
     * Set the array of entries and their translations.
     *
     * If an empty array is provided, the method should consider this a request
     * to empty the entries store.
     *
     * @param  array|Traversable|null $entries Optional array of zero or more entries to set the catalog.
     * @return CatalogInterface Chainable
     */
    public function setEntries($entries = null);

    /**
     * Get an entry in the catalog
     *
     * @param  string $ident An entry's key.
     * @return TranslationString
     */
    public function entry($ident);

    /**
     * Determine if the catalog has a specified entry
     *
     * @param  string $ident An entry's key.
     * @return boolean
     */
    public function hasEntry($ident);

    /**
     * Add entry to the catalog
     *
     * @param  string                           $ident        A unique key for this entry.
     * @param  TranslationStringInterface|array $translations A set of translations.
     * @return CatalogInterface Chainable
     */
    public function addEntry($ident, $translations);

    /**
     * Remove an entry from the catalog
     *
     * @param  string $ident An entry's key.
     * @return CatalogInterface Chainable
     */
    public function removeEntry($ident);

    /**
     * Add a translation to an entry in the catalog
     *
     * @param  string $ident An entry's key.
     * @param  string $lang  A language identifier.
     * @param  string $val   The translation to be added.
     * @return CatalogInterface Chainable
     */
    public function addEntryTranslation($ident, $lang, $val);

    /**
     * Remove a translation from an entry in the catalog
     *
     * @param  string $ident An entry's key.
     * @param  string $lang  A language identifier.
     * @return CatalogInterface Chainable
     */
    public function removeEntryTranslation($ident, $lang);

    /**
     * Get a translation for an entry in the catalog
     *
     * @param  string $ident An entry's key.
     * @param  string $lang  Optional. Defaults to the current language.
     * @return string
     */
    public function translate($ident, $lang = null);
}
