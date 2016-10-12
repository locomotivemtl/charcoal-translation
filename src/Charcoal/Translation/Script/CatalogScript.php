<?php

namespace Charcoal\Translation\Script;

use \ArrayAccess;
use \Traversable;
use \InvalidArgumentException;
use \OutOfBoundsException;
use \RuntimeException;
use \Exception;

use \Pimple\Container;

// From PSR-7
use \Psr\Http\Message\RequestInterface;
use \Psr\Http\Message\ResponseInterface;

// From 'charcoal-core'
use \Charcoal\Model\ModelFactory;

// From 'charcoal-admin'
use \Charcoal\Admin\AdminScript;

// From 'charcoal-translation'
use \Charcoal\Language\LanguageInterface;

/**
 * Catalog all strings to be translated.
 *
 * Extracts translatable strings directly from the source code (Mustache and PHP)
 * and saves them as a CSV file.
 *
 * When updating, a summary will list _new strings_ and _obsolete strings_.
 */
class CatalogScript extends AdminScript
{
    use ScriptSupportTrait {
        ScriptSupportTrait::defaultArguments as extraDefaultArguments;
    }

    /**
     * A mask of directory delimiters.
     *
     * @const string
     */
    const DIRECTORY_SEPARATORS = '/\\';

    /**
     * The terminator goes at the end of a row in tabular data.
     *
     * @const string
     */
    const TABULAR_TERMINATOR = "\n";

    /**
     * The delimiter goes between fields of a row in tabular data.
     *
     * @const string
     */
    const TABULAR_DELIMITER = ',';

    /**
     * The enclosure wraps a field in tabular data.
     *
     * @const string
     */
    const TABULAR_ENCLOSURE = '"';

    /**
     * The default directory path for the translations file.
     *
     * @const string
     */
    const DEFAULT_DIRNAME = 'translations';

    /**
     * The default file name for the translations file.
     *
     * @const string
     */
    const DEFAULT_BASENAME = 'messages.csv';

    /**
     * The "cherry-pick" strategy.
     *
     * @const string
     */
    const MERGE_STRATEGY_PICK = 'pick';

    /**
     * The "merge" strategy.
     *
     * @const string
     */
    const MERGE_STRATEGY_MERGE = 'merge';

    /**
     * The "ours" strategy.
     *
     * @const string
     */
    const MERGE_STRATEGY_OURS = 'ours';

    /**
     * The "theirs" strategy.
     *
     * @const string
     */
    const MERGE_STRATEGY_THEIRS = 'theirs';

    /**
     * The default merge strategy for unexpected languages.
     *
     * @const string
     */
    const DEFAULT_LANGUAGE_MERGE = self::MERGE_STRATEGY_MERGE;

    /**
     * The default number of directories to descend into.
     *
     * @const string
     */
    const DEFAULT_MAX_DEPTH = 4;

    /**
     * The number of directories to descend into.
     *
     * @var integer
     */
    protected $maxDepth;

    /**
     * The base path to scan for translations and updating the catalog.
     *
     * @var string|null
     */
    protected $basePath;

    /**
     * The included source paths to scan for translatable text.
     *
     * @var array|null
     */
    protected $includedPaths;

    /**
     * The excluded source paths from scan.
     *
     * @var array|null
     */
    protected $excludedPaths;

    /**
     * The file where the extracted strings are saved to.
     *
     * @var string|null
     */
    protected $outputPath;

    /**
     * The languages the text will be translated to.
     *
     * @var array|null
     */
    protected $languages = [];

    /**
     * The merge strategy for unexpected languages.
     *
     * @var string|null
     */
    protected $languageMergeStrategy;

    /**
     * The language in which the text in the source code is written.
     *
     * @var string|null
     */
    protected $sourceLanguage;





    /**
     * @var string $fileType
     */
    protected $fileType;

    /**
     * @var string $output
     */
    protected $output;

    /**
     * @var string $path
     */
    protected $path;

    /**
     * @var array $locales
     */
    protected $locales;

    /**
     * AppConfig
     *
     * @var [type]
     */
    protected $appConfig;

    /**
     * @return void
     */
    protected function init()
    {
        parent::init();

        $this->setDescription(
            'The <underline>translation/update</underline> script extracts '.
            'translatable strings from files of given paths. It can display '.
            'them or merge the new ones into the translations file.'
        );
    }

    /**
     * Inject dependencies from a DI Container.
     *
     * @param  Container $container A dependencies container instance.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setBasePath($container['config']['base_path']);
        $this->setSourceLanguage($container['translator/config']->defaultLanguage());
        $this->setLanguages($container['translator/locales']->availableLanguages());
    }

    /**
     * Run the script.
     *
     * @param  RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param  ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        unset($request);

        try {
            $this->start();
        } catch (Exception $e) {
            $this->climate()->error($e->getMessage());
        }

        return $response;
    }

    /**
     * Execute the prime directive.
     *
     * @return self
     */
    public function start()
    {
        $cli  = $this->climate();
        $args = $cli->arguments;
        $ask  = $args->defined('interactive');
        $dry  = $args->defined('dry_run');

        $progress = $cli->progress(100);

        $cli->br();
        $cli->bold()->underline()->out('Translation Messages Extractor and Dumper');
        $cli->br();

        $resultMessage = 'Translation file was successfully updated';
        $z = 0;

        /**
         * Step 1: Resolve Arguments
         */
        $z++;
        $progress->advance();
        $this->parseArguments();

        // Resolve conflicts
        $merge = $this->languageMergeStrategy();
        if ($merge === self::MERGE_STRATEGY_THEIRS) {
            if ($ask || $this->verbose()) {
                $languages = $this->languages();
                if ($languages) {
                    $cli->comment(
                        sprintf(
                            'The selected merge strategy for languages [%1$s] ignores the current set: %2$s',
                            $merge,
                            implode(', ', $languages)
                        )
                    );

                    if ($ask) {
                        $input = $cli->confirm('Continue with selected merge strategy?');
                        if (!$input->confirmed()) {
                            $cli->info('Canceled Extraction');
                            return $this;
                        }
                    }
                }
            }

            $this->clearLanguages();
        }

        /**
         * Step 2: Build collection of source files
         */
        $z++;
        $progress->advance(1, ($this->quiet() ? null : 'Searching for source files…'));

        $files = $this->sourceFiles();
        $count = count($files);
        $steps = 6;
        $total = ($count + $steps);

        /** Update the total and current mark to refresh progress bar */
        $progress->total($total)->forceRedraw();

        if ($count === 0) {
            $cli->error('No files found.');
            return $this;
        } elseif ($count === 1) {
            if (!$this->quiet()) {
                $cli->comment('Discovered one source file.');
            }
        } else {
            if (!$this->quiet()) {
                $cli->comment(sprintf('Discovered %s source files.', $count));
            }
        }

        if (!$this->quiet()) {
            $input = $cli->confirm('Extract translatable strings?');
            if ($input->confirmed()) {
                $cli->info('Starting Extraction');
            } else {
                $cli->info('Canceled Extraction');
                return $this;
            }
        }

        /**
         * Step 3: Import existing translations from translations file
         */
        $z++;
        $outputFile = $this->outputFile();
        if (is_readable($outputFile)) {
            if (!$this->quiet() || $merge === self::MERGE_STRATEGY_PICK) {
                $importMessage = 'Loading existing translations file…';
            } else {
                $importMessage = null;
            }

            $cli->advance(1, $importMessage);
            $messages = $this->fromCSV();
        } else {
            $cli->advance();
            $messages = [];
        }

        /**
         * Step 4: Extract messages from source files
         */
        $z++;
        $basePath = $this->basePath();
        foreach ($files as $file) {
            $cli->advance(1, ($this->quiet() ? null : 'Parsing source files…'));

            if ($this->verbose()) {
                $cli->whisper(str_replace($basePath, '', $file));
            }

            $this->extractEntries($file, $messages);
        }

        /**
         * Step 5: Update translations files with latest messages and translations
         */
        $z++;
        if ($this->quiet()) {
            $writeMessage = null;
        } else {
            $writeMessage = 'Writing to translations file…';

            if ($this->verbose()) {
                $writeMessage .= ' '.$this->outputPath();
            }
        }

        $cli->advance(1, $writeMessage);

        if ($dry) {
            $extractedMessagesCount = 0;

            $list = [];
            foreach ($messages as $id => $entry) {
                $status = (isset($entry['status']) ? $entry['status'] : null);
                switch ($status) {
                    case 'new':
                        $list[] = sprintf('<green>%s</green>', $id);
                        break;

                    case 'obsolete':
                        $list[] = sprintf('<red>%s</red>', $id);
                        break;

                    default:
                        $list[] = $id;
                }
            }

            $messagesCount = count($list);
            $extractedMessagesCount += $messagesCount;

            $cli->info(
                sprintf(
                    'Messages extracted for "%s" (%d message%s)',
                    $this->outputPath(),
                    $messagesCount,
                    ($messagesCount > 1 ? 's' : '')
                )
            );
            $cli->columns($list);

            $resultMessage = sprintf(
                '%d message%s successfully extracted',
                $extractedMessagesCount,
                ($extractedMessagesCount > 1 ? 's were' : ' was')
            );
        } else {
            $this->toCSV($messages);
        }

        /**
         * Step 6: Conclusion
         */
        $z++;
        $cli->advance(1, 'Done');

        if (!$this->quiet()) {
            $cli->shout($resultMessage.'.');
        }

        return $this;
    }

    /**
     * Retrieve the source files.
     *
     * Filters the included and excluded paths relative to the base path.
     *
     * @return array
     */
    protected function sourceFiles()
    {
        static $files;

        if ($files === null) {
            $basePath      = $this->basePath();
            $includedPaths = $this->includedPaths();
            $excludedPaths = $this->excludedPaths();

            $included = [];
            foreach ($includedPaths as $relPath) {
                $included = array_merge($included, $this->globRecursive($basePath.'/'.$relPath, GLOB_BRACE));
            }

            $excluded = [];
            foreach ($excludedPaths as $relPath) {
                $excluded = array_merge($excluded, $this->globRecursive($basePath.'/'.$relPath, GLOB_BRACE));
            }

            $files = array_diff($included, $excluded);
        }

        return $files;
    }

    /**
     * Extract translatable strings from the given file.
     *
     * @param  string            $file     The source file to scan.
     * @param  array|ArrayAccess $messages If $messages is provided, then it is filled
     *     with the found strings.
     * @throws InvalidArgumentException If the $messages aren't accessible.
     * @return boolean Returns TRUE if matches were found.
     */
    protected function extractEntries($file, &$messages)
    {
        if (!is_array($messages) && !($messages instanceof ArrayAccess)) {
            throw new InvalidArgumentException(
                'The $messages parameter must be an accessible array.'
            );
        }

        $result  = false;
        $content = file_get_contents($file);
        $context = $this->resolveContextByFile($file, $content);
        $pattern = $this->sourceParserByFile($file);

        if (preg_match($pattern, $content)) {
            preg_match_all($pattern, $content, $matches);

            $result  = true;
            $matches = $matches['entry'];
            foreach ($matches as $id) {
                if (isset($messages[$id])) {
                    $messages[$id]['context'][] = $context;
                    if ($messages[$id]['status'] === 'obsolete') {
                        $messages[$id]['status'] = 'updated';
                    }
                } else {
                    $messages[$id] = [
                        'translations' => [],
                        'context'      => [ $context ],
                        'status'       => 'new'
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Resolve the context of the given file.
     *
     * @param  string $file The source file to resolve.
     * @param  string $body The $file contents.
     * @throws InvalidArgumentException If the file is invalid.
     * @return string Returns a special context for the $file or the file itself,
     *     relative to the base path.
     */
    protected function resolveContextByFile($file, $body = null)
    {
        unset($body);

        if (!is_string($file)) {
            throw new InvalidArgumentException('The file must be a string.');
        }

        return str_replace($this->basePath(), '', $file);
    }

    /**
     * Retrieve the token for translatable strings for the given file.
     *
     * @param  string $file The source file to lookup.
     * @throws InvalidArgumentException If the file is invalid.
     * @return string Returns a regular expression for matching
     *     translatable strings from the given file type.
     */
    protected function sourceParserByFile($file)
    {
        if (!is_string($file)) {
            throw new InvalidArgumentException('The file must be a string.');
        }

        $type = pathinfo($file, PATHINFO_EXTENSION);

        return $this->sourceParser(strtolower($type));
    }

    /**
     * Retrieve the token for translatable strings for the given file type.
     *
     * Note: The RegExp pattern must use "entry" as a named subpattern.
     *
     * @param  string $type The source file type.
     * @throws InvalidArgumentException If the file type is invalid or unsupported.
     * @throws OutOfBoundsException If the parser is unsupported.
     * @return string Returns a regular expression for matching
     *     translatable strings from the given parser type.
     */
    protected function sourceParser($type)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException('The file type must be a string.');
        }

        switch ($type) {
            case 'mustache':
                return '~\{\{#\s*_t\s*\}\}(?<entry>.+?)\{\{\/\s*_t\s*\}\}~s';
            case 'php':
                return '~\b(?<function>_t|trans|translate)\(\p{Xps}*([\"\'])(?<entry>.+?)\2\p{Xps}*\)~s';
        }

        throw new OutOfBoundsException(
            sprintf(
                'Supported source types are: %1$s; received %2$s'.
                'Unsupported file type "%1$s". Must be one of %2$s',
                implode(', ', [ 'mustache', 'php' ]),
                (is_object($type) ? get_class($type) : gettype($type))
            )
        );
    }



    // CLI Arguments
    // =================================================================================================================

    /**
     * Retrieve the script's supported arguments.
     *
     * @return array
     */
    public function defaultArguments()
    {
        static $arguments;

        if ($arguments === null) {
            $validateLanguages = function ($response) {
                if (strlen($response) === 0) {
                    return true;
                }

                try {
                    $this->asArray($response);
                } catch (Exception $e) {
                    unset($e);
                    return false;
                }

                return true;
            };
            $validateLanguages = $validateLanguages->bindTo($this);

            $validatePaths = function ($response) {
                if (strlen($response) === 0) {
                    return true;
                }

                try {
                    $this->processMultiplePaths($response);
                } catch (Exception $e) {
                    unset($e);
                    return false;
                }

                return true;
            };
            $validatePaths = $validatePaths->bindTo($this);

            $validateCatalog = function ($response) {
                if (strlen($response) === 0) {
                    return true;
                }

                try {
                    $this->filterWritablePath($response);
                } catch (Exception $e) {
                    unset($e);
                    return false;
                }

                return true;
            };
            $validateCatalog = $validateCatalog->bindTo($this);

            $arguments = [
                'max_depth' => [
                    'longPrefix'   => 'max-depth',
                    'castTo'       => 'int',
                    'description'  => 'Descend at most the given number of levels of directories. '.
                                      'A negative value means no limit.',
                    'defaultValue' => static::DEFAULT_MAX_DEPTH,
                    'prompt'       => 'Scan Depth (of directories)',
                    'acceptValue'  => function ($response) {
                        return (strlen($response) === 0) || is_numeric($response);
                    }
                ],
                'base_path' => [
                    'prefix'       => 'b',
                    'longPrefix'   => 'base',
                    'required'     => true,
                    'description'  => 'Specify an alternate base path.',
                    'defaultValue' => $this->basePath(),
                    'prompt'       => 'Base Path',
                    'acceptValue'  => function ($response) {
                        return (strlen($response) === 0) || is_dir($response);
                    }
                ],
                'included_paths' => [
                    'prefix'       => 'i',
                    'longPrefix'   => 'include',
                    'required'     => true,
                    'description'  => 'Paths to search for source files (glob patterns).',
                    'defaultValue' => implode(', ', $this->defaultIncludedPaths()),
                    'prompt'       => 'Included Paths',
                    'acceptValue'  => $validatePaths
                ],
                'excluded_paths' => [
                    'prefix'       => 'x',
                    'longPrefix'   => 'exclude',
                    'description'  => 'Paths to ignore among the included paths (glob patterns).',
                    'defaultValue' => implode(', ', $this->defaultExcludedPaths()),
                    'prompt'       => 'Excluded Paths (within included paths)',
                    'acceptValue'  => $validatePaths
                ],
                'output_path' => [
                    'prefix'       => 'o',
                    'longPrefix'   => 'output-path',
                    'required'     => true,
                    'description'  => 'Where the translatable strings are saved to.',
                    'defaultValue' => $this->defaultOutputPath(),
                    'prompt'       => 'Store translatable text in',
                    'acceptValue'  => $validateCatalog
                ],
                'source_language' => [
                    'prefix'       => 's',
                    'longPrefix'   => 'source-language',
                    'required'     => true,
                    'description'  => 'The language in which the text in the source code is written.',
                    'defaultValue' => $this->sourceLanguage(),
                    'prompt'       => 'Source Language'
                ],
                'merge_languages' => [
                    'longPrefix'   => 'merge-languages',
                    'description'  => 'How to resolve unexpected languages from the translations file.',
                    'defaultValue' => static::DEFAULT_LANGUAGE_MERGE,
                    'prompt'       => 'How to handle unexpected languages from the translations file?',
                    'inputType'    => 'radio',
                    'options'      => $this->supportedLanguageMergeStrategies()
                ],
                'languages' => [
                    'prefix'       => 'l',
                    'longPrefix'   => 'languages',
                    'required'     => true,
                    'description'  => 'The languages the text will be translated to.',
                    'defaultValue' => implode(', ', $this->languages()),
                    'prompt'       => 'Languages to translate into',
                    'acceptValue'  => $validateLanguages
                ]
            ];
        }

        return array_merge($this->extraDefaultArguments(), $arguments);
    }

    /**
     * Set the depth for directory descent.
     *
     * @param  integer $depth The number of levels to descend.
     * @throws InvalidArgumentException If the depth is invalid.
     * @return self
     */
    public function setMaxDepth($depth)
    {
        if ($depth === null) {
            $this->maxDepth = null;

            return $this;
        }

        if (!is_integer($depth)) {
            throw new InvalidArgumentException('The depth must be an integer.');
        }

        if ($depth < 0) {
            $depth = -1;
        }

        $this->maxDepth = $depth;

        return $this;
    }

    /**
     * Retrieve the depth for directory descent.
     *
     * @return integer
     */
    public function maxDepth()
    {
        if ($this->maxDepth === null) {
            return static::DEFAULT_MAX_DEPTH;
        }

        return $this->maxDepth;
    }

    /**
     * Set the base path prepended to all given paths.
     *
     * @param  string $path The base path.
     * @throws InvalidArgumentException If the path is invalid.
     * @return self
     */
    public function setBasePath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('The path must be a string.');
        }

        if (!is_dir($path)) {
            throw new InvalidArgumentException('The base path does not exist.');
        }

        $this->basePath = rtrim($path, static::DIRECTORY_SEPARATORS);

        return $this;
    }

    /**
     * Retrieve the base path.
     *
     * @return string|null
     */
    public function basePath()
    {
        return $this->basePath;
    }

    /**
     * Set the included paths to scan for translatable text.
     *
     * @param  string|string[] $paths One or many paths to scan.
     * @return self
     */
    public function setIncludedPaths($paths)
    {
        $this->includedPaths = $this->processMultiplePaths($paths);

        return $this;
    }

    /**
     * Retrieve the included paths to scan for translatable text.
     *
     * @return array|null
     */
    public function includedPaths()
    {
        if ($this->includedPaths === null) {
            return $this->defaultIncludedPaths();
        }

        return $this->includedPaths;
    }

    /**
     * Retrieve the default included paths to scan for translatable text.
     *
     * @return array
     */
    public function defaultIncludedPaths()
    {
        return [
            'templates/*.mustache',
            'src/*.php'
        ];
    }

    /**
     * Set the excluded paths to scan for translatable text.
     *
     * @param  string|string[] $paths One or many paths to scan.
     * @return self
     */
    public function setExcludedPaths($paths)
    {
        $this->excludedPaths = $this->processMultiplePaths($paths);

        return $this;
    }

    /**
     * Retrieve the excluded paths to scan for translatable text.
     *
     * @return array|null
     */
    public function excludedPaths()
    {
        if ($this->excludedPaths === null) {
            return $this->defaultExcludedPaths();
        }

        return $this->excludedPaths;
    }

    /**
     * Retrieve the default excluded paths to scan for translatable text.
     *
     * @return array
     */
    public function defaultExcludedPaths()
    {
        return [];
    }

    /**
     * Set the file path to store translatable strings.
     *
     * @param  string $path A writable file path.
     * @throws InvalidArgumentException If the file format is not supported.
     * @return self
     */
    public function setOutputPath($path)
    {
        $file = $this->filterWritablePath($path);
        $type = pathinfo($file, PATHINFO_EXTENSION);

        $supportedFormats = $this->outputFormats();
        if (!in_array($type, $supportedFormats)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Wrong output format. Supported formats are: %s.',
                    implode(', ', $supportedFormats)
                )
            );
        }

        $this->outputPath = $file;

        return $this;
    }

    /**
     * Retrieve the file path to store translatable strings.
     *
     * @return string
     */
    public function outputPath()
    {
        if ($this->outputPath === null) {
            return $this->defaultOutputPath();
        }

        return $this->outputPath;
    }

    /**
     * Retrieve the file path to store translatable strings.
     *
     * @return string
     */
    public function defaultOutputPath()
    {
        static $defaultPath;

        if ($defaultPath === null) {
            $defaultPath = sprintf(
                '%1$s/%2$s',
                $this->filterPath(static::DEFAULT_DIRNAME),
                $this->filterPath(static::DEFAULT_BASENAME)
            );
        }

        return $defaultPath;
    }

    /**
     * Retrieve the supported output formats for the translations file.
     *
     * @return array
     */
    public function outputFormats()
    {
        return [ 'csv' ];
    }

    /**
     * Retrieve the absolute path to the file for storing translatable strings.
     *
     * @return string
     */
    public function outputFile()
    {
        return $this->basePath().'/'.$this->outputPath();
    }

    /**
     * Set the supported languages.
     *
     * @param  array|Traversable|null $languages One or many language codes.
     * @throws InvalidArgumentException If the given value is not a traversable collection.
     * @return self
     */
    public function setLanguages($languages)
    {
        $this->clearLanguages();

        $languages = $this->asArray($languages);

        if (is_array($languages) || $languages instanceof Traversable) {
            $this->addSourceLanguage();
            foreach ($languages as $lang) {
                $this->addLanguage($lang);
            }
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Must be an array or traversable collection of language codes; received %s.',
                    (is_object($languages) ? get_class($languages) : gettype($languages))
                )
            );
        }

        return $this;
    }

    /**
     * Remove all supported languages.
     *
     * @return self
     */
    public function clearLanguages()
    {
        $this->languages = [];

        return $this;
    }

    /**
     * Add a supported language.
     *
     * @param  mixed $lang A locale or language identifier.
     * @return self
     */
    public function addLanguage($lang)
    {
        $lang = $this->resolveLanguage($lang);

        $this->languages[$lang] = $lang;

        return $this;
    }

    /**
     * Determine if a language is supported.
     *
     * @param  mixed $lang A locale or language identifier.
     * @return boolean
     */
    public function hasLanguage($lang)
    {
        $lang = $this->resolveLanguage($lang);

        return isset($this->languages[$lang]);
    }

    /**
     * Determine if languages are supported.
     *
     * @return boolean
     */
    public function hasLanguages()
    {
        return boolval($this->languages);
    }

    /**
     * Retrieve the supported languages.
     *
     * @return array
     */
    public function languages()
    {
        return array_keys($this->languages);
    }

    /**
     * Set the merge strategy for unexpected languages.
     *
     * @param  string $strategy A merge strategy.
     * @throws InvalidArgumentException If the strategy is invalid.
     * @return self
     */
    public function setLanguageMergeStrategy($strategy)
    {
        if ($strategy === null) {
            $this->languageMergeStrategy = null;

            return $this;
        }

        if (!is_string($strategy)) {
            throw new InvalidArgumentException('The strategy must be a string.');
        }

        $supportedStrategies = $this->supportedLanguageMergeStrategies();
        if (!isset($supportedStrategies[$strategy])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Wrong language merge strategy. Supported strategies are: %s.',
                    implode(', ', array_keys($supportedStrategies))
                )
            );
        }

        $this->languageMergeStrategy = $strategy;

        return $this;
    }

    /**
     * Retrieve the merge strategy for unexpected languages.
     *
     * @return string
     */
    public function languageMergeStrategy()
    {
        if ($this->languageMergeStrategy === null) {
            return self::DEFAULT_LANGUAGE_MERGE;
        }

        return $this->languageMergeStrategy;
    }

    /**
     * Retrieve the supported merge strategies for unexpected languages.
     *
     * @return array Returns an associative array.
     */
    public function supportedLanguageMergeStrategies()
    {
        return [
            self::MERGE_STRATEGY_PICK   => 'Cherry pick their languages',
            self::MERGE_STRATEGY_MERGE  => 'Include their languages',
            self::MERGE_STRATEGY_OURS   => 'Ignore their languages',
            self::MERGE_STRATEGY_THEIRS => 'Favour their languages',
        ];
    }

    /**
     * Add the source language to the list of supported languages.
     *
     * @return self
     */
    public function addSourceLanguage()
    {
        $source = $this->sourceLanguage();

        if ($source) {
            $this->addLanguage($source);
        }

        return $this;
    }

    /**
     * Set the language of the source code.
     *
     * @param  mixed $lang A locale or language identifier.
     * @throws InvalidArgumentException If the language is invalid.
     * @return self
     */
    public function setSourceLanguage($lang)
    {
        $this->sourceLanguage = $this->resolveLanguage($lang);

        return $this;
    }

    /**
     * Retrieve the language of the source code.
     *
     * @return array|null
     */
    public function sourceLanguage()
    {
        return $this->sourceLanguage;
    }



    // CSV Handling
    // =================================================================================================================

    /**
     * Retrieve existing messages from the translations file.
     *
     * @param  array|ArrayAccess $messages If the optional $messages is available,
     *     it will be used as the starting point.
     * @throws InvalidArgumentException If the $messages collection is invalid.
     * @return array Returns an associative array in the form of:
     *     ```json
     *     {
     *         "message id": {
     *             "translations": {
     *                 "en" => "English translation…",
     *                 "fr" => "Traduction française…",
     *                 "es" => "Traducción española…"
     *             },
     *             "context": [ "file path", "namespace", "comment" ]
     *         }
     *     }
     *     ```
     */
    public function fromCSV($messages = [])
    {
        if (!is_array($messages) && !($messages instanceof ArrayAccess)) {
            throw new InvalidArgumentException(
                'The $messages parameter must be an accessible array.'
            );
        }

        $file = fopen($this->outputFile(), 'r');

        if (!$file) {
            /** @todo Throw exception? */
            return $messages;
        }

        $cli   = $this->climate();
        $merge = $this->languageMergeStrategy();

        $languages = [];

        $r = 0;
        // @codingStandardsIgnoreStart
        while (($row = fgetcsv($file)) !== false) {
            // @codingStandardsIgnoreEnd
            /** Extract pre-defined languages from the header row */
            if ($r === 0) {
                $r++;

                $count = (count($row) - 1);
                for ($i = 1; $i < $count; $i++) {
                    $lang = $row[$i];
                    $languages[] = $lang;

                    switch ($merge) {
                        case self::MERGE_STRATEGY_PICK:
                            if (!$this->hasLanguage($lang)) {
                                $input = $cli->confirm(sprintf('Include this language "%s"?', $lang));
                                if ($input->confirmed()) {
                                    $this->addLanguage($lang);
                                }
                            }
                            break;

                        case self::MERGE_STRATEGY_THEIRS:
                        case self::MERGE_STRATEGY_MERGE:
                            $languages[] = $lang;
                            $this->addLanguage($lang);
                            break;
                    }
                }
                continue;
            }

            /** Parse the entries */
            $entry = $this->parseCsvRow($row, $languages);

            if (is_array($entry)) {
                $messages[$entry[0]] = $entry[1];
            }
        }

        return $messages;
    }

    /**
     * Write messages to the translations file.
     *
     * If the target file does not exist, it will be created.
     *
     * @param  array|Traversable $messages The messages to write.
     * @throws InvalidArgumentException If the $messages collection is invalid.
     * @throws RuntimeException If the translations file cannot be modified.
     * @return boolean Returns TRUE if the file was successfully saved.
     */
    public function toCSV($messages)
    {
        if (!is_array($messages) && !($messages instanceof Traversable)) {
            throw new InvalidArgumentException(
                'The $messages parameter must be an accessible array.'
            );
        }

        $outputFile = $this->outputFile();
        $languages  = $this->languages();

        /** Create the directory tree if it doesn't exist. */
        $dir = dirname($outputFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = fopen($outputFile, 'w');
        if (!$file) {
            throw new RuntimeException(
                sprintf(
                    'Cannot write to translations file: %s',
                    $this->outputPath()
                )
            );
        }

        fputcsv($file, $this->csvHeaderRow());

        foreach ($messages as $id => $entry) {
            $row = [ $id ];
            foreach ($languages as $lang) {
                if (isset($entry['translations'][$lang])) {
                    $row[] = $entry['translations'][$lang];
                } else {
                    $row[] = '';
                }
            }

            if (is_array($entry['context'])) {
                $row[] = implode(',', array_unique($entry['context']));
            } else {
                $row[] = $entry['context'];
            }

            fputcsv($file, $row);
        }

        fclose($file);

        return true;
    }

    /**
     * Parse a given row of fields from a CSV.
     *
     * Example of a CSV row:
     * ```csv
     * "source","en","fr","es","context"
     * "message id","English…","…française…","…española…","file path,namespace,comment"
     * ```
     *
     * @param  array      $row       A row of fields from {@see fgetcsv()}.
     * @param  array|null $languages The languages defined in the CSV's header.
     * @return array|null
     */
    public function parseCsvRow(array $row, array $languages = null)
    {
        if ($languages === null) {
            $languages = $this->languages();
        }

        $langCount = count($languages);
        $colCount  = count($row);
        $required  = ($langCount + 2);

        /** Minimum field requirements: "source", "<translation>" [, "<…>" ], "context" */
        if ($colCount < $required) {
            return null;
        }

        $i = 1;
        $l = 0;
        $translations = [];
        for (; $i <= $langCount; $i++, $l++) {
            $translations[$languages[$l]] = $row[$i];
        }

        return [
            reset($row),
            [
                'translations' => $translations,
                'context'      => end($row),
                'status'       => 'obsolete'
            ]
        ];
    }

    /**
     * Retrieve the header columns for a CSV file.
     *
     * @see    self::parseCsvRow() For an example of CSV structure.
     * @return array
     */
    public function csvHeaderRow()
    {
        static $columns;

        if ($columns === null) {
            $columns   = [ 'source' ];
            $languages = $this->languages();
            foreach ($languages as $lang) {
                $columns[] = $lang;
            }

            $columns[] = 'context';
        }

        return $columns;
    }

    /**
     * Retrieve the row terminator.
     *
     * @return string
     */
    public function terminator()
    {
        return static::TABULAR_TERMINATOR;
    }

    /**
     * Retrieve the field enclosure.
     *
     * @return string
     */
    public function enclosure()
    {
        return static::TABULAR_ENCLOSURE;
    }

    /**
     * Retrieve the field delimiter.
     *
     * @return string
     */
    public function delimiter()
    {
        return static::TABULAR_DELIMITER;
    }
}
