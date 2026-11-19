<x-app titel="Sicherungen">
    <x-seitenkopf titel="Sicherungen" ueber="Admin">
        <x-slot:unter>Täglich um 02.30 Uhr automatisch, vor jedem Update und vor jeder Wiederherstellung. Aufbewahrung {{ $aufbewahrung }} Tage.</x-slot:unter>
        <x-slot:aktionen>
            <form method="POST" action="{{ route('admin.sicherungen') }}">@csrf<button class="knopf-rot"><x-icon name="datenbank" class="h-4 w-4" /> Jetzt sichern</button></form>
        </x-slot:aktionen>
    </x-seitenkopf>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="karte lg:col-span-2">
            <div class="karte-kopf"><h2 class="titel text-sm">Vorhandene Sicherungen</h2><span class="label">{{ count($sicherungen) }}</span></div>
            <ul class="divide-y divide-nacht/5">
                @forelse ($sicherungen as $s)
                    <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center" x-data="{ auf: false }">
                        <div class="min-w-0 flex-1"><p class="truncate font-mono text-xs font-bold">{{ $s['name'] }}</p><p class="text-xs text-beton">{{ \Carbon\Carbon::createFromTimestamp($s['zeit'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }} | {{ number_format($s['groesse'] / 1024, 0, '.', "'") }} KB</p></div>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.sicherungen.download', $s['name']) }}" class="knopf-rahmen knopf-klein"><x-icon name="download" class="h-3.5 w-3.5" /></a>
                            <button type="button" class="knopf-rahmen knopf-klein" x-on:click="auf = !auf">Einspielen</button>
                            <form method="POST" action="{{ route('admin.sicherungen.loeschen', $s['name']) }}" onsubmit="return confirm('Sicherung löschen?')">@csrf @method('DELETE')<button class="knopf-leise knopf-klein"><x-icon name="loeschen" class="h-3.5 w-3.5" /></button></form>
                        </div>
                        <form x-show="auf" x-collapse method="POST" action="{{ route('admin.sicherungen.wiederherstellen', $s['name']) }}" class="w-full space-y-2 sm:basis-full">@csrf
                            <p class="text-xs">Alle aktuellen Daten werden durch diese Sicherung ersetzt. Vorher wird automatisch der jetzige Stand gesichert. Zum Bestätigen <span class="font-bold">WIEDERHERSTELLEN</span> eintippen.</p>
                            <div class="flex gap-2"><input name="bestaetigung" class="feld flex-1" autocomplete="off"><button class="knopf-rot">Einspielen</button></div>
                        </form>
                    </li>
                @empty
                    <li><x-leer icon="datenbank" titel="Noch keine Sicherung" /></li>
                @endforelse
            </ul>
        </div>
        <aside>
            <form method="POST" action="{{ route('admin.sicherungen.hochladen') }}" enctype="multipart/form-data" class="karte space-y-3 p-4">@csrf
                <p class="titel text-sm">Sicherung hochladen</p>
                <p class="text-xs text-beton">Zum Beispiel beim Umzug auf einen neuen Server. Danach in der Liste einspielen.</p>
                <input type="file" name="datei" required accept=".gz,.json" class="feld py-2.5 text-xs">
                <button class="knopf-nacht w-full"><x-icon name="upload" class="h-4 w-4" /> Hochladen</button>
            </form>
        </aside>
    </div>
</x-app>
