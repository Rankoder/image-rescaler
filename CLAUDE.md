# image-rescaler

A plain-PHP application that scales an uploaded image down to a thumbnail and stores it in the chosen storage: local filesystem, S3 or Dropbox. Two entry points: REST API and CLI.

## Structure

| Path | Contents |
|------|----------|
| `src/Domain/` | interfaces and value objects, no dependencies on other layers |
| `src/Application/` | services: processing, validation, storage |
| `src/Application/CLI/resize.php` | CLI entry point |
| `src/Infrastructure/` | HTTP controllers |
| `public/index.php` → `routes.php` → `ImageController` | API entry point (bramus/router) |
| `config/` | S3 config from environment variables (`dropbox.php` exists but is unused; Dropbox reads `getenv` directly) |
| `docker/entrypoint.sh` | runs on container start: `composer install`, creates `storage/`, then Apache |
| `tests/Unit/`, `tests/Functional/` | PHPUnit; directories mirror the namespace (`tests/Unit/App/…`) |

## Commands

PHP and Composer run only in Docker, in the `web` service.

```sh
docker compose up --build -d                    # build and start (localhost:8080, API at POST /upload)
docker compose exec web vendor/bin/phpunit      # all tests
docker compose exec web vendor/bin/phpunit --testsuite "Unit Tests"
```

## Git

- Branch off `master`, PR into `master`; no intermediate `develop`.
- Branch name: `<type>/ir-<nr>-<short-desc>`, e.g. `docs/ir-02-claude-md`. Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.
- One number is one task: one branch, one PR. Numbers increase and are never reused. A fix during a task is another commit on the same branch.
- Conventional Commits: `type(scope): imperative summary`, e.g. `fix(storage): reject path traversal in output filename`.
- Konrad commits and pushes.
- Before a PR: clean run from a fresh clone of the branch (`docker compose build --no-cache`), then everything the README describes, tests included. Findings outside the task's scope go to the task that owns that area, not into this branch.

## Code standard

Everything in the repo is in English: code, names, commits, docs.

The current code does not meet all of these rules yet. New and changed code follows them; the rest is cleaned up in dedicated refactor tasks, not along the way.

Kept:
- `declare(strict_types=1)` in every file,
- PSR-4 (`App\` → `src/`) and the Domain / Application / Infrastructure layers.

Added:
- formatting per PER-CS,
- classes are `final` unless inheritance is intended,
- constructor property promotion and `readonly` wherever state does not change,
- enums instead of strings with a fixed set of values (e.g. storage type),
- dependencies injected through the constructor; no `new` of services and no `.env` reads inside classes,
- no docblocks that repeat the signature; a comment only where the code cannot say "why",
- file type checked by content (MIME), not by extension; user-supplied names and paths always validated.

## Module READMEs

Each module gets its own `README.md` once rebuilt: purpose, public API, dependencies, how to test. Read a module's README before working in it.

## Docs stay current

A change that invalidates this file or a README updates it in the same task.
