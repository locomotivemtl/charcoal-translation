<?php

namespace Charcoal\Tests\Translation;

use PHPUnit_Framework_TestCase;

use Symfony\Component\Translation\MessageSelector;

// Local Dependencies
use Charcoal\Translator\LanguageManager;
use Charcoal\Translator\Translation;
use Charcoal\Translator\Translator;

/**
 *
 */
class TranslatorTest extends PHPUnit_Framework_TestCase
{
    private $languageManager;
    private $obj;

    public function setUp()
    {
        $this->languageManager = new LanguageManager([
            'languages' => [
                'foo' => ['locale'=>'foo-FOO'],
                'bar' => ['locale'=>'bar-BAR']
            ],
            'default_language' => 'foo',
            'fallback_languages' => ['foo']
        ]);
        $this->obj = new Translator([
            'locale'            => 'foo',
            'message_selector'  => new MessageSelector(),
            'cache_dir'         => null,
            'debug'             => false,
            'language_manager'  => $this->languageManager
        ]);
    }

    public function testTranslation()
    {
        $ret = $this->obj->translation('foo');
        $this->assertInstanceOf(Translation::class, $ret);
        $this->assertEquals('foo', (string)$ret);
    }

    public function testSetLocaleSetLanguageManagerCurrentLanguage()
    {
        $this->obj->setLocale('bar');
        $this->assertEquals('bar', $this->languageManager->currentLanguage());
    }
}
