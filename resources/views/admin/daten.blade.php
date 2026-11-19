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
</x-app>
