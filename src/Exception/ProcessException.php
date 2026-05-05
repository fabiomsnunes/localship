<?php

/**
 * Thrown when an external process (ssh, rsync, wp-cli alias call) exits non-zero.
 *
 * @package LocalShip\Exception
 */

declare(strict_types=1);

namespace LocalShip\Exception;

use RuntimeException;

final class ProcessException extends RuntimeException
{
}
