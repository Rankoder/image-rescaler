## Summary

<!-- What changes and why. Mention files only where it helps. Nothing outside this branch's scope. -->

## How to test

<!-- Copy-paste commands from `git switch` to the expected output, so a reviewer can check without digging. Requests go to $BASE, so they work in a second copy on another port. Screenshots only for UI changes; for the API, curl with its response. -->

```sh
git switch <branch>
docker compose up --build -d
BASE=http://localhost:8080    # a second copy: its APP_PORT, e.g. 8081
curl -s $BASE/health
```

## Verified

<!-- `bin/fresh-check <branch>` with its result; `bin/fresh-check --prod <branch>` when the change touches Docker or Composer dependencies. Anything else that was run, with results. -->

## Not verified

<!-- What was not checked and why, e.g. S3 or Dropbox without real accounts. "Nothing" if everything was. -->
