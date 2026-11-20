<x-app titel="Einstellungen">
    <x-seitenkopf titel="Einstellungen" ueber="Admin" />
    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" class="karte space-y-4 p-4">@csrf
            <p class="titel text-sm">Allgemein</p>
            <div><label class="feld-label" for="verein_name">Verein</label><input id="verein_name" name="verein_name" class="feld" value="{{ $werte['verein_name'] }}"></div>
            <div><label class="feld-label" for="tool_name">Name des Tools</label><input id="tool_name" name="tool_name" class="feld" value="{{ $werte['tool_name'] }}"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="feld-label" for="erinnerung_tage">Erinnerung (Tage vorher)</label><input id="erinnerung_tage" type="number" name="erinnerung_tage" min="0" max="30" class="feld" value="{{ $werte['erinnerung_tage'] }}"></div>
                <div><label class="feld-label" for="backup_aufbewahrung">Sicherungen (Tage)</label><input id="backup_aufbewahrung" type="number" name="backup_aufbewahrung" min="1" max="365" class="feld" value="{{ $werte['backup_aufbewahrung'] }}"></div>
            </div>
            <div><label class="feld-label" for="wochenmail_tag">Wochenmail am</label>
                <select id="wochenmail_tag" name="wochenmail_tag" class="feld">@foreach ([1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'] as $k => $l)<option value="{{ $k }}" @selected($werte['wochenmail_tag'] == $k)>{{ $l }}</option>@endforeach</select></div>
            <p class="label pt-2">Funktionen</p>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="funktion_push" value="1" class="h-5 w-5" @checked($werte['funktion_push'])> Push-Benachrichtigungen</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="funktion_wochenmail" value="1" class="h-5 w-5" @checked($werte['funktion_wochenmail'])> Wochenzusammenfassung</label>
            <button class="knopf-rot">Speichern</button>
        </form>

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Hallen</h2></div>
            <div class="divide-y divide-nacht/5">
                @foreach ($hallen as $h)
                    <details class="group px-4 py-3">
                        <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-bold">{{ $h->name }}<x-icon name="pfeil" class="h-4 w-4 transition-transform group-open:rotate-90" /></summary>
                        @include('admin._halle', ['h' => $h])
                    </details>
                @endforeach
                <details class="group px-4 py-3">
                    <summary class="label flex cursor-pointer list-none items-center gap-2 text-rot"><x-icon name="plus" class="h-4 w-4" /> Neue Halle</summary>
                    @include('admin._halle', ['h' => null])
                </details>
            </div>
        </section>

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Turniere</h2></div>
            <div class="divide-y divide-nacht/5">
                @foreach ($turniere->push(null) as $t)
                    <details class="group px-4 py-3">
                        <summary class="flex cursor-pointer list-none items-center justify-between text-sm {{ $t ? 'font-bold' : 'label text-rot' }}">{{ $t?->name ?? '+ Neues Turnier' }}<x-icon name="pfeil" class="h-4 w-4 transition-transform group-open:rotate-90" /></summary>
                        <form method="POST" action="{{ route('admin.turniere.speichern', $t) }}" class="mt-3 space-y-2">@csrf
                            <input name="name" required class="feld" value="{{ $t?->name }}" placeholder="Name">
                            <input name="kurz" required maxlength="12" class="feld" value="{{ $t?->kurz }}" placeholder="Kürzel, z.B. HERBST">
                            <select name="halle_id" class="feld"><option value="">Halle</option>@foreach ($hallen as $h)<option value="{{ $h->id }}" @selected($t?->halle_id === $h->id)>{{ $h->name }}</option>@endforeach</select>
                            <textarea name="beschreibung" class="feld" rows="2" placeholder="Beschreibung">{{ $t?->beschreibung }}</textarea>
                            <button class="knopf-rot knopf-klein">Speichern</button>
                        </form>
                    </details>
                @endforeach
            </div>
        </section>
    </div>

    <section class="karte mt-6" id="listen">
        <div class="karte-kopf"><h2 class="titel text-sm">Eigene Listen</h2><span class="label">ab Werk leer, ihr bestimmt die Einträge</span></div>
        <div class="grid gap-px bg-nacht/5 md:grid-cols-2 xl:grid-cols-3">
            @foreach (\App\Models\Auswahlliste::BEREICHE as $bereich => [$name, $hilfe])
                <div class="bg-white p-4">
                    <p class="text-sm font-bold">{{ $name }}</p>
                    <p class="mt-0.5 text-xs text-beton">{{ $hilfe }}</p>
                    <ul class="mt-3 flex flex-wrap gap-1.5">
                        @forelse ($listen->get($bereich, collect()) as $e)
                            <li class="flex items-center gap-1 border border-nacht/15 py-1 pl-2.5 pr-1 text-xs">
                                {{ $e->wert }}
                                <form method="POST" action="{{ route('admin.auswahllisten.loeschen', $e) }}" onsubmit="return confirm('Eintrag entfernen?')">@csrf @method('DELETE')<button class="p-0.5 text-beton hover:text-rot" aria-label="{{ $e->wert }} entfernen"><x-icon name="x" class="h-3.5 w-3.5" /></button></form>
                            </li>
                        @empty
                            <li class="text-xs text-beton">Noch keine Einträge.</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ route('admin.auswahllisten.speichern') }}" class="mt-3 flex gap-2">@csrf
                        <input type="hidden" name="bereich" value="{{ $bereich }}">
                        <input name="wert" required maxlength="120" class="feld py-2 text-sm" placeholder="Neuer Eintrag" aria-label="Neuer Eintrag für {{ $name }}">
                        <button class="knopf-nacht knopf-klein" aria-label="Hinzufügen"><x-icon name="plus" class="h-4 w-4" /></button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
</x-app>
