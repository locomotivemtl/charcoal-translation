<?php

namespace Charcoal\Translation;

use \InvalidArgumentException;

// Dependency from 'charcoal-config'
use \Charcoal\Config\AbstractConfig;

// Local Dependencies
use \Charcoal\Polyglot\MultilingualAwareInterface;
use \Charcoal\Translation\TranslatableTrait;

/**
 * Configuration handler for translations, such as instances of TranslationStringInterface.
 */
class TranslationConfig extends AbstractConfig implements MultilingualAwareInterface
{
    use TranslatableTrait;

    /**
     * Store a global, reusable, instance of TranslationConfig.
     *
     * @var TranslationConfig
     */
    private static $globalInstance;

    /**
     * Get the default configuration
     *
     * @return array
     */
    public function defaults()
    {
        return [
            'languages' => [
                'en' => [
                    'name' => 'English'
                ],
                'fr' => [
                    'name' => 'Français'
                ]
            ]
        ];
    }

    /**
     * Assign a global instance of TranslationConfig for sharing a locale setup.
     *
     * @todo   [mcaskill 2015-12-07] This method must be replaced with equivalence in app Container.
     * @param  TranslationConfig $config A TranslationConfig instance.
     * @return void
     */
    public static function setInstance(TranslationConfig $config)
    {
        static::$globalInstance = $config;
    }

    /**
     * Retrieve a global instance or a new instance of TranslationConfig.
     *
     * @see    AbstractSource::addFilter() Similar implementation.
     * @see    AbstractProperty::fields() Similar implementation.
     * @see    ConfigurableInterface::createConfig() Similar method.
     *
     * @todo   This method needs to be deprecated.
     * @param  array|string|null $data Optional data to pass to the new TranslationConfig instance.
     * @return TranslationConfig
     */
    public static function instance($data = null)
    {
        if ($data === null && isset(static::$globalInstance)) {
            return static::$globalInstance;
        }

        return new self($data);
    }
}
