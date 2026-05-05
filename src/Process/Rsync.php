<?php

/**
 * Builds rsync argv arrays for LocalShip's push/pull/clone operations.
 *
 * @package LocalShip\Process
 */

declare(strict_types=1);

namespace LocalShip\Process;

/**
 * Pure command builder — does not execute anything. Pass the result into Runner::runStreaming().
 *
 * Path semantics:
 *   - Source and destination paths are treated as directories, with a trailing slash appended
 *     when missing so rsync syncs the contents (not the directory itself).
 *   - For remote endpoints, the env's SSH alias is rendered as `ssh-host:path` using the
 *     `ssh` field from the alias (caller passes it in).
 *
 * Excludes are written to a tempfile and passed via --exclude-from to avoid argv length limits
 * and quoting issues with the shared default list.
 */
final class Rsync
{
    private const BASE_FLAGS = [
        '-a',          // archive (preserve perms, times, symlinks, recursion)
        '--delete',    // mirror: remove files on the destination not present at source
        '--human-readable',
        '--info=stats2,progress2',
    ];

    /**
     * Build an rsync argv for transferring one directory.
     *
     * @param string             $source         Local path or "user@host:path" for remote source.
     * @param string             $destination    Local path or "user@host:path" for remote destination.
     * @param string             $excludesFile   Path to a file containing one rsync pattern per line.
     * @param string|null        $sshKey         Optional path to an SSH private key.
     * @param array<int, string> $extraFlags     Additional flags appended after BASE_FLAGS.
     *
     * @return array<int, string>
     */
    public static function buildTransfer(
        string $source,
        string $destination,
        string $excludesFile,
        ?string $sshKey = null,
        array $extraFlags = []
    ): array {
        $argv   = [ 'rsync' ];
        $argv   = array_merge($argv, self::BASE_FLAGS);
        $argv   = array_merge($argv, $extraFlags);
        $argv[] = '--exclude-from=' . $excludesFile;

        if (null !== $sshKey) {
            $argv[] = '-e';
            $argv[] = 'ssh -i ' . self::escapeForRshOption($sshKey) . ' -o BatchMode=yes';
        } else {
            $argv[] = '-e';
            $argv[] = 'ssh -o BatchMode=yes';
        }

        $argv[] = self::ensureTrailingSlash($source);
        $argv[] = self::ensureTrailingSlash($destination);

        return $argv;
    }

    /**
     * Render a remote endpoint as `user@host:/path/within/site` for use as a source or destination.
     *
     * Takes the SSH portion from a WP-CLI alias (the `user@host:/base/path` string) and
     * appends a sub-path (e.g. `wp-content/uploads`).
     *
     * @throws \InvalidArgumentException If the alias does not contain a host:path separator.
     */
    public static function remoteEndpoint(string $sshAlias, string $subPath): string
    {
        // WP-CLI's alias format is `user@host:/path` or `user@host:path`. Find the FIRST colon
        // that is not inside the user part (i.e. after the `@`).
        $atPos = strpos($sshAlias, '@');
        $colonSearchFrom = false === $atPos ? 0 : $atPos + 1;
        $colonPos = strpos($sshAlias, ':', $colonSearchFrom);

        if (false === $colonPos) {
            throw new \InvalidArgumentException(
                sprintf('SSH alias "%s" does not look like user@host:path.', $sshAlias)
            );
        }

        $hostPart = substr($sshAlias, 0, $colonPos);
        $basePath = substr($sshAlias, $colonPos + 1);

        $base    = rtrim($basePath, '/');
        $sub     = ltrim($subPath, '/');
        $joined  = '' === $sub ? $base : $base . '/' . $sub;

        return $hostPart . ':' . $joined;
    }

    /**
     * Build a local path within a WordPress install (e.g. wp-content/uploads).
     */
    public static function localEndpoint(string $basePath, string $subPath): string
    {
        $base = rtrim($basePath, '/');
        $sub  = ltrim($subPath, '/');

        return '' === $sub ? $base : $base . '/' . $sub;
    }

    private static function ensureTrailingSlash(string $path): string
    {
        // Remote endpoints contain a colon (host:path). Append the slash to the path part only.
        $colonPos = strpos($path, ':');
        if (false !== $colonPos && false !== strpos(substr($path, 0, $colonPos), '@')) {
            $head = substr($path, 0, $colonPos + 1);
            $tail = substr($path, $colonPos + 1);

            return $head . self::ensureTrailingSlashLocal($tail);
        }

        return self::ensureTrailingSlashLocal($path);
    }

    private static function ensureTrailingSlashLocal(string $path): string
    {
        if ('' === $path) {
            return $path;
        }
        return '/' === substr($path, -1) ? $path : $path . '/';
    }

    /**
     * Quote a key path for embedding in an `ssh -i <path>` command string.
     *
     * The whole `-e` argument is one shell string consumed by rsync, so paths with spaces or
     * apostrophes need single-quote escaping.
     */
    private static function escapeForRshOption(string $path): string
    {
        if (1 === preg_match('/^[A-Za-z0-9_\-.\/]+$/', $path)) {
            return $path;
        }

        return "'" . str_replace("'", "'\\''", $path) . "'";
    }
}
