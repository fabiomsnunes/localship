# LocalShip

A WP-CLI package that automates pushing, pulling, and cloning WordPress sites between [Local by Flywheel](https://localwp.com/) and any SSH-accessible VPS with WP-CLI.

```bash
wp localship clone production    # bootstrap a fresh local copy from a live site
wp localship push staging        # push DB + files to staging
wp localship pull production     # pull DB + uploads from production
```

> **Status:** pre-release. v1 in active development. Not yet on Packagist — install from source (see below).

## Why

Manually pushing a WordPress site to staging or production usually means: WP-CLI export, zip, SCP, unzip, edit `wp-config.php`, run search-replace twice, flush caches. 15–30 minutes per push, and one mistake away from breaking a live site.

LocalShip collapses that into a single command, reuses WP-CLI's built-in `@alias` system for SSH, uses `rsync` for fast incremental file sync, and adds a hostname-typing confirmation that makes accidental production pushes nearly impossible.

## Install

Requires PHP 7.4+, WP-CLI, `rsync`, and `ssh` on the dev machine; WP-CLI on the remote.

```bash
git clone https://github.com/fabiomsnunes/localship.git ~/localship
cd ~/localship && composer install
wp package install ~/localship
```

Once on Packagist, this will become `wp package install fabiomsnunes/localship`.

## Quickstart

In any WordPress site directory:

```bash
wp localship init                # interactive: SSH details, env URLs/paths
wp localship status              # check connectivity to each env
wp localship push staging        # full DB + files push
```

For a fresh local copy of an existing live site:

```bash
# 1. Create an empty Local site via Local's UI
# 2. cd into it, then:
wp localship clone production
```

## Commands

| Command | Purpose |
|---|---|
| `init` | Scaffold `wp-cli.yml` (aliases + `localship:` block) for the current site |
| `status` | Show config summary + connectivity check per env |
| `clone <env>` | First-time clone from a remote into the current empty Local site |
| `push <env>` | Push DB + files to env |
| `pull <env>` | Pull DB + uploads from env |

Common flags: `--only=<list>`, `--exclude=<list>`, `--dry-run`, `--no-backup`. See `docs/usage.md`.

## Production safety

Any env listed in `localship.protected_envs` (default `[production]`) requires typing the target hostname before any destructive step. Mistyping aborts cleanly. Non-TTY contexts refuse to run on protected envs unless `--yes-i-know` is passed. Remote backup runs automatically before every push.

## Documentation

- [Install](docs/install.md)
- [Usage](docs/usage.md)
- [Configuration](docs/configuration.md)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
