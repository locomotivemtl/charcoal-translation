<?php

namespace Charcoal\Translation\ServiceProvider;

use LogicException;
use ReflectionClass;

// From 'pimple/pimple'
use Pimple\Container;
use Pimple\ServiceProviderInterface;

// From 'symfony/translation'
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader;

// From 'charcoal/translation'
use LocaleServiceProvider;
use Charcoal\Translation\Config\TranslatorConfig;

/**
 * Translation Service Provider
 *
 * Provides a service for translating your application into different languages.
 *
 * The service provider is based on {@author Fabien Potencier <fabien@symfony.com>}'s
 * work on the Silex micro-framework.
 *
 * ## Dependencies
 *
 * - `config` (optional): A base application configuration object.
 *
 * ## Parameters
 *
 * - `translator/domains` (optional): A mapping of domains, locales, and messages.
 *   This parameter contains the translation data for all languages and domains.
 * - `locale` (optional): The active language for the translator. This should be
 *   set based on some request parameter. Defaults to `en` (English).
 * - `locale/fallbacks` (optional): Fallback languages for the translator.
 *   Used when the current language is not available, cannot be determined,
 *   or has no messages set. Defaults to `en` (English).
 *
 * ## Services
 *
 * - `translator`: An instance of
 *   {@link http://api.symfony.com/master/Symfony/Component/Translation/Translator.html `Translator`},
 *   that is used for translation.
 * - `translator/message-selector`: An instance of
 *   {@link http://api.symfony.com/master/Symfony/Component/Translation/MessageSelector.html `MessageSelector`}.
 */
class TranslationServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services and parameters on the given container.
     *
     * @todo [mcaskill 2016-06-01] Should we add a 'translator/available-loaders' list?
     *       In the style of {@see \Charcoal\App\ServiceProvider\CacheServiceProvider}.
     * @todo [mcaskill 2016-06-01] Should we implement a Validation service (à la Symfony Validation)?
     * @todo [mcaskill 2016-06-01] Move vendor resource registration to their respective service providers.
     *
     * @param  Container $container The container instance.
     * @throws RuntimeException If no active languages are provided or translations are not valid.
     * @return void
     */
    public function register(Container $container)
    {
        /**
         * @param Container $container A container instance.
         * @return CacheConfig
         */
        $container['translation/config'] = function (Container $container) {
            $appConfig = $container['config'];

            return new TranslationConfig($appConfig->get('translation'));
        };

        $container['translator'] = function (Container $container) {
            if (!isset($container['locale'])) {
                throw new LogicException(
                    sprintf(
                        'To use the %1$s, you must define a \'locale\' parameter on your DI container. '.
                        'This should be set using middleware or a service provider (e.g., %2$s).',
                        'TranslationServiceProvider',
                        LocaleServiceProvider::class
                    )
                );
            }

            $translator = new Translator(
                $container['locale'],
                $container['translator/message-selector'],
                $container['translator/cache-dir'],
                $container['debug']
            );

            if (isset($container['locale/fallbacks'])) {
                $translator->setFallbackLocales($container['locale/fallbacks']);
            }

            /** Register default loaders */
            foreach ($container['translator/loaders'] as $loaderIdent => $loader) {
                $translator->addLoader($loaderIdent, $loader);
            }

            /*
            $translator->addLoader('array', new ArrayLoader());
            $translator->addLoader('csv', new CsvFileLoader());
            $translator->addLoader('xliff', new XliffFileLoader());
            */

            /** Register vendor resources */
            /*
            if (isset($container['validator'])) {
                $r    = new ReflectionClass('Symfony\Component\Validator\Validation');
                $dir  = rtrim(realpath(dirname($r->getFilename()).'/../../../../'), '/');
                $dir .= '/translations/symfony/validator';
                $file = "{$dir}/validators.{$container['locale']}.xlf";
                if (file_exists($file)) {
                    $translator->addResource('xliff', $file, $container['locale'], 'validators');
                }
            }
            */

            /** From 'charcoal/ui' */
            if (isset($container['form/factory'])) {
                $r    = new ReflectionClass('Charcoal\Ui\ServiceProvider\FormServiceProvider');
                $dir  = rtrim(realpath(dirname($r->getFilename()).'/../../../../'), '/');
                $dir .= '/translations/charcoal/ui';
                $file = "{$dir}/validators.{$container['locale']}.xlf";
                if (file_exists($file)) {
                    $translator->addResource('xliff', $file, $container['locale'], 'validators');
                }
            }

            /** Register default resources */
            foreach ($container['translator/resources'] as $resource) {
                $translator->addResource($resource[0], $resource[1], $resource[2], $resource[3]);
            }

            foreach ($container['translator/domains'] as $domain => $data) {
                foreach ($data as $locale => $messages) {
                    $translator->addResource('array', $messages, $locale, $domain);
                }
            }

            return $translator;
        };

        $container['translator/message-selector'] = function () {
            return new MessageSelector();
        };

        $container['translator/resources'] = $container->protect(function (Container $container) {
            return [];
        });

        $container['translator/domains']   = [];
        $container['translator/cache-dir'] = null;
    }
}
