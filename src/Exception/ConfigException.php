<?php
/**
 * Thrown when a LocalShip config is missing required fields or otherwise invalid.
 *
 * @package LocalShip\Exception
 */

declare( strict_types = 1 );

namespace LocalShip\Exception;

use RuntimeException;

final class ConfigException extends RuntimeException {
}
