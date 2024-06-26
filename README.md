# Image Rescaler

Aplikacja do przeskalowywania obrazów i przesyłania ich do wybranych przestrzeni do przechowywania plików, takich jak lokalny system plików, S3, Dropbox i OneDrive.

## Wymagania

- PHP 8.3
- Docker
- Docker Compose
- Composer

## Wymagania wstępne

1. Docker
2. Docker Compose

## Sekcja dla leniwych

```shell
composer run build            // Buduje projekt
composer run create-storage   // Tworzy folder storage
composer run ir-up            // Uruchamia obraz z dockera aplikacji
composer run install          // Instaluje zależności z composer.json
composer run run-tests-all    // Uruchamia wszystkie testy
```

## Instrukcja uruchomienia aplikacji

1. Utwórz plik `.env` na podstawie `.env.example` i wypełnij odpowiednie wartości:
2.  Utwórz folder storage w głównym katalogu projektu:
   ```shell
   mkdir storage
   ```

3. Zbuduj i uruchom kontenery Docker:
    ```sh
    docker-compose up --build
    docker-compose up
    ```
4. Zainstaluj zależności:
   ```shell
   docker-compose exec web composer install
   ```
5Sprawdź, czy aplikacja działa, odwiedzając `http://localhost:8080/public/upload` w przeglądarce. Powinna być taka wiadomość.
```{"status":"error","message":"Invalid request parameters"}```


## Instrukcja używania CLI

1. W kontenerze `web` uruchom poniższą komendę, aby przeskalować obraz i przesłać go do wybranego serwisu:
   ```
   -f: Ścieżka pliku (w przykładzie to ścieżka do folderu storage)
   -s: Nazwa serwisu: s3, dropbox, local
   -w: Szerokość obrazu w px
   -h: Wysokość obrazu w px
   -o: Nazwa wyjściowa
   ```

    ```sh
    docker-compose exec web php /var/www/html/src/Application/CLI/resize.php -f <ścieżka_do_pliku> -s <service> -w <max_width> -h <max_height> [-o <output_filename>]
    ```

   Przykład:
    ```sh
    docker-compose exec web php /var/www/html/src/Application/CLI/resize.php -f /var/www/html/storage/example.jpg -s local -w 200 -h 300 newName
    ```

## Instrukcja uruchomienia testów

1. Aby uruchomić testy jednostkowe i funkcjonalne, użyj poniższej komendy w kontenerze `web`:
    ```sh
    docker-compose exec web vendor/bin/phpunit tests/  
    ```

2. Aby uruchomić konkretne testy, użyj poniższej komendy, podając ścieżkę do pliku testowego:
    ```sh
    docker-compose exec web vendor/bin/phpunit tests/Functional/Services/LocalStorageServiceTest.php
    ```

## Korzystanie z API

1. Aby przesłać obraz za pomocą API, użyj narzędzia takiego jak Postman lub cURL, aby wysłać POST request na `http://localhost:8080`.

2. Przykładowy request:
    ```sh
    curl -X POST http://localhost:8080 \
    -F "file=@/path/to/your/image.jpg" \
    -F "service=local" \
    -F "max_width=200" \
    -F "max_height=300" \
    -F "output_filename=rescaled_image"
    ```

3. Otrzymasz odpowiedź zawierającą status i URL do przesłanego obrazu:
    ```json
    {
        "status": "success",
        "path": "storage/images/rescaled_image_v1.jpg",
        "url": "url: http://localhost:8080/rescaled_image_v1.jpg"
    }
    ```





