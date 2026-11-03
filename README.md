# RPS-Banka
19.10, Banka

## Zagon spletne banke

Strani uporabljajo Bootstrap 5 in PHP API, ki je povezan z MySQL/MariaDB shemo
`benkobanka.sql`. Projekt zaženite prek PHP strežnika (ne z neposrednim
odpiranjem HTML datotek), na primer v imeniku projekta z `php -S localhost:8000`
ali prek Apache v XAMPP.

1. Uvozite `benkobanka.sql` v MySQL/MariaDB.
2. Pred zagonom nastavite okoljske spremenljivke
   `BENKOBANKA_DB_HOST`, `BENKOBANKA_DB_NAME` (`benkobanka`),
   `BENKOBANKA_DB_USER` in `BENKOBANKA_DB_PASSWORD`. V XAMPP mora biti
   omogočena tudi razširitev PHP `pdo_mysql`.
3. Odprite `http://localhost:8000/RPS%2019.10%20banka%20spletna%20stram/index.html`.
4. Datoteko `benkobanka.mp3` pozneje dodajte v isti imenik kot HTML strani.
   Glasba se začne predvajati po kliku na gumb »Vklopi glasbo«.

Registracija shrani geslo kot varno zgoščeno vrednost in odpre osebni račun z
začetnim stanjem 0 €. Prijava, pregled računov/kreditov in urejanje profila
delujejo prek sej in tabel `Stranka`, `Racun` ter `Kredit`. To je šolski
prototip, ne produkcijski sistem za obdelavo pravih bančnih podatkov.
