<?php
/**
 * Reads, validates, and parses the `localship:` block of a wp-cli.yml.
 *
 * @package LocalShip\Config
 */

declare( strict_types = 1 );

namespace LocalShip\Config;

use LocalShip\Exception\ConfigException;
use Symfony\Component\Yaml\Yaml;

/**
 * Two entry points:
 *
 *   - loadFromArray()  — pure, takes a raw associative array. Used by tests and by
 *                        loadFromWpCli() after extracting WP-CLI's extra_config.
 *   - loadFromFile()   — reads a wp-cli.yml from disk and delegates to loadFromArray().
 *
 * Validation lives in this class. Once a SiteConfig is returned, every consumer can
 * trust it without re-checking.
 */
final class ConfigLoader {

	private const META_KEYS = [ 'active_theme', 'protected_envs', 'excludes_extra' ];

	private const DEFAULT_PROTECTED_ENVS = [ 'production' ];

	/**
	 * Build a SiteConfig from the full parsed wp-cli.yml structure.
	 *
	 * Expects an associative array with at minimum a `localship` key whose value is an
	 * associative array. Standard WP-CLI alias keys (e.g. "@staging") are read so that
	 * env entries without their own SSH alias can still resolve.
	 *
	 * @param array<string, mixed> $raw Parsed wp-cli.yml as a plain array.
	 *
	 * @throws ConfigException If required fields are missing or malformed.
	 */
	public function loadFromArray( array $raw ): SiteConfig {
		if ( ! isset( $raw['localship'] ) || ! is_array( $raw['localship'] ) ) {
			throw new ConfigException( 'Missing `localship:` block in wp-cli.yml.' );
		}

		$ls = $raw['localship'];

		$activeTheme   = $this->extractActiveTheme( $ls );
		$excludesExtra = $this->extractExcludesExtra( $ls );
		$protectedEnvs = $this->extractProtectedEnvs( $ls );
		$envs          = $this->extractEnvs( $ls, $protectedEnvs, $raw );

		return new SiteConfig( $envs, $activeTheme, $excludesExtra );
	}

	/**
	 * Read and parse a wp-cli.yml from disk.
	 *
	 * @throws ConfigException If the file does not exist or cannot be parsed.
	 */
	public function loadFromFile( string $path ): SiteConfig {
		if ( ! is_readable( $path ) ) {
			throw new ConfigException( sprintf( 'wp-cli.yml not found or not readable at %s.', $path ) );
		}

		try {
			$parsed = Yaml::parseFile( $path );
		} catch ( \Throwable $e ) {
			throw new ConfigException(
				sprintf( 'Failed to parse %s: %s', $path, $e->getMessage() ),
				0,
				$e
			);
		}

		if ( ! is_array( $parsed ) ) {
			throw new ConfigException( sprintf( 'wp-cli.yml at %s did not parse to a mapping.', $path ) );
		}

		return $this->loadFromArray( $parsed );
	}

	/**
	 * @param array<string, mixed> $ls
	 */
	private function extractActiveTheme( array $ls ): ?string {
		if ( ! isset( $ls['active_theme'] ) ) {
			return null;
		}
		if ( ! is_string( $ls['active_theme'] ) || '' === $ls['active_theme'] ) {
			throw new ConfigException( '`localship.active_theme` must be a non-empty string when set.' );
		}

		return $ls['active_theme'];
	}

	/**
	 * @param array<string, mixed> $ls
	 *
	 * @return array<int, string>
	 */
	private function extractExcludesExtra( array $ls ): array {
		if ( ! isset( $ls['excludes_extra'] ) ) {
			return [];
		}
		if ( ! is_array( $ls['excludes_extra'] ) ) {
			throw new ConfigException( '`localship.excludes_extra` must be a list of strings.' );
		}
		$out = [];
		foreach ( $ls['excludes_extra'] as $item ) {
			if ( ! is_string( $item ) || '' === $item ) {
				throw new ConfigException( '`localship.excludes_extra` entries must be non-empty strings.' );
			}
			$out[] = $item;
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $ls
	 *
	 * @return array<int, string>
	 */
	private function extractProtectedEnvs( array $ls ): array {
		if ( ! isset( $ls['protected_envs'] ) ) {
			return self::DEFAULT_PROTECTED_ENVS;
		}
		if ( ! is_array( $ls['protected_envs'] ) ) {
			throw new ConfigException( '`localship.protected_envs` must be a list of env names.' );
		}
		$out = [];
		foreach ( $ls['protected_envs'] as $item ) {
			if ( ! is_string( $item ) || '' === $item ) {
				throw new ConfigException( '`localship.protected_envs` entries must be non-empty strings.' );
			}
			$out[] = $item;
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $ls
	 * @param array<int, string>   $protectedEnvs
	 * @param array<string, mixed> $rawTopLevel
	 *
	 * @return array<string, EnvConfig>
	 */
	private function extractEnvs( array $ls, array $protectedEnvs, array $rawTopLevel ): array {
		$envs = [];
		foreach ( $ls as $key => $value ) {
			if ( in_array( $key, self::META_KEYS, true ) ) {
				continue;
			}
			if ( ! is_array( $value ) ) {
				throw new ConfigException(
					sprintf( '`localship.%s` must be a mapping with `url` and `path`.', $key )
				);
			}
			if ( ! isset( $value['url'] ) || ! is_string( $value['url'] ) || '' === $value['url'] ) {
				throw new ConfigException(
					sprintf( '`localship.%s.url` is required and must be a non-empty string.', $key )
				);
			}
			if ( ! isset( $value['path'] ) || ! is_string( $value['path'] ) || '' === $value['path'] ) {
				throw new ConfigException(
					sprintf( '`localship.%s.path` is required and must be a non-empty string.', $key )
				);
			}

			$isProtected = 'local' !== $key && in_array( $key, $protectedEnvs, true );
			$alias       = $this->resolveAlias( $key, $rawTopLevel );

			$envs[ $key ] = new EnvConfig( $key, $value['url'], $value['path'], $isProtected, $alias );
		}

		if ( ! isset( $envs['local'] ) ) {
			throw new ConfigException( '`localship.local` block is required (with `url` and `path`).' );
		}

		return $envs;
	}

	/**
	 * Find the WP-CLI alias for a given env name.
	 *
	 * Aliases are top-level keys prefixed with "@" in wp-cli.yml. The local env never has one.
	 *
	 * @param array<string, mixed> $rawTopLevel
	 */
	private function resolveAlias( string $envName, array $rawTopLevel ): ?string {
		if ( 'local' === $envName ) {
			return null;
		}
		$candidate = '@' . $envName;
		if ( isset( $rawTopLevel[ $candidate ] ) ) {
			return $candidate;
		}

		throw new ConfigException(
			sprintf(
				'Env `%s` is defined under `localship:` but has no matching `%s:` alias in wp-cli.yml.',
				$envName,
				$candidate
			)
		);
	}
}
