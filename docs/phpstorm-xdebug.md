# PhpStorm: tests and Xdebug from the container

PHP is not installed on the host, so PhpStorm has to use the PHP from the `web` container. After this one-time setup you can run and debug tests with the gutter arrows.

The development image already contains Xdebug (`docker/xdebug.ini`): step debugging on port 9003, started only when a run asks for it. PhpStorm asks for it by itself when you choose **Debug**.

Before you start: the container must be running (`docker compose up -d`).

## A. CLI interpreter

1. Open **Settings** (`⌘,` on macOS, `Ctrl+Alt+S` elsewhere) and select **PHP** in the tree.
2. In the **CLI Interpreter** row click the `…` button. The "CLI Interpreters" window opens.
3. Click `+` in the top left corner → **From Docker, Vagrant, VM, WSL, Remote…**
4. Select **Docker Compose** and fill in:
   - **Server**: click **New…**, choose your Docker (e.g. **Docker for Mac**), wait for "Connection successful", confirm with OK. This is needed only once per PhpStorm installation.
   - **Configuration files**: `./compose.yaml`
   - **Service**: `web`
   - **PHP interpreter path**: `php`
5. Confirm with OK. The new interpreter appears on the left of the "CLI Interpreters" window.
6. Under **Lifecycle** select **Connect to existing container ('docker-compose exec')**. The other option starts a new container for every run and installs Composer dependencies each time.
7. Below you should see the PHP version (8.5.x) and "Debugger: Xdebug 3.x". If not, click the refresh icon next to **PHP executable**.
8. Name it, e.g. `image-rescaler web`, and confirm with OK.
9. Back on the **PHP** page choose this interpreter in **CLI Interpreter**. **Path mappings** should show the project directory mapped to `/var/www/html`. Click **Apply**.

## B. PHPUnit

1. In the tree go to **PHP → Test Frameworks**.
2. Click `+` → **PHPUnit by Remote Interpreter** → choose the interpreter from part A → OK.
3. Select **Use Composer autoloader**, set **Path to script** to `/var/www/html/vendor/autoload.php` and click the refresh icon next to it. The PHPUnit version should appear.
4. Tick **Default configuration file** and set it to `/var/www/html/phpunit.xml`. Confirm with OK.

## C. Run and debug a test

1. Open any test class.
2. Click the green arrow next to the class name → **Run**. The results show up in the Run panel.
3. Set a breakpoint (click in the gutter) in the class that the test covers.
4. Click the same arrow → **Debug**. Execution stops at the breakpoint and the Debug panel shows the variables.

## When something does not work

- **"Container is not running"**: start it with `docker compose up -d` and run again.
- **No "Debugger: Xdebug" in the interpreter**: rebuild the image (`docker compose up --build -d`) and click the refresh icon from step A.7.
- **The breakpoint is ignored**: check that **Path mappings** (step A.9) point to `/var/www/html` and that the debug port in **PHP → Debug → Xdebug** includes 9003.
