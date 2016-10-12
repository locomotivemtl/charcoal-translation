<?php

namespace Charcoal\Translation\Catalog;

use \RuntimeException;
use \InvalidArgumentException;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \RecursiveCallbackFilterIterator;
use \SplFileInfo;
use \Traversable;

// Dependency from 'Pimple'
use \Pimple\Container;

// Dependency from 'charcoal-core'
use \Charcoal\Loader\FileLoader;

// Local dependencies
use \Charcoal\Translation\Catalog\Catalog;
use \Charcoal\Translation\Catalog\CatalogInterface;
use \Charcoal\Translation\Catalog\Resource;
use \Charcoal\Translation\Catalog\ResourceInterface;

/**
 * Load translations from files.
 */
class ResourceRepository extends FileLoader
{
    /**
     * @const Matches an IETF language tag composed of two subtags:
     *        • 2 or 3-letter language (ISO 639-1, ISO 639-2, and ISO 639-3)
     *        • 2-letter or 3-digit country subtag (ISO 3166-1 or UN M.49)
     */
    # PHP 5.6 -- const RFC5646 = self::ISO639 . '(?:[-_](?:' . self::ISO3166_1 . '|' . self::UNM49 . '))?';
    const RFC5646 = '(?<language>[a-z]{2,3})(?:[-_](?<country>[A-Z]{2}|[0-9]{3}))?';

    /**
     * The $paths resolved to files.
     *
     * @var array[]
     */
    protected $resolvedPaths = [];

    /**
     * Store reference to cache from container.
     *
     * @var PoolInterface
     */
    protected $cache;

    /**
     * Store reference to translation catalog.
     *
     * @var CatalogInterface
     */
    protected $catalog;

    /**
     * The languages to import.
     *
     * @var array
     */
    protected $languages = [];

    /**
     * A list of available file formats.
     *
     * @var array
     */
    protected $supportedFormats = [ 'ini', 'csv', 'json', 'php', 'yaml', 'yml' ];

    /**
     * A list of available file formats.
     *
     * @var array
     */
    protected $supportedFormatsRegex = '!^.+\.(ini|csv|json|php|yaml|yml)$!i';

    /**
     * The class name of the translation catalog model.
     *
     * Must be a fully-qualified PHP namespace and an implementation of
     * {@see \Charcoal\Translation\Catalog\CatalogInterface}.
     *
     * @var string
     */
    private $catalogClass = Catalog::class;

    /**
     * The class name of the translation resource model.
     *
     * Must be a fully-qualified PHP namespace and an implementation of
     * {@see \Charcoal\Translation\Catalog\ResourceInterface}.
     *
     * @var string
     */
    private $resourceClass = Resource::class;

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
     * Retrieve the loader's identifier.
     *
     * @return string
     */
    public function ident()
    {
        if ($this->ident === null) {
            $this->setIdent($this->languages());
        }

        return $this->ident;
    }

    /**
     * Load the languages from JSON files.
     *
     * @param  mixed $ident Optional, set the ident to load.
     * @return ResourceInterface[]
     */
    public function load($ident = null)
    {
        if ($ident !== null) {
            $this->setIdent($ident);
        }

        $ident = $this->ident();
        $messages = [];

        if ($this->cache) {
            $item = $this->cache->getItem('translations/resources/'.$ident);

            $messages = $item->get();
            if ($item->isMiss()) {
                $item->lock();

                $messages = $this->loadFromRepositories();

                $item->set($messages);
                $this->cache->save($item);
            }
        } else {
            $messages = $this->loadFromRepositories();
        }

        return $messages;
    }


    /**
     * Load the translation files as resources.
     *
     * @return ResourceInterface[]
     */
    public function loadFromRepositories()
    {
        $files = $this->paths();

        if (empty($files)) {
            return null;
        }

        $pattern   = '!(?|\/'.self::RFC5646.'\/|\.'.self::RFC5646.'\.)!';
        $basePath  = $this->basePath();
        $languages = $this->languages();
        $resources = [];
        foreach ($files as $file) {
            $domain   = str_replace($basePath, '', $file);
            $langCode = null;
            if (preg_match($pattern, $domain, $matches)) {
                $langCode = trim($matches[0], '/\\.');
                if (!in_array($matches['language'], $languages)) {
                    continue;
                }
            }

            $resource = $this->createTranslationResource();
            $resource->addFile($file);

            if (count($resource->keys())) {
                $resources[] = $resource->setSourceLanguage($langCode);
            }
        }

        return $resources;
    }

    /**
     * Append a path.
     *
     * @param  string $path A file or directory path.
     * @throws InvalidArgumentException If the path does not exist or is invalid.
     * @return self
     */
    public function addPath($path)
    {
        $path = $this->resolvePath($path);

        if (is_array($path)) {
            $this->paths = array_merge($this->paths, $path);
        } elseif ($path && $this->validatePath($path)) {
            $this->paths[] = $path;
        }

        return $this;
    }

    /**
     * Prepend a path.
     *
     * @param  string $path A file or directory path.
     * @return self
     */
    public function prependPath($path)
    {
        $path = $this->resolvePath($path);

        if (is_array($path)) {
            $this->paths = array_merge($path, $this->paths);
        } elseif ($path && $this->validatePath($path)) {
            array_unshift($this->paths, $path);
        }

        return $this;
    }

    /**
     * Parse a relative path using the base path if needed.
     *
     * @param  string $path The path to resolve.
     * @return string|array|boolean
     */
    public function resolvePath($path)
    {
        $path = parent::resolvePath($path);

        if (is_file($path)) {
            return $path;
        }

        /** If not a file, it's most likely a directory */
        return $this->expandPath($path);
    }

    /**
     * Recursively retrieve the files from the given directory.
     *
     * @todo   Add maximum depth property.
     * @param  string $path The path of a directory.
     * @return array|boolean
     */
    public function expandPath($path)
    {
        if (!is_dir($path)) {
            return false;
        }

        $directory = new RecursiveDirectoryIterator($path);
        $filter    = new RecursiveCallbackFilterIterator(
            $directory,
            function ($current, $key, $iterator) {
                $filename = $current->getFilename();
                if (preg_match('!^(\.\w+|\.$|\.\.$)!i', $filename)) {
                    return false;
                }

                if ($current->isDir()) {
                    return true;
                }

                return preg_match($this->supportedFormatsRegex, $filename);
            }
        );
        $iterator = new RecursiveIteratorIterator($filter);

        $files = [];
        foreach ($iterator as $file) {
            $files[] = strval($file);
        }

        return $files;
    }

    /**
     * Validate a resolved path.
     *
     * @param  string $path The path to validate.
     * @return boolean Returns TRUE if the path is valid otherwise FALSE.
     */
    public function validatePath($path)
    {
        return preg_match($this->supportedFormatsRegex, $path) && file_exists($path);
    }

    /**
     * Set the languages to import.
     *
     * @param  array|Traversable $languages The languages to import.
     * @return self
     */
    public function setLanguages($languages)
    {
        $this->languages = $languages;

        return $this;
    }

    /**
     * Retrieve the languages to import.
     *
     * @return array
     */
    public function languages()
    {
        return $this->languages;
    }

    /**
     * Set an translation catalog.
     *
     * @param  CatalogInterface $catalog The catalog, to store translations.
     * @return self
     */
    public function setCatalog(CatalogInterface $catalog)
    {
        $this->catalog = $catalog;

        return $this;
    }

    /**
     * Retrieve the translation catalog.
     *
     * @return CatalogInterface
     */
    public function catalog()
    {
        if (!isset($this->catalog)) {
            $this->setCatalog($this->createTranslationCatalog());
        }

        return $this->catalog;
    }

    /**
     * Create a catalog object.
     *
     * @throws RuntimeException If the catalog class is invalid.
     * @return CatalogInterface
     */
    protected function createTranslationCatalog()
    {
        $catalogClass = $this->catalogClass();
        $catalog   = new $catalogClass;

        if (!$catalog instanceof CatalogInterface) {
            throw new RuntimeException(
                sprintf(
                    'Catalog [%s] must implement CatalogInterface.',
                    $catalogClass
                )
            );
        }

        return $catalog;
    }

    /**
     * Set the class name of the object catalog model.
     *
     * @param  string $className The class name of the object catalog model.
     * @throws InvalidArgumentException If the class name is not a string.
     * @return AbstractPropertyDisplay Chainable
     */
    public function setCatalogClass($className)
    {
        if (!is_string($className)) {
            throw new InvalidArgumentException(
                'Translation catalog class name must be a string.'
            );
        }

        $this->catalogClass = $className;

        return $this;
    }

    /**
     * Retrieve the class name of the object catalog model.
     *
     * @return string
     */
    public function catalogClass()
    {
        return $this->catalogClass;
    }

    /**
     * Create a resource object.
     *
     * @throws RuntimeException If the resource class is invalid.
     * @return ResourceInterface
     */
    public function createTranslationResource()
    {
        $resourceClass = $this->resourceClass();
        $resource   = new $resourceClass;

        if (!$resource instanceof ResourceInterface) {
            throw new RuntimeException(
                sprintf(
                    'Resource [%s] must implement ResourceInterface.',
                    $resourceClass
                )
            );
        }

        return $resource;
    }

    /**
     * Set the class name of the object resource model.
     *
     * @param  string $className The class name of the object resource model.
     * @throws InvalidArgumentException If the class name is not a string.
     * @return AbstractPropertyDisplay Chainable
     */
    public function setResourceClass($className)
    {
        if (!is_string($className)) {
            throw new InvalidArgumentException(
                'Translation resource class name must be a string.'
            );
        }

        $this->resourceClass = $className;

        return $this;
    }

    /**
     * Retrieve the class name of the object resource model.
     *
     * @return string
     */
    public function resourceClass()
    {
        return $this->resourceClass;
    }
}
