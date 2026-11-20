# Domain

The core vocabulary of the application: enums, value objects and the interfaces (ports) that Application calls and Infrastructure implements. No logic that touches files, network or environment.

## Public API

### Enums (`Enums/`)

| Enum | Cases | Notes |
|---|---|---|
| `StorageType` | `Local` (`local`), `S3` (`s3`), `Dropbox` (`dropbox`) | values are the accepted `service` field |
| `ImageFormat` | `Jpeg`, `Png`, `Webp`, backed by the MIME type | `extension()`, `extensions()`, `label()` |
| `ImageAddOutcome` | `Added`, `AlreadyExists`, `LimitReached` | result of adding an image to the input directory |

### Value objects (`ValueObjects/`)

| Class | Holds |
|---|---|
| `ImageInfo` | `mimeType`, `width`, `height` read from the file's content |
| `ImageValueObject` | `getPath()`, `getFilename()` of an image ready to store |

### Interfaces (`Interfaces/`)

| Interface | Methods | Implemented in Infrastructure by |
|---|---|---|
| `ImageInspector` | `sizeInBytes()`, `inspect()` (null when not an image) | `Image\LocalImageInspector` |
| `ImageLibrary` | `names()`, `path()`, `addWithinLimit()` | `Filesystem\InputDirectory` |
| `ImageReencoder` | `reencode()` into a clean temporary file | `Image\InterventionImageReencoder` |
| `ImageResizer` | `cover()`: scale to cover the frame, crop the overflow evenly | `Image\InterventionImageResizer` |
| `StorageRegistry` | `get(StorageType)` | `Storage\LazyStorageRegistry` |
| `StorageServiceInterface` | `upload()`, `getPublicUrl()` | `Storage\AbstractStorageService` → `LocalStorageService`, `S3StorageService`, `DropboxStorageService` |

## Dependencies

- Allowed: `App\Domain` and PHP's standard classes, nothing else. It is a whitelist, so a new package or layer is rejected without changing the rule.
- Enforced by `test_domain_depends_only_on_itself_and_php` in `tests/Architecture/LayerDependencyTest.php` (phpat, run by PHPStan).
- Used by: Application (the ports and enums) and Infrastructure (the adapters).

## How to test

- Domain has no test directory of its own; its enums and value objects are exercised through the tests of Application (`tests/Unit/Application`) and Infrastructure (`tests/Functional/Infrastructure`).
- The dependency rule is checked by `docker compose exec web composer phpstan`, not by PHPUnit.
- Before a PR: `docker compose exec web composer check` (CS, PHPStan with the architecture rules, all tests).
