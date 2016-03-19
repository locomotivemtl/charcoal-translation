<?php

namespace Charcoal\Translation\Catalog;

// Local Dependency
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
}
