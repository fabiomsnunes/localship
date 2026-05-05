<?php

/**
 * The `wp localship` command surface.
 *
 * @package LocalShip\Command
 */

declare(strict_types=1);

namespace LocalShip\Command;

use LocalShip\Flow\InitFlow;
use LocalShip\Flow\StatusFlow;
use LocalShip\Util\Prompt;
use WP_CLI;

/**
 * Push, pull, and clone WordPress sites between Local by Flywheel and any SSH-accessible VPS.
 *
 * ## EXAMPLES
 *
 *     # Bootstrap a fresh local copy from a live site
 *     $ wp localship clone production
 *
 *     # Push DB + files to staging
 *     $ wp localship push staging
 *
 *     # Pull DB + uploads from production to refresh local data
 *     $ wp localship pull production
 */
final class LocalShipCommand
{
    /**
     * Interactively scaffold a `wp-cli.yml` (aliases + `localship:` block) for the current site.
     *
     * ## OPTIONS
     *
     * [--force]
     * : Overwrite an existing wp-cli.yml.
     *
     * ## EXAMPLES
     *
     *     $ cd "~/Local Sites/client-x/app/public" && wp localship init
     *
     * @when before_wp_load
     *
     * @param array<int,string>    $args       Positional args.
     * @param array<string,string> $assoc_args Associative args.
     */
    public function init(array $args, array $assoc_args): void
    {
        $cwd    = getcwd();
        if (false === $cwd) {
            WP_CLI::error('Could not determine current working directory.');
        }
        $target = $cwd . '/wp-cli.yml';
        $force  = array_key_exists('force', $assoc_args);

        if (file_exists($target) && ! $force) {
            WP_CLI::error(sprintf(
                'wp-cli.yml already exists at %s. Pass --force to overwrite.',
                $target
            ));
        }

        $prompt = new Prompt(
            Prompt::stdinReader(),
            static function (string $line): void {
                WP_CLI::log($line);
            }
        );
        $flow   = new InitFlow($prompt, static function (string $line): void {
            WP_CLI::log($line);
        });

        $config = $flow->gather($cwd);
        $yaml   = $flow->render($config);

        if (false === file_put_contents($target, $yaml)) {
            WP_CLI::error(sprintf('Failed to write %s.', $target));
        }

        WP_CLI::success(sprintf('Wrote %s.', $target));
        WP_CLI::log('Next: run `wp localship status` to verify connectivity.');
    }

    /**
     * Show the current site's config summary and run connectivity checks per env.
     *
     * ## EXAMPLES
     *
     *     $ wp localship status
     *
     * @when before_wp_load
     *
     * @param array<int,string>    $args       Positional args.
     * @param array<string,string> $assoc_args Associative args.
     */
    public function status(array $args, array $assoc_args): void
    {
        $context = Context::bootstrap($assoc_args);
        $flow    = new StatusFlow($context->runner(), static function (string $line): void {
            WP_CLI::log($line);
        });

        if (! $flow->run($context->config())) {
            WP_CLI::halt(1);
        }
    }

    /**
     * Bootstrap a fresh local copy of a remote site.
     *
     * Runs `init` if no config exists, then pulls everything (DB + uploads + plugins + themes)
     * and runs reverse search-replace.
     *
     * ## OPTIONS
     *
     * <env>
     * : Source environment to clone from (e.g. production, staging).
     *
     * [--only=<list>]
     * : Override default scope. Comma-separated tokens: db, uploads, plugins, themes.
     *
     * [--exclude=<list>]
     * : Tokens to remove from the default scope.
     *
     * [--dry-run]
     * : Print what would happen without making changes.
     *
     * ## EXAMPLES
     *
     *     # In an empty Local site directory
     *     $ wp localship clone production
     *
     * @when before_wp_load
     *
     * @param array<int,string>    $args       Positional args.
     * @param array<string,string> $assoc_args Associative args.
     */
    public function clone(array $args, array $assoc_args): void
    {
        WP_CLI::error('Not implemented yet. Coming in step 9 of the build.');
    }

    /**
     * Push DB + files to a remote env.
     *
     * Protected envs (default: production) require typing the target hostname before any
     * destructive step.
     *
     * ## OPTIONS
     *
     * <env>
     * : Target environment (e.g. staging, production).
     *
     * [--only=<list>]
     * : Comma-separated scope tokens: db, uploads, plugins, themes.
     *
     * [--exclude=<list>]
     * : Tokens to remove from the default scope.
     *
     * [--db-only]
     * : Alias for --only=db.
     *
     * [--files-only]
     * : Alias for --only=uploads,plugins,themes.
     *
     * [--dry-run]
     * : Print what would happen without making changes.
     *
     * [--no-backup]
     * : Skip the automatic remote DB backup. Refused on protected envs.
     *
     * [--yes-i-know]
     * : Bypass interactive prompts in non-TTY contexts.
     *
     * ## EXAMPLES
     *
     *     $ wp localship push staging
     *     $ wp localship push staging --only=db
     *     $ wp localship push production --dry-run
     *
     * @when before_wp_load
     *
     * @param array<int,string>    $args       Positional args.
     * @param array<string,string> $assoc_args Associative args.
     */
    public function push(array $args, array $assoc_args): void
    {
        WP_CLI::error('Not implemented yet. Coming in step 8 of the build.');
    }

    /**
     * Pull DB + uploads from a remote env to local.
     *
     * Default scope is `db,uploads` (routine refresh). Use `--only=db,uploads,plugins,themes`
     * for a full refresh, or `clone` for first-time bootstrap.
     *
     * ## OPTIONS
     *
     * <env>
     * : Source environment (e.g. staging, production).
     *
     * [--only=<list>]
     * : Comma-separated scope tokens: db, uploads, plugins, themes.
     *
     * [--exclude=<list>]
     * : Tokens to remove from the default scope.
     *
     * [--db-only]
     * : Alias for --only=db.
     *
     * [--files-only]
     * : Alias for --only=uploads,plugins,themes.
     *
     * [--dry-run]
     * : Print what would happen without making changes.
     *
     * ## EXAMPLES
     *
     *     $ wp localship pull staging
     *     $ wp localship pull production --only=db
     *
     * @when before_wp_load
     *
     * @param array<int,string>    $args       Positional args.
     * @param array<string,string> $assoc_args Associative args.
     */
    public function pull(array $args, array $assoc_args): void
    {
        WP_CLI::error('Not implemented yet. Coming in step 7 of the build.');
    }
}
