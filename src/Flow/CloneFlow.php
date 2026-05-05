<?php

/**
 * Clone an empty Local site from a remote env: init-if-needed, then pull everything.
 *
 * @package LocalShip\Flow
 */

declare(strict_types=1);

namespace LocalShip\Flow;

use LocalShip\Config\ConfigLoader;
use LocalShip\Config\SiteConfig;
use LocalShip\Util\Prompt;

/**
 * Drives the day-1 onboarding for a new client site:
 *
 *   1. If `wp-cli.yml` is missing, run InitFlow interactively and write it.
 *   2. Reload the resulting config.
 *   3. Run PullFlow with $skipLocalBackup=true and full default scope.
 *
 * The skipLocalBackup flag matters: a freshly created Local site has nothing meaningful to
 * back up, and emitting an empty pre-pull dump only adds noise.
 */
final class CloneFlow
{
    /** @var InitFlow */
    private $initFlow;

    /** @var PullFlow */
    private $pullFlow;

    /** @var callable(string):void */
    private $log;

    public function __construct(InitFlow $initFlow, PullFlow $pullFlow, callable $log)
    {
        $this->initFlow = $initFlow;
        $this->pullFlow = $pullFlow;
        $this->log      = $log;
    }

    /**
     * Run the clone.
     *
     * Returns the loaded SiteConfig so the command layer can print "next steps" hints
     * referencing concrete env names.
     */
    public function run(string $cwd, string $envName, Scope $scope, ?SiteConfig $existing = null): SiteConfig
    {
        $config = $existing;

        if (null === $config) {
            ($this->log)('No wp-cli.yml found; running init first.');
            $generated  = $this->initFlow->gather($cwd);
            $configPath = $cwd . '/wp-cli.yml';
            $yaml       = $this->initFlow->render($generated);
            if (false === file_put_contents($configPath, $yaml)) {
                throw new \RuntimeException(sprintf('Failed to write %s.', $configPath));
            }
            ($this->log)(sprintf('Wrote %s.', $configPath));
            $config = (new ConfigLoader())->loadFromArray($generated);
        }

        if (! $config->hasEnv($envName)) {
            throw new \InvalidArgumentException(sprintf('Unknown env "%s".', $envName));
        }

        $this->pullFlow->run($config, $envName, $scope, true);

        return $config;
    }
}
