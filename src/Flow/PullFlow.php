<?php

/**
 * Pull DB + files from a remote env to local.
 *
 * @package LocalShip\Flow
 */

declare(strict_types=1);

namespace LocalShip\Flow;

use LocalShip\Config\SiteConfig;
use LocalShip\Process\Rsync;
use LocalShip\Process\Runner;
use LocalShip\Safety\Excludes;
use LocalShip\Safety\Lock;

/**
 * The §7.3 sequence:
 *
 *   1. Acquire site lock
 *   2. (clone-mode skips this) local DB backup
 *   3. Export remote DB to a tempfile
 *   4. Import to local
 *   5. Search-replace: remote URL → local URL (--all-tables)
 *   6. Search-replace: remote path → local path (--all-tables)
 *   7. Rsync each file-tier in scope (uploads/plugins/themes)
 *   8. Flush local caches
 *
 * The clone command reuses this flow via the $skipLocalBackup flag — there's nothing
 * meaningful to back up when the local site is freshly created.
 */
final class PullFlow
{
    /** @var Runner */
    private $runner;

    /** @var Excludes */
    private $excludes;

    /** @var callable(string):void */
    private $log;

    public function __construct(Runner $runner, Excludes $excludes, callable $log)
    {
        $this->runner   = $runner;
        $this->excludes = $excludes;
        $this->log      = $log;
    }

    public function run(SiteConfig $config, string $envName, Scope $scope, bool $skipLocalBackup = false): void
    {
        $local  = $config->local();
        $remote = $config->env($envName);
        $alias  = $remote->sshAlias();
        if (null === $alias) {
            throw new \InvalidArgumentException(sprintf('Cannot pull from local-only env %s.', $envName));
        }

        ($this->log)(sprintf(
            'Pulling %s -> local. Scope: %s.',
            $envName,
            implode(', ', $scope->tokens())
        ));

        $lock = new Lock($local->path());
        $lock->acquire();
        $excludesFile = null;

        try {
            if ($scope->has(Scope::TOKEN_DB)) {
                if (! $skipLocalBackup) {
                    $this->backupLocalDb($local->path());
                }
                $remoteDump = $this->exportRemoteDb($alias);
                $this->importLocalDb($local->path(), $remoteDump);
                $this->searchReplace($local->path(), $remote->url(), $local->url());
                $this->searchReplace($local->path(), $remote->path(), $local->path());
                @unlink($remoteDump);
            }

            $subPaths = $scope->fileSubPaths();
            if ([] !== $subPaths) {
                $excludesFile = $this->excludes->writeMerged($config);
                foreach ($subPaths as $sub) {
                    $this->rsyncDown($alias, $remote->path(), $local->path(), $sub, $excludesFile);
                }
            }

            $this->flushLocalCaches($local->path());
        } finally {
            if (null !== $excludesFile && is_file($excludesFile)) {
                @unlink($excludesFile);
            }
            $lock->release();
        }

        ($this->log)('Pull complete.');
    }

    private function backupLocalDb(string $localPath): void
    {
        $stamp  = gmdate('Ymd-His');
        $target = $localPath . '/.localship-pre-pull-' . $stamp . '.sql';
        ($this->log)('Backing up local DB before import.');
        $this->runner->run(['wp', 'db', 'export', $target], $localPath);
    }

    private function exportRemoteDb(string $alias): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'localship-remote-db-') . '.sql';
        ($this->log)(sprintf('Exporting remote DB via %s.', $alias));
        $this->runner->run(['wp', $alias, 'db', 'export', $tmp]);

        return $tmp;
    }

    private function importLocalDb(string $localPath, string $dump): void
    {
        ($this->log)('Importing into local DB.');
        $this->runner->run(['wp', 'db', 'import', $dump], $localPath);
    }

    private function searchReplace(string $localPath, string $from, string $to): void
    {
        if ($from === $to) {
            ($this->log)(sprintf('Skip search-replace (identical): %s', $from));
            return;
        }
        ($this->log)(sprintf('Search-replace: %s  ->  %s', $from, $to));
        $this->runner->run(
            [
                'wp',
                'search-replace',
                $from,
                $to,
                '--all-tables',
                '--skip-columns=guid',
                '--report-changed-only',
            ],
            $localPath
        );
    }

    private function rsyncDown(string $alias, string $remoteBase, string $localBase, string $subPath, string $excludesFile): void
    {
        $sshTarget = $this->aliasToSshTarget($alias);
        $source    = Rsync::remoteEndpoint($sshTarget . ':' . $remoteBase, $subPath);
        $dest      = Rsync::localEndpoint($localBase, $subPath);

        ($this->log)(sprintf('Rsync %s  <-  %s:%s/%s', $dest, $sshTarget, rtrim($remoteBase, '/'), $subPath));
        $this->runner->runStreaming(Rsync::buildTransfer($source, $dest, $excludesFile));
    }

    private function flushLocalCaches(string $localPath): void
    {
        ($this->log)('Flushing local caches.');
        // best-effort each: cache flush + rewrite flush. Don't fail the whole pull if one
        // misbehaves on a fresh install.
        try {
            $this->runner->run(['wp', 'cache', 'flush'], $localPath);
        } catch (\Throwable $e) {
            ($this->log)('  (cache flush failed: ' . trim($e->getMessage()) . ')');
        }
        try {
            $this->runner->run(['wp', 'rewrite', 'flush'], $localPath);
        } catch (\Throwable $e) {
            ($this->log)('  (rewrite flush failed: ' . trim($e->getMessage()) . ')');
        }
    }

    /**
     * Convert a WP-CLI `@alias` name to the underlying user@host SSH target.
     *
     * For now we re-shell to `wp cli alias get <alias>` rather than re-parsing wp-cli.yml,
     * because WP-CLI's resolution handles the various legal forms. If the alias contains a
     * path component (`user@host:/path`), only the user@host part is returned — the path
     * is supplied separately by the caller.
     */
    private function aliasToSshTarget(string $alias): string
    {
        $raw = $this->runner->run(['wp', 'cli', 'alias', 'get', $alias]);
        // `cli alias get` outputs YAML lines like "ssh: user@host:/path".
        if (1 === preg_match('/^\s*ssh:\s*(.+)$/m', $raw, $m)) {
            $sshValue = trim($m[1]);
        } else {
            $sshValue = trim($raw);
        }

        // Strip any trailing :path so we have just user@host.
        $atPos = strpos($sshValue, '@');
        $colon = strpos($sshValue, ':', false === $atPos ? 0 : $atPos + 1);

        return false === $colon ? $sshValue : substr($sshValue, 0, $colon);
    }
}
