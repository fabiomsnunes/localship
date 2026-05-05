<?php

/**
 * The hostname-typing confirmation prompt for protected envs.
 *
 * @package LocalShip\Safety
 */

declare(strict_types=1);

namespace LocalShip\Safety;

use LocalShip\Config\EnvConfig;

/**
 * The single thing standing between an operator and a wiped production site.
 *
 * Asks the user to type the target hostname (e.g. "client-x.com"). Anything else — `yes`,
 * Enter, a typo, the wrong site — aborts. The hostname is per-site, which defeats muscle
 * memory: if you typed the wrong env, the hostname you're being asked for won't match
 * what you expected.
 */
final class HostnameConfirm
{
    /** @var callable(string):string */
    private $reader;

    /** @var callable(string):void */
    private $log;

    /**
     * @param callable(string):string $reader Reads a line from the user given a prompt string.
     * @param callable(string):void   $log    Sink for warning/abort messages.
     */
    public function __construct(callable $reader, callable $log)
    {
        $this->reader = $reader;
        $this->log    = $log;
    }

    /**
     * Prompt the user and verify the typed string matches the env's hostname.
     *
     * @return bool True if the typed string matches; false otherwise.
     */
    public function confirm(EnvConfig $env, string $operation = 'push'): bool
    {
        $expected = $env->hostname();
        $header   = sprintf(
            'You are about to %s %s at %s.',
            strtoupper($operation),
            strtoupper($env->name()),
            $env->url()
        );
        ( $this->log )($header);

        $prompt = sprintf('Type the hostname to confirm (%s): ', $expected);
        $typed  = trim(( $this->reader )($prompt));

        if ($typed === $expected) {
            return true;
        }

        ( $this->log )(sprintf('Hostname mismatch (got "%s", expected "%s"). Aborting.', $typed, $expected));

        return false;
    }

    /**
     * Standard reader implementation that uses fgets() on STDIN.
     *
     * Wrapped as a callable so tests can substitute a fake reader.
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
}
