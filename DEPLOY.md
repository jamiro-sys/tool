# Betrieb auf Netcup Webhosting 2000

Einmalige Einrichtung, danach aktualisiert sich das Tool bei jedem Push auf `main` von selbst.

## Aufbau auf dem Webspace

```
ok-tool/
  shared/
    .env              Zugangsdaten (nur auf dem Server)
    storage/          Dokumente, Sicherungen, Logs
  releases/
    20261119...-abc1234/   die letzten 5 Versionen
  current -> releases/... aktive Version
```

Ein Update lädt eine neue Version nach `releases/`, sichert die Datenbank, migriert, und schaltet dann `current` um. Fällt etwas aus, bleibt die alte Version aktiv.

## 1. Plesk vorbereiten

1. **PHP**: Unter *PHP-Einstellungen* der Domain PHP 8.3 oder neuer wählen (FPM). Erweiterungen `pdo_mysql`, `mbstring`, `intl`, `gmp` (für Push) aktiv lassen.
2. **Datenbank**: Unter *Datenbanken* eine MariaDB-Datenbank mit Benutzer anlegen.
3. **SSH**: Unter *Webhosting-Zugang* SSH-Zugriff aktivieren (`/bin/bash`).
4. **Dokumentstamm**: Unter *Hosting-Einstellungen* den Dokumentstamm auf `ok-tool/current/public` setzen.
5. **SSL**: Let's Encrypt aktivieren und "Weiterleitung auf HTTPS" einschalten.
6. **E-Mail**: Ein Postfach anlegen, z. B. `ok@deine-domain.ch`, für den Versand.

## 2. Einmal per SSH einrichten

Den PHP-Befehl prüfen. Je nach Server heisst er anders, zum Beispiel:

```bash
php -v
```

Falls dort eine alte Version erscheint, in Plesk unter *PHP-Einstellungen* den Pfad der CLI nachsehen (oft `/opt/plesk/php/8.3/bin/php`) und diesen überall statt `php` verwenden.

Ordner und Konfiguration anlegen:

```bash
mkdir -p ~/ok-tool/shared ~/ok-tool/releases
nano ~/ok-tool/shared/.env
```

Inhalt der `.env` (Werte anpassen):

```
APP_NAME="OK-Tool"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://deine-domain.ch
APP_TIMEZONE=Europe/Zurich
APP_LOCALE=de

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_LIFETIME=43200
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=...            (Postausgangsserver aus Plesk, z. B. mxXXXX.netcup.net)
MAIL_PORT=465
MAIL_USERNAME=ok@deine-domain.ch
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=ok@deine-domain.ch
MAIL_FROM_NAME="OK Raiffeisen-Hallencup"

VAPID_SUBJECT="mailto:ok@deine-domain.ch"
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
```

Den `APP_KEY` erzeugst du nach dem ersten Update (Schritt 4).

## 3. GitHub verbinden

1. Auf deinem Computer einen eigenen Schlüssel nur für die Updates erzeugen:

   ```bash
   ssh-keygen -t ed25519 -f ok-tool-deploy -N ""
   ```

2. Den Inhalt von `ok-tool-deploy.pub` auf dem Server an `~/.ssh/authorized_keys` anhängen.
3. Im GitHub-Repository unter *Settings > Secrets and variables > Actions*:
   - Secrets: `SSH_HOST` (Server aus Plesk), `SSH_USER`, `SSH_PORT` (falls nicht 22), `SSH_KEY` (Inhalt von `ok-tool-deploy`), `DEPLOY_PFAD` (absoluter Pfad, z. B. `/var/www/vhosts/deine-domain.ch/ok-tool`, mit `pwd` im Ordner prüfen)
   - Variables: `DEPLOY_AKTIV` = `ja`, optional `PHP_BIN` (z. B. `/opt/plesk/php/8.3/bin/php`)
4. Unter *Settings > Environments* ein Environment `produktion` anlegen. Dort kann man auf Wunsch eine Freigabe vor jedem Update verlangen.

Ab jetzt gilt: Push auf `main` → Tests laufen → bei Erfolg wird das Update eingespielt. Manuell geht es unter *Actions > Tests und automatisches Update > Run workflow*.

## 4. Erstes Update und erster Admin

Nach dem ersten erfolgreichen Update per SSH:

```bash
cd ~/ok-tool/current
php artisan key:generate --force
php artisan db:seed --force
php artisan ok:admin deine@adresse.ch "Vorname Nachname"
```

`key:generate` schreibt in `shared/.env`. Danach einmal `php artisan optimize` ausführen. `db:seed` legt Ressorts, die zwei Turniere, zwei Hallen und typische Vorlagen an (in Produktion ohne Demodaten). `ok:admin` gibt einen Einladungslink aus, über den du dein Passwort setzt.

## 5. Cronjob

In Plesk unter *Geplante Aufgaben* eine Aufgabe anlegen:

- Typ: Befehl ausführen
- Befehl: `php ~/ok-tool/current/artisan schedule:run` (bei Bedarf mit vollem PHP-Pfad)
- Ausführen: jede Minute (`* * * * *`). Erlaubt das Paket nur alle 5 Minuten, geht das auch: alle Zeitpunkte im Tool sind auf 5 Minuten abgestimmt, Mails kommen dann bis zu 5 Minuten später.

Der Cron erledigt: Warteschlange (Mails, Push), Erinnerungen 07.15 Uhr, Tageszusammenfassung 18.00 Uhr, Wochenmail, Status der Ausgaben, Sicherung 02.30 Uhr. Unter *Admin > System* siehst du, ob er läuft.

## 6. Push einschalten

Unter *Admin > System > Push-Schlüssel erzeugen* klicken und die zwei Werte in `shared/.env` eintragen, dann `php artisan optimize`. Danach kann jede Person Push unter *Benachrichtigungen* auf ihrem Gerät aktivieren. Auf dem iPhone muss das Tool dafür zuerst über *Teilen > Zum Home-Bildschirm* installiert werden.

## Zurückrollen

```bash
bash ~/ok-tool/current/scripts/zurueckrollen.sh ~/ok-tool
```

Schaltet auf die vorherige Version. Datenbankänderungen bleiben bestehen; bei Bedarf unter *Admin > Sicherungen* die Sicherung `vor-update` einspielen.

## Wenn etwas nicht geht

| Problem | Lösung |
|---|---|
| Weisse Seite oder Fehler 500 | *Admin > Logs* oder `~/ok-tool/shared/storage/logs/` ansehen |
| Keine Mails | *Admin > System > Testmail an mich*, Versandprotokoll prüfen |
| Cron "prüfen" | PHP-Pfad im Cronjob prüfen |
| Update in GitHub rot | Den Schritt in *Actions* öffnen, die Meldung zeigt die Ursache |
