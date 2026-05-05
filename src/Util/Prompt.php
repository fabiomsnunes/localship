<?php

/**
 * Minimal interactive prompt helper.
 *
 * @package LocalShip\Util
 */

declare(strict_types=1);

namespace LocalShip\Util;

/**
 * Tiny wrapper around stdin reads with default values and required-field semantics.
 *
 * The reader is injected so init flows can be exercised by tests without a real TTY. In
 * production code, use Prompt::stdinReader() to get a fgets-based implementation.
 */
final class Prompt
{
    /** @var callable(string):string */
    private $reader;

    /** @var callable(string):void */
    private $writer;

    /**
     * @param callable(string):string $reader Reads one line given a prompt.
     * @param callable(string):void   $writer Writes one line of user-facing output.
     */
    public function __construct(callable $reader, callable $writer)
    {
        $this->reader = $reader;
        $this->writer = $writer;
    }

    /**
     * Ask for a value, optionally with a default and a required check.
     *
     * @param string|null $default  Shown in brackets; returned if user just hits Enter.
     * @param bool        $required If true, blank answers reprompt rather than returning "".
     */
    public function ask(string $label, ?string $default = null, bool $required = true): string
    {
        while (true) {
            $hint  = null === $default ? '' : sprintf(' [%s]', $default);
            $line  = ($this->reader)(sprintf('%s%s: ', $label, $hint));
            $value = trim($line);

            if ('' === $value && null !== $default) {
                return $default;
            }
            if ('' !== $value) {
                return $value;
            }
            if (! $required) {
                return '';
            }

            ($this->writer)(sprintf('  (%s is required.)', $label));
        }
    }

    public function confirm(string $label, bool $default = true): bool
    {
        $hint  = $default ? 'Y/n' : 'y/N';
        $line  = ($this->reader)(sprintf('%s [%s]: ', $label, $hint));
        $value = strtolower(trim($line));

        if ('' === $value) {
            return $default;
        }

        return 'y' === $value || 'yes' === $value;
    }

    /**
     * Standard reader that uses fgets() on STDIN.
     *
     * @return callable(string):string
     */
    public static function stdinReader(): callable
    {
        return static function (string $prompt): string {
            fwrite(STDOUT, $prompt);
            $line = fgets(STDIN);

            return false === $line ? '' : $line;
        };
    }

    /**
     * Standard writer that uses WP_CLI::log().
     *
     * @return callable(string):void
     */
    public static function wpCliWriter(): callable
    {
        return static function (string $line): void {
            \WP_CLI::log($line);
        };
    }
}
