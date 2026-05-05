# Usage

This page covers the day-to-day workflows. For installation, see [install.md](install.md). For the full config schema, see [configuration.md](configuration.md).

## Commands at a glance

| Command | Use when |
|---|---|
| `wp localship init` | First-time setup of `wp-cli.yml` for a site |
| `wp localship status` | Check connectivity to all envs |
| `wp localship clone <env>` | Bootstrap a fresh local copy from a remote env |
| `wp localship push <env>` | Send local DB + files to staging or production |
| `wp localship pull <env>` | Refresh local from staging or production |

## Recipes

### Clone a live site to local (day-1 onboarding)

You have a client's live site at `client-x.com`, no staging, and you need to work on it locally.

1. **Create an empty Local site** via Local's UI: "Create New Site" → name `client-x` → default WP install. Local handles WP install, MySQL, dev domain.
2. **Run clone:**

   ```bash
   cd "~/Local Sites/client-x/app/public"
   wp localship clone production
   ```

   On the first run there's no `wp-cli.yml`, so LocalShip walks you through `init` interactively (SSH user/host, prod URL/path, etc.) and writes the config. Then it pulls everything (DB + uploads + plugins + themes) and runs reverse search-replace so the site loads at `client-x.local`.

3. **Verify** by opening `http://client-x.local` in your browser.

### Push to staging

```bash
wp localship push staging
```

Default scope: DB + uploads + plugins + themes. Auto-creates a remote DB backup before importing. Add `--dry-run` to preview without changing anything.

### Push to production

```bash
wp localship push production
```

You'll be prompted to type the production hostname (e.g. `client-x.com`). Anything else — `yes`, Enter, a typo — aborts. The hostname is per-site, which defeats muscle memory: if you typed the wrong env, the hostname you're being asked for won't match what you expected.

In a non-TTY context (cron, CI), the operation is refused unless `--yes-i-know` is passed. `--no-backup` is rejected on protected envs even when explicit.

### Refresh local data from staging

```bash
wp localship pull staging
```

Default scope: DB + uploads (the routine refresh case — not plugins/themes, those usually live in your local code). Use `--only=db,uploads,plugins,themes` for a full refresh.

### Push only a specific tier

```bash
# DB only (e.g. you ran a migration locally)
wp localship push staging --db-only

# Files only (e.g. tweaking CSS, no DB schema change)
wp localship push staging --files-only

# Composable
wp localship push staging --only=db,plugins
wp localship push staging --exclude=uploads
```

v1 tokens: `db`, `uploads`, `plugins`, `themes`. Phase 2 will add per-slug tokens like `plugins/woocommerce`.

### Preview without executing

```bash
wp localship push staging --dry-run
wp localship pull production --dry-run
```

Prints every command that would run, transfers nothing.

### Check connectivity before a big run

```bash
wp localship status
```

Prints config summary and probes each remote env via `wp @<env> core version`. A green line per env means SSH and remote WP-CLI both work.

## Production safety summary

- **Hostname-typing prompt** on any env in `localship.protected_envs` (default: `production`).
- **Remote DB backup** runs automatically before every push unless `--no-backup`.
- **`--no-backup` is refused on protected envs** even if explicit.
- **Non-TTY refusal** for protected envs unless `--yes-i-know`.
- **Per-site lockfile** prevents concurrent pushes/pulls on the same site.
- **`wp-config.php` is never overwritten** — it's in the default rsync exclude list.

## GDPR / production data

`wp localship pull production` brings real user data to your laptop. That's fine for short debugging sessions on an encrypted machine, but consider:

- Pulling from `staging` instead, which itself should have anonymized data.
- Disk encryption on your dev machine.
- Deleting local copies once the bug is reproduced.

A built-in `--anonymize` flag is not part of v1. Use staging where possible.

## Caveats

- **Bricks Builder, Elementor, ACF**: serialized data fields are handled by `wp search-replace --all-tables` (which LocalShip uses). Test on a real Bricks site before relying on this in production.
- **Multisite**: not supported in v1.
- **PHP/MySQL version drift**: LocalShip assumes local and remote can read each other's DB exports. Major version mismatches (MySQL 5.7 → 8 in particular) can produce subtle issues — test on staging first.
- **Large sites**: a 5GB+ uploads dir will take real time on the first sync. Subsequent rsync runs are fast (delta-only), so the second push/pull is seconds.
