<?php

/**
 * Status checker — config summary + per-env connectivity probes.
 *
 * @package LocalShip\Flow
 */

declare(strict_types=1);

namespace LocalShip\Flow;

use LocalShip\Config\EnvConfig;
use LocalShip\Config\SiteConfig;
use LocalShip\Exception\ProcessException;
use LocalShip\Process\Runner;

/**
 * For each remote env, probe via `wp @<env> core version`. WP-CLI handles SSH transparently
 * via its alias system, so a successful probe verifies both the SSH path and the remote
 * WP-CLI install in one shot. Failures are caught per-env so one broken env doesn't hide the
 * state of the others.
 *
 * The local env only needs a path-exists check — there's nothing to probe over the network.
 */
final class StatusFlow
{
    /** @var Runner */
    private $runner;

    /** @var callable(string):void */
    private $log;

    public function __construct(Runner $runner, callable $log)
    {
        $this->runner = $runner;
        $this->log    = $log;
    }

    public function run(SiteConfig $config): bool
    {
        ($this->log)('LocalShip status:');
        ($this->log)('');

        $allOk = $this->reportLocal($config->local());

        foreach ($config->remoteEnvNames() as $name) {
            ($this->log)('');
            $allOk = $this->reportRemote($config->env($name)) && $allOk;
        }

        ($this->log)('');
        ($this->log)($allOk ? 'All checks passed.' : 'One or more checks failed.');

        return $allOk;
    }

    private function reportLocal(EnvConfig $env): bool
    {
        ($this->log)(sprintf('local  -> %s', $env->url()));
        ($this->log)(sprintf('         %s', $env->path()));
        if (! is_dir($env->path())) {
            ($this->log)(sprintf('  [FAIL] local path does not exist: %s', $env->path()));
            return false;
        }
        ($this->log)('  [ok]   path exists');

        return true;
    }

    private function reportRemote(EnvConfig $env): bool
    {
        $protectedTag = $env->isProtected() ? ' (protected)' : '';
        ($this->log)(sprintf('%s -> %s%s', $env->name(), $env->url(), $protectedTag));
        ($this->log)(sprintf('         alias %s', (string) $env->sshAlias()));

        $alias = $env->sshAlias();
        if (null === $alias) {
            return true;
        }

        try {
            $version = $this->runner->run(['wp', $alias, 'core', 'version'], null, null, 30);
            ($this->log)(sprintf('  [ok]   reachable (WP %s on remote)', trim($version)));
            return true;
        } catch (ProcessException $e) {
            ($this->log)('  [FAIL] ' . $this->oneLine($e->getMessage()));
            return false;
        }
    }

    private function oneLine(string $msg): string
    {
        $msg = preg_replace('/\s+/', ' ', $msg);
        return trim(null === $msg ? '' : $msg);
    }
}
