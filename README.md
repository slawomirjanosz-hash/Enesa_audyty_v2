# System zarządzania firmą

Aplikacja Laravel wspierająca obsługę klientów i dostawców, CRM, oferty, audyty, projekty, harmonogramy Gantta, finanse, zapotrzebowania, dokumenty i strefę klienta. Widoczność modułów można ustawić osobno dla każdego wdrożenia w `Ustawienia → Dane firmy`.

## Wymagania

- PHP 8.2 lub nowszy z rozszerzeniami GD i ZIP
- Composer 2
- Node.js i npm
- MySQL/MariaDB w środowisku produkcyjnym; testy używają SQLite

## Uruchomienie lokalne

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

Na Windows plik `.env.example` można również skopiować ręcznie. Dane pierwszego administratora należy utworzyć odpowiednim seederem lub w istniejącym procesie administracyjnym.

## Kontrola jakości

```bash
php artisan test
npm run build
composer audit --locked --no-dev
npm audit
```

Testy funkcjonalne obejmują uprawnienia, moduły aplikacji, klientów, dostawców, projekty, finanse, import Excela i Gantta.

## Konfiguracja środowiska

Najważniejsze zmienne znajdują się w `.env.example`. W produkcji należy ustawić co najmniej:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` i `APP_KEY`
- połączenie `DB_*`
- `MAIL_MAILER`, `MAIL_FROM_*`, `ADMIN_EMAIL` i klucz wybranego dostawcy poczty
- `PUBLIC_SURVEY_URL`, jeśli publiczne formularze mają korzystać z innej domeny
- `APP_TIMEZONE=Europe/Warsaw`

Nie należy przechowywać prawdziwych kluczy ani haseł w repozytorium.

## Railway

Railway korzysta z `railpack.json` i automatycznego dostawcy PHP. Railpack instaluje rozszerzenia wymagane w `composer.json`, zależności PHP i Node, buduje zasoby Vite, optymalizuje Laravel, tworzy dowiązanie storage oraz wykonuje migracje przy starcie.

Przypomnienia o zaległych zadaniach korzystają z harmonogramu Laravel. Na Railway trzeba dodać osobny Cron Job uruchamiany co minutę:

```bash
php artisan schedule:run
```

Harmonogram aplikacji sam wysyła przypomnienia codziennie o 08:00 czasu `Europe/Warsaw`. Obecne wiadomości e-mail są wysyłane synchronicznie, więc osobny worker kolejki nie jest wymagany.

## Struktura modułów

- `CompanySettings::enabled_modules` steruje dostępnością modułów całego wdrożenia.
- Dostęp pracowników do klientów i dokumentów jest ograniczany przez `AuditorAccessService` i polityki Laravel.
- Klienci korzystają wyłącznie ze strefy klienta; trasy pracowników są chronione middleware `staff.role`.
- Dostawcy są oddzieleni od klientów przez `companies.company_type`.
- Projekt może być wewnętrzny (`company_id = null`) albo przypisany do klienta.

## Dalsza rozbudowa

Największe starsze widoki i kontrolery zawierają nadal dużo kodu w pojedynczych plikach. Nowe funkcje warto dodawać jako osobne serwisy, Form Requesty i komponenty Blade/Livewire zamiast rozszerzać szczególnie `OfferController`, `companies/show.blade.php`, `offers/edit.blade.php` i `crm/index.blade.php`.

Globalna kontrola Laravel Pint nie jest jeszcze częścią CI, ponieważ starsze pliki wymagają osobnego, mechanicznego uporządkowania formatowania. Taką zmianę najlepiej wykonać w oddzielnym commicie, bez łączenia jej z rozwojem funkcji.
