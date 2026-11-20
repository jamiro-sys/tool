# OK-Tool Raiffeisen-Hallencup

Laravel 13, Blade, Alpine, Tailwind. Sprache Deutsch (Schweiz): ss statt scharfes s, keine Gedankenstriche.

## Regeln fuer Aenderungen
- **FCM-Styleguide nachfuehren**: Jede Aenderung an Farben, Schrift, Knoepfen, Feldern, Labels, Kopfzeile oder anderen sichtbaren Bausteinen auch in `resources/views/styleguide.blade.php` nachtragen (Abschnitt "Bausteine im OK-Tool" bzw. passender Abschnitt). Die Seite ist unter `/styleguide` und als fixer Eintrag in "Dateien" fuer alle herunterladbar (Drucken als PDF). Massgebend fuer die Inhalte ist der Styleguide-Canvas des OK.
- Keine vorbefuellten Inhalte: Listen und Kategorien legt das OK selbst an.
- Begriffe: "Turnier" = einzelne Durchfuehrung (Model `Ausgabe`), "Turnierart" = Reihe (Model `Turnier`).
- Vor jedem Merge: alle Tests, Browser-Durchgang von einem leeren Tool, Sweep aller Seiten auf PC und Handy, Screenshots der geaenderten Seiten selbst ansehen.
