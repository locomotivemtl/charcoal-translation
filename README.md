Charcoal Translation
====================

[![Build Status][travis-badge]][travis]
[![Code Quality][scrutinizer-badge]][scrutinizer]
[![Coverage Status][coveralls-badge]][coveralls]
[![Insight][sensiolabs-badge]][sensiolabs]
[![License][pugx-license]][packagist]

The Charcoal Translation component provides internationalization tools for your application.

---

**Internationalization** (abbreviated as **i18n**) is the process of designing a software application so that it can potentially be adapted to various languages and regions without engineering changes. **Localization** (abbreviated as **l10n**) is the process of adapting internationalized software for a specific region or language by adding locale-specific components and translating text. Charcoal features support for message, view, and route translations as well as date and number formatting.

## Table of Contents

-   [Installation](#installation)
    -   [Requirements](#requirements)
    -   [Via Composer](#with-composer)
    -   [Without Composer](#without-composer)
    -   [Registering](#registering)
-   [Basic Usage](#basic-usage)
    -   [Locale and Language](#locale-and-language)
    -   [Message Translation](#message-translation)
    -   [Message Formatting](#message-formatting)
    -   [View Translation](#view-translation)
    -   [Route Translation](#route-translation)
    -   [Formatting Date and Number Values](#formatting-date-and-number)
-   [Acknowledgements](#acknowledgements)
-   [Contributing](#contributing)

## Installation

### Requirements

If you're using the Charcoal framework, consult the [core package][charcoal-core] for server requirements.  
As for the Translation component, your server simply needs to meet the following requirement:

-   [PHP 5.5+](https://php.net)

#### Suggested

-   [Internationalization][php-intl] PHP extension

### With Composer

The preferred way to install the Translation component is through [Composer](composer) (available on [Packagist][packagist]).


```shell
★ composer require locomotivemtl/charcoal-translation
```

### Without Composer

The Translation component requires a [PSR-4][psr-4] compliant autoloader and a _dependency container_ to prepare, manage, and provide its services (based on [Pimple][pimple]).

### Registering

The Translation component supplies two service providers:

-   `TranslationServiceProvider` — Provides the translation service.
-   `LocaleServiceProvider` — Manages the locale(s) of a Charcoal application.

#### With Charcoal's Application Configuration

**Method #1**

```json
"service_providers": {
    "charcoal/translation/service-provider/locale": {},
    "charcoal/translation/service-provider/translation": {
        "locale/fallbacks": [ "fr", "en" ]
    }
}
```

**Method #2**

```json
"locale": {
    "fallbacks": [ "fr", "en" ]
},

"service_providers": {
    "charcoal/translation/service-provider/locale": {},
    "charcoal/translation/service-provider/translation": {}
}
```

#### With PHP

```php
use Charcoal\Translation\ServiceProvider\LocaleServiceProvider;
use Charcoal\Translation\ServiceProvider\TranslationServiceProvider;

$container->register(new LocaleServiceProvider());
$container->register(new TranslationServiceProvider(), [
    'locale/fallbacks' => [ 'fr', 'en' ]
]);
```

## Basic Usage

### Locale and Language

A **locale** is a set of parameters that defines the user's language, region, and any special variant preferences that the user wants to see in their user interface. It is usually identified by an ID consisting of at least a language identifier and region identifier.

Examples of identifiers include:

-   `en-US` (English, United States)
-   `zh-Hant-TW` (Chinese, Traditional Script, Taiwan)
-   `fr-CA`, `fr-FR` (French for Canada and France respectively)

For consistency, all locale identifiers used in Charcoal applications should be canonicalized to the format of `ll-CC` (see [RFC 4646](http://www.faqs.org/rfcs/rfc4646)), where `ll` is a two- or three-letter lowercase language code according to [ISO-639](http://www.loc.gov/standards/iso639-2/) and `CC` is a two-letter country code according to [ISO-3166](http://www.iso.org/iso/en/prods-services/iso3166ma/02iso-3166-code-lists/list-en1.html). More details about locale can be found in the [documentation of the ICU project](http://userguide.icu-project.org/locale#TOC-The-Locale-Concept).

Unless otherwise noted, Charcoal is tolerant of both hyphens and underscores as ID delimiters.

Charcoal often uses the term "language" to refer to a locale.

A Charcoal application use two kinds of languages:

-   _Source Language_ — refers to the language in which the text messages in the source code are written (often called the "default language").
-   _Target Language_ — refers to the language that should be used to display content to end-users (often called the "current language").

The message translator mainly translates a text message from source language to target language.

You can configure the languages in the application configuration like so:

```
{
	"locale": "fr",
	"source_locale": "en"
}
```

```
{
    "locale": {
        // set target language to be Russian
        "current_language": "ru-RU",

        // set source language to be English
        "source_language": "en-US",
    }
}
```

The default value for the source language is `en-US`. It is recommended that you keep this default value unchanged, because it is usually much easier to find people who can translate from English to other languages than from non-English to non-English.

You often need to set the target language dynamically based on different factors, such as the language preference of end users. Instead of configuring it in the application configuration, you can use the following statement to change the language:

```
// change target language to Chinese
$container['current_locale'] = 'zh-CN';
$container['source_locale']  = 'en-CA';
```

> 👉 If your source language varies among different parts of your code, you can override the source language for different message sources, which are described in the next section.

### Message Translation

### Message Formatting

### View Translation

### Route Translation

### Formatting Date and Number Values

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

## Acknowledgements

Charcoal Translation uses the following open source software:

-   [Symfony Translation](https://symfony.com/doc/current/components/translation/)

Charcoal Translation is based on the following open source software:

-   [Silex Locale](http://silex.sensiolabs.org/doc/master/providers/locale.html)
-   [Silex Translation](http://silex.sensiolabs.org/doc/master/providers/translation.html) (integrates _Symfony Translation_)
-   [Yii Internationalization](http://www.yiiframework.com/doc-2.0/guide-tutorial-i18n.html)

Charcoal Translation's README and documentation also draws from the following sources:

-   "[Internationalization and localization](https://en.wikipedia.org/wiki/Internationalization_and_localization)" from _Wikipedia: The Free Encyclopedia_. Wikimedia Foundation, Inc. 13 Septembre 2016‎
-   "[Locale (computer software)](https://en.wikipedia.org/wiki/Locale_(computer_software))" from _Wikipedia: The Free Encyclopedia_. Wikimedia Foundation, Inc. 13 Septembre 2016‎
-   "[The Locale class][php-locale]" from _PHP Documentation_. The PHP Group. 13 Septembre 2016‎

## Contributing

### Guidelines

1.  Charcoal utilizes [_PSR-1_][psr-1], [_PSR-2_][psr-2], [_PSR-4_][psr-4], [_PSR-6_][psr-6], and [_PSR-7_][psr-7].
2.  Charcoal has a minimum PHP version requirement of _PHP 5.5_. Pull requests must not require a PHP version greater than PHP 5.5 unless the feature is only utilized conditionally.
3.  All pull requests must include unit tests to ensure the change works as expected and to prevent regressions.

    >   The test suite can be executed with `composer phpunit`.
4.  Source code must be described using _DocBlocks_.

    >   Travis generates an API reference with [phpDocumentor](https://locomotivemtl.github.io/charcoal-translation/docs/master/) and [ApiGen](https://locomotivemtl.github.io/charcoal-translation/apigen/master/).
5.  Source code must be consistent with Charcoal's [coding standard](phpcs.xml).

    >   Coding style validation/enforcement can be performed with `composer phpcs`.  
    >   An auto-fixer is also available with `composer phpcbf`.

[charcoal-core]:      https://github.com/locomotivemtl/charcoal-core

[php-intl]:           https://php.net/intl
[php-locale]:         https://php.net/locale
[composer]:           https://getcomposer.org/download/
[pimple]:             http://pimple.sensiolabs.org
[packagist]:          https://packagist.org/packages/locomotivemtl/charcoal-translation
[unicode-plurals]:    http://www.unicode.org/cldr/charts/latest/supplemental/language_plural_rules.html

[psr-1]:              https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[psr-2]:              https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[psr-4]:              https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md
[psr-6]:              https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-6-cache.md
[psr-7]:              https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md

[travis]:             https://travis-ci.org/locomotivemtl/charcoal-translation
[scrutinizer]:        https://scrutinizer-ci.com/g/locomotivemtl/charcoal-translation
[coveralls]:          https://coveralls.io/github/locomotivemtl/charcoal-translation
[sensiolabs]:         https://insight.sensiolabs.com/projects/533b5796-7e69-42a7-a046-71342146308a

[travis-badge]:       https://travis-ci.org/locomotivemtl/charcoal-translation.svg?branch=master
[scrutinizer-badge]:  https://scrutinizer-ci.com/g/locomotivemtl/charcoal-translation/badges/quality-score.png?b=master
[coveralls-badge]:    https://coveralls.io/repos/github/locomotivemtl/charcoal-translation/badge.svg?branch=master
[sensiolabs-badge]:   https://insight.sensiolabs.com/projects/533b5796-7e69-42a7-a046-71342146308a/mini.png
[pugx-license]:       https://poser.pugx.org/locomotivemtl/charcoal-translation/license.svg