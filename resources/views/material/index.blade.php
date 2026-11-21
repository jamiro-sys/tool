@php $f = fn ($z) => rtrim(rtrim(number_format($z, 2, '.', "'"), '0'), '.'); @endphp
<x-app titel="Material">
    <x-seitenkopf titel="Materialliste" ueber="Alle Ressorts">
        <x-slot:unter>Was ihr wofür braucht, wem es gehört und wer es bei welchem Turnier mitgebracht oder gemietet hat.</x-slot:unter>
        <x-slot:aktionen>
            @if ($anzahl)<a href="{{ route('material.drucken', array_filter($filter)) }}" target="_blank" class="knopf-rahmen"><x-icon name="drucker" class="h-4 w-4" /> Liste drucken</a>@endif
            @can('schreiben')<a href="#neu" class="knopf-rot" x-data x-on:click="document.querySelector('#neu details').open = true"><x-icon name="plus" class="h-4 w-4" /> Material</a>@endcan
        </x-slot:aktionen>
    </x-seitenkopf>

    <div class="mb-6 grid grid-cols-3 gap-3">
        <div class="karte p-4"><p class="label">Material</p><p class="zahl mt-2 text-3xl">{{ $anzahl }}</p></div>
        <div class="karte p-4"><p class="label">Nicht in Ordnung</p><p class="zahl mt-2 text-3xl {{ $reparieren ? '' : 'text-beton' }}">{{ $reparieren }}</p></div>
        <div class="karte p-4 {{ $kaufPruefen ? 'border-gelb' : '' }}"><p class="label">Kauf prüfen</p><p class="zahl mt-2 text-3xl {{ $kaufPruefen ? '' : 'text-beton' }}">{{ $kaufPruefen }}</p></div>
    </div>

    <form method="GET" class="mb-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_minmax(0,1.5fr)] lg:items-end" x-data x-on:change="$el.submit()">
        <div><label class="feld-label" for="m-ressort">Ressort</label>
            <select id="m-ressort" name="ressort" class="feld"><option value="">Alle Ressorts</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected($filter['ressort'] == $r->id)>{{ $r->name }}</option>@endforeach<option value="ohne" @selected($filter['ressort'] === 'ohne')>Ohne Ressort</option></select></div>
        <div><label class="feld-label" for="m-gruppe">Gruppe</label>
            <select id="m-gruppe" name="gruppe" class="feld"><option value="">Alle Gruppen</option>@foreach ($alleGruppen as $g)<option @selected($filter['gruppe'] === $g)>{{ $g }}</option>@endforeach</select></div>
        <div><label class="feld-label" for="m-standort">Standort</label>
            <select id="m-standort" name="standort" class="feld"><option value="">Alle Standorte</option>@foreach ($alleStandorte as $o)<option @selected($filter['standort'] === $o)>{{ $o }}</option>@endforeach</select></div>
        <div><label class="feld-label" for="m-eigentum">Eigentum</label>
            <select id="m-eigentum" name="eigentum" class="feld"><option value="">Alle</option>@foreach (\App\Models\Material::EIGENTUM as $k => $l)<option value="{{ $k }}" @selected($filter['eigentum'] === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="feld-label" for="m-q">Suche</label><input id="m-q" name="q" value="{{ $filter['q'] }}" class="feld" placeholder="Name, Zweck oder Notiz"></div>
    </form>
    @if (array_filter($filter))
        <p class="-mt-3 mb-5 text-xs"><a href="{{ route('material.index') }}" class="font-bold text-rot hover:underline">Filter zurücksetzen</a></p>
    @endif

    @if ($anzahl === 0)
        <section class="karte mb-6"><x-leer icon="kiste" titel="Noch kein Material" text="Erfasse, was eure Ressorts fürs Turnier brauchen. Bei jedem Turnier haltet ihr fest, ob es vom Verein kam, mitgebracht oder gemietet wurde." /></section>
    @elseif ($gruppiert->isEmpty() && array_filter($filter))
        <section class="karte mb-6"><x-leer icon="filter" titel="Nichts gefunden" text="Passe Ressort oder Suche an." /></section>
    @endif

    @foreach ($gruppiert as $ressortName => $liste)
        <section class="karte mb-5">
            <div class="karte-kopf"><h2 class="titel text-sm">{{ $ressortName }}</h2><span class="label">{{ $liste->count() }}</span></div>
            <div class="divide-y divide-nacht/5">
                @foreach ($liste as $m)
                    @php $bilanz = $m->mietBilanz(); $verlauf = $m->einsaetze->filter(fn ($e) => $e->ausgabe)->sortByDesc(fn ($e) => $e->ausgabe->datum_von); @endphp
                    <details class="group px-4 py-3">
                        <summary class="flex cursor-pointer list-none items-center gap-3">
                            @if ($m->foto_pfad)<img src="{{ route('material.foto', $m) }}" alt="" class="h-11 w-11 shrink-0 object-cover" loading="lazy">@endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold">{{ $m->name }} <span class="font-normal text-beton">{{ $f($m->menge) }} {{ $m->einheit }}</span></span>
                                <span class="block truncate text-xs text-beton">{{ collect([$m->zweck, \App\Models\Material::EIGENTUM[$m->eigentum].($m->eigentuemer ? ' ('.$m->eigentuemer.')' : ''), $m->standort])->filter()->implode(' | ') }}</span>
                            </span>
                            <span class="hidden flex-wrap justify-end gap-1.5 sm:flex">
                                @if ($bilanz['pruefen'])<span class="marke-gelb">Kauf prüfen</span>@endif
                                @if ($bilanz['anzahl'])<span class="marke-rahmen">{{ $bilanz['anzahl'] }}x gemietet</span>@endif
                                @if ($m->zustand !== 'gut')<span class="{{ $m->zustand === 'defekt' ? 'marke-rahmen border-rot text-rot' : 'marke-gelb' }}">{{ \App\Models\Material::ZUSTAND[$m->zustand] }}</span>@endif
                            </span>
                            <x-icon name="pfeil" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-90" />
                        </summary>

                        <div class="mt-3 flex flex-wrap gap-1.5 sm:hidden">
                            @if ($bilanz['pruefen'])<span class="marke-gelb">Kauf prüfen</span>@endif
                            @if ($bilanz['anzahl'])<span class="marke-rahmen">{{ $bilanz['anzahl'] }}x gemietet</span>@endif
                            @if ($m->zustand !== 'gut')<span class="{{ $m->zustand === 'defekt' ? 'marke-rahmen border-rot text-rot' : 'marke-gelb' }}">{{ \App\Models\Material::ZUSTAND[$m->zustand] }}</span>@endif
                        </div>

                        <div class="mt-4 grid gap-6 lg:grid-cols-2">
                            <div>
                                <p class="label mb-2">Verlauf</p>
                                @if ($verlauf->isEmpty())
                                    <p class="text-sm text-beton">Noch bei keinem Turnier eingesetzt. Füge es im Turnier unter «Material» zur Einsatzliste hinzu.</p>
                                @else
                                    <ul class="divide-y divide-nacht/5 border border-nacht/10">
                                        @foreach ($verlauf as $e)
                                            <li class="flex items-start justify-between gap-3 px-3 py-2 text-sm">
                                                <span class="min-w-0"><a href="{{ route('material.ausgabe', $e->ausgabe) }}" class="font-bold hover:text-rot">{{ $e->ausgabe->titel }}</a><span class="block text-xs text-beton">{{ $e->herkunftText() }}</span></span>
                                                @if ($e->kosten !== null)<span class="zahl shrink-0 text-sm">CHF {{ number_format($e->kosten, 2, '.', "'") }}</span>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($bilanz['anzahl'])
                                        <p class="mt-2 text-xs {{ $bilanz['pruefen'] ? 'font-bold text-nacht' : 'text-beton' }}">{{ $bilanz['anzahl'] }}x gemietet, zusammen CHF {{ number_format($bilanz['kosten'], 2, '.', "'") }}.@if ($bilanz['pruefen']) Ein eigener Kauf könnte sich lohnen.@endif</p>
                                    @endif
                                @endif
                                @if ($m->notizen)<p class="label mb-1 mt-4">Notizen</p><p class="text-sm text-beton">{{ $m->notizen }}</p>@endif
                            </div>
                            @can('schreiben')
                                <div><p class="label">Bearbeiten</p>@include('material._formular', ['m' => $m])</div>
                            @endcan
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @endforeach

    @can('schreiben')
        <section class="karte" id="neu">
            <details class="group px-4 py-3" @if ($anzahl === 0) open @endif>
                <summary class="label flex cursor-pointer list-none items-center gap-2 text-rot"><x-icon name="plus" class="h-4 w-4" /> Neues Material</summary>
                @include('material._formular', ['m' => null])
            </details>
        </section>
    @endcan
</x-app>
