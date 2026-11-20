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
        {{-- Zeitstrahl relativ zum Turniertag --}}
        @php
            $tag = $ausgabe->datum_von;
            $mitTermin = $aufgaben->whereNotNull('faellig_am');
            $min = min(-7, (int) $tag->diffInDays($mitTermin->min('faellig_am') ?? $tag, false));
            $max = max(7, (int) $tag->diffInDays($mitTermin->max('faellig_am') ?? $tag, false));
            $spanne = max(1, $max - $min);
            $pos = fn ($d) => (($tag->diffInDays($d, false) - $min) / $spanne) * 100;
            $heutePos = $pos(today());
        @endphp
        <div class="karte overflow-x-auto">
            <div class="min-w-[720px]">
                <div class="relative ml-48 h-10 border-b border-nacht/10">
                    @php $schritt = $spanne > 200 ? 56 : ($spanne > 100 ? 28 : ($spanne > 50 ? 14 : 7)); @endphp
                    @for ($w = (int) (ceil($min / $schritt) * $schritt); $w <= $max; $w += $schritt)
                        <span class="absolute top-3 -translate-x-1/2 whitespace-nowrap text-[10px] font-bold uppercase tracking-label {{ $w === 0 ? 'text-rot' : 'text-beton' }}" style="left: {{ ($w - $min) / $spanne * 100 }}%">
                            {{ $w === 0 ? 'Turnier' : ($w < 0 ? '-' : '+').abs(intdiv($w, 7)).' W' }}
                        </span>
                    @endfor
                </div>
                @foreach ($aufgaben->groupBy('ressort_id') as $gruppe)
                    <div class="flex border-b border-nacht/5">
                        <div class="w-48 shrink-0 border-r border-nacht/10 px-3 py-2"><span class="titel text-xs">{{ $gruppe->first()->ressort->name }}</span></div>
                        <div class="relative flex-1 py-2" style="min-height: {{ max(1, $gruppe->count()) * 30 + 8 }}px">
                            <span class="absolute inset-y-0 w-[2px] bg-rot/40" style="left: {{ ($tag->diffInDays($tag, false) - $min) / $spanne * 100 }}%"></span>
                            @if ($heutePos >= 0 && $heutePos <= 100)<span class="absolute inset-y-0 w-px border-l border-dashed border-nacht/40" style="left: {{ $heutePos }}%"></span>@endif
                            @foreach ($gruppe->values() as $i => $aufgabe)
                                @if ($aufgabe->faellig_am)
                                    @php $p = $pos($aufgabe->faellig_am); $links = $p < 30; @endphp
                                    <a href="{{ route('aufgaben.show', $aufgabe) }}" title="{{ $aufgabe->titel }} ({{ $aufgabe->faellig_am->format('d.m.') }})"
                                       class="absolute flex h-6 items-center gap-1.5 whitespace-nowrap text-[11px] font-bold hover:text-rot {{ $links ? 'flex-row-reverse -translate-x-[6px]' : '-translate-x-full pr-2' }}" style="left: calc({{ $p }}% + {{ $links ? '0px' : '6px' }}); top: {{ $i * 30 + 6 }}px">
                                        <span class="max-w-[200px] truncate {{ $aufgabe->status === 'erledigt' ? 'text-beton line-through' : '' }}">{{ $aufgabe->titel }}</span>
                                        <span class="h-3 w-3 shrink-0 {{ $aufgabe->status === 'erledigt' ? 'bg-rot' : ($aufgabe->istUeberfaellig() ? 'bg-gelb' : 'border-2 border-nacht bg-white') }}"></span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-xs text-beton">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 bg-rot"></span> erledigt</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 border-2 border-nacht"></span> offen</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 bg-gelb"></span> überfällig</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-px border-l border-dashed border-nacht"></span> heute</span>
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
