# Configuration

LocalShip reads a single `wp-cli.yml` per WordPress site. The file lives at the project root and combines two things:

1. **Standard WP-CLI alias entries** (`@staging`, `@production`, etc.) — used for SSH access. WP-CLI handles these natively; LocalShip just reuses them.
2. **A `localship:` block** — per-env URLs and paths, plus a few site-level fields (active theme, protected envs, extra excludes).

`wp localship init` writes this file for you interactively. The rest of this page documents the schema.

## Full annotated example

```yaml
# Path to the WordPress install relative to this file.
# In a Local by Flywheel site this is typically `app/public`.
path: app/public

# Standard WP-CLI alias section. LocalShip reuses these for SSH access —
# no separate SSH config is needed.
@staging:
  ssh: user@host:/home/user/webapps/client-x-staging

@production:
  ssh: user@host:/home/user/webapps/client-x

# LocalShip-specific section. WP-CLI passes unknown top-level keys through
# to packages via extra_config, so this whole block is read by LocalShip.
localship:
  # The local environment never has SSH details — it's the dev machine.
  local:
    url: http://client-x.local
    path: /Users/you/Local Sites/client-x/app/public

  # One block per remote env. Names must match the @<name> aliases above.
  staging:
    url: https://staging.client-x.com
    path: /home/user/webapps/client-x-staging

  production:
    url: https://client-x.com
    path: /home/user/webapps/client-x

  # The slug of the theme you're actively developing on this site.
  active_theme: client-x-child

  # Envs listed here require typing the target hostname before any
  # destructive step. Defaults to ["production"] if omitted.
  protected_envs:
    - production

  # Per-site additions to the default rsync exclude list (data/exclude.default.txt).
  excludes_extra: []
```

## Field reference

### Top level

| Key            | Type   | Required | Description                                                          |
|----------------|--------|----------|----------------------------------------------------------------------|
| `path`         | string | yes\*    | WP install path relative to wp-cli.yml. Standard WP-CLI field.       |
| `@<env>`       | map    | per env  | Standard WP-CLI alias. Must exist for every remote env in `localship`. |

\* `path` is technically optional from WP-CLI's perspective but Local sites always use `app/public`.

### `localship.local` (required)

| Key    | Type   | Required | Description                              |
|--------|--------|----------|------------------------------------------|
| `url`  | string | yes      | Full local URL (e.g. `http://x.local`).  |
| `path` | string | yes      | Absolute path to the local WP root.      |

### `localship.<env>` (one per remote env)

| Key    | Type   | Required | Description                                |
|--------|--------|----------|--------------------------------------------|
| `url`  | string | yes      | Full base URL of that env.                 |
| `path` | string | yes      | Absolute path to the WP root on that env.  |

The env name must match a `@<name>` alias at the top level. Otherwise LocalShip refuses to load the config.

### `localship.active_theme`

Slug of your theme. Currently informational; `push-theme` / `pull-theme` (Phase 2) will use it.

### `localship.protected_envs`

List of env names that require typing the target hostname before any destructive step. Defaults to `["production"]`. Set to `[]` to disable the safety entirely on this site (not recommended).

### `localship.excludes_extra`

Per-site additions to the default rsync exclude list. The default list (which excludes `wp-config.php`, `.htaccess`, caches, `.git`, `node_modules`, etc.) is in `data/exclude.default.txt` inside the package and is the source of truth for "things we never sync".

## Should I commit `wp-cli.yml`?

It's your call. Two reasonable patterns:

- **Commit it** if your team shares the same SSH keys/aliases and paths. Cleaner onboarding for new devs.
- **Gitignore it** if it contains paths or hostnames you'd rather not publish. The `wp-cli.yml.example` template can serve as the committed reference.

LocalShip itself doesn't care either way.

## Adding a custom env (e.g. `preview`)

Same pattern as staging/production:

```yaml
@preview:
  ssh: user@host:/home/user/webapps/client-x-preview

localship:
  # ...existing blocks...
  preview:
    url: https://preview.client-x.com
    path: /home/user/webapps/client-x-preview
```

Now `wp localship push preview` and `wp localship pull preview` work. Add `preview` to `protected_envs` if you want the hostname-typing prompt for it too.
