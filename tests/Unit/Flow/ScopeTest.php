<?php

declare(strict_types=1);

namespace LocalShip\Tests\Unit\Flow;

use LocalShip\Exception\ConfigException;
use LocalShip\Flow\Scope;
use PHPUnit\Framework\TestCase;

final class ScopeTest extends TestCase
{
    public function testDefaultsApplyWhenNoFlagsPassed(): void
    {
        $scope = Scope::fromAssocArgs([Scope::TOKEN_DB, Scope::TOKEN_UPLOADS], []);

        self::assertSame([Scope::TOKEN_DB, Scope::TOKEN_UPLOADS], $scope->tokens());
    }

    public function testOnlyOverridesDefaults(): void
    {
        $scope = Scope::fromAssocArgs(Scope::ALL_TOKENS, ['only' => 'db,uploads']);

        self::assertSame([Scope::TOKEN_DB, Scope::TOKEN_UPLOADS], $scope->tokens());
    }

    public function testExcludeRemovesFromDefaults(): void
    {
        $scope = Scope::fromAssocArgs(Scope::ALL_TOKENS, ['exclude' => 'uploads']);

        self::assertFalse($scope->has(Scope::TOKEN_UPLOADS));
        self::assertTrue($scope->has(Scope::TOKEN_DB));
        self::assertTrue($scope->has(Scope::TOKEN_PLUGINS));
        self::assertTrue($scope->has(Scope::TOKEN_THEMES));
    }

    public function testDbOnlyShortcut(): void
    {
        $scope = Scope::fromAssocArgs(Scope::ALL_TOKENS, ['db-only' => '']);

        self::assertSame([Scope::TOKEN_DB], $scope->tokens());
    }

    public function testFilesOnlyShortcut(): void
    {
        $scope = Scope::fromAssocArgs(Scope::ALL_TOKENS, ['files-only' => '']);

        self::assertFalse($scope->has(Scope::TOKEN_DB));
        self::assertTrue($scope->has(Scope::TOKEN_UPLOADS));
        self::assertTrue($scope->has(Scope::TOKEN_PLUGINS));
        self::assertTrue($scope->has(Scope::TOKEN_THEMES));
    }

    public function testDbOnlyAndFilesOnlyAreMutuallyExclusive(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('mutually exclusive');

        Scope::fromAssocArgs(Scope::ALL_TOKENS, ['db-only' => '', 'files-only' => '']);
    }

    public function testUnknownTokenInOnlyThrows(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('mu-plugins');

        Scope::fromAssocArgs(Scope::ALL_TOKENS, ['only' => 'db,mu-plugins']);
    }

    public function testTrimsWhitespaceAndDeduplicates(): void
    {
        $scope = Scope::fromAssocArgs(Scope::ALL_TOKENS, ['only' => 'db, uploads ,db,uploads']);

        self::assertSame([Scope::TOKEN_DB, Scope::TOKEN_UPLOADS], $scope->tokens());
    }

    public function testEmptyResultThrows(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Scope is empty');

        Scope::fromAssocArgs([Scope::TOKEN_DB], ['exclude' => 'db']);
    }

    public function testFileSubPathsReflectsScopeMembership(): void
    {
        $scope = Scope::fromAssocArgs(Scope::ALL_TOKENS, ['only' => 'uploads,plugins']);

        self::assertSame(
            ['wp-content/uploads', 'wp-content/plugins'],
            $scope->fileSubPaths()
        );
    }

    public function testFileSubPathsExcludesDb(): void
    {
        $scope = Scope::fromAssocArgs([Scope::TOKEN_DB], []);

        self::assertSame([], $scope->fileSubPaths());
    }
}
