<?php

namespace Charcoal\Translation;

// From 'charcoal-translation'
use Charcoal\Polyglot\MultilingualAwareInterface;

/**
 * Basic Implementation of TranslatorAwareInterface
 */
trait TranslatorAwareTrait
{
    /**
     * The translator instance.
     *
     * @var MultilingualAwareInterface
     */
    private $translator;

    /**
     * Sets a translator instance on the object.
     *
     * @param MultilingualAwareInterface $translator A translator.
     *
     * @return self
     */
    public function setTranslator(MultilingualAwareInterface $translator)
    {
        $this->translator = $translator;

        return $this;
    }

    /**
     * Retrieve the translator instance.
     *
     * @return MultilingualAwareInterface
     */
    public function translator()
    {
        return $this->translator;
    }
}
