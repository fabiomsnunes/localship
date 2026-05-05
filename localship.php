<?php

/**
 * LocalShip — WP-CLI package bootstrap.
 *
 * Registers the `localship` command with WP-CLI. Subcommands are public methods on
 * \LocalShip\Command\LocalShipCommand: init, status, clone, push, pull.
 *
 * @package   LocalShip
 * @license   GPL-2.0-or-later
 * @link      https://github.com/fabiomsnunes/localship
 */

declare(strict_types=1);

if (! defined('WP_CLI') || ! WP_CLI) {
    return;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

WP_CLI::add_command('localship', \LocalShip\Command\LocalShipCommand::class);
