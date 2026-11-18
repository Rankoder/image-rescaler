# image-rescaler

What the app does, its structure and all commands are in the README, imported here:

@README.md

PHP and Composer run only in Docker, in the `web` service. Before a PR, `composer check` must pass.

## Git

- Branch off `develop`, PR into `develop`; `master` is updated only by a PR from `develop`.
- Branch name: `<type>/ir-<nr>-<short-desc>`, e.g. `docs/ir-02-claude-md`. Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.
- The branch type describes the whole task, a commit type the change in that commit, so they can differ: a `fix/…` branch may hold a `test(…)` commit. `feat` is a feature of the application itself; environment, Docker, CI and tooling are `chore`.
- One number is one task: one branch, one PR. Numbers increase and are never reused. A fix during a task is another commit on the same branch.
- Conventional Commits: `type(scope): imperative summary`, e.g. `fix(storage): reject path traversal in output filename`.
- Merging a PR: a merge commit for environment and cleanup tasks, whose commits each say something on their own; squash for a feature whose branch holds work-in-progress commits.
- Before a PR: `bin/fresh-check <branch>`, with `--prod` when the task touches Docker or Composer dependencies, then whatever else the README describes for the changed area. Findings outside the task's scope go to the task that owns that area, not into this branch.

## Code standard

Everything in the repo is in English: code, names, commits, docs.

The current code does not meet all of these rules yet. New and changed code follows them; the rest is cleaned up in dedicated refactor tasks, not along the way.

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

Each module gets its own `README.md` once rebuilt: purpose, public API, dependencies, how to test. Read a module's README before working in it.

## Guides

Step-by-step instructions (e.g. IDE setup) live in `docs/`, one file per topic. The README only links to them, so they are not loaded with it; read a guide when the task needs it.

## Docs stay current

A change that invalidates this file or a README updates it in the same task.
