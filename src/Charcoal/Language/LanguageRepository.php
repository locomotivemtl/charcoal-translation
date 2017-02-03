<?php

namespace Charcoal\Language;

use \RuntimeException;
use \InvalidArgumentException;

// From Pimple
use \Pimple\Container;

// From PSR-6
use \Psr\Cache\CacheItemPoolInterface;

// From 'charcoal-core'
use \Charcoal\Loader\FileLoader;

// From 'charcoal-translation'
use \Charcoal\Language\Language;
use \Charcoal\Language\LanguageInterface;

/**
 * Load language metadata from JSON file(s)
 */
class LanguageRepository extends FileLoader
{
    /**
     * Store reference to cache from container.
     *
     * @var CacheItemPoolInterface
     */
    protected $cachePool;

    /**
     * A model to apply language metadata onto.
     *
     * @var LanguageInterface
     */
    protected $source;

    /**
     * Return new LanguageRepository.
     *
     * @param array $data The repository's dependencies.
     */
    public function __construct(array $data = null)
    {
        parent::__construct($data);

        $this->setCachePool($data['cache']);
    }

    /**
     * Set the cache service.
     *
     * @param  CacheItemPoolInterface $cache A PSR-6 compliant cache pool instance.
     * @return LanguageRepository Chainable
     */
    private function setCachePool(CacheItemPoolInterface $cache)
    {
        $this->cachePool = $cache;

        return $this;
    }

    /**
     * Retrieve the cache service.
     *
     * @throws RuntimeException If the cache service was not previously set.
     * @return CacheItemPoolInterface
     */
    private function cachePool()
    {
        if (!isset($this->cachePool)) {
            throw new RuntimeException(sprintf(
                'Cache Pool is not defined for "%s"',
                get_class($this)
            ));
        }

        return $this->cachePool;
    }

    /**
     * Set the loader's identifier.
     *
     * @param  mixed $ident A subset of language identifiers.
     * @throws InvalidArgumentException If the ident is invalid.
     * @return LanguageRepository Chainable
     */
    public function setIdent($ident)
    {
        if (is_array($ident)) {
            if (count($ident)) {
                sort($ident);
                $ident = implode(',', $ident);
            } else {
                $ident = 'all';
            }
        }

        return parent::setIdent($ident);
    }

    /**
     * Retrieve the LanguageInterface model.
     *
     * @return LanguageInterface
     */
    public function source()
    {
        if (!$this->source) {
            $this->source = Language::class;
        }

        return $this->source;
    }

    /**
     * Assign the LanguageInterface model.
     *
     * @param  LanguageInterface $source Repository source.
     * @return LanguageRepository Chainable
     */
    public function setSource(LanguageInterface $source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Create one or more new instances of LanguageInterface.
     *
     * @param  array $langs A list of language identifiers to create.
     * @throws InvalidArgumentException If no languages are passed.
     * @return LanguageInterface[]
     * @todo   Implement Factory
     */
    public function make(array $langs)
    {
        if (!count($langs)) {
            throw new InvalidArgumentException(
                'Must be passed at least one language code.'
            );
        }

        $this->setIdent($langs);

        $index = $this->load();

        return [];
    }

    /**
     * Load the languages from JSON files.
     *
     * @param  mixed $ident Optional, set the ident to load.
     * @return array
     */
    public function load($ident = null)
    {
        if ($ident !== null) {
            $this->setIdent($ident);
        }

        $ident = $this->ident();
        $index = [];

        $cacheKey  = str_replace('/', '.', 'languages/index/'.$ident);
        $cacheItem = $this->cachePool()->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            $cacheItem->lock();

            $index = $this->loadFromRepositories();

            if ('all' !== $ident) {
                $languages = [];
                $subset    = explode(',', $ident);

                foreach ($subset as $langCode) {
                    if (isset($index[$langCode])) {
                        $languages[$langCode] = $index[$langCode];
                    }
                }

                $index = $languages;
            }

            $cacheItem->set($index);
            $this->cachePool()->save($cacheItem);

            return $index;
        }

        return $cacheItem->get();
    }

    /**
     * Load the metadata from JSON files.
     *
     * @return array
     * @todo   [mcaskill 2016-02-11] Add support for directories.
     */
    public function loadFromRepositories()
    {
        $paths = $this->paths();

        if (empty($paths)) {
            return null;
        }

        $languages = [];
        foreach ($paths as $path) {
            $data = $this->loadJsonFile($path);

            if (is_array($data)) {
                // Resolve any sub-lists
                if (isset($data['languages'])) {
                    $data = $data['languages'];
                } elseif (isset($data['data'])) {
                    $data = $data['data'];
                }

                $languages = array_replace_recursive($languages, $data);
            }
        }

        return $languages;
    }
}
