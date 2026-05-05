# Install

## Requirements

On the **dev machine** (where you run `wp localship`):
- PHP 7.4 or newer
- WP-CLI 2.7+
- `rsync`
- `ssh` with key-based access to your remote(s)
- Composer (for installing from source while LocalShip is pre-release)

On each **remote** (staging, production):
- WP-CLI installed and on `$PATH`
- A working WordPress install with a valid `wp-config.php` (see [Configuration](configuration.md))
- SSH access using the key configured on your dev machine

## Install from source (current — pre-release)

Until LocalShip is on Packagist, install it as a local WP-CLI package:

```bash
git clone https://github.com/fabiomsnunes/localship.git ~/localship
cd ~/localship && composer install
```

Then make `wp localship` available globally. Pick one of:

### Option A: load via your global wp-cli config (recommended for development)

```bash
mkdir -p ~/.wp-cli
cat >> ~/.wp-cli/config.yml <<'EOF'
require:
  - /Users/YOU/localship/localship.php
EOF
```

`wp localship` now works from any directory. Local edits to the source are picked up immediately — no reinstall step.

### Option B: install as a WP-CLI package

```bash
wp package install /path/to/localship
```

Note: at the time of writing, WP-CLI 2.12 may have a bundled-package conflict (`dist-archive-command`) that blocks `wp package install`. If you hit it, use Option A or update WP-CLI core.

## Install from Packagist (future)

Once LocalShip is published:

```bash
wp package install fabiomsnunes/localship
```

## Verify

```bash
wp localship
```

You should see:

```
usage: wp localship clone <env> [...]
   or: wp localship init [...]
   or: wp localship pull <env> [...]
   or: wp localship push <env> [...]
   or: wp localship status
```

## Update

If installed via git clone:

```bash
cd ~/localship && git pull && composer install
```

If installed via `wp package`:

```bash
wp package update
```

## Uninstall

Remove the require line from `~/.wp-cli/config.yml`, or:

```bash
wp package uninstall fabiomsnunes/localship
```
