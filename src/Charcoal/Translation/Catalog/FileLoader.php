<?php

namespace Charcoal\Translation\Catalog;

use \InvalidArgumentException;

use \Psr\Log\LoggerAwareInterface;
use \Psr\Log\LoggerAwareTrait;

/**
 * Base file loader.
 */
class FileLoader implements
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * The base path to prepend to any relative paths to search in.
     *
     * @var string $basePath
     */
    private $basePath = '';

    /**
     * The paths to search in.
     *
     * @var string $paths
     */
    private $paths = [];

    /**
     * The loader's identifier (for caching found paths).
     *
     * @var string $ident
     */
    private $ident;

    /**
     * Default constructor, if none is provided by the concrete class implementations.
     *
     * ## Required dependencies
     * - `logger` A PSR-3 logger
     *
     * @param array $data The class dependencies map.
     */
    final public function __construct(array $data = null)
    {
        if (isset($data['logger'])) {
            $this->setLogger($data['logger']);
        }

        if (isset($data['base_path'])) {
            $this->setBasePath($data['base_path']);
        }

        if (isset($data['paths'])) {
            $this->setPaths($data['paths']);
        }
    }

    /**
     * Retrieve the loader's identifier.
     *
     * @return string
     */
    public function ident()
    {
        return $this->ident;
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
     * Retrieve the base path for relative search paths.
     *
     * @return string
     */
    public function basePath()
    {
        return $this->basePath;
    }

    /**
     * Assign a base path for relative search paths.
     *
     * @param  string $basePath The base path to use.
     * @throws InvalidArgumentException if the base path parameter is not a string.
     * @return self
     */
    public function setBasePath($basePath)
    {
        if (!is_string($basePath)) {
            throw new InvalidArgumentException(
                'Base path must be a string'
            );
        }

        $basePath = realpath($basePath);

        $this->basePath = rtrim($basePath, '/\\').DIRECTORY_SEPARATOR;

        return $this;
    }

    /**
     * Returns the content of the first file found in search path
     *
     * @param  string|null $ident
     * @return string File content
     */
    public function load($ident = null)
    {
        if ($ident === null) {
            return '';
        }

        $filename = $this->firstMatchingFilename($ident);
        if ($filename) {
            $file_content = file_get_contents($filename);
            $this->set_content($file_content);
            return $file_content;
        }

        return '';
    }

    /**
     * @param string $filename
     * @return string|null The file content, or null if no file found.
     */
    protected function loadFirstFromSearchPath($filename)
    {
        $paths = $this->paths();
        if (empty($paths)) {
            return null;
        }
        foreach ($paths as $path) {
            $f = $path.DIRECTORY_SEPARATOR.$filename;
            if (file_exists($f)) {
                $fileContent = file_get_contents($f);
                return $fileContent;
            }
        }

        return null;
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function firstMatchingFilename($filename)
    {
        if (file_exists($filename)) {
            return $filename;
        }
        $paths = $this->paths();
        if (empty($paths)) {
            return null;
        }
        foreach ($paths as $path) {
            $f = $path.DIRECTORY_SEPARATOR.$filename;
            if (file_exists($f)) {
                return $f;
            }
        }

        return null;
    }

    /**
     * @param string $filename
     * @return array
     */
    protected function allMatchingFilenames($filename)
    {
        $ret = [];
        if (file_exists($filename)) {
            $ret[] = $filename;
        }

        $paths = $this->paths();
        if (empty($paths)) {
            return $ret;
        }
        foreach ($paths as $path) {
            $f = $path.DIRECTORY_SEPARATOR.$filename;
            if (file_exists($f)) {
                $ret[] = $f;
            }
        }

        return $ret;
    }

    /**
     * Retrieve the searchable paths.
     *
     * @return string[]
     */
    public function paths()
    {
        return $this->paths;
    }

    /**
     * Assign a list of paths.
     *
     * @param  string[] $paths The list of paths to add.
     * @return self
     */
    public function setPaths(array $paths)
    {
        $this->paths = [];
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
     * @return self
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

        $this->paths[] = $this->resolvePath($path);

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
        array_unshift($this->paths, $path);

        return $this;
    }

    /**
     * @param string $path The path to resolve.
     * @throws InvalidArgumentException If the path argument is not a string.
     * @return string
     */
    public function resolvePath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException(
                'Path needs to be a string'
            );
        }

        $basePath = $this->basePath();

        $path = ltrim($path, '/\\');

        if ($basePath && strpos($path, $basePath) === false) {
            $path = $basePath.$path;
        }

        return $path;
    }
}
