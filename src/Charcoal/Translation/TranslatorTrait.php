<?php

namespace Charcoal\Translation;

// Dependency from Pimple
use Pimple\Container;

/**
 * Translation Helper Trait
 */
trait TranslatorTrait
{
    private $this->translator;

    /**
     * Inject dependencies from a Container.
     *
     * @param  Container $container A dependencies container instance.
     * @return self
     */
    public function setTranslatorTraitDependencies(Container $container)
    {
        $this->translator = $container['translator'];

        return $this;
    }

    /**
     * Alias for {@see self::trans()} and {@see self::transChoice()}.
     *
     * @param mixed  $... Parameters for {@see self::trans()} or {@see self::transChoice()}.
     *
     * @return string The translated string.
     */
    public function translate()
    {
        $args = func_get_args();
        $str  = [ 'locale', 'domain', 'id' ];

        $id = $number = $parameters = $domains = $locale = null;

        foreach ($args as $arg) {
            switch (gettype($arg)) {
                case 'integer':
                    if (isset($number)) {
                        throw new InvalidArgumentException('Only one integer parameter allowed: $number.');
                    } else {
                        $number = $arg;
                    }
                    break;

                case 'array':
                    if (isset($parameters)) {
                        throw new InvalidArgumentException('Only one array parameter allowed: $parameters.');
                    } else {
                        $parameters = $arg;
                    }
                    break;

                case 'string':
                    if (count($str)) {
                        $s = array_pop($str);
                        ${$s} = $arg;
                    } else {
                        throw new InvalidArgumentException('Only three string parameters allowed: $id, $domain, $locale.');
                    }
                    break;
            }
        }

        if (!isset($parameters)) {
            $parameters = [];
        }

        if (!isset($domain)) {
            $domain = 'messages';
        }

        if ( $number ) {
            return $this->transChoice($id, $number, $parameters, $domain, $locale);
        } else {
            return $this->trans($id, $parameters, $domain, $locale);
        }
    }

    /**
     * Translates the given message.
     *
     * @param string $id         The message key.
     * @param array  $parameters An array of parameters for the message.
     * @param string $domain     The domain for the message.
     * @param string $locale     The locale to translate to.
     *
     * @return string The translated string.
     */
    public function trans($id, array $parameters = [], $domain = 'messages', $locale = null)
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    /**
     * Translates the given choice message by choosing a translation according to a number.
     *
     * @param string $id         The message key.
     * @param int    $number     The number to use to find the indice of the message.
     * @param array  $parameters An array of parameters for the message.
     * @param string $domain     The domain for the message.
     * @param string $locale     The locale to translate to.
     *
     * @return string The translated string.
     */
    public function transChoice($id, $number, array $parameters = [], $domain = 'messages', $locale = null)
    {
        return $this->translator->transChoice($id, $number, $parameters, $domain, $locale);
    }
}
