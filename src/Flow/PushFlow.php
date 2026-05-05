<?php

/**
 * Push DB + files from local to a remote env.
 *
 * @package LocalShip\Flow
 */

declare(strict_types=1);

namespace LocalShip\Flow;

use LocalShip\Config\SiteConfig;
use LocalShip\Process\Rsync;
use LocalShip\Process\Runner;
use LocalShip\Safety\Excludes;
use LocalShip\Safety\HostnameConfirm;
use LocalShip\Safety\Lock;

/**
 * The §7.2 sequence:
 *
 *   1. (caller already verified config + invoked HostnameConfirm for protected envs)
 *   2. Acquire site lock
 *   3. Remote DB backup (unless --no-backup; refused on protected envs)
 *   4. Export local DB to tempfile
 *   5. Import to remote via WP-CLI alias
 *   6. Search-replace: local URL  -> remote URL (--all-tables)
 *   7. Search-replace: local path -> remote path (--all-tables)
 *   8. Rsync each file-tier in scope
 *   9. Flush remote caches
 */
final class PushFlow
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

    public function run(SiteConfig $config, string $envName, Scope $scope, bool $skipBackup): void
    {
        $local  = $config->local();
        $remote = $config->env($envName);
        $alias  = $remote->sshAlias();
        if (null === $alias) {
            throw new \InvalidArgumentException(sprintf('Cannot push to local-only env %s.', $envName));
        }

        if ($skipBackup && $remote->isProtected()) {
            throw new \InvalidArgumentException(
                sprintf('--no-backup is refused on protected envs. (%s is protected.)', $envName)
            );
        }

        ($this->log)(sprintf(
            'Pushing local -> %s. Scope: %s.',
            $envName,
            implode(', ', $scope->tokens())
        ));

        $lock = new Lock($local->path());
        $lock->acquire();
        $excludesFile = null;
        $localDump    = null;

        try {
            if ($scope->has(Scope::TOKEN_DB)) {
                if (! $skipBackup) {
                    $this->backupRemoteDb($alias);
                }
                $localDump = $this->exportLocalDb($local->path());
                $this->importRemoteDb($alias, $localDump);
                $this->searchReplaceRemote($alias, $local->url(), $remote->url());
                $this->searchReplaceRemote($alias, $local->path(), $remote->path());
            }

            $subPaths = $scope->fileSubPaths();
            if ([] !== $subPaths) {
                $excludesFile = $this->excludes->writeMerged($config);
                foreach ($subPaths as $sub) {
                    $this->rsyncUp($alias, $local->path(), $remote->path(), $sub, $excludesFile);
                }
            }

            $this->flushRemoteCaches($alias);
        } finally {
            if (null !== $localDump && is_file($localDump)) {
                @unlink($localDump);
            }
            if (null !== $excludesFile && is_file($excludesFile)) {
                @unlink($excludesFile);
            }
            $lock->release();
        }

        ($this->log)('Push complete.');
    }

    /**
     * Run the hostname-typing prompt for this env, returning whether the operator confirmed.
     */
    public static function confirmIfProtected(
        SiteConfig $config,
        string $envName,
        HostnameConfirm $confirm
    ): bool {
        $env = $config->env($envName);
        if (! $env->isProtected()) {
            return true;
        }

        return $confirm->confirm($env, 'push');
    }

    private function backupRemoteDb(string $alias): void
    {
        $stamp  = gmdate('Ymd-His');
        $target = sprintf('wp-content/backups/backup-%s.sql', $stamp);
        ($this->log)(sprintf('Backing up remote DB to %s.', $target));
        // mkdir -p in case wp-content/backups doesn't exist yet on the remote.
        $this->runner->run(
            ['wp', $alias, 'eval', sprintf('@mkdir(\'wp-content/backups\', 0775, true);')]
        );
        $this->runner->run(['wp', $alias, 'db', 'export', $target]);
    }

    private function exportLocalDb(string $localPath): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'localship-local-db-') . '.sql';
        ($this->log)('Exporting local DB.');
        $this->runner->run(['wp', 'db', 'export', $tmp], $localPath);

        return $tmp;
    }

    private function importRemoteDb(string $alias, string $dump): void
    {
        ($this->log)(sprintf('Importing into remote DB via %s.', $alias));
        $this->runner->run(['wp', $alias, 'db', 'import', $dump]);
    }

    private function searchReplaceRemote(string $alias, string $from, string $to): void
    {
        if ($from === $to) {
            ($this->log)(sprintf('Skip search-replace (identical): %s', $from));
            return;
        }
        ($this->log)(sprintf('Search-replace on remote: %s  ->  %s', $from, $to));
        $this->runner->run(
            [
                'wp',
                $alias,
                'search-replace',
                $from,
                $to,
                '--all-tables',
                '--skip-columns=guid',
                '--report-changed-only',
            ]
        );
    }

    private function rsyncUp(string $alias, string $localBase, string $remoteBase, string $subPath, string $excludesFile): void
    {
        $sshTarget = $this->aliasToSshTarget($alias);
        $source    = Rsync::localEndpoint($localBase, $subPath);
        $dest      = Rsync::remoteEndpoint($sshTarget . ':' . $remoteBase, $subPath);

        ($this->log)(sprintf('Rsync %s  ->  %s:%s/%s', $source, $sshTarget, rtrim($remoteBase, '/'), $subPath));
        $this->runner->runStreaming(Rsync::buildTransfer($source, $dest, $excludesFile));
    }

    private function flushRemoteCaches(string $alias): void
    {
        ($this->log)('Flushing remote caches.');
        try {
            $this->runner->run(['wp', $alias, 'cache', 'flush']);
        } catch (\Throwable $e) {
            ($this->log)('  (remote cache flush failed: ' . trim($e->getMessage()) . ')');
        }
        try {
            $this->runner->run(['wp', $alias, 'rewrite', 'flush']);
        } catch (\Throwable $e) {
            ($this->log)('  (remote rewrite flush failed: ' . trim($e->getMessage()) . ')');
        }
    }

    private function aliasToSshTarget(string $alias): string
    {
        $raw = $this->runner->run(['wp', 'cli', 'alias', 'get', $alias]);
        if (1 === preg_match('/^\s*ssh:\s*(.+)$/m', $raw, $m)) {
            $sshValue = trim($m[1]);
        } else {
            $sshValue = trim($raw);
        }
        $atPos = strpos($sshValue, '@');
        $colon = strpos($sshValue, ':', false === $atPos ? 0 : $atPos + 1);

        return false === $colon ? $sshValue : substr($sshValue, 0, $colon);
    }
}
