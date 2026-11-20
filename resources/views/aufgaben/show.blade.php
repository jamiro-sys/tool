<x-app :titel="$aufgabe->titel">
    @php
        $u = auth()->user();
        $darf = $u->can('aufgabe-bearbeiten', $aufgabe);
        $cl = $aufgabe->checklisteFortschritt();
    @endphp
    <x-seitenkopf :titel="$aufgabe->titel" :ueber="$aufgabe->ressort->name.' | '.$aufgabe->ausgabe->titel" :zurueck="route('aufgaben.index', $aufgabe->ausgabe)">
        <x-slot:aktionen>
            @if ($darf)
                <button type="button" class="knopf-rahmen" x-on:click="$dispatch('modal-bearbeiten')" data-kuerzel="e"><x-icon name="stift" class="h-4 w-4" /> Bearbeiten</button>
            @endif
        </x-slot:aktionen>
    </x-seitenkopf>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Status --}}
            <section class="karte p-4" x-data="aufgabeStatus('{{ route('aufgaben.status', $aufgabe) }}', '{{ $aufgabe->status }}')">
                <p class="label mb-3">Status</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (\App\Models\Aufgabe::STATUS as $k => $l)
                        <button type="button" @if ($darf) x-on:click="setze('{{ $k }}')" @else disabled @endif
                                class="knopf border transition-colors" :class="status === '{{ $k }}' ? '{{ $k === 'erledigt' ? 'bg-rot border-rot text-white' : 'bg-nacht border-nacht text-white' }}' : 'border-nacht/20 bg-white hover:border-nacht'">
                            @if ($k === 'erledigt')<x-icon name="check" class="h-4 w-4" />@endif {{ $l }}
                        </button>
                    @endforeach
                </div>
                @if ($aufgabe->erledigt_am)
                    <p class="mt-3 text-xs text-beton">Erledigt am {{ $aufgabe->erledigt_am->format('d.m.Y H:i') }}</p>
                @endif
            </section>

            @if ($aufgabe->beschreibung)
                <section class="karte p-4">
                    <p class="label mb-2">Beschreibung</p>
                    <div class="prosa">{{ $aufgabe->beschreibung }}</div>
                </section>
            @endif

            @if ($aufgabe->anleitung)
                <section class="karte border-l-4 border-l-rot p-4" x-data="{ auf: {{ $u->istExperte() ? 'false' : 'true' }} }">
                    <button type="button" class="flex w-full items-center justify-between" x-on:click="auf = !auf">
                        <span class="flex items-center gap-2"><x-icon name="buch" class="h-4 w-4 text-rot" /><span class="titel text-sm">So geht's</span></span>
                        <x-icon name="pfeil" class="h-4 w-4 transition-transform" ::class="auf ? 'rotate-90' : ''" />
                    </button>
                    <div x-show="auf" x-collapse><div class="prosa mt-3">{{ $aufgabe->anleitung }}</div></div>
                </section>
            @endif

            @if ($aufgabe->checkliste)
                <section class="karte" x-data="{ f: {{ json_encode($cl) }} }" x-on:checkliste.window="f = $event.detail">
                    <div class="karte-kopf">
                        <h2 class="titel text-sm">Checkliste</h2>
                        <span class="label" x-text="f.erledigt + '/' + f.total">{{ $cl['erledigt'] }}/{{ $cl['total'] }}</span>
                    </div>
                    <ul>
                        @foreach ($aufgabe->checkliste as $i => $punkt)
                            <li x-data="checkpunkt('{{ route('aufgaben.checkpunkt', $aufgabe) }}', {{ $i }}, {{ ! empty($punkt['erledigt']) ? 'true' : 'false' }})" class="border-b border-nacht/5 last:border-0">
                                <label class="flex cursor-pointer items-center gap-3 px-4 py-3">
                                    <input type="checkbox" class="h-5 w-5" :checked="erledigt" x-on:change="umschalten()" @disabled(! $darf)>
                                    <span class="text-sm" :class="erledigt ? 'text-beton line-through' : ''">{{ $punkt['text'] }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Kommentare --}}
            <section class="karte" id="kommentare">
                <div class="karte-kopf"><h2 class="titel text-sm">Absprachen</h2><span class="label">{{ $aufgabe->kommentare->count() }}</span></div>
                <ul class="divide-y divide-nacht/5">
                    @forelse ($aufgabe->kommentare as $k)
                        <li class="flex gap-3 px-4 py-3">
                            <x-person :user="$k->user" />
                            <div class="min-w-0 flex-1">
                                <p class="text-xs"><span class="font-bold">{{ $k->user?->name ?? 'Ehemaliges Mitglied' }}</span> <span class="text-beton">{{ $k->created_at->diffForHumans() }}</span></p>
                                <p class="prosa mt-1">{{ $k->text }}</p>
                            </div>
                            @if ($k->user_id === $u->id || $u->istAdmin())
                                <form method="POST" action="{{ route('kommentare.destroy', $k) }}" onsubmit="return confirm('Kommentar löschen?')">@csrf @method('DELETE')
                                    <button class="text-beton hover:text-rot" aria-label="Löschen"><x-icon name="loeschen" class="h-4 w-4" /></button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-5 text-sm text-beton">Noch keine Absprachen. Mit @Vorname erwähnst du jemanden.</li>
                    @endforelse
                </ul>
                @can('schreiben')
                    <form method="POST" action="{{ route('aufgaben.kommentar', $aufgabe) }}" class="flex gap-2 border-t border-nacht/10 p-3">
                        @csrf
                        <textarea name="text" rows="1" required class="feld min-h-[44px] flex-1" placeholder="Nachricht, Frage, Update ..." x-data x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"></textarea>
                        <button class="knopf-nacht" aria-label="Senden"><x-icon name="senden" class="h-4 w-4" /></button>
                    </form>
                @endcan
            </section>

            {{-- Erkenntnisse --}}
            <section class="karte">
                <div class="karte-kopf">
                    <h2 class="titel text-sm flex items-center gap-2"><x-icon name="idee" class="h-4 w-4 text-rot" /> Fürs nächste Mal</h2>
                </div>
                <ul class="divide-y divide-nacht/5">
                    @foreach ($aufgabe->erkenntnisse as $e)
                        <li class="px-4 py-3 text-sm">
                            <div class="flex items-center gap-2"><span class="marke-rahmen">{{ \App\Models\Erkenntnis::VORSCHLAEGE[$e->vorschlag] }}</span>
                                @if ($e->umgesetzt_am)<span class="marke-erledigt">umgesetzt</span>@endif
                                <span class="text-xs text-beton">{{ $e->user?->name }}</span></div>
                            <p class="mt-1">{{ $e->text }}</p>
                        </li>
                    @endforeach
                </ul>
                @can('schreiben')
                    <form method="POST" action="{{ route('erkenntnisse.store') }}" class="space-y-3 border-t border-nacht/10 p-4">
                        @csrf
                        <input type="hidden" name="aufgabe_id" value="{{ $aufgabe->id }}">
                        <textarea name="text" required class="feld" rows="2" placeholder="Was sollten wir nächstes Mal anders machen? z.B. zwei Wochen früher bestellen"></textarea>
                        <div class="flex flex-wrap items-center gap-2">
                            <select name="vorschlag" class="feld w-auto">
                                @foreach (\App\Models\Erkenntnis::VORSCHLAEGE as $k => $l)
                                    @if ($k !== 'neu' || ! $aufgabe->vorlage_id)<option value="{{ $k }}" @selected($k === ($aufgabe->vorlage_id ? 'anpassen' : 'neu'))>{{ $l }}</option>@endif
                                @endforeach
                            </select>
                            <button class="knopf-nacht">Festhalten</button>
                        </div>
                    </form>
                @endcan
            </section>
        </div>

        {{-- Seitenspalte --}}
        <aside class="space-y-6">
            <section class="karte divide-y divide-nacht/5">
                <div class="px-4 py-3"><p class="label">Fällig</p><p class="mt-1 text-sm font-bold {{ $aufgabe->istUeberfaellig() ? 'text-rot' : '' }}">{{ $aufgabe->faellig_am?->locale('de_CH')->isoFormat('dd, D. MMMM YYYY') ?? 'ohne Termin' }} <span class="font-normal text-beton">({{ $aufgabe->faelligText() }})</span></p>
                    <p class="mt-0.5 text-xs text-beton">{{ $aufgabe->tage_vorher !== null ? $aufgabe->wannText() : ($aufgabe->faellig_am ? 'fixes Datum' : '') }}</p></div>
                <div class="px-4 py-3"><p class="label mb-1">Verantwortlich</p><x-person :user="$aufgabe->verantwortlich" gross><span class="text-sm font-bold">{{ $aufgabe->verantwortlich?->name ?? 'Noch offen' }}</span></x-person></div>
                @if ($aufgabe->begleitung)
                    <div class="px-4 py-3"><p class="label mb-1">Begleitung</p><x-person :user="$aufgabe->begleitung" gross><span class="text-sm">{{ $aufgabe->begleitung->name }}</span></x-person></div>
                @endif
                <div class="flex justify-between px-4 py-3 text-sm"><span class="label">Priorität</span><span>{{ \App\Models\Vorlage::PRIORITAETEN[$aufgabe->prioritaet] }}</span></div>
                <div class="px-4 py-3">
                    <div class="flex justify-between text-sm"><span class="label">Aufwand geplant</span><span>{{ \App\Support\Aufwand::text($aufgabe->aufwand_geplant, 'offen') }}</span></div>
                    @if ($darf)
                        <form method="POST" action="{{ route('aufgaben.update', $aufgabe) }}" class="mt-2 space-y-2">@csrf @method('PATCH')
                            <label class="label block" for="aufwand_effektiv">Effektiv</label>
                            <div class="flex flex-wrap items-center gap-2"><x-aufwand name="aufwand_effektiv" id="aufwand_effektiv" :wert="$aufgabe->aufwand_effektiv" klein /><button class="knopf-leise knopf-klein ml-auto">Speichern</button></div>
                        </form>
                    @endif
                </div>
                @can('ressort-verwalten', $aufgabe->ressort)
                    <form method="POST" action="{{ route('aufgaben.update', $aufgabe) }}" class="space-y-3 px-4 py-3">@csrf @method('PATCH')
                        @include('aufgaben._wie_oft', ['a' => $aufgabe, 'schmal' => true])
                        <div class="flex items-center justify-between gap-2">
                            @if ($aufgabe->vorlage)<a href="{{ route('vorlagen.show', $aufgabe->vorlage) }}" class="label hover:text-rot">Im Jahresplan ansehen</a>@else<span></span>@endif
                            <button class="knopf-nacht knopf-klein">Speichern</button>
                        </div>
                    </form>
                @else
                    <div class="px-4 py-3 text-sm"><span class="label block">Wie oft?</span>
                        @if ($aufgabe->vorlage?->aktiv)
                            Jedes Jahr{{ $aufgabe->vorlage->turnierartText() ? ', '.$aufgabe->vorlage->turnierartText() : '' }}
                        @else
                            Nur dieses Turnier
                        @endif
                    </div>
                @endcan
            </section>
            @include('partials.verknuepfungen', ['bezug' => $aufgabe, 'typ' => 'aufgabe'])

            @if ($frueher->count())
                <section class="karte">
                    <div class="karte-kopf"><h2 class="titel text-xs">Früher erledigt von</h2></div>
                    <ul class="divide-y divide-nacht/5">
                        @foreach ($frueher as $f)
                            <li><a href="{{ route('aufgaben.show', $f) }}" class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm hover:bg-kreide">
                                <span><span class="block font-bold">{{ $f->ausgabe->titel }}</span><span class="text-xs text-beton">{{ $f->verantwortlich?->name ?? 'niemand' }} @if ($f->aufwand_effektiv) | {{ \App\Support\Aufwand::text($f->aufwand_effektiv) }} @endif</span></span>
                                <x-status :status="$f->status" />
                            </a></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($aufgabe->dokumente->count())
                <section class="karte">
                    <div class="karte-kopf"><h2 class="titel text-xs">Dokumente</h2></div>
                    <ul class="divide-y divide-nacht/5">
                        @foreach ($aufgabe->dokumente as $d)
                            <li><x-datei-link :d="$d" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-kreide"><x-icon :name="$d->typ === 'datei' ? 'datei' : 'link'" class="h-4 w-4 text-beton" /> {{ $d->titel }}</x-datei-link></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @can('aufgabe-verwalten', $aufgabe)
                <form method="POST" action="{{ route('aufgaben.destroy', $aufgabe) }}" onsubmit="return confirm('Aufgabe wirklich löschen? Tipp: Status Entfällt behält die Geschichte.')">@csrf @method('DELETE')
                    <button class="knopf-leise w-full text-beton hover:text-rot"><x-icon name="loeschen" class="h-4 w-4" /> Aufgabe löschen</button>
                </form>
            @endcan
        </aside>
    </div>

    @if ($darf)
        <x-modal name="bearbeiten" titel="Aufgabe bearbeiten">
            <form method="POST" action="{{ route('aufgaben.update', $aufgabe) }}">
                @csrf @method('PATCH')
                @include('aufgaben._formular', ['aufgabe' => $aufgabe, 'mitDetails' => true])
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="knopf-leise" x-on:click="offen = false">Abbrechen</button>
                    <button class="knopf-rot">Speichern</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app>
