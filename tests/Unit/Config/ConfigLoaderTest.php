<?php

/**
 * Tests for ConfigLoader.
 *
 * @package LocalShip\Tests\Unit\Config
 */

declare(strict_types=1);

namespace LocalShip\Tests\Unit\Config;

use LocalShip\Config\ConfigLoader;
use LocalShip\Exception\ConfigException;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function minimalRawConfig(): array
    {
        return [
            '@staging'    => [ 'ssh' => 'user@host:/path/to/staging' ],
            '@production' => [ 'ssh' => 'user@host:/path/to/prod' ],
            'localship'   => [
                'local'      => [
                    'url'  => 'http://client-x.local',
                    'path' => '/Users/fabio/Local Sites/client-x/app/public',
                ],
                'staging'    => [
                    'url'  => 'https://staging.client-x.com',
                    'path' => '/path/to/staging',
                ],
                'production' => [
                    'url'  => 'https://client-x.com',
                    'path' => '/path/to/prod',
                ],
            ],
        ];
    }

    public function testLoadsMinimalValidConfig(): void
    {
        $config = ( new ConfigLoader() )->loadFromArray($this->minimalRawConfig());

        self::assertSame('http://client-x.local', $config->local()->url());
        self::assertSame('https://staging.client-x.com', $config->env('staging')->url());
        self::assertSame('https://client-x.com', $config->env('production')->url());
        self::assertSame([ 'local', 'staging', 'production' ], $config->envNames());
        self::assertSame([ 'staging', 'production' ], $config->remoteEnvNames());
        self::assertNull($config->activeTheme());
        self::assertSame([], $config->excludesExtra());
    }

    public function testProtectedEnvsDefaultsToProductionWhenOmitted(): void
    {
        $config = ( new ConfigLoader() )->loadFromArray($this->minimalRawConfig());

        self::assertTrue($config->env('production')->isProtected());
        self::assertFalse($config->env('staging')->isProtected());
        self::assertFalse($config->local()->isProtected());
    }

    public function testCustomProtectedEnvsOverrideDefault(): void
    {
        $raw                                       = $this->minimalRawConfig();
        $raw['localship']['protected_envs']        = [ 'staging' ];
        $config                                    = ( new ConfigLoader() )->loadFromArray($raw);

        self::assertFalse($config->env('production')->isProtected());
        self::assertTrue($config->env('staging')->isProtected());
    }

    public function testThrowsWhenLocalshipBlockMissing(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing `localship:` block');

        ( new ConfigLoader() )->loadFromArray([ '@staging' => [ 'ssh' => 'user@host:/x' ] ]);
    }

    public function testThrowsWhenLocalEnvMissing(): void
    {
        $raw = $this->minimalRawConfig();
        unset($raw['localship']['local']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('`localship.local`');

        ( new ConfigLoader() )->loadFromArray($raw);
    }

    public function testThrowsWhenEnvMissingUrl(): void
    {
        $raw                                  = $this->minimalRawConfig();
        unset($raw['localship']['staging']['url']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('`localship.staging.url`');

        ( new ConfigLoader() )->loadFromArray($raw);
    }

    public function testThrowsWhenEnvMissingPath(): void
    {
        $raw                                   = $this->minimalRawConfig();
        unset($raw['localship']['production']['path']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('`localship.production.path`');

        ( new ConfigLoader() )->loadFromArray($raw);
    }

    public function testThrowsWhenRemoteEnvHasNoMatchingAlias(): void
    {
        $raw = $this->minimalRawConfig();
        unset($raw['@staging']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('@staging');

        ( new ConfigLoader() )->loadFromArray($raw);
    }

    public function testRemoteEnvsCarrySshAlias(): void
    {
        $config = ( new ConfigLoader() )->loadFromArray($this->minimalRawConfig());

        self::assertSame('@staging', $config->env('staging')->sshAlias());
        self::assertSame('@production', $config->env('production')->sshAlias());
        self::assertNull($config->local()->sshAlias());
        self::assertTrue($config->local()->isLocal());
        self::assertFalse($config->env('staging')->isLocal());
    }

    public function testHostnameStripsScheme(): void
    {
        $config = ( new ConfigLoader() )->loadFromArray($this->minimalRawConfig());

        self::assertSame('client-x.com', $config->env('production')->hostname());
        self::assertSame('staging.client-x.com', $config->env('staging')->hostname());
        self::assertSame('client-x.local', $config->local()->hostname());
    }

    public function testActiveThemeAndExcludesExtraAreParsed(): void
    {
        $raw                                  = $this->minimalRawConfig();
        $raw['localship']['active_theme']     = 'client-x-child';
        $raw['localship']['excludes_extra']   = [ 'wp-content/uploads/big-files/', '*.tmp' ];

        $config = ( new ConfigLoader() )->loadFromArray($raw);

        self::assertSame('client-x-child', $config->activeTheme());
        self::assertSame([ 'wp-content/uploads/big-files/', '*.tmp' ], $config->excludesExtra());
    }

    public function testRejectsEmptyActiveTheme(): void
    {
        $raw                              = $this->minimalRawConfig();
        $raw['localship']['active_theme'] = '';

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('active_theme');

        ( new ConfigLoader() )->loadFromArray($raw);
    }

    public function testRejectsNonListExcludesExtra(): void
    {
        $raw                                 = $this->minimalRawConfig();
        $raw['localship']['excludes_extra']  = 'not a list';

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('excludes_extra');

        ( new ConfigLoader() )->loadFromArray($raw);
    }

    public function testCustomRemoteEnvNameWorks(): void
    {
        $raw                              = $this->minimalRawConfig();
        $raw['@preview']                  = [ 'ssh' => 'user@host:/path/to/preview' ];
        $raw['localship']['preview']      = [
            'url'  => 'https://preview.client-x.com',
            'path' => '/path/to/preview',
        ];

        $config = ( new ConfigLoader() )->loadFromArray($raw);

        self::assertTrue($config->hasEnv('preview'));
        self::assertSame('@preview', $config->env('preview')->sshAlias());
        self::assertFalse($config->env('preview')->isProtected());
    }

    public function testEnvLookupUnknownThrows(): void
    {
        $config = ( new ConfigLoader() )->loadFromArray($this->minimalRawConfig());

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Unknown env "qa"');

        $config->env('qa');
    }
}
