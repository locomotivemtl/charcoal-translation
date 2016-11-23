<?php

namespace Charcoal\Translation\Catalog;

// From 'charcoal-translation'
use \Charcoal\Translation\Catalog\CatalogInterface;

/**
 * Describes a translation catalog-aware instance.
 */
interface CatalogAwareInterface
{
    /**
     * Sets a translation catalog instance on the object.
     *
     * @param CatalogInterface $catalog A translation catalog object.
     *
     * @return null
     */
    public function setCatalog(CatalogInterface $catalog);

    /**
     * Retrieve the translation catalog instance.
     *
     * @return CatalogInterface
     */
    public function catalog();
}
