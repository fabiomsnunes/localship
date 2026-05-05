<?php

/**
 * Per-site lockfile to prevent concurrent push/pull/clone operations on the same site.
 *
 * @package LocalShip\Safety
 */

declare(strict_types=1);

namespace LocalShip\Safety;

use LocalShip\Exception\ProcessException;

/**
 * Uses an mkdir-based lock — atomic on macOS and Linux without requiring `flock`. The lock
 * directory is created under the site path; releasing it removes the directory. A trap on
 * shutdown ensures the lock is released even if the process aborts.
 */
final class Lock
{
    /** @var string */
    private $sitePath;

    /** @var string */
    private $lockDir;

    /** @var bool */
    private $held = false;

    public function __construct(string $sitePath)
    {
        $this->sitePath = rtrim($sitePath, '/');
        $this->lockDir  = $this->sitePath . '/.localship.lock';
    }

    /**
     * Try to acquire the lock. Throws if another process already holds it.
     *
     * @throws ProcessException
     */
    public function acquire(): void
    {
        if (! is_dir($this->sitePath)) {
            throw new ProcessException(sprintf('Site path does not exist: %s', $this->sitePath));
        }

        // @ silences the warning that mkdir() emits when the directory already exists; we
        // detect that case from the return value and translate it into a typed exception.
        if (! @mkdir($this->lockDir, 0700)) {
            throw new ProcessException(
                sprintf(
                    'Another LocalShip operation is already running on this site (lock at %s). '
                    . 'If you are sure no other run is in progress, remove that directory and retry.',
                    $this->lockDir
                )
            );
        }

        file_put_contents($this->lockDir . '/pid', (string) getmypid());
        $this->held = true;

        // Release on normal shutdown; signal handlers (if pcntl is available) handle Ctrl-C.
        register_shutdown_function(function (): void {
            $this->release();
        });
    }

    public function release(): void
    {
        if (! $this->held) {
            return;
        }
        $pidFile = $this->lockDir . '/pid';
        if (is_file($pidFile)) {
            @unlink($pidFile);
        }
        @rmdir($this->lockDir);
        $this->held = false;
    }

    public function path(): string
    {
        return $this->lockDir;
    }
}
