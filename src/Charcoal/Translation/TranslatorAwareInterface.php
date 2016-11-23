<?php

namespace Charcoal\Translation;

// From 'charcoal-translation'
use Charcoal\Polyglot\MultilingualAwareInterface;

/**
 * Describes a translator-aware instance.
 */
interface TranslatorAwareInterface
{
    /**
     * Sets a translator instance on the object.
     *
     * @param MultilingualAwareInterface $translator A translator.
     *
     * @return null
     */
    public function setTranslator(MultilingualAwareInterface $translator);

    /**
     * Retrieve the translator instance.
     *
     * @return MultilingualAwareInterface
     */
    public function translator();
}
