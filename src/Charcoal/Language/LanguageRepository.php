<?php

namespace Charcoal\Language;

use \InvalidArgumentException;

// Dependency from 'Pimple'
use \Pimple\Container;

// Local dependencies
use \Charcoal\Language\Language;
use \Charcoal\Language\LanguageInterface;
use \Charcoal\Translation\Catalog\FileLoader;

/**
 * Load language metadata from JSON file(s).
 *
 * @todo Implement {@see \Charcoal\Loader\FileLoader} as a trait; mixed with {@see \Charcoal\View\AbstractLoader}.
 * @todo Overhaul class to extend {@see \Charcoal\Factory\AbstractFactory}.
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
        $this->cache = $container['cache'];

        return $this;
    }

    /**
     * Set the loader's identifier.
     *
     * @param  mixed $ident A subset of language identifiers.
     * @throws InvalidArgumentException If the ident is invalid.
     * @return FileLoader Chainable
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

        if (!is_string($ident)) {
            throw new InvalidArgumentException(
                sprintf(
                    '%1$s::%2$s() — Identifier must be a string.',
                    __CLASS__,
                    __FUNCTION__
                )
            );
        }

        $this->ident = $ident;

        return $this;
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
     * @param  LanguageInterface $source
     * @return self
     */
    public function setSource($source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Alias of {@see FileLoader::searchPath()}
     *
     * @return string[]
     */
    public function paths()
    {
        return $this->searchPath();
    }

    /**
     * Assign a list of paths.
     *
     * @param  string[] $paths The list of paths to add.
     * @return self
     */
    public function setPaths(array $paths)
    {
        $this->searchPath = [];
        $this->addPaths($paths);

        return $this;
    }

    /**
     * Append a list of paths.
     *
     * @param  string[] $paths The list of paths to add.
     * @return self
     */
    public function addPaths(array $paths)
    {
        foreach ($paths as $path) {
            $this->addPath($path);
        }

        return $this;
    }

    /**
     * Append a path.
     *
     * @param  string $path A file or directory path.
     * @throws InvalidArgumentException if the path does not exist or is invalid
     * @return \Charcoal\Service\Loader\Metadata (Chainable)
     */
    public function addPath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException(
                'Path should be a string.'
            );
        }

        if (!file_exists($path)) {
            throw new InvalidArgumentException(
                sprintf('Path does not exist: "%s"', $path)
            );
        }

        $this->searchPath[] = $path;

        return $this;
    }

    /**
     * Create one or more new instances of LanguageInterface.
     *
     * @param  array $langs A list of language identifiers to create.
     * @return LanguageInterface[]
     * @todo   Implement Factory
     */
    public function make(array $langs)
    {
        if (!count($langs)) {
            throw new InvalidArgumentException('Must be passed at least one language code.');
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
            $item = $this->cache->getItem('languages', 'index', $ident);

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
     * @todo   Add support for directories.
     */
    public function loadFromRepositories()
    {
        $searchPaths = $this->paths();
        if (empty($searchPaths)) {
            return null;
        }

        $languages = [];
        foreach ($searchPaths as $path) {
            /*if (is_dir($path)) {
                $dir  = rtrim($path, '/');
                $path = $dir.'/'.$lang;
            }*/

            $data = $this->loadRepository($path);

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

    /**
     * Load the contents of the provided JSON file.
     *
     * @param  mixed $filename The file path to retrieve.
     * @throws InvalidArgumentException If a JSON decoding error occurs.
     * @return array|null
     */
    private function loadRepository($filename)
    {
        $content = file_get_contents($filename);

        if ($content === null) {
            return null;
        }

        $data  = json_decode($content, true);
        $error = json_last_error();

        if ($error == JSON_ERROR_NONE) {
            return $data;
        }

        switch ($error) {
            case JSON_ERROR_NONE:
                break;
            case JSON_ERROR_DEPTH:
                $issue = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $issue = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $issue = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $issue = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $issue = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $issue = 'Unknown error';
                break;
        }

        throw new InvalidArgumentException(
            sprintf('JSON %s could not be parsed: "%s"', $filename, $issue)
        );
    }
}
