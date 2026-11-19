# OK-Tool Raiffeisen-Hallencup

Organisationstool für das OK des Raiffeisen-Hallencups des FC Münsterlingen. Zwei Turniere pro Saison, jedes in seiner Halle. Viele Aufgaben wiederholen sich, manche ändern sich, neue kommen dazu, alte fallen weg. Das Tool macht daraus einen klaren Jahreszyklus und sorgt dafür, dass Wissen im Ressort bleibt, wenn Personen wechseln.

## Grundidee

1. **Vorlagen** sind das Gedächtnis des OK: wiederkehrende Aufgaben mit Anleitung, Checkliste, Zeitpunkt (Tage vor dem Turnier) und Aufwand. Eine Vorlage gilt für beide Turniere oder nur für eines.
2. **Neue Ausgabe**: Der Assistent schlägt alle aktiven Vorlagen vor, berechnet die Termine ab Turniertag, übernimmt die verantwortlichen Personen vom letzten Mal und zeigt die Hinweise aus dem letzten Rückblick direkt daneben. Pro Aufgabe: übernehmen, anpassen oder weglassen. Neue Aufgaben lassen sich ergänzen.
3. **Rückblick**: Nach dem Turnier wird der effektive Aufwand erfasst und pro Aufgabe festgehalten, was bleibt, was angepasst und was gestrichen wird. Die Ressortleitung übernimmt die Vorschläge mit einem Klick in die Vorlagen. Einmalige Aufgaben werden so zu neuen Vorlagen. Gestrichene Vorlagen werden archiviert, nicht gelöscht. Jede Änderung an einer Vorlage steht im Verlauf.
4. **Ressortübergabe**: Jedes Ressort hat einen Steckbrief (Auftrag, Personen, Jahreslauf, Kontakte, Wissen, Dokumente, Erkenntnisse, Aufwand über die Jahre) und eine Übergabe-Bereitschaft in Prozent. Eine Übergabe läuft mit Checkliste, die Nachfolge bekommt sofort Einblick und kann bei Aufgaben als Begleitung mitarbeiten. Die Leitung wechselt erst, wenn beide Seiten bestätigt haben. Das Übergabedossier lässt sich drucken oder als PDF sichern.

## Rollen und Ansichten

| Rolle | Darf |
|---|---|
| Admin (OK-Präsidium) | alles, inkl. Ausgaben, Personen, System |
| Ressortleitung | eigenes Ressort voll: Vorlagen, Mitglieder, Übergaben, Erkenntnisse übernehmen |
| OK-Mitglied | Aufgaben im eigenen Ressort und eigene Aufgaben bearbeiten, kommentieren, Erkenntnisse festhalten |
| Nur lesen | alles sehen, nichts ändern (Einarbeitung, Schnuppern) |

Unabhängig davon wählt jede Person ihre **Ansicht**: *Einfach* (Fokus auf "Meine Aufgaben", grosse Knöpfe, Wischen zum Abhaken) oder *Experte* (Dashboards, Board, Zeitstrahl, Vorlagen, Auswertungen, Tastenkürzel `/` Suche, `n` Neu, `e` Bearbeiten).

## Weitere Funktionen

- Dashboard pro Ausgabe mit Countdown, Burndown, Status und Fortschritt pro Ressort
- Aufgaben als Liste, Board (Ziehen und Ablegen) oder Zeitstrahl relativ zum Turniertag
- Auswertung: Aufwand pro Ressort über die Jahre, Vergleich der Turniere, Pünktlichkeit, Arbeitsverteilung
- Benachrichtigungen in der App, per E-Mail und als Push (PWA), pro Ereignis und Kanal einstellbar, mit Ruhezeit und Tageszusammenfassung
- Erinnerungen vor Fälligkeit, Wochenmail, Rundmeldungen an alle oder ein Ressort
- Admin: Personen und Einladungen, "Als Person ansehen", Systemstatus (Cron, Warteschlange, fehlgeschlagene Jobs), Wartungsmodus, Logs, Sicherungen mit Wiederherstellung, Export und Import von Vorlagen, CSV, Gesamtarchiv, Protokoll aller Änderungen
- Automatische Updates über GitHub Actions, siehe [DEPLOY.md](DEPLOY.md)
- Erscheinungsbild nach dem FCM- und Hallencup-Styleguide (FCM-Rot, Kreide, Nachtblau, Linien-Gelb, Betongrau; Plus Jakarta Sans; Kopfzeile "Anstoss")

## Technik

Laravel 13, PHP 8.3, Blade mit Alpine.js, Tailwind CSS, Chart.js. MariaDB auf dem Webhosting, SQLite lokal und in den Tests. Kein Dauerprozess nötig: alles Zeitgesteuerte läuft über einen Cronjob.

## Lokal starten

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build
php artisan serve
```

Mit `APP_ENV=local` werden Demodaten angelegt. Anmeldung zum Beispiel mit `lena.meier@example.ch` (Admin) oder `sara.huber@example.ch` (Ressortleitung, einfache Ansicht), Passwort jeweils `passwort`.

Tests:

```bash
php artisan test
```

## Wichtige Orte im Code

| Thema | Datei |
|---|---|
| Ausgabe aus Vorlagen, Rückblick, Vorlagenverlauf | `app/Services/AusgabePlaner.php` |
| Benachrichtigungen | `app/Services/Benachrichtiger.php`, `app/Notifications/Hinweis.php`, `app/Channels/WebPushKanal.php` |
| Sicherungen | `app/Services/Sicherung.php` |
| Rechte | `app/Providers/AppServiceProvider.php` (Gates) |
| Zeitplan | `routes/console.php` |
| Übergabe-Bereitschaft | `app/Models/Ressort.php` (`wissensstand`) |
| Styleguide-Farben und Bausteine | `tailwind.config.js`, `resources/css/app.css`, `resources/views/components` |
