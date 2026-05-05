<?php

/**
 * Resolves --only / --exclude / --db-only / --files-only into a normalized scope.
 *
 * @package LocalShip\Flow
 */

declare(strict_types=1);

namespace LocalShip\Flow;

use LocalShip\Exception\ConfigException;

/**
 * The set of things a single push/pull/clone operation will move.
 *
 * Tokens (v1): db, uploads, plugins, themes.
 *
 * Defaults differ per command: pull defaults to db+uploads (routine refresh), push defaults
 * to db+uploads+plugins+themes (full sync), clone defaults to the same as push. Callers
 * pass the default in; flags shrink or expand it.
 */
final class Scope
{
    public const TOKEN_DB      = 'db';
    public const TOKEN_UPLOADS = 'uploads';
    public const TOKEN_PLUGINS = 'plugins';
    public const TOKEN_THEMES  = 'themes';

    public const ALL_TOKENS = [
        self::TOKEN_DB,
        self::TOKEN_UPLOADS,
        self::TOKEN_PLUGINS,
        self::TOKEN_THEMES,
    ];

    /** @var array<int,string> */
    private $tokens;

    /**
     * @param array<int,string> $tokens
     */
    private function __construct(array $tokens)
    {
        $unique = array_values(array_unique($tokens));
        sort($unique);
        $this->tokens = $unique;
    }

    /**
     * Build a Scope from the raw flag map produced by WP-CLI's assoc_args parser.
     *
     * @param array<int,string>    $defaults  Tokens active when no flags are passed.
     * @param array<string,string> $assocArgs Raw associative args (key => value, or key => "" for boolean flags).
     */
    public static function fromAssocArgs(array $defaults, array $assocArgs): self
    {
        // --db-only / --files-only are shortcuts that fully replace the scope.
        $dbOnly    = array_key_exists('db-only', $assocArgs);
        $filesOnly = array_key_exists('files-only', $assocArgs);

        if ($dbOnly && $filesOnly) {
            throw new ConfigException('--db-only and --files-only are mutually exclusive.');
        }

        if ($dbOnly) {
            return new self([self::TOKEN_DB]);
        }
        if ($filesOnly) {
            return new self([self::TOKEN_UPLOADS, self::TOKEN_PLUGINS, self::TOKEN_THEMES]);
        }

        $only    = self::parseList($assocArgs['only'] ?? null, '--only');
        $exclude = self::parseList($assocArgs['exclude'] ?? null, '--exclude');

        $tokens = [] !== $only ? $only : $defaults;
        $tokens = array_values(array_diff($tokens, $exclude));

        if ([] === $tokens) {
            throw new ConfigException('Scope is empty after applying flags. Nothing to do.');
        }

        return new self($tokens);
    }

    /**
     * @return array<int,string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    public function has(string $token): bool
    {
        return in_array($token, $this->tokens, true);
    }

    /**
     * Return rsync-relative subpaths corresponding to the file-tier tokens in this scope.
     *
     * Used by PushFlow/PullFlow to issue one rsync invocation per file tier rather than a
     * single one with complex includes — simpler, easier to dry-run, easier to debug.
     *
     * @return array<int,string> Relative paths within the WP root (no leading slash).
     */
    public function fileSubPaths(): array
    {
        $paths = [];
        if ($this->has(self::TOKEN_UPLOADS)) {
            $paths[] = 'wp-content/uploads';
        }
        if ($this->has(self::TOKEN_PLUGINS)) {
            $paths[] = 'wp-content/plugins';
        }
        if ($this->has(self::TOKEN_THEMES)) {
            $paths[] = 'wp-content/themes';
        }

        return $paths;
    }

    /**
     * @param mixed $raw
     *
     * @return array<int,string>
     */
    private static function parseList($raw, string $flagName): array
    {
        if (null === $raw || '' === $raw) {
            return [];
        }
        if (! is_string($raw)) {
            throw new ConfigException(sprintf('%s expects a comma-separated string.', $flagName));
        }

        $tokens = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static function ($t) {
                return '' !== $t;
            }
        ));

        foreach ($tokens as $token) {
            if (! in_array($token, self::ALL_TOKENS, true)) {
                throw new ConfigException(sprintf(
                    '%s: unknown token "%s". Valid tokens: %s.',
                    $flagName,
                    $token,
                    implode(', ', self::ALL_TOKENS)
                ));
            }
        }

        return $tokens;
    }
}
