<?php

/**
 * Tests for HostnameConfirm.
 *
 * @package LocalShip\Tests\Unit\Safety
 */

declare(strict_types=1);

namespace LocalShip\Tests\Unit\Safety;

use LocalShip\Config\EnvConfig;
use LocalShip\Safety\HostnameConfirm;
use PHPUnit\Framework\TestCase;

final class HostnameConfirmTest extends TestCase
{
    private function prodEnv(): EnvConfig
    {
        return new EnvConfig(
            'production',
            'https://client-x.com',
            '/var/www/client-x',
            true,
            '@production'
        );
    }

    /**
     * @return array{0: callable(string):string, 1: array<int,string>, 2: array<int,string>}
     */
    private function makeFakes(string $userTypes): array
    {
        $prompts = [];
        $logs    = [];
        $reader  = function (string $prompt) use (&$prompts, $userTypes): string {
            $prompts[] = $prompt;
            return $userTypes;
        };
        $log     = function (string $line) use (&$logs): void {
            $logs[] = $line;
        };

        return [ $reader, $prompts, $logs, $log ];
    }

    public function testAcceptsExactHostnameMatch(): void
    {
        [ $reader, , $logs, $log ] = $this->makeFakes('client-x.com');
        $confirm                   = new HostnameConfirm($reader, $log);

        self::assertTrue($confirm->confirm($this->prodEnv()));
    }

    public function testAcceptsHostnameWithTrailingNewline(): void
    {
        [ $reader, , $logs, $log ] = $this->makeFakes("client-x.com\n");
        $confirm                   = new HostnameConfirm($reader, $log);

        self::assertTrue($confirm->confirm($this->prodEnv()));
    }

    public function testRejectsYesShortcut(): void
    {
        [ $reader, , , $log ] = $this->makeFakes('yes');
        $confirm              = new HostnameConfirm($reader, $log);

        self::assertFalse($confirm->confirm($this->prodEnv()));
    }

    public function testRejectsBlank(): void
    {
        [ $reader, , , $log ] = $this->makeFakes('');
        $confirm              = new HostnameConfirm($reader, $log);

        self::assertFalse($confirm->confirm($this->prodEnv()));
    }

    public function testRejectsHostnameOfDifferentSite(): void
    {
        [ $reader, , , $log ] = $this->makeFakes('other-site.com');
        $confirm              = new HostnameConfirm($reader, $log);

        self::assertFalse($confirm->confirm($this->prodEnv()));
    }

    public function testRejectsTrailingGarbage(): void
    {
        [ $reader, , , $log ] = $this->makeFakes('client-x.com extra');
        $confirm              = new HostnameConfirm($reader, $log);

        self::assertFalse($confirm->confirm($this->prodEnv()));
    }

    public function testPromptIncludesExpectedHostname(): void
    {
        $prompts = [];
        $reader  = function (string $prompt) use (&$prompts): string {
            $prompts[] = $prompt;
            return '';
        };
        $confirm = new HostnameConfirm($reader, static function (): void {
        });

        $confirm->confirm($this->prodEnv());

        self::assertNotEmpty($prompts);
        self::assertStringContainsString('client-x.com', $prompts[0]);
    }

    public function testHeaderIncludesEnvAndUrl(): void
    {
        $logs   = [];
        $reader = static function (): string {
            return '';
        };
        $log    = function (string $line) use (&$logs): void {
            $logs[] = $line;
        };

        ( new HostnameConfirm($reader, $log) )->confirm($this->prodEnv());

        $banner = $logs[0] ?? '';
        self::assertStringContainsString('PRODUCTION', $banner);
        self::assertStringContainsString('https://client-x.com', $banner);
    }
}
