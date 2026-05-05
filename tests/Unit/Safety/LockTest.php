<?php

/**
 * Tests for Lock.
 *
 * @package LocalShip\Tests\Unit\Safety
 */

declare(strict_types=1);

namespace LocalShip\Tests\Unit\Safety;

use LocalShip\Exception\ProcessException;
use LocalShip\Safety\Lock;
use PHPUnit\Framework\TestCase;

final class LockTest extends TestCase
{
    /** @var string */
    private $tempSite;

    protected function setUp(): void
    {
        $this->tempSite = sys_get_temp_dir() . '/localship-lock-test-' . bin2hex(random_bytes(4));
        mkdir($this->tempSite);
    }

    protected function tearDown(): void
    {
        // Best-effort cleanup of the temp tree.
        $lockDir = $this->tempSite . '/.localship.lock';
        if (is_file($lockDir . '/pid')) {
            @unlink($lockDir . '/pid');
        }
        @rmdir($lockDir);
        @rmdir($this->tempSite);
    }

    public function testAcquireCreatesLockDir(): void
    {
        $lock = new Lock($this->tempSite);
        $lock->acquire();

        self::assertDirectoryExists($this->tempSite . '/.localship.lock');
        self::assertFileExists($this->tempSite . '/.localship.lock/pid');

        $lock->release();
    }

    public function testReleaseRemovesLockDir(): void
    {
        $lock = new Lock($this->tempSite);
        $lock->acquire();
        $lock->release();

        self::assertDirectoryDoesNotExist($this->tempSite . '/.localship.lock');
    }

    public function testSecondAcquireFailsWhileFirstIsHeld(): void
    {
        $first = new Lock($this->tempSite);
        $first->acquire();

        $second = new Lock($this->tempSite);
        $this->expectException(ProcessException::class);
        $this->expectExceptionMessage('already running');
        try {
            $second->acquire();
        } finally {
            $first->release();
        }
    }

    public function testThrowsWhenSitePathMissing(): void
    {
        $lock = new Lock($this->tempSite . '/does-not-exist');

        $this->expectException(ProcessException::class);
        $this->expectExceptionMessage('does not exist');
        $lock->acquire();
    }

    public function testReleaseIsIdempotent(): void
    {
        $lock = new Lock($this->tempSite);
        $lock->acquire();
        $lock->release();
        // Second release on a held=false lock should be a no-op, not an error.
        $lock->release();

        $this->expectNotToPerformAssertions();
    }
}
