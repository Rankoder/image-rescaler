# PhpStorm: trying the API with the HTTP client

`http/api.http` holds ready requests for every endpoint, each with a short check of the response. PhpStorm runs them with its built-in HTTP client; no plugin or extra tool is needed.

Before you start: the container must be running (`docker compose up -d`).

## Environments

`http/http-client.env.json` defines where the requests go. The port in each `baseUrl` must be the `APP_PORT` from the `.env` of the copy you want to reach:

| Environment | `baseUrl` | Use for |
|-------------|-----------|---------|
| `dev` | `http://localhost:8080` | a copy running on the default `APP_PORT` |
| `review` | `http://localhost:8081` | a second copy on another `APP_PORT`; change the port if yours differs, see "A second copy next to this one" in the README |

## Running the requests

1. Open `http/api.http`.
2. In the toolbar at the top of the editor, open the environment selector (it shows **No Environment** at first) and choose `dev` or `review`.
   For a run configuration, set it in **Run → Edit Configurations → All in api → Environment** instead.
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
| List the images in the input directory | `200`, the sample images among them |
| Crop a sample image from the input directory | `200`, `landscape.jpg` cropped to 200 × 150 and stored as `http_client_sample_v<n>.jpg` |
| Add the test image to the input directory | `201` with `http_client_added.jpg` on the first run, `422` `already_exists` after that |
| Unknown route | `404`, error `not_found` |

Each run of the upload requests adds another file to `storage/`; remove them with `rm storage/http_client_*_v*.*`, and the added image with `rm storage/input/http_client_added.jpg`.
