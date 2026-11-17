# Image Rescaler

A plain-PHP REST API and CLI tool that scales an image down to a thumbnail and stores it in the chosen storage: local filesystem, Amazon S3 or Dropbox.

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
# {"status":"error","message":"Invalid request parameters"}
```

PHP errors and warnings are never shown in responses; they go to the container log:

```sh
docker compose logs -f web
```

## A second copy next to this one

A second copy of the project, e.g. a clone or worktree used for review, can run at the same time. In its `.env` set another host port and its own Compose project name:

```sh
APP_PORT=8081
COMPOSE_PROJECT_NAME=image-rescaler-review
```

Without the project name, two copies in directories with the same name would share containers and replace each other. With these two lines `docker compose up --build -d` in that copy serves `http://localhost:8081`, and `docker compose exec web …` there runs in its own container.

## Production image

The Dockerfile has two targets. `dev` is what Quick start runs. `prod` holds only the application code and production dependencies (no Composer, Xdebug or dev packages), runs Apache as `www-data` and reports its health through `GET /health`:

```sh
docker compose -f compose.prod.yaml up --build -d
docker compose -f compose.prod.yaml ps      # STATUS shows (healthy)
```

It publishes the same port 8080, so stop the development container first (`docker compose stop`). Settings come from the same `.env`; files stored with the `local` service live in the `storage` volume.

## Configuration

All settings come from `.env`:

| Variable | Description |
|----------|-------------|
| `APP_PORT` | host port of the development container, `8080` by default |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | S3 credentials |
| `AWS_REGION` | S3 region, e.g. `eu-central-1` |
| `AWS_BUCKET_NAME` | target bucket |
| `DROPBOX_ACCESS_TOKEN` | Dropbox API token |
| `ALLOWED_IMAGE_TYPES` | comma-separated MIME types, e.g. `image/jpeg,image/png` |
| `MAX_FILE_SIZE` | maximum upload size in bytes |
| `MAX_IMAGE_WIDTH`, `MAX_IMAGE_HEIGHT` | upper limit for the requested dimensions |

Only the variables of the storage you use need real values.

## API

Any other path or method returns `404` with `{"status": "error", "message": "Not found"}`.

### `GET /health`

Tells whether the application is up; meant for container health checks and monitoring. Returns `200`:

```json
{"status": "ok"}
```

### `POST /upload`

`multipart/form-data` fields:

| Field | Required | Description |
|-------|----------|-------------|
| `file` | yes | image to scale |
| `max_width` | yes | target width in px |
| `max_height` | yes | target height in px |
| `service` | no | `local` (default), `s3` or `dropbox` |
| `output_filename` | no | name of the stored file; a `_v<n>` suffix is added to keep names unique |

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
{"status": "success", "path": "thumbnail_v1.jpg", "url": "http://localhost:8080/thumbnail_v1.jpg"}
```

Errors return `{"status": "error", "message": "..."}` with `400` for missing or invalid fields and `500` for everything else.

## CLI

```sh
docker compose exec web php src/Application/CLI/resize.php \
  -f <file_path> -w <max_width> -h <max_height> [-s <service>] [-o <output_filename>]
```

| Option | Description |
|--------|-------------|
| `-f` | path to the image inside the container, e.g. `/var/www/html/storage/photo.jpg` |
| `-w`, `-h` | target width and height in px |
| `-s` | `local` (default), `s3` or `dropbox` |
| `-o` | name of the stored file |

## Development

Tests and quality tools run in the container as Composer scripts:

```sh
docker compose exec web composer test                # all tests; test:unit, test:functional or test:integration for one suite
docker compose exec web composer phpstan             # static analysis, level max
docker compose exec web composer cs                  # code style check (PER-CS); cs:fix applies it
docker compose exec web composer check               # cs, phpstan and test in one go, as before a PR
```

`docker compose exec web composer list` shows them with descriptions. Existing PHPStan errors are recorded in `phpstan-baseline.neon`; new code must pass without adding to it.

Unit tests cover single classes, functional tests a service with its external client mocked, integration tests the whole upload through the controller into `storage/`, without mocks. S3 and Dropbox have no integration tests, since they need real accounts.

Tests take their environment variables from `phpunit.xml`, not from `.env`, and run in random order, so they cannot lean on each other. PHPUnit prints the seed; `docker compose exec web vendor/bin/phpunit --order-by random --random-order-seed <seed>` repeats that order.

To run and debug tests from PhpStorm (Docker interpreter, Xdebug), follow [docs/phpstorm-xdebug.md](docs/phpstorm-xdebug.md).

## Project structure

```
src/
├── Domain/                interfaces and value objects
├── Application/           image processing, validation, storage services
│   └── CLI/resize.php     CLI entry point
└── Infrastructure/        HTTP controllers
public/index.php           HTTP entry point → routes.php → controllers (bramus/router)
config/                    S3 config from environment variables (dropbox.php is unused; Dropbox reads getenv directly)
docker/entrypoint.sh       dev container start: dependencies, storage dir, Apache
docker/php.ini             PHP settings: errors to the log, not to responses
docker/xdebug.ini          Xdebug settings, dev image only
docs/                      step-by-step guides, linked from this README
tests/                     Unit, Functional and Integration (PHPUnit); fixtures/ holds the test image
phpstan.neon.dist          PHPStan config; phpstan-baseline.neon holds existing errors
.php-cs-fixer.dist.php     PHP CS Fixer config
```

## Known limitations

- The image is resized to exactly `max_width` × `max_height`; the aspect ratio is not preserved.
- The `url` returned for `local` storage is not served by the web server.
- The CLI does not validate the input file the way the API does.

## License

MIT, see [LICENSE](LICENSE).
