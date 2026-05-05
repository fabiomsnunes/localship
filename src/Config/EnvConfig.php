<?php

/**
 * Value object representing a single environment within a site's LocalShip config.
 *
 * @package LocalShip\Config
 */

declare(strict_types=1);

namespace LocalShip\Config;

/**
 * One environment (local, staging, production, or any custom name).
 *
 * Envs other than `local` carry an SSH alias (the `@name` from wp-cli.yml). The local
 * env runs on the dev machine and has no alias.
 */
final class EnvConfig
{
    /** @var string */
    private $name;

    /** @var string */
    private $url;

    /** @var string */
    private $path;

    /** @var bool */
    private $isProtected;

    /** @var string|null */
    private $sshAlias;

    /**
     * @param string      $name        Env name (e.g. "local", "staging", "production").
     * @param string      $url         Full base URL of the env (scheme + host).
     * @param string      $path        Absolute path to the WordPress install on that env.
     * @param bool        $isProtected Whether destructive ops require hostname-typing confirm.
     * @param string|null $sshAlias    The `@name` WP-CLI alias for remote access. Null for local.
     */
    public function __construct(string $name, string $url, string $path, bool $isProtected, ?string $sshAlias)
    {
        $this->name        = $name;
        $this->url         = $url;
        $this->path        = $path;
        $this->isProtected = $isProtected;
        $this->sshAlias    = $sshAlias;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isProtected(): bool
    {
        return $this->isProtected;
    }

    public function sshAlias(): ?string
    {
        return $this->sshAlias;
    }

    public function isLocal(): bool
    {
        return null === $this->sshAlias;
    }

    /**
     * The hostname portion of the URL — what the user types to confirm a protected push.
     *
     * Strips scheme, port, path, query, and fragment. "https://www.example.com:8443/foo"
     * becomes "www.example.com". The result is what HostnameConfirm prompts for.
     */
    public function hostname(): string
    {
        $host = parse_url($this->url, PHP_URL_HOST);

        return is_string($host) ? $host : $this->url;
    }
}
