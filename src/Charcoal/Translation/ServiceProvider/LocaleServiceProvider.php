<?php

namespace Charcoal\Translation\ServiceProvider;

use InvalidArgumentException;

// From 'pimple/pimple'
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Locale Service Provider
 *
 * Manages the locale of a Charcoal application.
 *
 * The service provider is based on {@author Fabien Potencier <fabien@symfony.com>}'s
 * work on the Silex micro-framework.
 *
 * Note: It is recommended that locales be specified in terms of an
 * {@link http://en.wikipedia.org/wiki/IETF_language_tag IETF language tag}.
 * For example, `en` stands for _English_, while `en-US` stands for _English (United States)_.
 *
 * ## Dependencies
 *
 * - `config` (optional): A base application configuration object.
 *
 * ## Parameters
 *
 * - `locale`: Specifies the language in which the application should display content to end users.
 *   The default value is `en` (English). You should configure this parameter if your application
 *   needs to display a different language or to support multiple languages.
 *   When a request is being handled, it is automatically set according to the `_locale` request attribute
 *   of the current route.
 * - `locale/source`: Specifies the language that the application code is written in.
 *   The default value is `en-US`, meaning English (United States). You should configure this parameter
 *   if the text content in your code is not in _English_.
 *
 * ## Services
 *
 * - n/a
 */
class LocaleServiceProvider implements ServiceProviderInterface
{
    /**
     * The default locale.
     *
     * @var string
     */
    const DEFAULT_LOCALE = 'en-US';

    /**
     * Registers services and parameters on the given container.
     *
     * @param  Container $container The container instance.
     * @throws RuntimeException If no active languages are provided or translations are not valid.
     * @return void
     */
    public function register(Container $container)
    {
        $defaultLocale = static::DEFAULT_LOCALE;

        /**
         * The language in which the application should display content to end users.
         *
         * @param  Container $container A container instance.
         * @return string
         */
        $container['locale'] = function (Container $container) use ($defaultLocale) {
            $config = $container['config'];

            $locale = $config->get('locale');
            if ($locale) {
                if (isset($locale['target'])) {
                    return $locale['target'];
                }

                if (isset($locale['current'])) {
                    return $locale['current'];
                }

                if (!is_array($locale)) {
                    return $locale;
                }
            }

            return $defaultLocale;
        };

        /**
         * The language that the application code is written in.
         *
         * @param  Container $container A container instance.
         * @return string
         */
        $container['locale/source'] = function (Container $container) use ($defaultLocale) {
            $config = $container['config'];

            $locale = $config->get('locale.source');
            if ($locale) {
                return $locale;
            }

            $locale = $config->get('source_locale');
            if ($locale) {
                return $locale;
            }

            return $defaultLocale;
        };

        /**
         * The language that the application code is written in.
         *
         * @param  Container $container A container instance.
         * @return string
         */
        $container['locale/available-languages'] = function (Container $container) use ($defaultLocale) {
            $config = $container['config'];

            $languages = $config->get('locale.languages');
            if (is_array($languages)) {
                return $languages;
            }

            $languages = $config->get('languages');
            if (is_array($languages)) {
                return $languages;
            }

            $languages = [
                'en' => [
                    'ident'  => 'en',
                    'locale' => $defaultLocale,
                    'label'  =>
                ]
            ];

            return new ArrayIterator($languages);
        };
    }
}
