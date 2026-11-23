<x-app :titel="$aufgabe->titel">
    @php
        $u = auth()->user();
        $darf = $u->can('aufgabe-bearbeiten', $aufgabe);
        $cl = $aufgabe->checklisteFortschritt();
        $jaehrlich = (bool) $aufgabe->vorlage?->aktiv;
        $mehrAnzahl = $aufgabe->verknuepfungen()->count() + $aufgabe->dokumente->count() + $frueher->count();
    @endphp
    <div class="max-w-3xl">
    <x-seitenkopf :titel="$aufgabe->titel" :ueber="$aufgabe->ressort->name.' | '.$aufgabe->ausgabe->titel" :zurueck="route('aufgaben.index', $aufgabe->ausgabe)">
        <x-slot:aktionen>
            @can('aufgabe-verwalten', $aufgabe)
                <button type="button" class="knopf-leise" x-on:click="$dispatch('modal-loeschen')" data-loeschen><x-icon name="loeschen" class="h-4 w-4" /> Löschen</button>
            @endcan
            @if ($darf)
                <button type="button" class="knopf-rahmen" x-on:click="$dispatch('modal-bearbeiten')" data-kuerzel="e"><x-icon name="stift" class="h-4 w-4" /> Bearbeiten</button>
            @endif
        </x-slot:aktionen>
    </x-seitenkopf>

    <div class="space-y-5">
        {{-- Steckbrief und Erledigt --}}
        <section class="karte" x-data="aufgabeStatus('{{ route('aufgaben.status', $aufgabe) }}', '{{ $aufgabe->status }}')" data-steckbrief>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-4 p-4 sm:grid-cols-4">
                <div class="col-span-2 sm:col-span-1">
                    <dt class="label">Fällig</dt>
                    <dd class="mt-1 text-sm font-bold">{{ $aufgabe->faellig_am ? $aufgabe->faellig_am->copy()->locale('de_CH')->isoFormat('dd, D. MMM YYYY') : 'Ohne Termin' }}</dd>
                    <dd class="text-xs text-beton">{{ $aufgabe->tage_vorher !== null ? $aufgabe->wannText() : ($aufgabe->faellig_am ? 'fixes Datum' : '') }}</dd>
                    @if ($aufgabe->istUeberfaellig())
                        <dd class="mt-1"><span class="marke-gelb"><x-icon name="uhr" class="h-3 w-3" /> {{ $aufgabe->faelligText() }}</span></dd>
                    @elseif ($aufgabe->istHeuteFaellig())
                        <dd class="mt-1"><span class="marke bg-rot text-white">Heute</span></dd>
                    @endif
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <dt class="label">Wer</dt>
                    <dd class="mt-1"><x-person :user="$aufgabe->verantwortlich"><span class="text-sm font-bold">{{ $aufgabe->verantwortlich?->name ?? 'Noch offen' }}</span></x-person></dd>
                    @if ($aufgabe->begleitung)<dd class="mt-1 text-xs text-beton">mit {{ $aufgabe->begleitung->name }} (Begleitung)</dd>@endif
                </div>
                <div>
                    <dt class="label">Aufwand</dt>
                    <dd class="mt-1 text-sm">{{ \App\Support\Aufwand::text($aufgabe->aufwand_geplant, 'offen') }}</dd>
                    @if ($aufgabe->aufwand_effektiv)<dd class="text-xs text-beton">effektiv {{ \App\Support\Aufwand::text($aufgabe->aufwand_effektiv) }}</dd>@endif
                </div>
                <div>
                    <dt class="label">Wie oft</dt>
                    <dd class="mt-1 text-sm">{{ $aufgabe->saison_aufgabe ? 'Einmal pro Saison' : ($jaehrlich ? 'Bei jedem Turnier' : 'Nur dieses Mal') }}</dd>
                    @if ($aufgabe->saison_aufgabe)<dd class="text-xs text-beton">gilt für die ganze Saison {{ $aufgabe->ausgabe->saison }}</dd>
                    @elseif ($jaehrlich && $aufgabe->vorlage->turniere->isNotEmpty())<dd class="text-xs text-beton">nur {{ $aufgabe->vorlage->turnierartText() }}</dd>@endif
                </div>
            </dl>
            @if ($aufgabe->prioritaet === 'hoch')
                <p class="px-4 pb-3"><span class="marke-rahmen border-rot text-rot">Wichtig</span></p>
            @endif

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-nacht/10 bg-kreide/50 p-4">
                @if ($darf)
                    {{-- Offen oder in Arbeit: ein Hauptknopf; alles andere nur als kleine Textknoepfe --}}
                    <button type="button" x-show="status === 'offen' || status === 'in_arbeit'" @if (! $aufgabe->istOffen()) x-cloak @endif x-on:click="setze('erledigt')" :disabled="laedt" class="knopf-rot"><x-icon name="check" class="h-4 w-4" /> Als erledigt markieren</button>
                    <span x-show="erledigt" @unless ($aufgabe->status === 'erledigt') x-cloak @endunless class="inline-flex min-h-[44px] items-center gap-2 bg-rot px-4 text-[11px] font-bold uppercase tracking-label text-white"><x-icon name="check" class="h-4 w-4" /> Erledigt</span>
                    <span x-show="status === 'entfaellt'" @unless ($aufgabe->status === 'entfaellt') x-cloak @endunless class="marke-entfaellt">Entfällt</span>
                    <span class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                        <span x-show="erledigt" x-cloak class="text-beton" x-text="'am ' + (@js($aufgabe->erledigt_am?->format('d.m.Y')) || new Date().toLocaleDateString('de-CH'))"></span>
                        <span x-show="status === 'in_arbeit'" @unless ($aufgabe->status === 'in_arbeit') x-cloak @endunless class="marke-in_arbeit">In Arbeit</span>
                        <button type="button" x-show="status === 'offen'" @unless ($aufgabe->status === 'offen') x-cloak @endunless x-on:click="setze('in_arbeit')" class="font-bold uppercase tracking-label text-beton hover:text-nacht">Ich bin dran</button>
                        <button type="button" x-show="status === 'offen' || status === 'in_arbeit'" @if (! $aufgabe->istOffen()) x-cloak @endif x-on:click="if (confirm('Diese Aufgabe entfällt für dieses Turnier?')) setze('entfaellt')" class="font-bold uppercase tracking-label text-beton hover:text-rot">Entfällt</button>
                        <button type="button" x-show="!(status === 'offen' || status === 'in_arbeit')" @if ($aufgabe->istOffen()) x-cloak @endif x-on:click="setze('offen')" class="font-bold uppercase tracking-label text-beton hover:text-rot">Wieder öffnen</button>
                    </span>
                @else
                    <x-status :status="$aufgabe->status" />
                    @if ($aufgabe->erledigt_am)<span class="text-xs text-beton">am {{ $aufgabe->erledigt_am->format('d.m.Y') }}</span>@endif
                @endif
            </div>
            @if ($darf && ! $aufgabe->aufwand_effektiv)
                <form method="POST" action="{{ route('aufgaben.status', $aufgabe) }}" x-show="erledigt" x-cloak class="flex flex-wrap items-center gap-2 border-t border-nacht/10 px-4 py-3">
                    @csrf
                    <input type="hidden" name="status" value="erledigt">
                    <label class="text-sm" for="aufwand_effektiv_schnell">Wie lange hat es gedauert?</label>
                    <x-aufwand name="aufwand_effektiv" id="aufwand_effektiv_schnell" klein />
                    <button class="knopf-leise knopf-klein">Festhalten</button>
                </form>
            @endif
        </section>

        @if ($aufgabe->beschreibung)
            <section class="karte p-4">
                <div class="prosa">{{ $aufgabe->beschreibung }}</div>
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

        @if ($aufgabe->anleitung)
            <section class="karte border-l-4 border-l-rot p-4" x-data="{ auf: {{ $u->istExperte() ? 'false' : 'true' }} }">
                <button type="button" class="flex w-full items-center justify-between" x-on:click="auf = !auf">
                    <span class="flex items-center gap-2"><x-icon name="buch" class="h-4 w-4 text-rot" /><span class="titel text-sm">So geht's</span></span>
                    <x-icon name="pfeil" class="h-4 w-4 transition-transform" ::class="auf ? 'rotate-90' : ''" />
                </button>
                <div x-show="auf" x-collapse><div class="prosa mt-3">{{ $aufgabe->anleitung }}</div></div>
            </section>
        @endif

        {{-- Absprachen --}}
        <section class="karte" id="kommentare">
            <div class="karte-kopf"><h2 class="titel text-sm">Absprachen</h2>@if ($aufgabe->kommentare->count())<span class="label">{{ $aufgabe->kommentare->count() }}</span>@endif</div>
            @if ($aufgabe->kommentare->count())
                <ul class="divide-y divide-nacht/5">
                    @foreach ($aufgabe->kommentare as $k)
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
                    @endforeach
                </ul>
            @endif
            @can('schreiben')
                <form method="POST" action="{{ route('aufgaben.kommentar', $aufgabe) }}" class="flex gap-2 {{ $aufgabe->kommentare->count() ? 'border-t border-nacht/10' : '' }} p-3">
                    @csrf
                    <textarea name="text" rows="1" required class="feld !min-h-[44px] h-11 flex-1 resize-none" placeholder="Frage oder Update, mit @Vorname erwähnen" x-data x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"></textarea>
                    <button class="knopf-nacht" aria-label="Senden"><x-icon name="senden" class="h-4 w-4" /></button>
                </form>
            @endcan
        </section>

        {{-- Fuers naechste Mal --}}
        <section class="karte" x-data="{ neu: false }">
            <div class="karte-kopf">
                <h2 class="titel text-sm flex items-center gap-2"><x-icon name="idee" class="h-4 w-4 text-rot" /> Fürs nächste Mal</h2>
                @can('schreiben')
                    <button type="button" x-show="!neu" x-on:click="neu = true; $nextTick(() => $refs.idee.focus())" class="label flex items-center gap-1 hover:text-rot"><x-icon name="plus" class="h-3.5 w-3.5" /> Idee festhalten</button>
                @endcan
            </div>
            @if ($aufgabe->erkenntnisse->count())
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
            @else
                <p x-show="!neu" class="px-4 py-3 text-xs text-beton">Was sollten wir nächstes Mal anders machen? Wird im Rückblick gesammelt.</p>
            @endif
            @can('schreiben')
                <form method="POST" action="{{ route('erkenntnisse.store') }}" x-show="neu" x-cloak class="space-y-3 border-t border-nacht/10 p-4">
                    @csrf
                    <input type="hidden" name="aufgabe_id" value="{{ $aufgabe->id }}">
                    <textarea name="text" required class="feld" rows="2" x-ref="idee" placeholder="z.B. zwei Wochen früher bestellen"></textarea>
                    <div class="flex flex-wrap items-center gap-2">
                        <select name="vorschlag" class="feld w-auto" aria-label="Vorschlag">
                            @foreach (\App\Models\Erkenntnis::VORSCHLAEGE as $k => $l)
                                @if ($k !== 'neu' || ! $aufgabe->vorlage_id)<option value="{{ $k }}" @selected($k === ($aufgabe->vorlage_id ? 'anpassen' : 'neu'))>{{ $l }}</option>@endif
                            @endforeach
                        </select>
                        <button type="button" class="knopf-leise" x-on:click="neu = false">Abbrechen</button>
                        <button class="knopf-nacht">Festhalten</button>
                    </div>
                </form>
            @endcan
        </section>

        {{-- Mehr: Links, Dokumente, frueher, Jahresplan, loeschen --}}
        <section x-data="{ mehr: {{ $mehrAnzahl ? 'true' : 'false' }} }" class="border-t border-nacht/10 pt-3">
            <button type="button" class="flex w-full items-center justify-between py-2" x-on:click="mehr = !mehr" :aria-expanded="mehr">
                <span class="label text-nacht">Mehr: Links, Dokumente, frühere Turniere, Jahresplan</span>
                <x-icon name="pfeil" class="h-4 w-4 transition-transform" ::class="mehr ? 'rotate-90' : ''" />
            </button>
            <div x-show="mehr" x-collapse {{ $mehrAnzahl ? '' : 'x-cloak' }} class="mt-2 grid gap-5 md:grid-cols-2">
                @include('partials.verknuepfungen', ['bezug' => $aufgabe, 'typ' => 'aufgabe'])

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

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 md:col-span-2">
                    @if ($aufgabe->vorlage)
                        <a href="{{ route('vorlagen.show', $aufgabe->vorlage) }}" class="label flex items-center gap-1 hover:text-rot"><x-icon name="vorlagen" class="h-4 w-4" /> Im Jahresplan ansehen</a>
                    @endif
                </div>
            </div>
        </section>
    </div>
    </div>

    @if ($darf)
        <x-modal name="bearbeiten" titel="Aufgabe bearbeiten">
            <form method="POST" action="{{ route('aufgaben.update', $aufgabe) }}">
                @csrf @method('PATCH')
                @include('aufgaben._formular', ['aufgabe' => $aufgabe])
                <div class="mt-5 flex items-center justify-end gap-2">
                    @can('aufgabe-verwalten', $aufgabe)
                        <button type="button" class="mr-auto label flex items-center gap-1 text-beton hover:text-rot" x-on:click="offen = false; $dispatch('modal-loeschen')"><x-icon name="loeschen" class="h-4 w-4" /> Löschen</button>
                    @endcan
                    <button type="button" class="knopf-leise" x-on:click="offen = false">Abbrechen</button>
                    <button class="knopf-rot">Speichern</button>
                </div>
            </form>
        </x-modal>
    @endif

    @can('aufgabe-verwalten', $aufgabe)
        <x-modal name="loeschen" titel="Aufgabe löschen">
            <form method="POST" action="{{ route('aufgaben.destroy', $aufgabe) }}" class="space-y-4 text-sm">
                @csrf @method('DELETE')
                <p><span class="font-bold">«{{ $aufgabe->titel }}»</span> wird aus dem Turnier {{ $aufgabe->ausgabe->titel }} gelöscht, mit Checkliste, Absprachen und Links. Das lässt sich nicht rückgängig machen.</p>
                @if ($aufgabe->saison_aufgabe)
                    <p class="border-l-4 border-rot bg-kreide px-3 py-2">Sie gilt für die ganze Saison {{ $aufgabe->ausgabe->saison }} und verschwindet damit auch bei den anderen Turnieren der Saison.</p>
                @endif
                @if ($jaehrlich)
                    @can('ressort-verwalten', $aufgabe->ressort)
                        <label class="flex cursor-pointer items-start gap-3 border border-nacht/20 bg-white px-3 py-2.5 has-[:checked]:border-rot has-[:checked]:bg-rot/5">
                            <input type="checkbox" name="aus_jahresplan" value="1" class="mt-1 h-4 w-4">
                            <span><span class="block font-bold">Auch im Jahresplan auf «Kommt nicht mehr» stellen</span>
                                <span class="block text-xs text-beton">Ohne Häkchen kommt die Aufgabe bei neuen Turnieren wieder ({{ $aufgabe->vorlage->wieOftText() }}).</span></span>
                        </label>
                    @else
                        <p class="text-beton">Im Jahresplan bleibt sie. Bei neuen Turnieren kommt sie wieder.</p>
                    @endcan
                @endif
                <p class="text-xs text-beton">Nur dieses Mal nicht nötig? Dann besser «Entfällt» wählen, so bleibt sichtbar, dass sie bewusst weggelassen wurde.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="knopf-leise" x-on:click="offen = false">Abbrechen</button>
                    <button class="knopf-rot"><x-icon name="loeschen" class="h-4 w-4" /> Endgültig löschen</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app>
