<?php

namespace Charcoal\Tests\Translator;

use PHPUnit_Framework_TestCase;

// Local Dependencies
use Charcoal\Translator\LanguageManager;

/**
 *
 */
class LanguageManagerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Object under test.
     * @var LanguageConfig
     */
    private $obj;

    public function setUp()
    {
        $this->obj = new LanguageManager([
            'languages' => [
                'foo' => ['locale'=>'foo-FOO'],
                'bar' => ['locale'=>'bar-BAR']
            ],
            'default_language' => 'foo',
            'fallback_languages' => ['foo']
        ]);
    }

    public function testAvailableLanguages()
    {
        $this->assertEquals(['foo','bar'], $this->obj->availableLanguages());
    }

    public function testSetCurrentLanguage()
    {
        $this->assertEquals('foo', $this->obj->currentLanguage());

        $this->obj->setCurrentLanguage('bar');
        $this->assertEquals('bar', $this->obj->currentLanguage());

        $this->obj->setCurrentLanguage(null);
        $this->assertEquals('foo', $this->obj->currentLanguage());

        $this->setExpectedException('\InvalidArgumentException');
        $this->obj->setCurrentLanguage('foobazbar');

    }
}
