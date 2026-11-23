<x-app :titel="'Aufgaben '.$ausgabe->titel">
    @php $u = auth()->user(); @endphp
    <x-seitenkopf :titel="$ausgabe->titel" ueber="Aufgaben" :zurueck="route('ausgaben.show', $ausgabe)">
        <x-slot:unter>{{ $ausgabe->datumText() }} @if ($ausgabe->halle) in {{ $ausgabe->halle->name }} @endif</x-slot:unter>
        <x-slot:aktionen>
            <div class="flex border border-nacht/20 bg-white">
                @foreach (['liste' => 'liste', 'board' => 'board'] as $k => $icon)
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

    @if ($saisonAufgaben->isNotEmpty() && $ansicht === 'liste')
        <section class="karte mb-5 border-l-4 border-l-rot" data-saison-block>
            <div class="karte-kopf">
                <div><h2 class="titel text-sm">Für die ganze Saison {{ $ausgabe->saison }}</h2>
                    <p class="text-xs text-beton">Einmal pro Saison, geführt im Turnier {{ $saisonAufgaben->pluck('ausgabe.titel')->unique()->implode(', ') }}</p></div>
                <span class="label">{{ $saisonAufgaben->filter->istOffen()->count() }} offen</span>
            </div>
            @foreach ($saisonAufgaben->sortBy(fn ($a) => $a->istOffen() ? 0 : 1) as $aufgabe)
                @include('aufgaben._zeile', ['aufgabe' => $aufgabe])
            @endforeach
        </section>
    @endif

    @if ($aufgaben->isEmpty())
        <div class="karte">
            @if (array_filter($filter))
                <x-leer icon="filter" titel="Keine Aufgaben gefunden" text="Passe die Filter an oder setze sie zurück." />
            @else
                <x-leer icon="aufgaben" titel="Noch keine Aufgaben" text="Erfasse die erste Aufgabe mit «+ Aufgabe» oder nimm Aufgaben aus dem Jahresplan." />
            @endif
        </div>
    @elseif ($ansicht === 'liste')
        @foreach ($aufgaben->groupBy('ressort_id') as $gruppe)
            @php $r = $gruppe->first()->ressort; $erl = $gruppe->where('status', 'erledigt')->count(); @endphp
            <section class="karte mb-5 animate-einblenden">
                <div class="karte-kopf">
                    <a href="{{ route('ressorts.show', $r) }}" class="titel text-sm hover:text-rot">{{ $r->name }}</a>
                    <span class="flex w-32 items-center gap-2"><x-fortschritt :prozent="$gruppe->count() ? $erl / $gruppe->count() * 100 : 0" /><span class="label">{{ $erl }}/{{ $gruppe->count() }}</span></span>
                </div>
                @php
                    $nurErledigt = ($filter['status'] ?? '') === 'erledigt';
                    [$fertig, $aktiv] = $nurErledigt ? [collect(), $gruppe] : $gruppe->partition(fn ($x) => ! $x->istOffen());
                @endphp
                @foreach ($aktiv as $aufgabe)
                    @include('aufgaben._zeile', ['aufgabe' => $aufgabe, 'ohneRessort' => true])
                @endforeach
                @if ($fertig->count())
                    <div x-data="{ auf: {{ $aktiv->isEmpty() ? 'true' : 'false' }} }" class="border-t border-nacht/10">
                        <button type="button" class="flex w-full items-center gap-2 px-4 py-2.5 text-left label hover:text-rot" x-on:click="auf = !auf" :aria-expanded="auf">
                            <x-icon name="pfeil" class="h-3.5 w-3.5 transition-transform" ::class="auf ? 'rotate-90' : ''" />
                            <span x-text="auf ? 'Erledigte ausblenden' : '{{ $fertig->count() }} erledigt anzeigen'">{{ $fertig->count() }} erledigt anzeigen</span>
                        </button>
                        <div x-show="auf" x-collapse {{ $aktiv->isEmpty() ? '' : 'x-cloak' }} data-erledigte>
                            @foreach ($fertig->sortByDesc('erledigt_am') as $aufgabe)
                                @include('aufgaben._zeile', ['aufgabe' => $aufgabe, 'ohneRessort' => true])
                            @endforeach
                        </div>
                    </div>
                @endif
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
                                    @if ($aufgabe->istUeberfaellig())
                                        <span class="marke-gelb"><x-icon name="uhr" class="h-3 w-3" /> {{ $aufgabe->faelligText() }}</span>
                                    @elseif ($aufgabe->istHeuteFaellig())
                                        <span class="marke bg-rot text-white">Heute</span>
                                    @else
                                        <span class="font-bold">{{ $aufgabe->istOffen() ? $aufgabe->faelligText() : '' }}</span>
                                    @endif
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
        <p class="mt-2 text-xs text-beton"><span class="hidden lg:inline">Karten mit der Maus in eine andere Spalte ziehen, um den Status zu ändern.</span><span class="lg:hidden">Zum Ändern des Status die Karte antippen und die Aufgabe öffnen. Am PC lassen sich Karten auch verschieben.</span></p>
    @endif

    @can('schreiben')
        <x-modal name="neue-aufgabe" titel="Neue Aufgabe" :auf="$errors->any() && old('titel') !== null">
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
