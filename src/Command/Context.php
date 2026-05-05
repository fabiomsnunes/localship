<?php

/**
 * Shared command-side bootstrapping (config load, runner, logger).
 *
 * @package LocalShip\Command
 */

declare(strict_types=1);

namespace LocalShip\Command;

use LocalShip\Config\ConfigLoader;
use LocalShip\Config\SiteConfig;
use LocalShip\Exception\ConfigException;
use LocalShip\Process\Runner;
use WP_CLI;

/**
 * Centralises the boilerplate every subcommand other than `init` shares: locate
 * wp-cli.yml from cwd, parse it through ConfigLoader, build a Runner with --dry-run
 * threaded through, and provide a WP-CLI-friendly logger.
 */
final class Context
{
    /** @var SiteConfig */
    private $config;

    /** @var string */
    private $sitePath;

    /** @var Runner */
    private $runner;

    /** @var bool */
    private $dryRun;

    public function __construct(SiteConfig $config, string $sitePath, Runner $runner, bool $dryRun)
    {
        $this->config   = $config;
        $this->sitePath = $sitePath;
        $this->runner   = $runner;
        $this->dryRun   = $dryRun;
    }

    /**
     * Build a Context from the current process state and assoc_args.
     *
     * Aborts via WP_CLI::error() if no wp-cli.yml is reachable from cwd.
     *
     * @param array<string,string> $assocArgs
     */
    public static function bootstrap(array $assocArgs): self
    {
        $configPath = self::locateConfig();
        if (null === $configPath) {
            WP_CLI::error(
                'No wp-cli.yml found in the current directory or any parent. '
                . 'Run `wp localship init` to scaffold one.'
            );
        }

        try {
            $config = (new ConfigLoader())->loadFromFile($configPath);
        } catch (ConfigException $e) {
            WP_CLI::error($e->getMessage());
        }

        $sitePath = $config->local()->path();
        $dryRun   = array_key_exists('dry-run', $assocArgs);
        $logger   = static function (string $line): void {
            WP_CLI::log($line);
        };
        $runner   = new Runner($dryRun, $logger);

        return new self($config, $sitePath, $runner, $dryRun);
    }

    public function config(): SiteConfig
    {
        return $this->config;
    }

    public function runner(): Runner
    {
        return $this->runner;
    }

    public function sitePath(): string
    {
        return $this->sitePath;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * Walk up from cwd looking for wp-cli.yml. Returns null if none found.
     */
    private static function locateConfig(): ?string
    {
        $cwd = getcwd();
        if (false === $cwd) {
            return null;
        }
        $dir = $cwd;

        while (true) {
            $candidate = $dir . '/wp-cli.yml';
            if (is_file($candidate)) {
                return $candidate;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                return null;
            }
            $dir = $parent;
        }
    }
}
