@php
    $farben = [
        ['FCM-Rot', '#B8161C', '184 22 28', '0 95 90 20', 'Hauptfarbe: Flächen, Titel, Logo', 'bg-rot text-white', 45],
        ['Kreide', '#F6F5F0', '246 245 240', '0 0 3 3', 'Grund, Sponsorenleiste', 'bg-kreide text-nacht border border-nacht/10', 30],
        ['Nachtblau', '#14213D', '20 33 61', '100 80 35 50', 'Zweitfarbe: Text, Bänder', 'bg-nacht text-white', 15],
        ['Linien-Gelb', '#F5D000', '245 208 0', '0 15 100 0', 'Akzent, JAKO One Gelb 335, sparsam', 'bg-gelb text-nacht', 5],
        ['Betongrau', '#5B6472', '91 100 114', '40 25 10 45', 'Nebentext, Legenden', 'bg-beton text-white', 5],
    ];
    $stand = config('app.version').' | '.now()->format('d.m.Y');
@endphp
<!DOCTYPE html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FCM-Styleguide Raiffeisen-Hallencup</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm; }
        @media print { .abschnitt { break-inside: avoid-page; } a { color: inherit; text-decoration: none; } .seite-umbruch { break-before: page; } }
    </style>
</head>
<body class="bg-white text-nacht">
    <div class="kein-druck sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 bg-nacht px-4 py-3 text-white">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dateien.index') }}" class="label text-white">Zurück</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ asset('img/fcm-logo-negativ.svg') }}" download="fcm-logo-negativ.svg" class="knopf border border-white/30 text-white hover:bg-white/10"><x-icon name="download" class="h-4 w-4" /> Logo negativ (SVG)</a>
            <button onclick="window.print()" class="knopf bg-white text-nacht"><x-icon name="drucker" class="h-4 w-4" /> Als PDF herunterladen</button>
        </div>
    </div>

    <x-anstoss />

    <main class="mx-auto max-w-[182mm] px-4 py-8 sm:px-0">
        <p class="label text-rot">Styleguide | Ausgabe 2026/27</p>
        <h1 class="titel mt-1 text-5xl">Ein Turnier. Ein Auftritt.</h1>
        <p class="mt-3 max-w-prose text-sm text-beton">Plakat, Social Media, Rundschreiben, Website, Helfer-Shirts und dieses Tool sehen gleich aus: gleiche Farben, gleiche Logos, gleicher Aufbau. Diese Seite ist die Referenz für alle im OK und alle, die etwas für den Raiffeisen-Hallencup gestalten.</p>
        <p class="mt-2 text-xs text-beton">Stand {{ $stand }}</p>

        <section class="abschnitt mt-10">
            <h2 class="titel border-b-2 border-rot pb-1 text-xl">01 Logos</h2>
            <p class="mt-3 text-sm">Das Kreislogo des FC Münsterlingen ist das Hauptlogo. Dazu zwei Schriftlogos in Weiss für dunkle Flächen. Immer aus der Originaldatei, nie nachbauen, verzerren, drehen oder umfärben.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="border border-nacht/10 p-4"><p class="label">Hauptlogo, Kreis rot</p><p class="mt-1 text-sm">Standard auf Weiss und Kreide. Auch auf Nachtblau erlaubt.</p><p class="mt-2 text-xs text-beton">Originaldatei: 00_Brand_und_Vorlagen/Logos im Hallencup-Ordner.</p></div>
                <div class="bg-rot p-4 text-white"><img src="{{ asset('img/fcm-logo-negativ.svg') }}" alt="FCM Logo negativ" class="h-14"><p class="label mt-2 text-white/80">Schriftlogo stehend, negativ</p><p class="mt-1 text-sm">Nur auf FCM-Rot oder Nachtblau.</p></div>
                <div class="bg-nacht p-4 text-white"><p class="label text-white/70">Schriftlogo liegend, negativ</p><p class="mt-1 text-sm">FCM1947 und FCMünsterlingen. Für schmale Flächen: Kopfzeile, Banner, Shirt-Rücken.</p></div>
            </div>
            <ul class="mt-4 space-y-1.5 text-sm">
                <li><span class="font-bold">Schutzzone:</span> rundherum mindestens ein Drittel des Kreisdurchmessers frei, keine Texte oder Bilder darin.</li>
                <li><span class="font-bold">Mindestgrösse:</span> Kreis 15 mm (Druck) bzw. 48 px (digital), Schriftlogo 30 mm bzw. 140 px, an der Breite gemessen.</li>
                <li><span class="font-bold">Turniertitel:</span> Kreis plus «RAIFFEISEN» (klein, gesperrt) über «HALLENCUP» in der Titelschrift. Der Name ist Schrift, kein eigenes Logo.</li>
                <li><span class="font-bold">Hauptsponsor:</span> Raiffeisenbank Regio Altnau. Logo immer auf Weiss, in Originalfarben, nie umgefärbt oder beschnitten. Das Raiffeisen-Rot ist ihre Farbe, nicht unsere.</li>
            </ul>
        </section>

        <section class="abschnitt mt-10">
            <h2 class="titel border-b-2 border-rot pb-1 text-xl">02 Farben</h2>
            <p class="mt-3 text-sm">Fünf Farben, keine weiteren. FCM-Rot aus dem Vereinslogo ist die absolute Hauptfarbe. Nachtblau ergänzt, Gelb setzt Akzente wie die Linien am Hallenboden.</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-5">
                @foreach ($farben as [$name, $hex, $rgb, $cmyk, $wofuer, $klasse, $anteil])
                    <div class="flex flex-col">
                        <div class="{{ $klasse }} flex h-20 items-end p-2"><span class="titel text-sm {{ str_contains($klasse, 'text-white') ? 'text-white' : '' }}">{{ $anteil }} %</span></div>
                        <p class="mt-2 text-sm font-bold">{{ $name }}</p>
                        <p class="text-xs text-beton">{{ $wofuer }}</p>
                        <p class="mt-1 font-mono text-[11px]">HEX {{ $hex }}<br>RGB {{ $rgb }}<br>CMYK {{ $cmyk }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex h-3 w-full">@foreach ($farben as [$n, $h, $r, $c, $w, $k, $a])<span class="{{ $k }} border-0" style="flex: {{ $a }}"></span>@endforeach</div>
            <p class="mt-1 text-xs text-beton">Mengenverhältnis. CMYK sind Richtwerte: vor dem ersten grossen Druckauftrag einen Farb-Proof verlangen.</p>
            <div class="mt-4 grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">
                @foreach ([['Weiss auf Rot', 'bg-rot text-white', '6,6 : 1', true], ['Kreide auf Blau', 'bg-nacht text-kreide', '14,6 : 1', true], ['Gelb auf Blau', 'bg-nacht text-gelb', '10,6 : 1', true], ['Blau auf Gelb', 'bg-gelb text-nacht', '10,6 : 1', true], ['Rot auf Blau', 'bg-nacht text-rot', '2,4 : 1', false], ['Gelb auf Weiss', 'bg-white text-gelb border border-nacht/10', '1,5 : 1', false]] as [$t, $k, $kontrast, $ok])
                    <div><div class="{{ $k }} px-3 py-2.5 text-sm font-bold">{{ $t }}</div><p class="mt-1 text-[11px] {{ $ok ? 'text-beton' : 'font-bold text-rot' }}">{{ $ok ? 'Erlaubt' : 'Nicht verwenden' }}, Kontrast {{ $kontrast }}</p></div>
                @endforeach
            </div>
        </section>

        <section class="abschnitt mt-10">
            <h2 class="titel border-b-2 border-rot pb-1 text-xl">03 Schrift</h2>
            <p class="mt-3 text-sm">Eine Schriftfamilie: Plus Jakarta Sans (gratis bei Google Fonts, Ersatz Arial). Fett nur bei Titeln und Übertiteln, keine kursiven Texte.</p>
            <div class="mt-4 space-y-3 border-l-4 border-rot pl-4">
                <div><p class="label">ExtraBold 800, Versalien: Titel, Zahlen, Resultate</p><p class="titel text-3xl">Hallencup 2026/27</p></div>
                <div><p class="label">Bold 700, Versalien, leicht gesperrt: Zwischentitel, Labels</p><p class="text-sm font-bold uppercase tracking-label">Spielplan Samstag | Gruppe A</p></div>
                <div><p class="label">Regular 400: Fliesstext, nie fett, nie kursiv</p><p class="text-sm">Die Teams melden sich spätestens 30 Minuten vor dem ersten Spiel beim Turnierbüro.</p></div>
            </div>
            <table class="mt-4 w-full text-sm"><thead><tr class="border-b border-nacht/20 text-left"><th class="label py-1.5">Rolle</th><th class="label py-1.5">Druck A4</th><th class="label py-1.5">Digital</th></tr></thead>
                <tbody>@foreach ([['Titel', '48 bis 72 pt', '56 bis 96 px'], ['Zwischentitel', '16 bis 22 pt', '24 bis 32 px'], ['Fliesstext', '11 bis 12 pt', '16 bis 18 px'], ['Kleintext', '8 bis 9 pt', '13 bis 14 px']] as [$r, $d, $g])<tr class="border-b border-nacht/10"><td class="py-1.5 font-bold">{{ $r }}</td><td class="py-1.5">{{ $d }}</td><td class="py-1.5">{{ $g }}</td></tr>@endforeach</tbody></table>
        </section>

        <section class="abschnitt mt-10">
            <h2 class="titel border-b-2 border-rot pb-1 text-xl">04 Elemente</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><span class="font-bold">Kopfzeile «Anstoss»:</span> FCM-Rot, randabfallend. Links der Turniertitel, Datum als dritte Zeile. Hallenlinien weiss, 35 % Deckkraft, 2 px: Mittellinie durchgezogen über die ganze Höhe, Mittelkreis mit Anstosspunkt auf halber Höhe, mittig in der freien Fläche rechts, mindestens 16 px Abstand zu Titel und Text. Keine 16er-Bögen, in der Kopfzeile keine gelbe Linie.</li>
                <li><span class="font-bold">Tempo-Band:</span> Fläche um 12 Grad geneigt, Text gerade. Für Datum, Aufruf, Live-Resultat. Höchstens zwei pro Medium. <span class="tempo ml-1 text-xs font-bold uppercase tracking-label text-white"><span>Sa/So 5./6. Dez 2026</span></span></li>
                <li><span class="font-bold">Kategorien:</span> eckige Labels in Versalien <span class="marke-rahmen">Junioren E</span> <span class="marke-erledigt">Final</span>. Resultate immer in der Titelschrift.</li>
                <li><span class="font-bold">Icons:</span> Linien-Icons, 2 px Strich, runde Enden, eine Farbe. Keine Emojis in offiziellen Medien.</li>
                <li><span class="font-bold">Fotos:</span> echte Bilder vom eigenen Turnier, keine Stockfotos. Bei Kindern die Einwilligung der Eltern beachten. Über Fotos nur Weiss oder Kreide auf nachtblauer Fläche.</li>
            </ul>
        </section>

        <section class="abschnitt mt-10">
            <h2 class="titel border-b-2 border-rot pb-1 text-xl">05 Aufbau</h2>
            <ol class="mt-3 grid gap-2 text-sm sm:grid-cols-4">
                @foreach ([['Kopf', 'Turniertitel oben links, Datum darunter; Mittellinie und Mittelkreis rechts.'], ['Titel', 'Gross, fett, Versalien, linksbündig.'], ['Inhalt', 'Tempo-Band, dann Fakten mit Icons oder Text.'], ['Fuss', 'Sponsorenzeile: roter Akzent, feine Linie, Raiffeisen-Logo rechts.']] as $i => [$t, $b])
                    <li class="border border-nacht/10 p-3"><span class="zahl text-2xl text-rot">{{ $i + 1 }}</span><p class="mt-1 font-bold">{{ $t }}</p><p class="text-xs text-beton">{{ $b }}</p></li>
                @endforeach
            </ol>
            <p class="mt-3 text-xs text-beton">Ränder im Druck mindestens 15 mm, digital mindestens 6 % der Breite. Titel und Logo stehen an der gleichen linken Kante.</p>
        </section>

        <section class="abschnitt mt-10">
            <h2 class="titel border-b-2 border-rot pb-1 text-xl">06 Schreibweise</h2>
            <table class="mt-3 w-full text-sm"><tbody>
                @foreach ([['Name', 'Raiffeisen-Hallencup mit Bindestrich, kurz Hallencup. Nicht: Raiffeisen Hallen Cup, Hallen-Cup.'], ['Ausgabe', '2026/27'], ['Datum und Zeit', 'Sa/So, 5./6. Dezember 2026 | 09.00 Uhr'], ['Verein', 'FC Münsterlingen beim ersten Erwähnen ausgeschrieben, danach FCM.'], ['Hauptsponsor', 'Raiffeisenbank Regio Altnau'], ['Rechtschreibung', 'Schweizer Schreibweise: ss statt scharfes s. Keine Gedankenstriche.'], ['Im Tool', 'Ein Turnier ist eine Durchführung (z. B. Hallencup Dezember 2026/27), die Turnierart die Reihe (z. B. Hallencup Dezember).']] as [$k, $v])
                    <tr class="border-b border-nacht/10 align-top"><td class="w-40 py-2 pr-3 font-bold">{{ $k }}</td><td class="py-2">{{ $v }}</td></tr>
                @endforeach
            </tbody></table>
        </section>

        <section class="abschnitt mt-10">
            <h2 class="titel border-b-2 border-rot pb-1 text-xl">07 Nicht so</h2>
            <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                @foreach (['Logos nicht verzerren, drehen oder umfärben', 'Logos nie nachbauen', 'Weisse Logos nicht auf Gelb, Weiss oder Fotos', 'Sponsorlogo nie auf Farbe', 'Kein fetter oder kursiver Fliesstext', 'Keine Verläufe, keine Schatten', 'Keine Zusatzfarben', 'Kein roter Text auf Blau'] as $regel)
                    <li class="flex items-center gap-2 border border-nacht/10 px-3 py-2"><x-icon name="x" class="h-4 w-4 shrink-0 text-rot" /> {{ $regel }}</li>
                @endforeach
            </ul>
        </section>

        <section class="abschnitt mt-10">
            <h2 class="titel border-b-2 border-rot pb-1 text-xl">08 Bausteine im OK-Tool</h2>
            <p class="mt-3 text-sm">So setzt das Tool den Styleguide um. Neue Seiten verwenden nur diese Bausteine.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="space-y-2"><p class="label">Knöpfe</p><div class="flex flex-wrap gap-2"><span class="knopf-rot">Hauptaktion</span><span class="knopf-nacht">Zweitaktion</span><span class="knopf-rahmen">Neutral</span></div></div>
                <div class="space-y-2"><p class="label">Status-Labels</p><div class="flex flex-wrap gap-1.5"><span class="marke-offen">Offen</span><span class="marke-in_arbeit">In Arbeit</span><span class="marke-erledigt">Erledigt</span><span class="marke-gelb">Überfällig</span><span class="marke-rahmen">Kürzel</span></div></div>
                <div class="space-y-2"><p class="label">Felder</p><input class="feld" placeholder="Eingabefeld" readonly><select class="feld" disabled><option>Auswahl mit eigenem Pfeil</option></select></div>
                <div class="space-y-2"><p class="label">Zeitaufwand</p><x-aufwand name="muster_aufwand" :wert="1.5" /><p class="text-xs text-beton">Stunden und Minuten (5er Schritte), Anzeige z. B. «1 Std. 30 Min.»</p></div>
                <div class="space-y-2"><p class="label">Ja/Nein-Schalter</p><div class="inline-grid grid-cols-2 border border-nacht/20 text-[11px] font-bold uppercase tracking-label"><span class="bg-rot px-4 py-2 text-white">Ja</span><span class="px-4 py-2 text-beton">Nein</span></div><p class="text-xs text-beton">Ja Rot, Nein Nachtblau, inaktive Wahl Grau, z. B. «Jedes Jahr» im Jahresplan</p></div>
                <div class="space-y-2"><p class="label">Zeitpunkt vor dem Turnier</p><x-zeitpunkt name="muster_zeit" :wert="60" /><p class="text-xs text-beton">Tage, Wochen oder Monate (Monat = 30 Tage), Anzeige z. B. «2 Monate vorher»</p></div>
                <div class="space-y-2"><p class="label">Auswahlkacheln</p><label class="flex items-start gap-3 border border-rot bg-rot/5 px-3 py-2.5"><input type="radio" checked disabled class="mt-1"><span><span class="block text-sm font-bold">Jedes Jahr</span><span class="block text-xs text-beton">Gewählte Kachel mit rotem Rahmen</span></span></label></div>
                <div class="space-y-2"><p class="label">Karte und Fortschritt</p><div class="karte p-3"><p class="text-sm font-bold">Karte mit feiner Linie, ohne Schatten</p><x-fortschritt :prozent="60" class="mt-2" /></div></div>
            </div>
            <ul class="mt-4 space-y-1 text-xs text-beton">
                <li>Kalender: Kopf Nachtblau, gewählter Tag Rot, heute gelber Rahmen, eckig.</li>
                <li>Seitenleiste Nachtblau, aktiver Punkt mit rotem Rand links. Für alle: Start, Aufgaben, Jahresplan, Turniere, Ressorts, Material, Dateien. Fokus von Feldern: rote Linie unten.</li>
                <li>Reiter (z. B. im Ressort-Steckbrief): Übersicht zuerst, aktiver Reiter mit roter Linie unten und roter Zahl, auf dem Handy seitlich wischbar.</li>
                <li>Jahresplan: Liste je Ressort, Zeitpunkt links, Ja/Nein rechts. Neue Einträge werden kurz gelb hinterlegt.</li>
                <li>Dateien ablegen: Titel und Ressort werden aus dem Dateinamen vorgeschlagen und lassen sich ändern.</li>
                <li>Animationen dezent und abschaltbar (reduzierte Bewegung wird respektiert).</li>
            </ul>
        </section>

        <p class="mt-12 border-t border-nacht/10 pt-3 text-[10px] text-beton">OK Raiffeisen-Hallencup | {{ \App\Models\Einstellung::wertSicher('verein_name', 'FC Münsterlingen') }} | Styleguide Stand {{ $stand }}</p>
    </main>
</body>
</html>
