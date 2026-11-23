<x-app :titel="$ausgabe->titel">
    @php $tage = $ausgabe->tageBisTurnier(); @endphp
    <x-seitenkopf :titel="$ausgabe->titel" :ueber="$ausgabe->turnier->name" :zurueck="route('ausgaben.index')">
        <x-slot:unter>{{ $ausgabe->datumText() }} @if ($ausgabe->halle) | {{ $ausgabe->halle->name }} @endif | <span class="font-bold text-nacht">{{ \App\Models\Ausgabe::STATUS[$ausgabe->status] }}</span></x-slot:unter>
        <x-slot:aktionen>
            <a href="{{ route('aufgaben.index', $ausgabe) }}" class="knopf-nacht"><x-icon name="aufgaben" class="h-4 w-4" /> Aufgaben</a>
            <a href="{{ route('material.ausgabe', $ausgabe) }}" class="knopf-rahmen"><x-icon name="kiste" class="h-4 w-4" /> Material</a>
            @if (in_array($ausgabe->status, ['rueckblick', 'abgeschlossen'], true) || $tage < 0)
                <a href="{{ route('ausgaben.rueckblick', $ausgabe) }}" class="knopf-rot"><x-icon name="idee" class="h-4 w-4" /> Rückblick</a>
            @endif
            @can('ausgaben-verwalten')
                <button type="button" class="knopf-rahmen" x-on:click="$dispatch('modal-ausgabe')" aria-label="Turnier bearbeiten"><x-icon name="stift" class="h-4 w-4" /></button>
            @endcan
        </x-slot:aktionen>
    </x-seitenkopf>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="karte p-4 bg-rot text-white border-rot">
            <p class="label text-white/80">{{ $tage >= 0 ? 'Tage bis Turnier' : 'Tage seit Turnier' }}</p>
            <p class="zahl mt-2 text-4xl" x-data="zaehler({{ abs($tage) }})" x-text="wert">{{ abs($tage) }}</p>
        </div>
        <div class="karte p-4" x-data="{ f: {{ json_encode($fortschritt) }} }" x-on:fortschritt.window="f = $event.detail">
            <p class="label">Fortschritt</p>
            <p class="zahl mt-2 text-4xl"><span x-text="f.prozent">{{ $fortschritt['prozent'] }}</span><span class="text-xl">%</span></p>
            <p class="mt-1 text-xs text-beton"><span x-text="f.erledigt">{{ $fortschritt['erledigt'] }}</span> von <span x-text="f.total">{{ $fortschritt['total'] }}</span> erledigt</p>
        </div>
        <a href="{{ route('aufgaben.index', [$ausgabe, 'nur' => 'ueberfaellig']) }}" class="karte p-4 hover:border-nacht">
            <p class="label">Überfällig</p>
            @php $ue = $ressorts->sum('ueberfaellig'); @endphp
            <p class="zahl mt-2 text-4xl {{ $ue ? '' : 'text-beton' }}">{{ $ue }}</p>
            @if ($ue)<span class="marke-gelb mt-1">nachfassen</span>@endif
        </a>
        <a href="{{ route('aufgaben.index', [$ausgabe, 'person' => 'keine', 'status' => 'offen_alle']) }}" class="karte p-4 hover:border-nacht">
            <p class="label">Ohne Zuteilung</p>
            <p class="zahl mt-2 text-4xl {{ $ohneZuteilung ? '' : 'text-beton' }}">{{ $ohneZuteilung }}</p>
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="karte xl:col-span-2">
            <div class="karte-kopf"><h2 class="titel text-sm">Countdown: offene Aufgaben</h2><span class="label">Plan und Ist pro Woche</span></div>
            <div class="h-72 p-4"><canvas x-data x-init="diagramm($el, 'burndown', {{ json_encode($burndown) }})" aria-label="Offene Aufgaben bis zum Turnier"></canvas></div>
        </section>
        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Status</h2></div>
            <div class="h-72 p-4"><canvas x-data x-init="diagramm($el, 'ring', { labels: {{ json_encode(array_values(\App\Models\Aufgabe::STATUS)) }}, werte: {{ json_encode($statusVerteilung->values()) }}, farben: ['#E4E3DD', '#14213D', '#B8161C', '#A9AEB8'] })" aria-label="Statusverteilung"></canvas></div>
        </section>

        <section class="karte xl:col-span-2">
            <div class="karte-kopf"><h2 class="titel text-sm">Ressorts</h2></div>
            <div class="grid gap-x-8 gap-y-5 p-4 sm:grid-cols-2">
                @forelse ($ressorts as $r)
                    <a href="{{ route('aufgaben.index', [$ausgabe, 'ressort' => $r['ressort']->id]) }}" class="group block">
                        <div class="mb-1.5 flex items-baseline justify-between">
                            <span class="text-sm font-bold group-hover:text-rot">{{ $r['ressort']->name }}</span>
                            <span class="flex items-center gap-2"><span class="zahl text-lg">{{ $r['prozent'] }}%</span></span>
                        </div>
                        <x-fortschritt :prozent="$r['prozent']" hoehe="h-2" />
                        <p class="mt-1 text-xs text-beton">{{ $r['erledigt'] }} von {{ $r['total'] }} @if ($r['ueberfaellig']) | <span class="font-bold text-nacht">{{ $r['ueberfaellig'] }} überfällig</span> @endif</p>
                    </a>
                @empty
                    <p class="text-sm text-beton">Noch keine Aufgaben.</p>
                @endforelse
            </div>
        </section>

        @if ($geschwister->isNotEmpty())
            <section class="karte border-l-4 border-l-rot p-4 lg:col-span-full" data-saison-karte>
                <p class="label">Saison {{ $ausgabe->saison }}</p>
                <p class="mt-1 text-sm">Gehört zusammen mit
                    @foreach ($geschwister as $g)<a href="{{ route('ausgaben.show', $g) }}" class="font-bold hover:text-rot">{{ $g->titel }}</a> ({{ $g->datumText() }})@if (! $loop->last), @endif @endforeach.
                    @if ($saisonOffen)<a href="{{ route('aufgaben.index', $ausgabe) }}" class="ml-1 font-bold text-rot hover:underline">{{ $saisonOffen }} {{ $saisonOffen === 1 ? 'Aufgabe' : 'Aufgaben' }} für die ganze Saison offen</a>@endif
                </p>
            </section>
        @endif

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Überfällig und nächste 14 Tage</h2></div>
            @forelse ($bald as $a)
                @include('aufgaben._zeile', ['aufgabe' => $a])
            @empty
                <x-leer icon="kalender" titel="Ruhige Tage" text="Nichts überfällig und in den nächsten zwei Wochen nichts fällig." />
            @endforelse
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @include('ausgaben._kennzahlen')
        @include('partials.verknuepfungen', ['bezug' => $ausgabe, 'typ' => 'ausgabe'])
    </div>

    @if ($ausgabe->notizen)
        <section class="karte mt-6 p-4"><p class="label mb-2">Notizen</p><div class="prosa">{{ $ausgabe->notizen }}</div></section>
    @endif

    @can('ausgaben-verwalten')
        <x-modal name="ausgabe" titel="Turnier bearbeiten">
            <form method="POST" action="{{ route('ausgaben.update', $ausgabe) }}" class="space-y-4">
                @csrf @method('PATCH')
                <div><label class="feld-label" for="a-titel">Titel</label><input id="a-titel" name="titel" class="feld" value="{{ $ausgabe->titel }}"></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="feld-label" for="a-von">Erster Tag</label><input id="a-von" type="date" name="datum_von" class="feld" value="{{ $ausgabe->datum_von->format('Y-m-d') }}"></div>
                    <div><label class="feld-label" for="a-bis">Letzter Tag</label><input id="a-bis" type="date" name="datum_bis" class="feld" value="{{ $ausgabe->datum_bis?->format('Y-m-d') }}"></div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="faelligkeiten_verschieben" value="1" checked> Aufgaben mit fixem Datum ebenfalls verschieben (Aufgaben mit Abstand zum Turnier wandern immer mit)</label>
                <div><label class="feld-label" for="a-status">Status</label>
                    <select id="a-status" name="status" class="feld">@foreach (\App\Models\Ausgabe::STATUS as $k => $l)<option value="{{ $k }}" @selected($ausgabe->status === $k)>{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="feld-label" for="a-notizen">Notizen</label><textarea id="a-notizen" name="notizen" class="feld">{{ $ausgabe->notizen }}</textarea></div>
                <div class="flex justify-end"><button class="knopf-rot">Speichern</button></div>
            </form>
            @can('admin')
                <form method="POST" action="{{ route('ausgaben.destroy', $ausgabe) }}" class="mt-6 border-t border-nacht/10 pt-4" onsubmit="return confirm('Turnier mit allen Aufgaben endgültig löschen?')">@csrf @method('DELETE')
                    <button class="knopf-leise text-beton hover:text-rot"><x-icon name="loeschen" class="h-4 w-4" /> Turnier löschen</button>
                </form>
            @endcan
        </x-modal>
    @endcan
</x-app>
