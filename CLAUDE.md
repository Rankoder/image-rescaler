# image-rescaler

Plain-PHP REST API and CLI tool that crops images to an exact frame and stores them in local storage, Amazon S3 or Dropbox.

- **Runtime:** PHP and Composer run only in Docker, in the `web` service (`docker compose up --build -d`).
- **Port:** configured via `APP_PORT` in `.env` (`8080` by default; secondary clones use their own `APP_PORT` and `COMPOSE_PROJECT_NAME`).
- **Contract:** HTTP endpoints, fields, limits and error codes are defined in `openapi.yaml` and verified by contract tests.
- **Commands:**
  - `docker compose exec web composer check` — CS, PHPStan (level max + phpat architecture rules) and all tests; must pass before a PR.
  - `docker compose exec web composer test` — PHPUnit (`test:unit`, `test:functional`, `test:integration`, `test:contract` for single suites).
  - `bin/fresh-check <branch>` (host) — clones the pushed branch, builds a fresh dev image and runs `composer check` (add `--prod` for Docker/Composer changes).
- **Details:** full setup, endpoints, CLI options and project structure are in `README.md` — read it on demand instead of loading it on every session.

## Git

- Branch off `develop`, PR into `develop`; `master` is updated only by a PR from `develop`.
- Branch name: `<type>/ir-<nr>-<short-desc>`, e.g. `docs/ir-02-claude-md`. Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.
- The branch type describes the whole task, a commit type the change in that commit, so they can differ: a `fix/…` branch may hold a `test(…)` commit. `feat` is a feature of the application itself; environment, Docker, CI and tooling are `chore`.
- One number is one task: one branch, one PR. Numbers increase and are never reused. A fix during a task is another commit on the same branch.
- Conventional Commits: `type(scope): imperative summary`, e.g. `fix(storage): reject path traversal in output filename`.
- Merging a PR: a merge commit for environment and cleanup tasks, whose commits each say something on their own; squash for a feature whose branch holds work-in-progress commits.
- Before a PR: `bin/fresh-check <branch>`, with `--prod` when the task touches Docker or Composer dependencies, then whatever else the README describes for the changed area. Findings outside the task's scope go to the task that owns that area, not into this branch.

## Code standard

Everything in the repo is in English: code, names, commits, docs, PR descriptions, reviews and merge comments.

Kept:
- `declare(strict_types=1)` in every file,
- PSR-4 (`App\` → `src/`) and the Domain / Application / Infrastructure layers; Domain depends on no other layer.

Added:
- formatting per PER-CS,
- classes are `final` unless inheritance is intended,
- constructor property promotion and `readonly` wherever state does not change,
- enums instead of strings with a fixed set of values (e.g. storage type),
- dependencies injected through the constructor; no `new` of services and no `.env` reads inside classes,
- no docblocks that repeat the signature; a comment only where the code cannot say "why",
- file type checked by content (MIME), not by extension; user-supplied names and paths always validated.

## Module READMEs

Each module has its own `README.md` (`src/Domain/README.md`, `src/Application/README.md`, `src/Infrastructure/README.md`) covering its purpose, public API, dependencies and how to test. Read a module's README as a map before opening its classes.

## Guides

Step-by-step instructions (e.g. IDE setup) live in `docs/`, one file per topic. The README only links to them, so they are not loaded with it; read a guide when the task needs it.

## Docs stay current

A change that invalidates this file or a README updates it in the same task.

| If you change | Also update |
|---------------|-------------|
| HTTP endpoint, parameters or status codes | `openapi.yaml`, `README.md`, `http/api.http` |
| Environment variable | `.env-example`, `README.md`, application startup |
| Client-visible behaviour | module `README.md`, contract tests |
| Composer dependency | `composer.lock`, `prod` image |
