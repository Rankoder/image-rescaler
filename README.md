# Image Rescaler

A plain-PHP REST API and CLI tool that scales an image down to a thumbnail and stores it in the chosen storage: local filesystem, Amazon S3 or Dropbox.

## Requirements

- Docker with Compose v2 (`docker compose`)

PHP 8.3, Composer and all extensions run inside the container; nothing else is needed on the host.

## Quick start

```sh
cp .env-example .env          # then fill in the values, see Configuration
docker compose up --build -d
```

On start the container installs Composer dependencies and creates the `storage/` directory. The API is available at `http://localhost:8080`.

Check that it responds:

```sh
curl -X POST http://localhost:8080/upload
# {"status":"error","message":"Invalid request parameters"}
```

## Configuration

All settings come from `.env`:

| Variable | Description |
|----------|-------------|
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | S3 credentials |
| `AWS_REGION` | S3 region, e.g. `eu-central-1` |
| `AWS_BUCKET_NAME` | target bucket |
| `DROPBOX_ACCESS_TOKEN` | Dropbox API token |
| `ALLOWED_IMAGE_TYPES` | comma-separated MIME types, e.g. `image/jpeg,image/png` |
| `MAX_FILE_SIZE` | maximum upload size in bytes |
| `MAX_IMAGE_WIDTH`, `MAX_IMAGE_HEIGHT` | upper limit for the requested dimensions |

Only the variables of the storage you use need real values.

## API

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

## Tests

```sh
docker compose exec web vendor/bin/phpunit                          # all tests
docker compose exec web vendor/bin/phpunit --testsuite "Unit Tests"  # or "Functional Tests"
```

## Project structure

```
src/
├── Domain/           interfaces and value objects
├── Application/      image processing, validation, storage services; CLI entry point
└── Infrastructure/   HTTP controller
public/index.php      HTTP entry point → routes.php
docker/entrypoint.sh  container start: dependencies, storage dir, Apache
tests/                Unit and Functional (PHPUnit)
```

## Known limitations

- The image is resized to exactly `max_width` × `max_height`; the aspect ratio is not preserved.
- The `url` returned for `local` storage is not served by the web server.
- The CLI does not validate the input file the way the API does.
