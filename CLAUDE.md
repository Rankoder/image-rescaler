# image-rescaler

Aplikacja w czystym PHP, która skaluje wgrany obrazek do miniatury i zapisuje go w wybranej przestrzeni: lokalnie, w S3 albo w Dropboxie. Dwa wejścia: REST API i CLI.

## Struktura

| Ścieżka | Co tam jest |
|---------|-------------|
| `src/Domain/` | interfejsy i value objecty, bez zależności od reszty |
| `src/Application/` | serwisy: przetwarzanie, walidacja, zapis |
| `src/Application/CLI/resize.php` | wejście CLI |
| `src/Infrastructure/` | kontrolery HTTP |
| `public/index.php` → `routes.php` → `ImageController` | wejście API (bramus/router) |
| `config/` | konfiguracja S3 i Dropboxa ze zmiennych środowiskowych |
| `tests/Unit/`, `tests/Functional/` | PHPUnit; katalogi odwzorowują `src/` |

## Komendy

PHP i Composer uruchamiamy tylko w Dockerze, w serwisie `web`.

```sh
docker compose up --build -d                    # build i start (localhost:8080)
docker compose exec web composer install        # zależności; potrzebne po buildzie, bo montowany katalog przykrywa vendor/ z obrazu
docker compose exec web vendor/bin/phpunit      # wszystkie testy
docker compose exec web vendor/bin/phpunit --testsuite "Unit Tests"
```

## Git

- Branch od `master` i PR do `master`; bez pośredniego `develop`.
- Branch: `<type>/ir-<nr>-<short-desc>`, np. `docs/ir-02-claude-md`. Typy: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.
- Jeden numer to jeden task, czyli jeden branch i jeden PR. Numery rosną kolejno i nigdy się nie powtarzają. Poprawka w trakcie taska to kolejny commit na tym samym branchu.
- Commity po angielsku w Conventional Commits: `type(scope): imperative summary`, np. `fix(storage): reject path traversal in output filename`.
- Commity i push robi Konrad.

## Standard kodu

Kod, nazwy i README po angielsku.

Zostaje:
- `declare(strict_types=1)` w każdym pliku,
- PSR-4 (`App\` → `src/`) i podział na warstwy Domain / Application / Infrastructure.

Dochodzi:
- formatowanie według PER-CS,
- klasy `final`, chyba że dziedziczenie jest zaplanowane,
- promocja właściwości w konstruktorze i `readonly` wszędzie, gdzie stan się nie zmienia,
- enumy zamiast stringów z ustalonym zbiorem wartości (np. rodzaj przestrzeni),
- zależności wstrzykiwane przez konstruktor; żadnego `new` serwisów ani czytania `.env` w środku klas,
- żadnych docblocków powtarzających sygnaturę; komentarz tylko tam, gdzie kod nie powie „dlaczego”,
- typ pliku sprawdzany po zawartości (MIME), nie po rozszerzeniu; nazwy i ścieżki od użytkownika zawsze walidowane.

## README modułów

Moduł po przebudowie dostaje własny `README.md` (po angielsku): cel, publiczne API, zależności, jak testować. Przed pracą w module najpierw czytam jego README.

## Dokumentacja na bieżąco

Zmiana, która unieważnia ten plik albo README, poprawia je w tym samym tasku.
