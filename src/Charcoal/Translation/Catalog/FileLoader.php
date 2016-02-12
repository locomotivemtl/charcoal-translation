<?php

namespace Charcoal\Translation\Catalog;

use \InvalidArgumentException;

/**
 *
 */
class FileLoader
{
    /**
     * @var array $searchPath
     */
    private $searchPath = [];

    /**
     * @var string $ident
     */
    private $ident;

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
     * @return string
     */
    public function ident()
    {
        return $this->ident;
    }

    /**
     * Returns the content of the first file found in search path
     *
     * @param string|null $ident
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
        $searchPath = $this->searchPath();
        if (empty($searchPath)) {
            return null;
        }
        foreach ($searchPath as $path) {
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
        $searchPath = $this->searchPath();
        if (empty($searchPath)) {
            return null;
        }
        foreach ($searchPath as $path) {
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

        $searchPath = $this->searchPath();
        if (empty($searchPath)) {
            return $ret;
        }
        foreach ($searchPath as $path) {
            $f = $path.DIRECTORY_SEPARATOR.$filename;
            if (file_exists($f)) {
                $ret[] = $f;
            }
        }

        return $ret;
    }

    /**
     * Get the object's search path, merged with global configuration path
     * @return array
     */
    public function searchPath()
    {
        return $this->searchPath;
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
}
