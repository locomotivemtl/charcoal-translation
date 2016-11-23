<?php

namespace Charcoal\Translation\Catalog;

// From 'charcoal-translation'
use \Charcoal\Language\LanguageInterface;
use \Charcoal\Translation\Catalog\CatalogInterface;

/**
 * Basic Implementation of {@see \Charcoal\Translation\Catalog\CatalogAwareInterface}
 */
trait CatalogAwareTrait
{
    /**
     * The translation catalog instance.
     *
     * @var CatalogInterface
     */
    protected $catalog;

    /**
     * Sets a translation catalog instance on the object.
     *
     * @param CatalogInterface $catalog A translation catalog object.
     *
     * @return self
     */
    public function setCatalog(CatalogInterface $catalog)
    {
        $this->catalog = $catalog;

        return $this;
    }

    /**
     * Retrieve the translation catalog instance.
     *
     * @return CatalogInterface
     */
    public function catalog()
    {
        return $this->catalog;
    }

    /**
     * Get a translation for an entry in the catalog.
     *
     * @param  string                   $ident An entry's key.
     * @param  LanguageInterface|string $lang  Optional. Defaults to the current language.
     * @return string
     */
    public function translate($ident, $lang = null)
    {
        return $this->catalog()->translate($ident, $lang);
    }
}
