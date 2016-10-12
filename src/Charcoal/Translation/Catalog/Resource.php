<?php

namespace Charcoal\Translation\Catalog;

use \InvalidArgumentException;
use \Charcoal\Config\AbstractConfig;
use \Charcoal\Translation\Catalog\ResourceInterface;

/**
 *
 */
class Resource extends AbstractConfig implements ResourceInterface
{
    /**
     * Disable key-notation.
     *
     * @var string
     */
    protected $separator = '';

    /**
     * The language of this resource if unilingual.
     *
     * @var string
     */
    private $sourceLanguage;

    /**
     * Assign the alternate's source.
     *
     * @param  mixed $lang A language object or identifier.
     * @throws InvalidArgumentException If the language is invalid.
     * @return self
     */
    public function setSourceLanguage($lang)
    {
        if ($lang === null) {
            $this->sourceLanguage = null;

            return $this;
        }

        if (is_array($lang) && isset($lang['ident'])) {
            $langCode = (string)$lang['ident'];
        } elseif ($lang instanceof LanguageInterface) {
            $langCode = strval($lang);
        } elseif (is_string($lang) || method_exists($lang, '__toString')) {
            $langCode = strval($lang);
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid language, received %s',
                    (is_object($lang) ? get_class($lang) : gettype($lang))
                )
            );
        }

        $this->sourceLanguage = $langCode;

        return $this;
    }

    /**
     * Retrieve the alternate's source.
     *
     * @return string|UriInterface
     */
    public function sourceLanguage()
    {
        return $this->sourceLanguage;
    }

    /**
     * Load a configuration file. The file type is determined by its extension.
     *
     * Supported file types are `ini`, `json`, `yml`, `php`, `csv`
     *
     * @param  string $filename A supported configuration file.
     * @throws InvalidArgumentException If the filename is invalid.
     * @return mixed The file content.
     */
    public function loadFile($filename)
    {
        if (!is_string($filename)) {
            throw new InvalidArgumentException(
                'Translation file must be a string.'
            );
        }

        if (!file_exists($filename)) {
            throw new InvalidArgumentException(
                sprintf('Translation file "%s" does not exist', $filename)
            );
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if ($ext === 'csv') {
            return $this->loadCsvFile($filename);
        } else {
            return parent::loadFile($filename);
        }
    }

    /**
     * Add a `.csv` file to the configuration.
     *
     * @param  string $filename A CSV configuration file.
     * @throws InvalidArgumentException If the file or invalid.
     * @return array The contents are returned as an associative array.
     */
    private function loadCsvFile($filename)
    {
        $handle = fopen($filename, 'r');
        if (!$handle) {
            throw new InvalidArgumentException(
                sprintf('CSV file "%s" is empty or invalid.')
            );
        }

        $languages = [];
        $messages  = [];

        $i = 0;
        while ($data = fgetcsv($handle)) {
            $i++;

            if ($i == 1) {
                $source   = $data[0];
                $count   = count($data);
                $context = $data[( $count-1 )];

                /**
                 * Remove "ident" and "context" columns.
                 * The rest of the columns are the available languages
                 */
                array_shift($data);
                array_pop($data);

                $languages = $data;

                continue;
            }

            // Ident / Key
            $source = $data[0];
            $translations = [];

            // Remove ident and context from the data
            // Saves as available langs
            array_shift($data);
            array_pop($data);

            $k = 0;
            $count = count($languages);
            for (; $k < $count; $k++) {
                $translations[$languages[$k]] = ($data[$k] ?: $source);
            }

            $messages[$source] = $translations;
        }

        return $messages;
    }
}
