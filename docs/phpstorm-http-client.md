# PhpStorm: trying the API with the HTTP client

`http/api.http` holds ready requests for every endpoint, each with a short check of the response. PhpStorm runs them with its built-in HTTP client; no plugin or extra tool is needed.

Before you start: the container must be running (`docker compose up -d`).

## Environments

`http/http-client.env.json` defines where the requests go:

| Environment | `baseUrl` | Use for |
|-------------|-----------|---------|
| `dev` | `http://localhost:8080` | this copy of the project |
| `review` | `http://localhost:8081` | a second copy next to it, see "A second copy next to this one" in the README |

## Running the requests

1. Open `http/api.http`.
2. In the toolbar at the top of the editor, open the environment selector (it shows **No Environment** at first) and choose `dev` or `review`.
3. Run one request: click the green arrow in the gutter next to its `###` line.
   Run all of them: click the double arrow (**Run All Requests in File**) in the same toolbar.
4. The **Services** tool window opens with the response. Its **Tests** tab shows whether each check passed.

## What the requests do

| Request | Expected |
|---------|----------|
| Health check | `200`, `{"status":"ok"}` |
| Upload the test image | `200`, the image from `tests/fixtures/` cropped to 100 × 100 and stored as `http_client_test_v<n>.jpg` in `storage/` |
| Upload a file type that is not allowed | `422`, error `unsupported_type` for `file` |
| Upload without the required fields | `422`, error `required` for `file`, `max_width` and `max_height` at once |
| Upload without multipart | `400`, error `malformed_request` |
| Unknown route | `404` with `{"status":"error","message":"Not found"}` |

Each run of the upload request adds another file to `storage/`; remove them with `rm storage/http_client_test_v*.jpg`.
