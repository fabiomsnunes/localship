<?php

/**
 * Tests for Excludes.
 *
 * @package LocalShip\Tests\Unit\Safety
 */

declare(strict_types=1);

namespace LocalShip\Tests\Unit\Safety;

use LocalShip\Config\ConfigLoader;
use LocalShip\Config\SiteConfig;
use LocalShip\Safety\Excludes;
use PHPUnit\Framework\TestCase;

final class ExcludesTest extends TestCase
{
    /** @var array<int,string> */
    private $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    private function siteConfigWithExtras(array $extras): SiteConfig
    {
        $raw = [
            '@staging'  => [ 'ssh' => 'user@host:/x' ],
            'localship' => [
                'local'   => [ 'url' => 'http://x.local', 'path' => '/local/x' ],
                'staging' => [ 'url' => 'https://staging.x.com', 'path' => '/remote/x' ],
                'excludes_extra' => $extras,
            ],
        ];

        return ( new ConfigLoader() )->loadFromArray($raw);
    }

    private function writeDefaultFile(string $body): string
    {
        $path              = tempnam(sys_get_temp_dir(), 'localship-default-');
        $this->tempFiles[] = $path;
        file_put_contents($path, $body);

        return $path;
    }

    public function testWriteMergedStripsCommentsAndBlankLines(): void
    {
        $default = $this->writeDefaultFile(<<<TXT
# header comment
wp-config.php

# another comment
.htaccess
TXT
        );

        $out               = ( new Excludes($default) )->writeMerged($this->siteConfigWithExtras([]));
        $this->tempFiles[] = $out;

        $lines = file($out, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertSame([ 'wp-config.php', '.htaccess' ], $lines);
    }

    public function testWriteMergedAppendsExtrasAfterDefaults(): void
    {
        $default = $this->writeDefaultFile("wp-config.php\n.htaccess\n");

        $out               = ( new Excludes($default) )->writeMerged(
            $this->siteConfigWithExtras([ 'wp-content/uploads/cache-bin/', '*.tmp' ])
        );
        $this->tempFiles[] = $out;

        $lines = file($out, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertSame(
            [ 'wp-config.php', '.htaccess', 'wp-content/uploads/cache-bin/', '*.tmp' ],
            $lines
        );
    }

    public function testWriteMergedTrimsWhitespaceFromDefaultLines(): void
    {
        $default = $this->writeDefaultFile("  wp-config.php  \n\t.htaccess\t\n");

        $out               = ( new Excludes($default) )->writeMerged($this->siteConfigWithExtras([]));
        $this->tempFiles[] = $out;

        $lines = file($out, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertSame([ 'wp-config.php', '.htaccess' ], $lines);
    }

    public function testBundledDefaultFileExists(): void
    {
        $path = Excludes::bundledDefault();

        self::assertFileExists($path);
        self::assertStringContainsString('wp-config.php', file_get_contents($path));
    }

    public function testBundledDefaultProducesValidExcludeFile(): void
    {
        $out               = ( new Excludes(Excludes::bundledDefault()) )->writeMerged($this->siteConfigWithExtras([]));
        $this->tempFiles[] = $out;

        $contents = file_get_contents($out);
        self::assertStringContainsString('wp-config.php', $contents);
        self::assertStringContainsString('.htaccess', $contents);
        self::assertStringContainsString('.git/', $contents);
        // Comments must not bleed through.
        self::assertStringNotContainsString('#', $contents);
    }
}
