<?php

namespace Charcoal\Tests\Translation\ServiceProvider;

use PHPUnit_Framework_TestCase;

use Pimple\Container;

// Local Dependencies
use Charcoal\Translator\ServiceProvider\TranslatorServiceProvider;
use Charcoal\Translator\LanguageManager;
use Charcoal\Translator\Translator;

/**
 *
 */
class TranslatorServiceProviderTest extends PHPUnit_Framework_TestCase
{
    private $obj;
    private $container;

    public function setUp()
    {
        $this->obj = new TranslatorServiceProvider();
        $this->container = new Container();
        $this->container['config'] = [
            'locales'=> [
                'languages' => [
                    'foo' => ['locale'=>'foo-FOO'],
                    'bar' => ['locale'=>'bar-BAR']
                ],
                'default_language'=>'foo',
                'fallback_languages'=>['foo']
            ],
            'translations' => [
                'paths' => []
            ]
        ];

        $this->container->register($this->obj);
    }

    public function testKeys()
    {
        $this->assertFalse(isset($this->container['foofoobarbarbaz']));
        $this->assertTrue(isset($this->container['languages']));
        $this->assertTrue(isset($this->container['language/config']));
        $this->assertTrue(isset($this->container['language/default']));
        $this->assertTrue(isset($this->container['language/browser']));
    }

    public function testLanguages()
    {
        $languages = $this->container['languages'];
        $this->assertArrayHasKey('foo', $languages);
    }

    public function testDefaultLanguage()
    {
        $defaultLanguage = $this->container['language/default'];
        $this->assertEquals('foo', $defaultLanguage);
    }

    public function testBrowserLanguageIsNullWithoutHttp()
    {
        $browserLanguage = $this->container['language/browser'];
        $this->assertNull($browserLanguage);
    }

    public function testBrowserLanguage()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'bar';
        $browserLanguage = $this->container['language/browser'];
        $this->assertEquals('bar', $browserLanguage);
    }

    public function testBrowserLanguageIsNullIfInvalidHttp()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'baz';
        $browserLanguage = $this->container['language/browser'];
        $this->assertNull($browserLanguage);
    }

    public function testFallbackLanguages()
    {
        $fallbackLanguages = $this->container['language/fallbacks'];
        $this->assertEquals(['foo'], $fallbackLanguages);
    }

    public function testLanguageManager()
    {
        $manager = $this->container['language/manager'];
        $this->assertInstanceOf(LanguageManager::class, $manager);
    }

    public function testTranslator()
    {
        $translator = $this->container['translator'];
        $this->assertInstanceOf(Translator::class, $translator);
    }
}
