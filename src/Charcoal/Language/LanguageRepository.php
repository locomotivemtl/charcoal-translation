<?php

namespace Charcoal\Language;

use \InvalidArgumentException;

// Dependency from 'Pimple'
use \Pimple\Container;

// Dependency from 'charcoal-core'
use \Charcoal\Loader\FileLoader;

// Local dependencies
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
     * @var PoolInterface
     */
    private $cache;

    /**
     * A model to apply language metadata onto.
     *
     * @var LanguageInterface
     */
    protected $source;

    /**
     * Inject dependencies from a Pimple Container.
     *
     * @param  Container $container A dependencies container instance.
     * @return self
     */
    public function setDependencies(Container $container)
    {
        $this->setBasePath($container['config']['base_path']);

        $this->cache = $container['cache'];

        return $this;
    }

    /**
     * Set the loader's identifier.
     *
     * @param  mixed $ident A subset of language identifiers.
     * @throws InvalidArgumentException If the ident is invalid.
     * @return self
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
     * @return self
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

        if ($this->cache) {
            $item = $this->cache->getItem('languages/index/'.$ident);

            $index = $item->get();
            if ($item->isMiss()) {
                $item->lock();

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

                $item->set($index);

                $this->cache->save($item);
            }
        } else {
            $index = $this->loadFromRepositories();
        }

        return $index;
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
