<?php

/**
 * Tests for the Rsync command builder.
 *
 * @package LocalShip\Tests\Unit\Process
 */

declare(strict_types=1);

namespace LocalShip\Tests\Unit\Process;

use LocalShip\Process\Rsync;
use PHPUnit\Framework\TestCase;

final class RsyncTest extends TestCase
{
    public function testBuildTransferIncludesArchiveAndDeleteFlags(): void
    {
        $argv = Rsync::buildTransfer('/src', '/dest', '/tmp/excludes');

        self::assertSame('rsync', $argv[0]);
        self::assertContains('-a', $argv);
        self::assertContains('--delete', $argv);
    }

    public function testBuildTransferAppendsExcludeFromFlag(): void
    {
        $argv = Rsync::buildTransfer('/src', '/dest', '/tmp/excludes');

        self::assertContains('--exclude-from=/tmp/excludes', $argv);
    }

    public function testBuildTransferAddsTrailingSlashToLocalPaths(): void
    {
        $argv = Rsync::buildTransfer('/src', '/dest', '/tmp/excludes');

        // Source and destination are the last two elements.
        self::assertSame('/src/', $argv[ count($argv) - 2 ]);
        self::assertSame('/dest/', $argv[ count($argv) - 1 ]);
    }

    public function testBuildTransferPreservesExistingTrailingSlash(): void
    {
        $argv = Rsync::buildTransfer('/src/', '/dest/', '/tmp/excludes');

        self::assertSame('/src/', $argv[ count($argv) - 2 ]);
        self::assertSame('/dest/', $argv[ count($argv) - 1 ]);
    }

    public function testBuildTransferAddsTrailingSlashToRemotePathPart(): void
    {
        $argv = Rsync::buildTransfer('/src', 'user@host:/var/www/site', '/tmp/excludes');

        self::assertSame('user@host:/var/www/site/', $argv[ count($argv) - 1 ]);
    }

    public function testBuildTransferUsesBatchModeSshByDefault(): void
    {
        $argv = Rsync::buildTransfer('/src', '/dest', '/tmp/excludes');
        $idx  = array_search('-e', $argv, true);

        self::assertNotFalse($idx);
        self::assertSame('ssh -o BatchMode=yes', $argv[ $idx + 1 ]);
    }

    public function testBuildTransferIncludesSshKeyWhenProvided(): void
    {
        $argv = Rsync::buildTransfer('/src', '/dest', '/tmp/excludes', '/Users/me/.ssh/id_ed25519');
        $idx  = array_search('-e', $argv, true);

        self::assertSame('ssh -i /Users/me/.ssh/id_ed25519 -o BatchMode=yes', $argv[ $idx + 1 ]);
    }

    public function testBuildTransferQuotesSshKeyWithSpaces(): void
    {
        $argv = Rsync::buildTransfer('/src', '/dest', '/tmp/excludes', '/Users/me/My Keys/id');
        $idx  = array_search('-e', $argv, true);

        self::assertSame("ssh -i '/Users/me/My Keys/id' -o BatchMode=yes", $argv[ $idx + 1 ]);
    }

    public function testBuildTransferAppendsExtraFlagsBeforeExcludesAndPaths(): void
    {
        $argv = Rsync::buildTransfer('/src', '/dest', '/tmp/excludes', null, [ '--bwlimit=2000', '-n' ]);

        self::assertContains('--bwlimit=2000', $argv);
        self::assertContains('-n', $argv);
        // Excludes-from must still come before the paths.
        $excludeIdx = array_search('--exclude-from=/tmp/excludes', $argv, true);
        $srcIdx     = count($argv) - 2;
        self::assertLessThan($srcIdx, $excludeIdx);
    }

    public function testRemoteEndpointAppendsSubPath(): void
    {
        $endpoint = Rsync::remoteEndpoint(
            'user@host.example.com:/home/user/webapps/client-x',
            'wp-content/uploads'
        );

        self::assertSame('user@host.example.com:/home/user/webapps/client-x/wp-content/uploads', $endpoint);
    }

    public function testRemoteEndpointHandlesTrailingSlashOnBase(): void
    {
        $endpoint = Rsync::remoteEndpoint(
            'user@host:/var/www/site/',
            '/wp-content/themes/'
        );

        self::assertSame('user@host:/var/www/site/wp-content/themes/', $endpoint);
    }

    public function testRemoteEndpointWithEmptySubPathReturnsBase(): void
    {
        $endpoint = Rsync::remoteEndpoint('user@host:/var/www/site', '');

        self::assertSame('user@host:/var/www/site', $endpoint);
    }

    public function testRemoteEndpointThrowsOnMissingColon(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Rsync::remoteEndpoint('user@host-without-path', 'sub');
    }

    public function testLocalEndpointJoinsPaths(): void
    {
        self::assertSame(
            '/Users/me/Local Sites/x/app/public/wp-content/uploads',
            Rsync::localEndpoint('/Users/me/Local Sites/x/app/public', 'wp-content/uploads')
        );
    }
}
