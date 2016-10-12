<?php

namespace Charcoal\Translation\Script;

use \InvalidArgumentException;

/**
 * Utility Belt.
 *
 * Requirements:
 * - `DIRECTORY_SEPARATORS` class constant.
 * - `DEFAULT_BASENAME` class constant.
 */
trait ScriptSupportTrait
{
    /**
     * The cache of globbed paths.
     *
     * @var array
     */
    protected static $globCache = [];

    /**
     * Retrieve the base path.
     *
     * @return string|null
     */
    abstract public function basePath();

    /**
     * Process multiple paths.
     *
     * @param  string|string[] $paths One or many paths to scan.
     * @throws InvalidArgumentException If the paths are invalid.
     * @return string[]
     */
    protected function processMultiplePaths($paths)
    {
        $paths = $this->asArray($paths);
        $paths = array_map([ $this, 'filterPath' ], $paths);
        $paths = array_filter($paths, [ $this, 'pathExists' ]);

        if ($paths === false) {
            throw new InvalidArgumentException('Received invalid paths.');
        }

        if (empty($paths)) {
            throw new InvalidArgumentException('Received empty paths.');
        }

        return $paths;
    }

    /**
     * Determine if the path exists.
     *
     * @param  string $path Path to the file or directory.
     * @throws InvalidArgumentException If the path is invalid.
     * @return boolean Returns TRUE if the path exists.
     */
    protected function pathExists($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('The path must be a string.');
        }

        return $this->globRecursive($this->basePath().'/'.$path, GLOB_BRACE);
    }

    /**
     * Filter the given path.
     *
     * Trims leading and trailing directory paths
     *
     * @param  string $path Path to the file or directory.
     * @throws InvalidArgumentException If the path is invalid.
     * @return string Returns the filtered path.
     */
    protected function filterPath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('The path must be a string.');
        }

        return trim(trim($path, static::DIRECTORY_SEPARATORS));
    }

    /**
     * Filter the given path to be writable.
     *
     * @param  string      $path A writable path to a file or directory.
     * @param  string|null $name The target file name.
     * @throws InvalidArgumentException If the path is invalid.
     * @return string Returns the filtered path.
     */
    protected function filterWritablePath($path, $name = null)
    {
        if ($name === null) {
            $name = static::DEFAULT_BASENAME;
        }

        $path = $this->filterPath($path);
        $test = $this->basePath().'/'.$path;

        if (is_dir($test)) {
            if (is_writable($test)) {
                $path .= '/'.$name;
            } else {
                throw new InvalidArgumentException('The catalog path is not writeable.');
            }
        } elseif (is_file($test)) {
            if (!is_writable($test)) {
                throw new InvalidArgumentException('The catalog file is not writeable.');
            }
        } else {
            $info = pathinfo($path);
            $path = $this->filterWritablePath($info['dirname'], $info['basename']);
        }

        return $path;
    }

    /**
     * Recursively find pathnames matching a pattern.
     *
     * @see    http://in.php.net/manual/en/function.glob.php#106595
     * @param  string  $pattern The search pattern.
     * @param  integer $flags   Bitmask of {@see glob()} options.
     * @return array
     */
    public function globRecursive($pattern, $flags = 0)
    {
        $maxDepth = $this->maxDepth();
        $depthKey = strval($maxDepth);

        if (isset(static::$globCache[$pattern][$depthKey])) {
            return static::$globCache[$pattern][$depthKey];
        }

        $depth = 1;
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', (GLOB_ONLYDIR|GLOB_NOSORT)) as $dir) {
            $files = array_merge($files, $this->globRecursive($dir.'/'.basename($pattern), $flags));
            $depth++;
            if ($depth >= $maxDepth) {
                break;
            }
        }

        static::$globCache[$pattern][$depthKey] = array_filter($files, 'is_file');

        return $files;
    }

    /**
     * Resolve the given language down to a language code.
     *
     * @param  mixed $lang A locale or language identifier.
     * @throws InvalidArgumentException If the language is invalid.
     * @return string Return the given language's code.
     */
    protected function resolveLanguage($lang)
    {
        if (is_string($lang)) {
            return $lang;
        } elseif (method_exists($lang, '__toString')) {
            return strval($lang);
        } elseif (is_array($lang) || $lang instanceof ArrayAccess) {
            if (isset($lang['ident'])) {
                return $lang['ident'];
            } elseif (isset($lang['code'])) {
                return $lang['code'];
            }
        } elseif ($lang instanceof LanguageInterface) {
            return $lang->ident();
        }

        throw new InvalidArgumentException(
            sprintf(
                'Must be a string-cast language code or an instance of LanguageInterface, received %s',
                (is_object($lang) ? get_class($lang) : gettype($lang))
            )
        );
    }

    /**
     * Resolve the given value as a collection of values.
     *
     * If the given value is a string, it will be split.
     *
     * @param  mixed $var An argument to split.
     * @throws InvalidArgumentException If the value cannot be parsed into an array.
     * @return array|Traversable
     */
    protected function asArray($var)
    {
        if (is_string($var)) {
            $var = preg_split('#(?<!\\\\)[\s,]+#', $var);
        }

        if (is_array($var) || $var instanceof Traversable) {
            return $var;
        }

        throw new InvalidArgumentException('The value cannot be split.');
    }

    /**
     * Parse command line arguments into script properties.
     *
     * @throws RuntimeException If a checkbox/radio argument has no options.
     * @return self
     */
    protected function parseArguments()
    {
        $cli  = $this->climate();
        $args = $cli->arguments;

        $ask    = $args->defined('interactive');
        $params = $this->arguments();
        foreach ($params as $key => $param) {
            $setter = $this->setter($key);

            if (!is_callable([ $this, $setter ])) {
                continue;
            }

            $value = $args->get($key);
            if ($value) {
                $this->{$setter}($value);
            }

            if ($ask) {
                if (isset($param['prompt'])) {
                    $label = $param['prompt'];
                } else {
                    continue;
                }

                $type = (isset($param['inputType']) ? $param['inputType'] : 'input');

                $accept = true;
                $prompt = 'prompt';
                switch ($type) {
                    case 'checkboxes':
                    case 'radio':
                        if (!isset($param['options'])) {
                            throw new RuntimeException(
                                sprintf('The [%s] argument has no options.', $key)
                            );
                        }

                        $accept = false;
                        $input  = $cli->{$type}($label, $param['options']);
                        break;

                    case 'confirm':
                        $prompt = 'confirmed';
                        $input  = $cli->confirm($label);
                        break;

                    case 'password':
                        $input = $cli->password($label);
                        $input->multiLine();
                        break;

                    case 'multiline':
                        $input = $cli->input($label);
                        $input->multiLine();
                        break;

                    default:
                        $input = $cli->input($label);
                        break;
                }

                if ($accept && isset($param['acceptValue'])) {
                    if (is_array($param['acceptValue']) || is_callable($param['acceptValue'])) {
                        $input->accept($param['acceptValue']);
                    }
                }

                $value = $input->{$prompt}();

                if ($value) {
                    $this->{$setter}($value);
                }
            }
        }

        return $this;
    }

    /**
     * Retrieve the script's supported arguments.
     *
     * @return array
     */
    public function defaultArguments()
    {
        $arguments = [
            'interactive' => [
                'prefix'       => 'i',
                'longPrefix'   => 'interactive',
                'noValue'      => true,
                'description'  => 'Ask any interactive question.'
            ],
            'dry_run' => [
                'longPrefix'   => 'dry-run',
                'noValue'      => true,
                'description'  => 'This will simulate the script and show you what would happen.'
            ]
        ];

        return array_merge(parent::defaultArguments(), $arguments);
    }
}
