# Image Rescaler

A plain-PHP REST API and CLI tool that crops an image to a thumbnail of exactly the requested size and stores it in the chosen storage: local filesystem, Amazon S3 or Dropbox.

## Requirements

- Docker with Compose v2 (`docker compose`)

PHP 8.5, Composer and all extensions run inside the container; nothing else is needed on the host.

## Quick start

```sh
cp .env-example .env          # then fill in the values, see Configuration
docker compose up --build -d
```

This is the development setup: the project directory is mounted into the container, which on start installs Composer dependencies and creates the `storage/` directory. The API is available at `http://localhost:8080`.

Check that it responds:

```sh
curl -X POST http://localhost:8080/upload
# {"status":"error","errors":[{"field":null,"code":"malformed_request","message":"The request must be multipart/form-data."}]}
```

PHP errors and warnings are never shown in responses; they go to the container log:

```sh
docker compose logs -f web
```

## A second copy next to this one

Several copies of the project, e.g. clones or worktrees, can run on one machine at the same time. In each copy's `.env` set its own host port and its own Compose project name:

```sh
APP_PORT=8090
COMPOSE_PROJECT_NAME=image-rescaler-copy2
```

Without the project name, two copies in directories with the same name would share containers and replace each other. With these two lines `docker compose up --build -d` in that copy serves `http://localhost:8090`, and `docker compose exec web …` there runs in its own container.

`compose.prod.yaml` names its project `image-rescaler-prod`, but only when `.env` has no `COMPOSE_PROJECT_NAME`; with it, the production setup takes the name from `.env` like the development one. So every copy that runs the production image needs its own `COMPOSE_PROJECT_NAME` and its own `APP_PORT`; copies without the variable, or with the same value, collide even on different ports. For the same reason the production and development setups of one copy share a project name, and `docker compose -f compose.prod.yaml up` replaces that copy's development container.

The `curl` examples in this README assume the default port `8080`; with another `APP_PORT`, replace the port in the URL.

## Production image

The Dockerfile has two targets. `dev` is what Quick start runs. `prod` holds only the application code and production dependencies (no Composer, Xdebug or dev packages), runs Apache as `www-data` and reports its health through `GET /health`:

```sh
docker compose -f compose.prod.yaml up --build -d
docker compose -f compose.prod.yaml ps      # STATUS shows (healthy)
```

It publishes the same host port as the development container, `APP_PORT` from `.env`, so stop that one first (`docker compose stop`). Settings come from the same `.env`; files stored with the `local` service and the input directory live in the `storage` volume, which starts with the sample images when it is created.

## Configuration

All settings come from `.env`: Compose passes them to the container as environment variables, and the application reads them once at startup (`AppConfig`). Tests take theirs from `phpunit.xml`.

| Variable | Description |
|----------|-------------|
| `APP_PORT` | host port of the development container, `8080` by default |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | S3 credentials |
| `AWS_REGION` | S3 region, e.g. `eu-central-1` |
| `AWS_BUCKET_NAME` | target bucket |
| `DROPBOX_ACCESS_TOKEN` | Dropbox API token; the app needs the `files.content.write`, `files.metadata.read` and `sharing.write` scopes, the last one for the returned link |
| `DROPBOX_DIRECTORY` | Dropbox folder the images go to, `/Aplikacje/image-rescaler` by default; it must start with `/`, otherwise the app refuses to start (`/health` answers `500`, the log says why) |
| `INPUT_DIR` | directory inside the container the CLI reads images from, `storage/input` when empty |

The limits of the API (types, size, dimensions) are fixed by the contract, not by configuration. Only the variables of the storage you use need real values.

## API

The contract is [openapi.yaml](openapi.yaml) (OpenAPI 3.0): fields, limits, responses and error codes. Contract tests check the responses against it.

Any other path returns `404` with `{"status": "error", "errors": [{"field": null, "code": "not_found", "message": "Not found."}]}`.

### `GET /health`

Tells whether the application is up; meant for container health checks and monitoring. Returns `200`:

```json
{"status": "ok"}
```

### `POST /upload`

`multipart/form-data` fields:

| Field | Required | Description |
|-------|----------|-------------|
| `file` | `file` or `image` | JPEG, PNG or WebP, at most 5 MB and 5000 × 5000 px; the type is read from the content and the extension must match it |
| `image` | `file` or `image` | name of an image in the input directory (see `GET /images`) instead of sending a file; the same rules apply |
| `max_width` | yes | width of the frame, 40–5000 px |
| `max_height` | yes | height of the frame, 40–5000 px |
| `service` | no | `local` (default), `s3` or `dropbox` |
| `output_filename` | no | letters, digits, `-` and `_`, up to 100 characters; a `_v<n>` suffix keeps names unique |

The image is scaled down until it covers the frame and the overflow is cut off evenly, so the result has exactly the frame's size and the source's format. An image smaller than the frame is rejected rather than enlarged.

```sh
curl -X POST http://localhost:8080/upload \
  -F "file=@/path/to/image.jpg" \
  -F "max_width=200" \
  -F "max_height=150" \
  -F "service=local" \
  -F "output_filename=thumbnail"
```

Success (`200`):

```json
{"status": "success", "path": "thumbnail_v1.jpg", "url": "http://localhost:8080/files/thumbnail_v1.jpg"}
```

Invalid fields return `422` with all errors at once; a request that is not `multipart/form-data` returns `400` in the same shape:

```json
{"status": "error", "errors": [
  {"field": "file", "code": "smaller_than_frame", "message": "The image is 150 × 150 px; it must be at least 200 × 150 px to fill the frame."},
  {"field": "service", "code": "unsupported", "message": "service must be one of: local, s3, dropbox."}
]}
```

The codes are listed in [openapi.yaml](openapi.yaml). Processing and storage failures return `500` with `{"status": "error", "errors": [{"field": null, "code": "server_error", "message": "The image could not be processed or stored."}]}`; the details go to the container log (`docker compose logs web`).

`url` points to the stored file: for `local` it is served by `GET /files/{name}` below, for `s3` it is the object's URL, for `dropbox` a shared link.

### `GET /images`

Lists the images in the input directory, `storage/input/`: the samples shipped in `resources/images/` (put there when the container starts, without replacing a file of the same name) and the ones added with `POST /images`.

```sh
curl http://localhost:8080/images
# {"images":["landscape.jpg","portrait.png","square.webp"]}
```

Crop one of them by its name instead of sending a file:

```sh
curl -X POST http://localhost:8080/upload -F "image=landscape.jpg" -F "max_width=200" -F "max_height=150"
```

### `POST /images`

Adds an image to the input directory. `file` follows the same rules as in `POST /upload`, without the frame; the optional `name` (letters, digits, `-` and `_`, up to 100 characters) sets its name, otherwise the file's own name reduced to those characters is used, and the extension comes from the type. A name that is taken is `422` `already_exists`: nothing is replaced.

```sh
curl -X POST http://localhost:8080/images -F "file=@/path/to/photo.jpg" -F "name=holiday"
# {"status":"success","name":"holiday.jpg"}   (201)
```

### `GET /files/{name}`

Serves an image stored with the `local` service, under the name `POST /upload` returned:

```sh
curl -o thumbnail.jpg http://localhost:8080/files/thumbnail_v1.jpg
```

Only plain names of JPEG, PNG and WebP files directly in `storage/` are served; anything else, `storage/input/` included, is `404`.

To send these requests from PhpStorm, with ready examples and checks of the responses, follow [docs/phpstorm-http-client.md](docs/phpstorm-http-client.md).

## CLI

The CLI reads images only from the input directory, `storage/input/` (`INPUT_DIR` overrides it), the same one `GET /images` lists, and goes through the same validation and processing as the API:

```sh
cp photo.jpg storage/input/
docker compose exec web bin/resize -f photo.jpg -w 200 -h 150 -o thumbnail
# Stored thumbnail_v1.jpg
docker compose exec web bin/resize -f landscape.jpg -w 200 -h 150   # a sample image
```

| Option | Description |
|--------|-------------|
| `-f` | file name in the input directory; a name leading outside it (`../`, an absolute path, a symlink) is rejected |
| `-w`, `-h` | width and height of the frame in px, as `max_width` and `max_height` in the API |
| `-s` | `local` (default), `s3` or `dropbox` |
| `-o` | name of the stored file, as `output_filename` in the API |

Invalid options are all reported on stderr, one line per option, and the exit code is `1`.

## Development

Tests and quality tools run in the container as Composer scripts:

```sh
docker compose exec web composer test                # all tests; test:unit, test:functional, test:integration or test:contract for one suite
docker compose exec web composer phpstan             # static analysis, level max, and architecture rules
docker compose exec web composer cs                  # code style check (PER-CS); cs:fix applies it
docker compose exec web composer check               # cs, phpstan and test in one go, as before a PR
```

`docker compose exec web composer list` shows them with descriptions. PHPStan runs at level max without a baseline: the code has no recorded errors left.

The architecture rules are PHPStan rules from phpat, written as classes in `tests/Architecture`: Domain depends only on itself and PHP's own classes, Application never on Infrastructure or on packages that do I/O, and Application holds no adapter (no class implementing a Domain interface) and no controller or command. Calls to I/O functions (`file_put_contents`, `getenv`) and scripts without a class (`bin/resize`, `routes.php`) are out of their reach and stay with code review.

Unit tests cover single classes, functional tests a service with its external client mocked, integration tests the whole upload through the controller into `storage/`, without mocks. Contract tests send one request per rule of `openapi.yaml` and check every response against it. S3 and Dropbox have no integration tests, since they need real accounts.

Tests take their environment variables from `phpunit.xml`, not from `.env`, and run in random order, so they cannot lean on each other. PHPUnit prints the seed; `docker compose exec web vendor/bin/phpunit --order-by random --random-order-seed <seed>` repeats that order.

To run and debug tests from PhpStorm (Docker interpreter, Xdebug), follow [docs/phpstorm-xdebug.md](docs/phpstorm-xdebug.md).

### Checking a branch from scratch

`bin/fresh-check` runs on the host and checks a pushed branch the way a new clone would see it: it clones the branch into a temporary directory, copies `.env-example` to `.env`, builds the dev image without cache, waits for `GET /health` and runs `composer check`. With `--prod` it then builds the production image and waits until it is healthy. Containers, images and the clone are removed at the end, also after a failure.

```sh
bin/fresh-check <branch>                # port 8090 by default
bin/fresh-check --prod <branch> 8091    # also the production image, on port 8091
```

Besides Docker it needs `git` and `curl` on the host. It runs next to the development container; the port only has to be free. CI runs `composer check` and the production image health check on every push.

## Project structure

```
src/
├── Domain/                enums, value objects and interfaces (ports)
├── Application/           the resize use case and its validation
└── Infrastructure/        Container and AppConfig, HTTP controllers, CLI, storage adapters, image processing
public/index.php           HTTP entry point → routes.php → controllers (bramus/router)
bin/resize                 CLI entry point
openapi.yaml               API contract
resources/images/          sample images, copied into storage/input on start
docker/entrypoint.sh       dev container start: dependencies, storage and input dirs, Apache
docker/php.ini             PHP settings: errors to the log, not to responses
docker/xdebug.ini          Xdebug settings, dev image only
bin/fresh-check            check of a pushed branch from a fresh clone
docs/                      step-by-step guides, linked from this README
http/                      PhpStorm HTTP client requests (api.http) and environments
tests/                     Unit, Functional, Integration and Contract (PHPUnit), Architecture (phpat); Support/ holds helpers, fixtures/ the test image
phpstan.neon.dist          PHPStan config
.php-cs-fixer.dist.php     PHP CS Fixer config
```

## License

MIT, see [LICENSE](LICENSE).
