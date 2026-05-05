<?php

declare(strict_types=1);

namespace LocalShip\Tests\Unit\Util;

use LocalShip\Util\LocalDetect;
use PHPUnit\Framework\TestCase;

final class LocalDetectTest extends TestCase
{
    public function testDetectsFromWpRoot(): void
    {
        $result = LocalDetect::fromCwd('/Users/me/Local Sites/client-x/app/public');

        self::assertSame('/Users/me/Local Sites/client-x/app/public', $result['path']);
        self::assertSame('http://client-x.local', $result['url']);
        self::assertSame('client-x', $result['site_name']);
    }

    public function testDetectsFromSubdirectoryOfWpRoot(): void
    {
        $result = LocalDetect::fromCwd('/Users/me/Local Sites/client-x/app/public/wp-content/themes/foo');

        self::assertSame('/Users/me/Local Sites/client-x/app/public', $result['path']);
        self::assertSame('client-x', $result['site_name']);
    }

    public function testReturnsNullsForUnrelatedPath(): void
    {
        $result = LocalDetect::fromCwd('/var/www/some-other-site');

        self::assertNull($result['path']);
        self::assertNull($result['url']);
        self::assertNull($result['site_name']);
    }

    public function testHandlesSiteNameWithSpecialCharacters(): void
    {
        $result = LocalDetect::fromCwd('/Users/me/Local Sites/wp_client.123/app/public');

        self::assertSame('wp_client.123', $result['site_name']);
        self::assertSame('http://wp_client.123.local', $result['url']);
    }

    public function testTrailingSlashOnCwdIsTolerated(): void
    {
        $result = LocalDetect::fromCwd('/Users/me/Local Sites/client-x/app/public/');

        self::assertSame('/Users/me/Local Sites/client-x/app/public', $result['path']);
    }

    public function testReturnsNullsWhenAppPublicMissing(): void
    {
        $result = LocalDetect::fromCwd('/Users/me/Local Sites/client-x');

        self::assertNull($result['path']);
    }
}
