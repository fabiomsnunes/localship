<?php

/**
 * Tests for Runner.
 *
 * @package LocalShip\Tests\Unit\Process
 */

declare(strict_types=1);

namespace LocalShip\Tests\Unit\Process;

use LocalShip\Exception\ProcessException;
use LocalShip\Process\Runner;
use PHPUnit\Framework\TestCase;

final class RunnerTest extends TestCase
{
    public function testDryRunDoesNotExecuteAndPrefixesLog(): void
    {
        $logged = [];
        $runner = new Runner(true, function ($line) use (&$logged): void {
            $logged[] = $line;
        });

        $out = $runner->run([ 'sh', '-c', 'echo SHOULD_NOT_RUN > /tmp/localship-test-marker' ]);

        self::assertSame('', $out);
        self::assertFileDoesNotExist('/tmp/localship-test-marker');
        self::assertCount(1, $logged);
        self::assertStringStartsWith('[dry-run] ', $logged[0]);
        self::assertStringContainsString('SHOULD_NOT_RUN', $logged[0]);
    }

    public function testRealRunReturnsStdout(): void
    {
        $runner = new Runner(false, static function (): void {
        });
        $out    = $runner->run([ 'printf', '%s', 'hello' ]);

        self::assertSame('hello', $out);
    }

    public function testRealRunThrowsOnNonZeroExit(): void
    {
        $runner = new Runner(false, static function (): void {
        });

        $this->expectException(ProcessException::class);
        $runner->run([ 'sh', '-c', 'exit 7' ]);
    }

    public function testRenderForLogQuotesArgsWithSpaces(): void
    {
        $rendered = Runner::renderForLog([ 'rsync', '-a', '/Users/me/Local Sites/x/', 'host:/var/www/x/' ]);

        self::assertStringContainsString("'/Users/me/Local Sites/x/'", $rendered);
    }

    public function testRenderForLogLeavesSimpleArgsUnquoted(): void
    {
        $rendered = Runner::renderForLog([ 'wp', 'db', 'export', '/tmp/file.sql' ]);

        self::assertSame('wp db export /tmp/file.sql', $rendered);
    }

    public function testRenderForLogEscapesSingleQuotes(): void
    {
        $rendered = Runner::renderForLog([ 'echo', "it's fine" ]);

        self::assertStringContainsString("'it'\\''s fine'", $rendered);
    }

    public function testRenderForLogHandlesEmptyArg(): void
    {
        $rendered = Runner::renderForLog([ 'cmd', '' ]);

        self::assertSame("cmd ''", $rendered);
    }
}
