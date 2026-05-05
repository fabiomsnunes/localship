<?php

/**
 * Builds the rsync exclude file used by every push/pull/clone operation.
 *
 * @package LocalShip\Safety
 */

declare(strict_types=1);

namespace LocalShip\Safety;

use LocalShip\Config\SiteConfig;
use LocalShip\Exception\ProcessException;

/**
 * Combines the central data/exclude.default.txt with per-site `excludes_extra` entries from
 * the SiteConfig and writes the result to a tempfile suitable for `rsync --exclude-from`.
 *
 * The default file is the source of truth for "files we never sync in either direction"
 * (PRD §7.5). Per-site additions are appended verbatim.
 */
final class Excludes
{
    /** @var string */
    private $defaultFile;

    public function __construct(string $defaultFile)
    {
        $this->defaultFile = $defaultFile;
    }

    /**
     * Default-file path for the package's bundled list.
     */
    public static function bundledDefault(): string
    {
        return dirname(__DIR__, 2) . '/data/exclude.default.txt';
    }

    /**
     * Write the merged exclude list to a temp file and return its path.
     *
     * The caller is responsible for unlinking the file once the rsync call completes (typically
     * via try/finally around the operation).
     *
     * @throws ProcessException If the default file is missing or the temp file cannot be written.
     */
    public function writeMerged(SiteConfig $config): string
    {
        if (! is_readable($this->defaultFile)) {
            throw new ProcessException(
                sprintf('Default exclude file not found at %s.', $this->defaultFile)
            );
        }

        $lines = $this->readDefaultPatterns();
        foreach ($config->excludesExtra() as $extra) {
            $lines[] = $extra;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'localship-exclude-');
        if (false === $tmp) {
            throw new ProcessException('Could not create temp file for rsync excludes.');
        }

        $payload = implode("\n", $lines) . "\n";
        if (false === file_put_contents($tmp, $payload)) {
            throw new ProcessException(sprintf('Failed to write exclude tempfile at %s.', $tmp));
        }

        return $tmp;
    }

    /**
     * Strip comments and blank lines from the default file, returning the active patterns.
     *
     * @return array<int, string>
     */
    private function readDefaultPatterns(): array
    {
        $contents = file_get_contents($this->defaultFile);
        if (false === $contents) {
            throw new ProcessException(sprintf('Could not read %s.', $this->defaultFile));
        }

        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ('' === $trimmed || '#' === $trimmed[0]) {
                continue;
            }
            $out[] = $trimmed;
        }

        return $out;
    }
}
