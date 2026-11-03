# RPS-Banka
19.10, Banka

## Zagon spletne banke

Strani uporabljajo Bootstrap 5 in PHP API, ki je povezan z MySQL/MariaDB shemo
`benkobanka.sql`. PHP strežnik je obvezen: strani ne bodo delovale prek
Live Serverja ali z neposrednim odpiranjem HTML datotek.

1. Zaženite MySQL v XAMPP Control Panelu.
2. V phpMyAdmin izberite **Import** in uvozite datoteko `benkobanka.sql`.
3. Dvokliknite `zagon.bat` v korenski mapi projekta. Za lokalni XAMPP uporabi
   privzete nastavitve (`127.0.0.1`, baza `benkobanka`, uporabnik `root` brez
   gesla). Če imate drugačne podatke, pred zagonom nastavite spremenljivke
   `BENKOBANKA_DB_HOST`, `BENKOBANKA_DB_NAME`, `BENKOBANKA_DB_USER` in
   `BENKOBANKA_DB_PASSWORD`. Skripta uporabi PHP iz `PATH` ali
   `C:\xampp\php\php.exe`.
4. Odprite `http://127.0.0.1:8000/`. Če povezava do baze ni nastavljena,
   PHP bo prikazal napako za bazo; za napako omrežne povezave preverite, ali
   strežnik še teče in ali ste stran odprli na zgornjem naslovu.
5. Datoteko `benkobanka.mp3` pozneje dodajte v isti imenik kot HTML strani.
   Glasba se začne predvajati po kliku na gumb »Vklopi glasbo«.

Registracija shrani geslo kot varno zgoščeno vrednost in odpre osebni račun z
začetnim stanjem 0 €. Prijava, pregled računov/kreditov in urejanje profila
delujejo prek sej in tabel `Stranka`, `Racun` ter `Kredit`. To je šolski
prototip, ne produkcijski sistem za obdelavo pravih bančnih podatkov.
