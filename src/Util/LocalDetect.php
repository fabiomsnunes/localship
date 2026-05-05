<?php

/**
 * Detects Local by Flywheel site metadata from a working directory.
 *
 * @package LocalShip\Util
 */

declare(strict_types=1);

namespace LocalShip\Util;

/**
 * Local by Flywheel sites live at:
 *
 *   ~/Local Sites/<site-name>/app/public/    (default since Local Lightning)
 *
 * The default local URL is "<site-name>.local". This class produces best-effort guesses;
 * `wp localship init` uses them as defaults the user can override at the prompt.
 *
 * No assumption is made beyond the directory layout — Local sets up MySQL and dnsmasq
 * separately, and our detection is purely string parsing on the cwd. Not finding a match
 * is normal; the init flow falls back to plain prompts in that case.
 */
final class LocalDetect
{
    /**
     * @return array{path: string|null, url: string|null, site_name: string|null}
     */
    public static function fromCwd(string $cwd): array
    {
        $cwd = rtrim($cwd, '/');

        // Match `<anywhere>/Local Sites/<name>/app/public[/anything]`.
        $pattern = '#/Local Sites/([^/]+)/app/public(?:/|$)#';
        if (1 !== preg_match($pattern, $cwd . '/', $m)) {
            return [
                'path'      => null,
                'url'       => null,
                'site_name' => null,
            ];
        }

        $siteName = $m[1];
        // Normalise the path back to the WP root regardless of how deep the user is.
        $cutAt    = strpos($cwd, '/Local Sites/' . $siteName . '/app/public');
        if (false === $cutAt) {
            $path = $cwd;
        } else {
            $path = substr($cwd, 0, $cutAt) . '/Local Sites/' . $siteName . '/app/public';
        }

        return [
            'path'      => $path,
            'url'       => 'http://' . $siteName . '.local',
            'site_name' => $siteName,
        ];
    }
}
