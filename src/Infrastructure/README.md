# Infrastructure

Everything that touches the outside world: configuration, wiring, HTTP and CLI entry points, the filesystem, image processing, storage services and logging. It implements the Domain ports and calls the Application use cases.

## Entry points

| Script | Flow |
|---|---|
| `public/index.php` | → `routes.php` (bramus/router) → `Container` → controller |
| `bin/resize` | → `Container::resizeCommand()` → `ResizeCommand::run()` |

Both read `getenv()` once and pass it to `AppConfig::fromEnvironment()`; no class reads the environment itself.

## Public API

| Area | Class | Role |
|---|---|---|
| root | `Container` | builds every service from `AppConfig`; controllers create only the per-request `MultipartRequest` |
| `Config/` | `AppConfig`, `AwsConfig` | all settings, read once at startup (`APP_HOST`, `APP_PORT`, `INPUT_DIR`, `AWS_*`, `DROPBOX_ACCESS_TOKEN`, `DROPBOX_DIRECTORY`) |
| `Controllers/` | `HealthController` | `GET /health` |
| | `ImageController` | `POST /upload` → `ResizeAndStoreImage` |
| | `ImagesController` | `GET /images`, `POST /images` → `InputImages` |
| | `FileController` | `GET /files/{name}`: serves images stored with the local service |
| | `NotFoundController` | every route not matched above |
| `Cli/` | `ResizeCommand` | `bin/resize -f <file in the input directory> -w <max_width> -h <max_height> [-s <service>] [-o <output_filename>]` |
| `Http/` | `MultipartRequest` | reads a multipart form: fields, the file as a `SourceFile`, unreadable requests |
| | `JsonResponses` | JSON responses in the shapes `openapi.yaml` defines (validation, malformed request, not found, method not allowed, server error) |
| `Filesystem/` | `InputDirectory` | `ImageLibrary` adapter and `sourceFile()` resolver; accepts only `ImageName` files inside the input directory and removes the temporary file in `addWithinLimit()` |
| | `ExclusiveCopy` | copies a file to a name only if it is free, never exposing a partial file |
| `Image/` | `LocalImageInspector` | `ImageInspector` adapter: size, MIME type and dimensions from content |
| | `InterventionImageResizer` | `ImageResizer` adapter (Intervention Image, GD driver) |
| | `InterventionImageReencoder` | `ImageReencoder` adapter; drops embedded metadata |
| `Storage/` | `AbstractStorageService` | `StorageServiceInterface` base: stores under the first free `_v<n>` name |
| | `LocalStorageService`, `S3StorageService`, `DropboxStorageService` | the three storages of `StorageType` |
| | `LazyStorageRegistry` | `StorageRegistry` adapter; builds a storage only when it is asked for, so a missing S3 or Dropbox setting fails only the uploads that use it |
| `Logging/` | `ErrorLogLogger` | PSR-3 logger writing to PHP's error log (`docker compose logs web`) |

The HTTP contract (paths, fields, limits, error codes) is `openapi.yaml`.

## Dependencies

- May depend on Domain, Application and vendor packages. No phpat rule restricts this layer.
- Classes here use the AWS SDK, the Dropbox client, Intervention Image, `Psr17Factory` (nyholm/psr7) and PSR-3; the router (bramus/router) and `ServerRequestCreator` (nyholm/psr7-server) are used only in `routes.php`.
- Adapters implementing a Domain port belong here, as do controllers and commands; the rules in `tests/Architecture/LayerDependencyTest.php` keep them out of Application.
- Some classes use Application value types: `InputDirectory` (adapter) and `MultipartRequest` build `SourceFile` with `ErrorCode` and `Field`; `MultipartRequest` also builds `ValidationError`.
- Scripts without a class (`routes.php`, `bin/resize`) and calls to I/O functions are outside the phpat rules and stay with code review.

## How to test

| Suite | Location | Command |
|---|---|---|
| Unit | `tests/Unit/Infrastructure` (`Config`, `Controllers`, `Http`, `Storage`) | `docker compose exec web composer test:unit` |
| Functional | `tests/Functional/Infrastructure` (`Container`, controllers, filesystem, image, storage with mocked S3 and Dropbox clients), `tests/Functional/RoutesTest.php`, `tests/Functional/UploadAttemptsTest.php` | `docker compose exec web composer test:functional` |
| Integration | `tests/Integration`: upload into local storage, parallel uploads, `ResizeCommand` | `docker compose exec web composer test:integration` |
| Contract | `tests/Contract`: responses of `/health`, `/upload`, `/images`, `/files` against `openapi.yaml` | `docker compose exec web composer test:contract` |

- Helpers and the test image: `tests/Support`, `tests/fixtures`.
- S3 and Dropbox have no integration tests, since they need real accounts.
- Before a PR: `docker compose exec web composer check`.
