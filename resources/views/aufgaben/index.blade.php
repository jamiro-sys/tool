<x-app :titel="'Aufgaben '.$ausgabe->titel">
    @php $u = auth()->user(); @endphp
    <x-seitenkopf :titel="$ausgabe->titel" ueber="Aufgaben" :zurueck="route('ausgaben.show', $ausgabe)">
        <x-slot:unter>{{ $ausgabe->datumText() }} @if ($ausgabe->halle) in {{ $ausgabe->halle->name }} @endif</x-slot:unter>
        <x-slot:aktionen>
            <div class="flex border border-nacht/20 bg-white">
                @foreach (['liste' => 'liste', 'board' => 'board', 'zeitstrahl' => 'zeitstrahl'] as $k => $icon)
                    <a href="{{ request()->fullUrlWithQuery(['ansicht' => $k]) }}" class="flex h-11 items-center gap-2 px-3 text-[11px] font-bold uppercase tracking-label {{ $ansicht === $k ? 'bg-nacht text-white' : 'hover:bg-kreide' }}">
                        <x-icon :name="$icon" class="h-4 w-4" /><span class="hidden sm:inline">{{ ucfirst($k) }}</span>
                    </a>
                @endforeach
            </div>
            @can('schreiben')
                <button type="button" class="knopf-rot" x-on:click="$dispatch('modal-neue-aufgabe')" data-kuerzel="n"><x-icon name="plus" class="h-4 w-4" /> Aufgabe</button>
            @endcan
        </x-slot:aktionen>
    </x-seitenkopf>

    {{-- Filter --}}
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-2 kein-druck" x-data x-on:change="$el.submit()">
        <input type="hidden" name="ansicht" value="{{ $ansicht }}">
        <div class="min-w-[140px] flex-1 sm:flex-none">
            <label class="feld-label" for="f-ressort">Ressort</label>
            <select id="f-ressort" name="ressort" class="feld">
                <option value="">Alle</option>
                @foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected(($filter['ressort'] ?? '') == $r->id)>{{ $r->name }}</option>@endforeach
            </select>
        </div>
        <div class="min-w-[140px] flex-1 sm:flex-none">
            <label class="feld-label" for="f-person">Person</label>
            <select id="f-person" name="person" class="feld">
                <option value="">Alle</option>
                <option value="{{ $u->id }}" @selected(($filter['person'] ?? '') == $u->id)>Ich</option>
                <option value="keine" @selected(($filter['person'] ?? '') === 'keine')>Ohne Zuteilung</option>
                @foreach ($personen as $p)@if ($p->id !== $u->id)<option value="{{ $p->id }}" @selected(($filter['person'] ?? '') == $p->id)>{{ $p->name }}</option>@endif @endforeach
            </select>
        </div>
        @if ($ansicht !== 'board')
            <div class="min-w-[140px] flex-1 sm:flex-none">
                <label class="feld-label" for="f-status">Status</label>
                <select id="f-status" name="status" class="feld">
                    <option value="">Alle aktiven</option>
                    <option value="offen_alle" @selected(($filter['status'] ?? '') === 'offen_alle')>Noch zu tun</option>
                    @foreach (\App\Models\Aufgabe::STATUS as $k => $l)<option value="{{ $k }}" @selected(($filter['status'] ?? '') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="min-w-[180px] flex-1">
            <label class="feld-label" for="f-q">Suche</label>
            <input id="f-q" name="q" value="{{ $filter['q'] ?? '' }}" class="feld" placeholder="Titel oder Text">
        </div>
        @if (array_filter($filter))
            <a href="{{ route('aufgaben.index', [$ausgabe, 'ansicht' => $ansicht]) }}" class="knopf-leise">Zurücksetzen</a>
        @endif
    </form>

    @if ($aufgaben->isEmpty())
        <div class="karte"><x-leer icon="filter" titel="Keine Aufgaben gefunden" text="Passe die Filter an oder erstelle eine neue Aufgabe." /></div>
    @elseif ($ansicht === 'liste')
        @foreach ($aufgaben->groupBy('ressort_id') as $gruppe)
            @php $r = $gruppe->first()->ressort; $erl = $gruppe->where('status', 'erledigt')->count(); @endphp
            <section class="karte mb-5 animate-einblenden">
                <div class="karte-kopf">
                    <a href="{{ route('ressorts.show', $r) }}" class="titel text-sm hover:text-rot">{{ $r->name }}</a>
                    <span class="flex w-32 items-center gap-2"><x-fortschritt :prozent="$gruppe->count() ? $erl / $gruppe->count() * 100 : 0" /><span class="label">{{ $erl }}/{{ $gruppe->count() }}</span></span>
                </div>
                @foreach ($gruppe as $aufgabe)
                    @include('aufgaben._zeile', ['aufgabe' => $aufgabe])
                @endforeach
            </section>
        @endforeach
    @elseif ($ansicht === 'board')
        <div class="-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:px-0 lg:grid lg:grid-cols-4 lg:overflow-visible">
            @foreach (\App\Models\Aufgabe::STATUS as $status => $label)
                @php $spalte = $aufgaben->where('status', $status); @endphp
                <section class="w-[80vw] shrink-0 snap-start sm:w-72 lg:w-auto"
                         x-data="{ drueber: false }"
                         x-on:dragover.prevent="drueber = true" x-on:dragleave="drueber = false"
                         x-on:drop.prevent="drueber = false; $dispatch('board-ablegen', { status: '{{ $status }}', id: $event.dataTransfer.getData('text/plain') })">
                    <div class="mb-2 flex items-center justify-between border-b-2 {{ $status === 'erledigt' ? 'border-rot' : 'border-nacht' }} pb-2">
                        <h2 class="titel text-xs">{{ $label }}</h2><span class="label">{{ $spalte->count() }}</span>
                    </div>
                    <div class="min-h-[120px] space-y-2 p-1 transition-colors" :class="drueber ? 'bg-nacht/5' : ''">
                        @foreach ($spalte as $aufgabe)
                            <article draggable="{{ $u->can('aufgabe-bearbeiten', $aufgabe) ? 'true' : 'false' }}" x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $aufgabe->id }}')"
                                     data-url="{{ route('aufgaben.status', $aufgabe) }}" id="karte-{{ $aufgabe->id }}"
                                     class="karte cursor-grab p-3 hover:border-nacht active:cursor-grabbing {{ $aufgabe->istUeberfaellig() ? 'border-l-4 border-l-gelb' : '' }}">
                                <a href="{{ route('aufgaben.show', $aufgabe) }}" class="block text-sm font-bold leading-snug hover:text-rot">{{ $aufgabe->titel }}</a>
                                <div class="mt-2 flex items-center justify-between gap-2 text-xs text-beton">
                                    <span class="font-bold uppercase tracking-label text-[10px] text-nacht">{{ $aufgabe->ressort->kuerzel }}</span>
                                    <span>{{ $aufgabe->faelligText() }}</span>
                                    <x-person :user="$aufgabe->verantwortlich" />
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
        <div x-data x-on:board-ablegen.window="
            const karte = document.getElementById('karte-' + $event.detail.id);
            if (!karte) return;
            okPost(karte.dataset.url, { status: $event.detail.status }).then(() => window.location.reload()).catch(e => $store.toast.zeige(e.message, 'fehler'))"></div>
        <p class="mt-2 text-xs text-beton">Karten mit der Maus in eine andere Spalte ziehen, um den Status zu ändern.</p>
    @else
        {{-- Zeitstrahl: Wochenleiste zur Orientierung, darunter Agenda Woche fuer Woche --}}
        @php
            $tag = $ausgabe->datum_von->copy()->startOfDay();
            $turnierWoche = $tag->copy()->startOfWeek();
            $dieseWoche = today()->startOfWeek();
            $mitTermin = $aufgaben->whereNotNull('faellig_am');
            $nachWoche = $mitTermin->groupBy(fn ($a) => $a->faellig_am->copy()->startOfWeek()->format('Y-m-d'));
            $erste = collect([$turnierWoche, $dieseWoche])->merge($nachWoche->keys()->map(fn ($k) => \Carbon\Carbon::parse($k)))->min();
            $letzte = collect([$turnierWoche, $dieseWoche])->merge($nachWoche->keys()->map(fn ($k) => \Carbon\Carbon::parse($k)))->max();
            if ($erste->diffInWeeks($letzte) > 70) { $erste = $letzte->copy()->subWeeks(70); }
            $wochen = [];
            for ($w = $erste->copy(); $w->lte($letzte); $w->addWeek()) {
                $k = $w->format('Y-m-d'); $liste = $nachWoche->get($k, collect());
                $wochen[$k] = [
                    'start' => $w->copy(), 'ende' => $w->copy()->endOfWeek(),
                    'abstand' => (int) round($turnierWoche->diffInDays($w, false) / 7),
                    'aufgaben' => $liste->sortBy('faellig_am'),
                    'erledigt' => $liste->whereIn('status', ['erledigt', 'entfaellt'])->count(),
                    'ueberfaellig' => $liste->filter->istUeberfaellig()->count(),
                ];
            }
            $maxAnzahl = max(1, collect($wochen)->max(fn ($w) => $w['aufgaben']->count()));
            $wochenText = fn ($n) => $n === 0 ? 'Turnierwoche' : (abs($n) === 1 ? '1 Woche' : abs($n).' Wochen').($n < 0 ? ' vorher' : ' danach');
            $ohneTermin = $aufgaben->whereNull('faellig_am');
        @endphp

        <section class="karte mb-6 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="titel text-sm">Wochen bis zum Turnier</h2>
                <div class="flex flex-wrap gap-3 text-[11px] text-beton">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 bg-rot"></span> erledigt</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 bg-nacht"></span> offen</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 bg-gelb"></span> überfällig</span>
                </div>
            </div>
            <div class="flex h-28 items-end gap-px sm:gap-[3px]" role="list" aria-label="Aufgaben pro Woche">
                @foreach ($wochen as $k => $w)
                    @php $n = $w['aufgaben']->count(); $offen = $n - $w['erledigt'] - $w['ueberfaellig']; $istTurnier = $w['abstand'] === 0; $istHeute = $w['start']->eq($dieseWoche); @endphp
                    <a href="{{ $n && $w['ende']->lt(today()) && $w['erledigt'] === $n ? '#erledigt' : '#woche-'.$k }}" role="listitem" title="{{ $wochenText($w['abstand']) }}: {{ $n }} {{ $n === 1 ? 'Aufgabe' : 'Aufgaben' }}"
                       class="group relative flex h-full min-w-0 flex-1 flex-col justify-end {{ $istTurnier ? 'bg-rot/10' : ($istHeute ? 'bg-gelb/25' : 'hover:bg-nacht/5') }}">
                        @if ($n)
                            <span class="flex flex-col-reverse" style="height: {{ max(8, $n / $maxAnzahl * 100) }}%">
                                @if ($w['erledigt'])<span class="bg-rot" style="flex: {{ $w['erledigt'] }}"></span>@endif
                                @if ($offen)<span class="bg-nacht" style="flex: {{ $offen }}"></span>@endif
                                @if ($w['ueberfaellig'])<span class="bg-gelb" style="flex: {{ $w['ueberfaellig'] }}"></span>@endif
                            </span>
                        @endif
                        @if ($istTurnier)<span class="absolute inset-x-0 top-0 h-[3px] bg-rot"></span>@endif
                        @if ($istHeute)<span class="absolute inset-x-0 top-0 h-[3px] bg-gelb"></span>@endif
                    </a>
                @endforeach
            </div>
            <div class="mt-1.5 flex gap-px text-[9px] font-bold uppercase tracking-label text-beton sm:gap-[3px] sm:text-[10px]">
                @php $schritt = max(1, (int) ceil(count($wochen) / 8)) * 2; @endphp
                @foreach (array_values($wochen) as $i => $w)
                    <span class="relative min-w-0 flex-1 overflow-visible whitespace-nowrap text-center {{ $w['abstand'] === 0 ? 'text-rot' : '' }}">
                        @if ($w['abstand'] === 0) T @elseif ($w['start']->eq($dieseWoche)) <span class="text-nacht">Heute</span> @elseif ($w['abstand'] % $schritt === 0) {{ $w['abstand'] > 0 ? '+' : '' }}{{ $w['abstand'] }} @endif
                    </span>
                @endforeach
            </div>
        </section>

        @php
            $erledigtVorbei = collect($wochen)->filter(fn ($w) => $w['aufgaben']->count() && $w['ende']->lt(today()) && $w['erledigt'] === $w['aufgaben']->count());
        @endphp
        <div class="space-y-3">
            @if ($erledigtVorbei->isNotEmpty())
                <details id="erledigt" class="group karte scroll-mt-24">
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                        <span class="min-w-0 flex-1"><span class="titel block text-sm">Bereits erledigt</span><span class="block text-xs text-beton">{{ $erledigtVorbei->count() }} {{ $erledigtVorbei->count() === 1 ? 'Woche' : 'Wochen' }} ohne offene Aufgaben</span></span>
                        <span class="label">{{ $erledigtVorbei->sum(fn ($w) => $w['aufgaben']->count()) }}</span>
                        <x-icon name="pfeil" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-90" />
                    </summary>
                    <div class="border-t border-nacht/10">
                        @foreach ($erledigtVorbei as $w)
                            <p class="label bg-kreide/70 px-4 py-1.5">{{ $wochenText($w['abstand']) }} | {{ $w['start']->format('d.m.') }} bis {{ $w['ende']->format('d.m.') }}</p>
                            @foreach ($w['aufgaben'] as $aufgabe) @include('aufgaben._zeile', ['aufgabe' => $aufgabe]) @endforeach
                        @endforeach
                    </div>
                </details>
            @endif
            @foreach ($wochen as $k => $w)
                @php
                    $n = $w['aufgaben']->count(); $istTurnier = $w['abstand'] === 0; $istHeute = $w['start']->eq($dieseWoche);
                    $vorbei = $w['ende']->lt(today()); $fertig = $n && $w['erledigt'] === $n;
                @endphp
                @continue(! $n && ! $istTurnier && ! $istHeute)
                @continue($vorbei && $fertig)
                <details id="woche-{{ $k }}" class="group karte scroll-mt-24 {{ $istHeute ? 'border-l-4 border-l-gelb' : '' }}" open>
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 {{ $istTurnier ? 'bg-rot text-white' : '' }}">
                        <span class="min-w-0 flex-1">
                            <span class="titel block text-sm {{ $istTurnier ? 'text-white' : '' }}">{{ $wochenText($w['abstand']) }}</span>
                            <span class="block text-xs {{ $istTurnier ? 'text-white/80' : 'text-beton' }}">{{ $w['start']->format('d.m.') }} bis {{ $w['ende']->format('d.m.Y') }}@if ($istTurnier) | Turnier {{ $ausgabe->datumText() }}@endif</span>
                        </span>
                        @if ($istHeute)<span class="marke-gelb">Diese Woche</span>@endif
                        @if ($w['ueberfaellig'] && ! $istTurnier)<span class="marke-gelb">{{ $w['ueberfaellig'] }} überfällig</span>@endif
                        @if ($n)<span class="label {{ $istTurnier ? 'text-white' : '' }}">{{ $w['erledigt'] }}/{{ $n }}</span>@endif
                        <x-icon name="pfeil" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-90" />
                    </summary>
                    @if ($n)
                        <div class="border-t border-nacht/10">
                            @foreach ($w['aufgaben'] as $aufgabe)
                                @include('aufgaben._zeile', ['aufgabe' => $aufgabe])
                            @endforeach
                        </div>
                    @else
                        <p class="border-t border-nacht/10 px-4 py-3 text-sm {{ $istTurnier ? '' : 'text-beton' }}">{{ $istTurnier ? 'Keine Aufgaben mit Termin in der Turnierwoche.' : 'Diese Woche ist nichts fällig.' }}</p>
                    @endif
                </details>
            @endforeach

            @if ($ohneTermin->isNotEmpty())
                <details class="group karte" open>
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                        <span class="titel flex-1 text-sm">Ohne Termin</span><span class="label">{{ $ohneTermin->count() }}</span>
                        <x-icon name="pfeil" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-90" />
                    </summary>
                    <div class="border-t border-nacht/10">@foreach ($ohneTermin as $aufgabe) @include('aufgaben._zeile', ['aufgabe' => $aufgabe]) @endforeach</div>
                </details>
            @endif
        </div>
    @endif

    @can('schreiben')
        <x-modal name="neue-aufgabe" titel="Neue Aufgabe">
            @if ($ressorts->isEmpty())
                <x-ohne-ressort />
            @else
            <form method="POST" action="{{ route('aufgaben.store', $ausgabe) }}">
                @csrf
                @include('aufgaben._formular', ['aufgabe' => null])
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="knopf-leise" x-on:click="offen = false">Abbrechen</button>
                    <button class="knopf-rot">Erstellen</button>
                </div>
            </form>
            @endif
        </x-modal>
    @endcan
</x-app>
