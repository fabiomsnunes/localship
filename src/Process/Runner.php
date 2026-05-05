<?php

/**
 * Runs shell commands with consistent logging and a global --dry-run gate.
 *
 * @package LocalShip\Process
 */

declare(strict_types=1);

namespace LocalShip\Process;

use LocalShip\Exception\ProcessException;
use Symfony\Component\Process\Process;

/**
 * Every destructive shell call in LocalShip routes through Runner so that --dry-run can be
 * honoured uniformly: when in dry-run mode, the command is logged but never executed.
 *
 * Runner does not parse strings into args — callers always pass an argv array. This avoids
 * shell-injection bugs from interpolated paths/URLs.
 */
final class Runner
{
    /** @var bool */
    private $dryRun;

    /** @var callable(string):void */
    private $log;

    /**
     * @param bool                   $dryRun  When true, run() prints commands and returns "" without executing.
     * @param callable(string):void  $log     Sink for human-readable progress lines (e.g. WP_CLI::log).
     */
    public function __construct(bool $dryRun, callable $log)
    {
        $this->dryRun = $dryRun;
        $this->log    = $log;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * Run an argv-style command and return its stdout.
     *
     * @param array<int, string>          $argv    Command and arguments. argv[0] is the executable.
     * @param string|null                 $cwd     Working directory, or null for the parent's cwd.
     * @param array<string, string>|null  $env     Environment overrides, or null to inherit.
     * @param int                         $timeout Seconds before the process is killed. 0 = no limit.
     *
     * @throws ProcessException If the command exits non-zero (only when not in dry-run).
     */
    public function run(array $argv, ?string $cwd = null, ?array $env = null, int $timeout = 0): string
    {
        $pretty = self::renderForLog($argv);

        if ($this->dryRun) {
            ( $this->log )('[dry-run] ' . $pretty);

            return '';
        }

        ( $this->log )('$ ' . $pretty);

        $process = new Process($argv, $cwd, $env, null, 0 === $timeout ? null : (float) $timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessException(
                sprintf(
                    "Command failed (exit %d): %s\n%s",
                    $process->getExitCode() ?? -1,
                    $pretty,
                    trim($process->getErrorOutput())
                )
            );
        }

        return $process->getOutput();
    }

    /**
     * Run a command and stream its stdout/stderr to the log sink as it produces output.
     *
     * Used for long-running operations like rsync where intermediate progress matters.
     *
     * @param array<int, string> $argv
     *
     * @throws ProcessException If the command exits non-zero.
     */
    public function runStreaming(array $argv, ?string $cwd = null, ?array $env = null): void
    {
        $pretty = self::renderForLog($argv);

        if ($this->dryRun) {
            ( $this->log )('[dry-run] ' . $pretty);

            return;
        }

        ( $this->log )('$ ' . $pretty);

        $process = new Process($argv, $cwd, $env, null, null);
        $exit    = $process->run(function ($type, $buffer): void {
            ( $this->log )(rtrim($buffer, "\n"));
        });

        if (0 !== $exit) {
            throw new ProcessException(
                sprintf('Command failed (exit %d): %s', $exit, $pretty)
            );
        }
    }

    /**
     * Render an argv array as a single shell-quoted string for logging.
     *
     * Output is human-readable, NOT a faithful round-trip to a shell. It exists for log
     * lines and dry-run output. Real execution always uses the argv form.
     *
     * @param array<int, string> $argv
     */
    public static function renderForLog(array $argv): string
    {
        return implode(' ', array_map(static function ($part) {
            if ('' === $part) {
                return "''";
            }
            if (1 === preg_match('/^[A-Za-z0-9_\-.\/=:@%+]+$/', $part)) {
                return $part;
            }

            return "'" . str_replace("'", "'\\''", $part) . "'";
        }, $argv));
    }
}
