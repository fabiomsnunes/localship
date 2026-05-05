# Changelog

All notable changes to LocalShip will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- v1 command surface: `init`, `status`, `clone`, `push`, `pull`.
- Interactive `init` with Local-by-Flywheel path autodetection.
- `clone` for first-time bootstrap from a remote (init-if-needed, then full pull).
- `push` / `pull` with `--only`, `--exclude`, `--db-only`, `--files-only`, `--dry-run`, `--no-backup`.
- Hostname-typing confirmation on protected envs (default: `production`).
- Non-TTY refusal on protected envs unless `--yes-i-know`.
- Per-site mkdir-based lockfile to prevent concurrent operations.
- Default rsync exclude list (`data/exclude.default.txt`) shared across sites.
- ConfigLoader with full validation of the `localship:` block.
- 75 unit tests, 148 assertions covering config loading, scope flags, rsync argv,
  hostname confirmation, lockfile contention, and exclude file merging.
- GitHub Actions workflows for PHPUnit (PHP 7.4 / 8.0 / 8.2 / 8.3) and PHPCS (PSR-12).
- Initial documentation: README, install.md, usage.md, configuration.md.
