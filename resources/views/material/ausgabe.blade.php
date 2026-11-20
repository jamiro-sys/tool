@php $f = fn ($z) => rtrim(rtrim(number_format($z, 2, '.', "'"), '0'), '.'); @endphp
<x-app :titel="'Material '.$ausgabe->titel">
    <x-seitenkopf titel="Material" :ueber="$ausgabe->titel" :zurueck="route('ausgaben.show', $ausgabe)">
        <x-slot:unter>{{ $ausgabe->datumText() }}. Einsatzliste für Auf- und Abbau, am Turniertag direkt auf dem Handy abhaken.</x-slot:unter>
        <x-slot:aktionen>
            <a href="{{ route('material.drucken', ['turnier' => $ausgabe->id]) }}" target="_blank" class="knopf-rahmen"><x-icon name="drucker" class="h-4 w-4" /> Packliste drucken</a>
            <a href="{{ route('material.index') }}" class="knopf-rahmen"><x-icon name="kiste" class="h-4 w-4" /> Materialliste</a>
        </x-slot:aktionen>
    </x-seitenkopf>

    <div class="mb-6 grid grid-cols-3 gap-3">
        @foreach (\App\Models\MaterialEinsatz::HAKEN as $feld => $label)
            <div class="karte p-4"><p class="label">{{ $label }}</p><p class="zahl mt-2 text-3xl">{{ $einsatz->where($feld, true)->count() }}<span class="text-lg text-beton"> / {{ $einsatz->count() }}</span></p></div>
        @endforeach
    </div>

    <section class="karte">
        <div class="karte-kopf"><h2 class="titel text-sm">Einsatzliste</h2>
            @if ($einsatz->contains(fn ($e) => $e->rueckgabeUeberfaellig()))<span class="marke-offen">Rückgabe überfällig</span>@endif
        </div>
        @if ($einsatz->isEmpty())
            <x-leer icon="kiste" titel="Noch nichts auf der Liste">
                @can('schreiben')
                    @if ($vorherAnzahl)
                        <form method="POST" action="{{ route('material.uebernehmen', $ausgabe) }}" class="mt-4">@csrf<button class="knopf-nacht">Aus {{ $vorher->titel }} übernehmen ({{ $vorherAnzahl }})</button></form>
                    @endif
                @endcan
            </x-leer>
        @endif
        <ul class="divide-y divide-nacht/5">
            @foreach ($einsatz as $e)
                <li class="px-4 py-3" x-data="{ auf: false }">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="min-w-0 flex-1 text-left" x-on:click="auf = !auf">
                            <span class="block text-sm font-bold">{{ $e->material->name }} <span class="font-normal text-beton">{{ $f($e->menge) }} {{ $e->material->einheit }}</span></span>
                            <span class="block text-xs {{ $e->rueckgabeUeberfaellig() ? 'font-bold text-rot' : 'text-beton' }}">{{ collect([$e->material->ressort?->kuerzel, $e->herkunftText(), $e->kosten !== null ? 'CHF '.number_format($e->kosten, 2, '.', "'") : null, $e->rueckgabe_bis ? 'zurück bis '.$e->rueckgabe_bis->format('d.m.Y') : null, $e->notiz])->filter()->implode(' | ') }}</span>
                        </button>
                        <div class="flex gap-1.5">
                            @foreach (\App\Models\MaterialEinsatz::HAKEN as $feld => $label)
                                @can('schreiben')
                                    <button type="button" x-data="materialHaken('{{ route('material.haken', [$ausgabe, $e]) }}', '{{ $feld }}', {{ $e->$feld ? 'true' : 'false' }})" x-on:click="umschalten()"
                                            :class="wert ? 'bg-rot text-white border-rot' : 'border-nacht/20 text-beton'" class="flex min-h-11 min-w-[4.5rem] items-center justify-center gap-1 border-2 px-2 text-[10px] font-bold uppercase tracking-label {{ $e->$feld ? 'bg-rot text-white border-rot' : 'border-nacht/20 text-beton' }}" aria-label="{{ $label }}: {{ $e->material->name }}">
                                        <x-icon name="check" class="h-3.5 w-3.5" /> {{ $label }}
                                    </button>
                                @else
                                    <span class="flex min-h-11 items-center border-2 px-2 text-[10px] font-bold uppercase tracking-label {{ $e->$feld ? 'bg-rot text-white border-rot' : 'border-nacht/20 text-beton' }}">{{ $label }}</span>
                                @endcan
                            @endforeach
                        </div>
                    </div>
                    @can('schreiben')
                        <div x-show="auf" x-collapse x-cloak>
                            <form method="POST" action="{{ route('material.einsatz', [$ausgabe, $e]) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
                                <label><span class="feld-label">Menge</span><input name="menge" type="number" step="any" min="0" inputmode="decimal" class="feld" value="{{ $f($e->menge) }}"></label>
                                <label><span class="feld-label">Notiz</span><input name="notiz" maxlength="255" class="feld" value="{{ $e->notiz }}"></label>
                                <label><span class="feld-label">Woher</span><select name="herkunft" class="feld">@foreach (\App\Models\MaterialEinsatz::HERKUNFT as $k => $l)<option value="{{ $k }}" @selected($e->herkunft === $k)>{{ $l }}</option>@endforeach</select></label>
                                <label><span class="feld-label">Wer aus dem OK</span><select name="quelle_user_id" class="feld"><option value="">niemand</option>@foreach ($personen as $p)<option value="{{ $p->id }}" @selected($e->quelle_user_id === $p->id)>{{ $p->name }}</option>@endforeach</select></label>
                                <label><span class="feld-label">Firma oder Person</span><input name="quelle" maxlength="150" class="feld" value="{{ $e->quelle }}" placeholder="z. B. Musik Meier, Primarschule"></label>
                                <label><span class="feld-label">Kosten CHF</span><input name="kosten" type="number" step="0.05" min="0" inputmode="decimal" class="feld" value="{{ $e->kosten }}"></label>
                                <label><span class="feld-label">Zurück bis</span><input name="rueckgabe_bis" type="date" class="feld" value="{{ $e->rueckgabe_bis?->format('Y-m-d') }}"></label>
                                <label class="sm:col-span-2"><span class="feld-label">Erinnerung an</span><select name="verantwortlich_id" class="feld"><option value="">niemand</option>@foreach ($personen as $p)<option value="{{ $p->id }}" @selected($e->verantwortlich_id === $p->id)>{{ $p->name }}</option>@endforeach</select></label>
                                <div class="flex items-center justify-end sm:col-span-2"><button class="knopf-rot knopf-klein">Speichern</button></div>
                            </form>
                            <form method="POST" action="{{ route('material.einsatz.loeschen', [$ausgabe, $e]) }}" class="mt-2" onsubmit="return confirm('Von der Liste entfernen?')">@csrf @method('DELETE')<button class="knopf-leise knopf-klein text-beton hover:text-rot"><x-icon name="loeschen" class="h-4 w-4" /> Von der Liste entfernen</button></form>
                        </div>
                    @endcan
                </li>
            @endforeach
        </ul>
        @can('schreiben')
            @if ($verfuegbar->isNotEmpty())
                <form method="POST" action="{{ route('material.einsatz', $ausgabe) }}" class="flex flex-col gap-2 border-t border-nacht/10 p-4 sm:flex-row">@csrf
                    <select name="material_id" required class="feld" aria-label="Material hinzufügen"><option value="">Material hinzufügen</option>@foreach ($verfuegbar as $m)<option value="{{ $m->id }}">{{ $m->name }}{{ $m->ressort ? ' ('.$m->ressort->kuerzel.')' : '' }}</option>@endforeach</select>
                    <input name="menge" type="number" step="any" min="0" inputmode="decimal" class="feld sm:w-28" placeholder="Menge" aria-label="Menge">
                    <button class="knopf-rot">Hinzufügen</button>
                </form>
            @elseif ($einsatz->isEmpty())
                <p class="border-t border-nacht/10 px-4 py-3 text-xs text-beton">Erfasse zuerst Material in der <a href="{{ route('material.index') }}" class="font-bold text-rot">Materialliste</a>.</p>
            @endif
        @endcan
    </section>
</x-app>
