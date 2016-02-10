Charcoal Translation
====================

Integrates the [Symfony Translation](https://symfony.com/doc/current/components/translation/) component to provide internationalization tools for Charcoal.

## Introducton

The Charcoal Translation package is inspired by Silex's [Locale](http://silex.sensiolabs.org/doc/master/providers/locale.html) and [Translation](http://silex.sensiolabs.org/doc/master/providers/translation.html) service providers.

#### What is localization?

Localization (often abbreviated [**l10n**](https://en.wikipedia.org/wiki/Internationalization_and_localization)) is the process of translating an internationalized (**i18n**) package.

## Installation

### Requirements

You need [PHP 5.5+](http://php.net) and the [Multibyte String](http://php.net/manual/en/book.mbstring.php) extension.

### With Composer

The Translation component is available on [Packagist](https://packagist.org/packages/locomotivemtl/charcoal-translation).

```shell
★ composer require locomotivemtl/charcoal-translation
```

### Without Composer

Requires a [PSR-4](http://www.php-fig.org/psr/psr-4/) compliant autoloader.

## Basic Usage

The Translation component provides two service providers:

-   `TranslationServiceProvider` — Provides the translation service.
-   `LocaleServiceProvider` — Manages the locale(s) of a Charcoal application.

### Translating Text

To translate a message, the Translator uses a catalog of translated messages loaded from translation resources defined in your application's settings.

If the translation is located in the catalog, the translation is returned. If the specified translation does not exist—or the domain isn't loaded—the original text is returned.

#### With PHP

You may retrieve translations from localization files using the Translator's `trans()` or `transChoice()` methods.

```
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\ArrayLoader;

$translator = new Translator('fr');
$translator->addLoader('array', new ArrayLoader());
$translator->addResource('array', [
    'Hello, World!' => 'Bonjour le monde!',
], 'fr');

echo $translator->trans('Hello, World!');
```

Which outputs "_Bonjour le monde!_"

#### With Mustache

If you are using the [Mustache templating engine](https://mustache.github.io/), you may use the `{{ _t }}` tag to echo the translation:

```
{{# _t }}The quick brown fox jumps over the lazy dog{{/ _t }}
```

Which outputs "_Le vif renard brun saute par-dessus le chien paresseux_"

### Creating Translations

Translation strings are stored in files within the `translations` directory. It is recommended that the directory structure match your application's PHP namespacing (as does object metadata).

```
/translations
    /foobar
        /en
            messages.php
        /fr
            messages.php
```

_TBD_

## Overriding Vendor Translations

Some packages might ship with their own localization files. You can override them by placing your own files within your _translations_ directory mapping the package's namespace.

```
/translations/charcoal/admin/fr/messages.csv
```

The file should only define the translations you wish to override. Any translations that you don't override will still be loaded from the package's original translations file.

## Development

To install the development environment:

```shell
★ composer install --prefer-source
```

Run the code checkers and unit tests with:

```shell
★ composer test
```

### API Documentation

-   The auto-generated `phpDocumentor` API documentation is available at [https://locomotivemtl.github.io/charcoal-translation/docs/master/](https://locomotivemtl.github.io/charcoal-translation/docs/master/)
-   The auto-generated `apigen` API documentation is available at [https://locomotivemtl.github.io/charcoal-translation/apigen/master/](https://locomotivemtl.github.io/charcoal-translation/apigen/master/)

### Development Dependencies

-   `phpunit/phpunit`
-   `squizlabs/php_codesniffer`
-   `satooshi/php-coveralls`

### Continuous Integration

| Service                    | Badge                                             | Description                                                              |
| -------------------------- | ------------------------------------------------- | ------------------------------------------------------------------------ |
| [Travis][travis]           | [![Build Status][travis-badge]][travis]           | Runs code sniff check and unit tests. Auto-generates API documentaation. |
| [Scrutinizer][scrutinizer] | [![Code Quality][scrutinizer-badge]][scrutinizer] | Code quality checker. Also validates API documentation quality.          |
| [Coveralls][coveralls]     | [![Coverage Status][coveralls-badge]][coveralls]  | Unit Tests code coverage.                                                |
| [Sensiolabs][sensiolabs]   | [![Insight][sensiolabs-badge]][sensiolabs]        | Another code quality checker, focused on PHP.                            |

### Coding Style

The Charcoal-App module follows the Charcoal coding-style:

-   [_PSR-1_](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md)
-   [_PSR-2_](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)
-   [_PSR-4_](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md), autoloading is therefore provided by _Composer_.
-   [_phpDocumentor_](http://phpdoc.org/) comments.
-   Read the [phpcs.xml](phpcs.xml) file for all the details on code style.

> Coding style validation / enforcement can be performed with `composer phpcs`. An auto-fixer is also available with `composer phpcbf`.

[travis]: https://travis-ci.org/locomotivemtl/charcoal-translation
[scrutinizer]: https://scrutinizer-ci.com/g/locomotivemtl/charcoal-translation
[coveralls]: https://coveralls.io/github/locomotivemtl/charcoal-translation
[sensiolabs]: https://insight.sensiolabs.com/projects/533b5796-7e69-42a7-a046-71342146308a

[travis-badge]: https://travis-ci.org/locomotivemtl/charcoal-translation.svg?branch=master
[scrutinizer-badge]: https://scrutinizer-ci.com/g/locomotivemtl/charcoal-translation/badges/quality-score.png?b=master
[coveralls-badge]: https://coveralls.io/repos/github/locomotivemtl/charcoal-translation/badge.svg?branch=master
[sensiolabs-badge]: https://insight.sensiolabs.com/projects/533b5796-7e69-42a7-a046-71342146308a/mini.png
