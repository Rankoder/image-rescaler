# Application

The use cases shared by HTTP and the CLI and the one validation of their input. It works on plain values and Domain ports; reading requests, image files and the environment is left to Infrastructure.

## Public API

### Services (`Services/`)

| Class | Role | Entry |
|---|---|---|
| `ResizeAndStoreImage` | the resize use case: validate, crop, store | `handle(ResizeInput): StoredImage`, throws `ValidationFailed` |
| `InputImages` | lists the input directory and adds an image to it under the upload rules | `list()`, `add(?SourceFile, ?string $name)`, throws `ValidationFailed` |
| `OutputFilename` | the stored file's name: the client's `output_filename` or a generated one, plus the extension | `for(ResizeRequest)` |
| `StoredImage` | result of a resize | `path`, `url` |

### Validation (`Validation/`)

| Class | Role |
|---|---|
| `ResizeInput` | raw input as an entry point received it; `null` means the field was not given |
| `SourceFile` | the image to process: `at()` a readable path, or `rejected()` with an `ErrorCode` |
| `ResizeRequestValidator` | `validate(ResizeInput): ResizeRequest`; reports all invalid fields at once |
| `ResizeRequest` | validated input: source path and name, `ImageFormat`, frame size, `StorageType`, output name |
| `ImageFileRules` | rules every source image follows: type from content matching the extension, size, dimensions, frame |
| `NameRule` | a client-chosen name without extension; `safeVersionOf()` for names the client controls only loosely |
| `ValidationError` | one error: `Field` (or `null` for the whole request), `ErrorCode`, message |
| `ValidationFailed` | exception carrying the list of `ValidationError` |
| `Field`, `ErrorCode` | enums whose values are the field names and error codes of the HTTP contract |

The limits these classes check and the error codes they return are part of the HTTP contract in `openapi.yaml`.

## Dependencies

- Uses Domain: the ports `ImageInspector`, `ImageLibrary`, `ImageReencoder`, `ImageResizer`, `StorageRegistry`, the value objects and the enums.
- Forbidden, per `tests/Architecture/LayerDependencyTest.php` (phpat, run by PHPStan):
  - `App\Infrastructure` (`test_application_does_not_depend_on_infrastructure`),
  - packages that do I/O: `Aws`, `GuzzleHttp`, `Intervention`, `Kunnu`, `Nyholm`, `Psr\Http` (`test_application_reaches_the_outside_world_only_through_adapters`),
  - a class implementing a `App\Domain\Interfaces` port, since that makes it an adapter (`test_application_holds_no_adapter`),
  - a class named `*Controller` or `*Command` (`test_application_holds_no_entry_point`).
- Calls to I/O functions are outside the rules' reach and stay with code review.
- Used by: Infrastructure (controllers, `ResizeCommand`, `Container`, and `InputDirectory` and `MultipartRequest`, which build `SourceFile` and `ValidationError`).

## How to test

- Unit tests; where a class needs a port, the test passes an anonymous class or a fake from `tests/Support` (`FakeImageInspector`, `InMemoryImageLibrary`): `tests/Unit/Application/Services` (`InputImagesTest`, `OutputFilenameTest`, `ResizeAndStoreImageTest`) and `tests/Unit/Application/Validation` (`ResizeRequestValidatorTest`, which also covers `ImageFileRules` and `NameRule`).
- `docker compose exec web composer test:unit` runs them; the dependency rules are checked by `docker compose exec web composer phpstan`.
- Before a PR: `docker compose exec web composer check`.
