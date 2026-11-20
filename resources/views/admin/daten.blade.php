<x-app titel="Daten">
    <x-seitenkopf titel="Daten" ueber="Admin">
        <x-slot:unter>Export, Import und Archiv. Deine Daten gehören dem Verein.</x-slot:unter>
    </x-seitenkopf>
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="karte p-4">
            <p class="titel text-sm">Vorlagenpaket</p>
            <p class="mt-1 text-xs text-beton">Alle Vorlagen mit Anleitungen als JSON. Zum Teilen mit einem anderen Verein oder als Sicherung des Wissens.</p>
            <a href="{{ route('admin.daten.vorlagen') }}" class="knopf-nacht mt-4 w-full"><x-icon name="download" class="h-4 w-4" /> Exportieren</a>
            <form method="POST" action="{{ route('admin.daten.vorlagen') }}" enctype="multipart/form-data" class="mt-4 space-y-2 border-t border-nacht/10 pt-4">@csrf
                <p class="label">Importieren</p>
                <input type="file" name="datei" accept=".json" required class="feld py-2.5 text-xs">
                <p class="text-xs text-beton">Bestehende Vorlagen mit gleichem Titel werden übersprungen.</p>
                <button class="knopf-rahmen w-full"><x-icon name="upload" class="h-4 w-4" /> Importieren</button>
            </form>
        </section>
        <section class="karte p-4">
            <p class="titel text-sm">Aufgaben als Tabelle</p>
            <p class="mt-1 text-xs text-beton">CSV für Excel oder Numbers.</p>
            <ul class="mt-3 divide-y divide-nacht/5">
                @foreach ($ausgaben as $a)<li><a href="{{ route('admin.daten.csv', $a) }}" class="flex items-center justify-between py-2 text-sm hover:text-rot">{{ $a->titel }} <x-icon name="download" class="h-4 w-4" /></a></li>@endforeach
            </ul>
        </section>
        <section class="karte p-4">
            <p class="titel text-sm">Gesamtexport</p>
            <p class="mt-1 text-xs text-beton">Alle Inhalte als lesbares JSON-Archiv (ohne Passwörter). Für die Ablage am Ende einer Saison.</p>
            <a href="{{ route('admin.daten.gesamt') }}" class="knopf-nacht mt-4 w-full"><x-icon name="archiv" class="h-4 w-4" /> Archiv herunterladen</a>
        </section>
    </div>

    <section class="karte mt-6 border-rot/40 p-4">
        <p class="titel text-sm text-rot">Neu beginnen</p>
        <p class="mt-1 text-sm text-beton">Löscht alle Inhalte: Ressorts, Turniere, Hallen, Vorlagen, Ausgaben, Aufgaben, Kontakte, Wissen und Verlauf. Personen und Einstellungen bleiben erhalten. Vorher wird automatisch eine Sicherung angelegt, die du unter Admin &gt; Sicherungen wieder einspielen kannst.</p>
        <form method="POST" action="{{ route('admin.daten.leeren') }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end" onsubmit="return confirm('Wirklich alle Inhalte löschen?')">@csrf
            <label class="flex-1">
                <span class="label">Zur Bestätigung LEEREN eintippen</span>
                <input name="bestaetigung" autocomplete="off" required class="feld mt-1" placeholder="LEEREN">
            </label>
            <button class="knopf-rot sm:w-auto"><x-icon name="loeschen" class="h-4 w-4" /> Alle Inhalte leeren</button>
        </form>
        @error('bestaetigung')<p class="mt-2 text-sm text-rot">{{ $message }}</p>@enderror
    </section>
</x-app>
