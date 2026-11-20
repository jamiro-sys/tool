@php $f = fn ($z) => $z === null ? '' : rtrim(rtrim(number_format($z, 2, '.', "'"), '0'), '.'); $chf = fn ($z) => $z === null ? '' : number_format($z, 2, '.', "'"); @endphp
<x-app :titel="'Festwirtschaft '.$ausgabe->titel">
    <x-seitenkopf titel="Festwirtschaft" :ueber="$ausgabe->titel" :zurueck="route('ausgaben.show', $ausgabe)">
        <x-slot:unter>{{ $ausgabe->datumText() }}. Einkauf vorbereiten, nach dem Turnier den Verbrauch eintragen. So stimmen die Mengen beim nächsten Mal.</x-slot:unter>
        <x-slot:aktionen><a href="{{ route('festwirtschaft.index') }}" class="knopf-rahmen"><x-icon name="becher" class="h-4 w-4" /> Produkte</a></x-slot:aktionen>
    </x-seitenkopf>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="karte xl:col-span-2">
            <div class="karte-kopf">
                <h2 class="titel text-sm">Einkauf</h2>
                <span class="label">{{ $ausgabe->einkauf->where('eingekauft', true)->count() }} von {{ $ausgabe->einkauf->count() }} erledigt</span>
            </div>
            @if ($ausgabe->einkauf->isEmpty())
                <x-leer icon="becher" titel="Noch keine Einkaufsposten">
                    @if ($vorher && $vorher->einkauf->isNotEmpty())
                        @can('schreiben')
                            <form method="POST" action="{{ route('festwirtschaft.uebernehmen', $ausgabe) }}" class="mt-4">@csrf<button class="knopf-nacht">Aus {{ $vorher->titel }} übernehmen ({{ $vorher->einkauf->count() }})</button></form>
                        @endcan
                    @endif
                </x-leer>
            @endif
            <div class="divide-y divide-nacht/5">
                @foreach ($ausgabe->einkauf as $e)
                    @php $alt = $vorher?->einkauf->first(fn ($x) => mb_strtolower($x->bezeichnung) === mb_strtolower($e->bezeichnung)); @endphp
                    <details class="group px-4 py-3">
                        <summary class="flex cursor-pointer list-none items-center gap-3">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center border-2 {{ $e->eingekauft ? 'border-rot bg-rot text-white' : 'border-nacht/25' }}">@if ($e->eingekauft)<x-icon name="check" class="h-3.5 w-3.5" />@endif</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold">{{ $e->bezeichnung }}</span>
                                <span class="block truncate text-xs text-beton">{{ collect([$e->lieferant, $alt ? 'zuletzt verbraucht '.($f($alt->verbraucht) ?: 'unbekannt') : null])->filter()->implode(' | ') }}</span>
                            </span>
                            <span class="text-right text-sm"><span class="zahl">{{ $f($e->bestellt) ?: '?' }}</span> <span class="text-xs text-beton">{{ $e->einheit }}</span>
                                @if ($e->verbraucht !== null)<span class="block text-xs text-beton">verbraucht {{ $f($e->verbraucht) }}, Rest {{ $f($e->rest()) }}</span>@endif</span>
                            <x-icon name="pfeil" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-90" />
                        </summary>
                        @can('schreiben')
                            @include('festwirtschaft._posten', ['e' => $e])
                            <form method="POST" action="{{ route('festwirtschaft.posten.loeschen', [$ausgabe, $e]) }}" class="mt-2" onsubmit="return confirm('Posten entfernen?')">@csrf @method('DELETE')<button class="knopf-leise knopf-klein text-beton hover:text-rot"><x-icon name="loeschen" class="h-4 w-4" /> Entfernen</button></form>
                        @endcan
                    </details>
                @endforeach
                @can('schreiben')
                    <details class="group px-4 py-3">
                        <summary class="label flex cursor-pointer list-none items-center gap-2 text-rot"><x-icon name="plus" class="h-4 w-4" /> Neuer Posten</summary>
                        @include('festwirtschaft._posten', ['e' => null])
                    </details>
                @endcan
            </div>
        </section>

        <div class="space-y-6">
            @php $total = $ausgabe->kassen->sum(fn ($k) => $k->einnahmen() ?? 0); @endphp
            <section class="karte p-4 {{ $total ? 'bg-nacht text-white border-nacht' : '' }}">
                <p class="label {{ $total ? 'text-white/70' : '' }}">Einnahmen aller Kassen</p>
                <p class="zahl mt-2 text-3xl">CHF {{ $chf($total) ?: '0.00' }}</p>
                <p class="mt-1 text-xs {{ $total ? 'text-white/70' : 'text-beton' }}">Bargeld am Ende minus Wechselgeld, plus TWINT und Karte</p>
            </section>

            @foreach ($ausgabe->kassen->push(null) as $k)
                <section class="karte" x-data="{ auf: {{ $k ? 'false' : 'false' }} }">
                    <button type="button" class="karte-kopf w-full text-left" x-on:click="auf = !auf">
                        @if ($k)
                            <span><span class="titel block text-sm">{{ $k->name }}</span><span class="text-xs text-beton">{{ $k->verantwortlich?->name ?? 'ohne Verantwortung' }}</span></span>
                            <span class="text-right">
                                @if ($k->abgeschlossen_am)<span class="marke-erledigt">abgeschlossen</span>@else<span class="marke-offen">offen</span>@endif
                                @if ($k->einnahmen() !== null)<span class="zahl mt-1 block text-base">{{ $chf($k->einnahmen()) }}</span>@endif
                            </span>
                        @else
                            <span class="label flex items-center gap-2 text-rot"><x-icon name="plus" class="h-4 w-4" /> Neue Kasse</span>
                        @endif
                    </button>
                    @can('schreiben')
                        <form method="POST" action="{{ route('festwirtschaft.kasse', [$ausgabe, $k]) }}" x-show="auf" x-collapse x-cloak class="space-y-2 p-4">@csrf
                            <input name="name" required maxlength="120" class="feld" value="{{ $k?->name }}" placeholder="Name, z. B. Grill" aria-label="Name der Kasse">
                            <select name="verantwortlich_id" class="feld" aria-label="Verantwortlich"><option value="">Verantwortlich</option>@foreach ($personen as $p)<option value="{{ $p->id }}" @selected($k?->verantwortlich_id === $p->id)>{{ $p->name }}</option>@endforeach</select>
                            <div class="grid grid-cols-2 gap-2">
                                <label><span class="feld-label">Wechselgeld Start</span><input name="start_bar" type="number" step="0.05" min="0" inputmode="decimal" class="feld" value="{{ $k?->start_bar }}"></label>
                                <label><span class="feld-label">Bargeld Ende</span><input name="ende_bar" type="number" step="0.05" min="0" inputmode="decimal" class="feld" value="{{ $k?->ende_bar }}"></label>
                                <label><span class="feld-label">TWINT</span><input name="ende_twint" type="number" step="0.05" min="0" inputmode="decimal" class="feld" value="{{ $k?->ende_twint }}"></label>
                                <label><span class="feld-label">Karte</span><input name="ende_karte" type="number" step="0.05" min="0" inputmode="decimal" class="feld" value="{{ $k?->ende_karte }}"></label>
                                <input name="gezaehlt_von" maxlength="120" class="feld" value="{{ $k?->gezaehlt_von }}" placeholder="Gezählt von" aria-label="Gezählt von">
                                <input name="zweite_person" maxlength="120" class="feld" value="{{ $k?->zweite_person }}" placeholder="Zweite Person" aria-label="Zweite Person">
                            </div>
                            <textarea name="notiz" rows="2" class="feld" placeholder="Notiz, z. B. Differenz erklärt" aria-label="Notiz">{{ $k?->notiz }}</textarea>
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($k && ! $k->abgeschlossen_am)<button name="abschliessen" value="1" class="knopf-nacht knopf-klein">Abschliessen (zu zweit)</button>@endif
                                <button class="knopf-rot knopf-klein">Speichern</button>
                            </div>
                        </form>
                        @if ($k)
                            <form method="POST" action="{{ route('festwirtschaft.kasse.loeschen', [$ausgabe, $k]) }}" x-show="auf" x-cloak class="px-4 pb-3" onsubmit="return confirm('Kasse entfernen?')">@csrf @method('DELETE')<button class="knopf-leise knopf-klein text-beton hover:text-rot"><x-icon name="loeschen" class="h-4 w-4" /> Entfernen</button></form>
                        @endif
                    @endcan
                </section>
            @endforeach
        </div>
    </div>
</x-app>
