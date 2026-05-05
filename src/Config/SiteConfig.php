<?php
/**
 * Value object representing a parsed and validated LocalShip site config.
 *
 * @package LocalShip\Config
 */

declare( strict_types = 1 );

namespace LocalShip\Config;

use LocalShip\Exception\ConfigException;

/**
 * The full per-site configuration.
 *
 * Built by ConfigLoader from the `localship:` block of a wp-cli.yml. Holds one EnvConfig
 * per environment plus a few site-level fields (active theme, extra excludes).
 */
final class SiteConfig {

	/** @var array<string, EnvConfig> */
	private $envs;

	/** @var string|null */
	private $activeTheme;

	/** @var array<int, string> */
	private $excludesExtra;

	/**
	 * @param array<string, EnvConfig> $envs          Map of env name → EnvConfig. Must include "local".
	 * @param string|null              $activeTheme   Slug of the active theme, or null.
	 * @param array<int, string>       $excludesExtra Per-site additions to the default rsync exclude list.
	 */
	public function __construct( array $envs, ?string $activeTheme, array $excludesExtra ) {
		if ( ! isset( $envs['local'] ) ) {
			throw new ConfigException( 'SiteConfig must include a "local" env.' );
		}
		$this->envs          = $envs;
		$this->activeTheme   = $activeTheme;
		$this->excludesExtra = $excludesExtra;
	}

	public function local(): EnvConfig {
		return $this->envs['local'];
	}

	public function env( string $name ): EnvConfig {
		if ( ! isset( $this->envs[ $name ] ) ) {
			$known = implode( ', ', array_keys( $this->envs ) );
			throw new ConfigException(
				sprintf( 'Unknown env "%s". Defined envs: %s.', $name, $known )
			);
		}

		return $this->envs[ $name ];
	}

	public function hasEnv( string $name ): bool {
		return isset( $this->envs[ $name ] );
	}

	/**
	 * @return array<int, string> Names of all defined envs, including "local".
	 */
	public function envNames(): array {
		return array_keys( $this->envs );
	}

	/**
	 * @return array<int, string> Names of envs other than "local".
	 */
	public function remoteEnvNames(): array {
		return array_values( array_filter(
			array_keys( $this->envs ),
			static function ( $name ) {
				return 'local' !== $name;
			}
		) );
	}

	public function activeTheme(): ?string {
		return $this->activeTheme;
	}

	/**
	 * @return array<int, string>
	 */
	public function excludesExtra(): array {
		return $this->excludesExtra;
	}
}
