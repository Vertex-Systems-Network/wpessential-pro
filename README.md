# WPEssential Pro

Premium module add-on for the [WPEssential](https://github.com/Vertex-Systems-Network/wpessential) WordPress platform.
The WordPress plugin header declares `Requires Plugins: wpessential`: Pro never runs without the Free plugin.

This repository was split out of the Free monorepo with full git history for every Pro file
(Free `main @ ba3621e5`). The packaged `wpessential-pro.zip` built here is byte-for-byte identical to the
ZIP the monorepo produced before the split.

## What lives where

| Repository | Contents |
|---|---|
| `Vertex-Systems-Network/wpessential` (Free) | `wpessential.php`, Platform/Kernel/Contracts/Bootstrap, Custom Post Types + Taxonomies modules, Core admin UI, WordPress.org package |
| `Vertex-Systems-Network/wpessential-pro` (this repo) | `wpessential-pro.php`, every other module under `frameworks/Modules/`, Pro admin entries (`fields`, `query`, `columns-runtime`, `custom-tables`), Pro tests, Free+Pro pair tooling |

## Local setup

Pro is developed against a Free checkout placed **beside** it:

```
workspace/
├── wpessential/       # git clone https://github.com/Vertex-Systems-Network/wpessential
└── wpessential-pro/   # this repository
```

```bash
composer update          # pulls wpessential/wpessential from ../wpessential (path repository)
vendor/bin/phpunit       # Pro unit suite
npm ci && npm run build:admin
php tools/release/build-pro-distributable.php   # -> artifacts/wpessential-pro.zip
```

Integration fixtures under `tests/Integration/` load Free classes through `tests/Integration/free-root.php`.
It reads `WPE_FREE_ROOT` and defaults to `../wpessential`.

## CI

Every workflow checks out the Free repository and moves it to `../wpessential` before it runs.

- Repository variable `WPE_FREE_REF`: the Free branch, tag or SHA to test against. The default is `main`.
- Secret `WPE_FREE_REPO_TOKEN`: a read-only token. It is only needed if the Free repository is private.

| Workflow | Purpose |
|---|---|
| `pro-ci.yml` | Lint, unit tests, admin build/typecheck/lint, deterministic Pro ZIP, ZIP boundary, packaged Free+Pro bootstrap pairs |
| `pro-platform-compatibility.yml` | Roles / Fields / Dashboard Widgets persistence on the WordPress × PHP × DB matrix |
| `custom-tables-r1-execution.yml`, `relations-edge-persistence.yml`, `query-native-execution-reference.yml`, `listings-reference-application.yml`, `status-reference-application.yml`, `multisite-runtime.yml`, `taxonomy-role-impact.yml` | Module runtime fixtures moved from the Free repo |

## P-006 evidence tooling

`tools/p006/` and `tools/release/p006-*` are the historical Free/Pro pair evidence harnesses.
They were written for the monorepo layout and some of them call `tools/release/build-free-pro-distributables.php`.
To re-run one, point it at the Free checkout first.

## License

GPL-3.0-or-later.
