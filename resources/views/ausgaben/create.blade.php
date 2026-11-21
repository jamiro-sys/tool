<x-app titel="Neues Turnier">
    <x-seitenkopf titel="Neues Turnier planen" ueber="Assistent" :zurueck="route('ausgaben.index')">
        <x-slot:unter>Schritt {{ $vorschlag ? '2' : '1' }} von 2: {{ $vorschlag ? 'Aufgaben prüfen, anpassen oder weglassen.' : 'Turnierart und Datum wählen.' }}</x-slot:unter>
    </x-seitenkopf>

    @if ($turniere->isEmpty())
        <section class="karte">
            <x-leer icon="pokal" titel="Zuerst eine Turnierart anlegen" text="Ein Turnier gehört immer zu einer Turnierart, z. B. Hallencup Dezember. Lege Turnierarten und Hallen unter Admin > Einstellungen an.">
                @can('admin')<a href="{{ route('admin.einstellungen') }}" class="knopf-rot mt-4"><x-icon name="einstellungen" class="h-4 w-4" /> Zu den Einstellungen</a>@endcan
            </x-leer>
        </section>
    @else
    {{-- Schritt 1 --}}
    <form method="GET" action="{{ route('ausgaben.create') }}" class="karte mb-6 p-4">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="md:col-span-2">
                <span class="feld-label">Turnierart</span>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($turniere as $t)
                        <label class="flex cursor-pointer items-center gap-3 border p-3 has-[:checked]:border-rot has-[:checked]:bg-rot/5 border-nacht/20">
                            <input type="radio" name="turnier_id" value="{{ $t->id }}" required @checked(($eingabe['turnier_id'] ?? null) == $t->id)>
                            <span><span class="block text-sm font-bold">{{ $t->name }}</span><span class="text-xs text-beton">{{ $t->halle?->name }} @if ($t->ausgaben->first()) | zuletzt {{ $t->ausgaben->first()->datum_von->format('d.m.Y') }} @endif</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="feld-label" for="datum_von">Turniertag (erster Tag)</label>
                <input type="date" id="datum_von" name="datum_von" required class="feld" value="{{ $eingabe['datum_von'] ?? '' }}">
            </div>
            <div class="flex items-end"><button class="knopf-nacht w-full">{{ $vorschlag ? 'Neu berechnen' : 'Weiter' }} <x-icon name="pfeil" class="h-4 w-4" /></button></div>
        </div>
    </form>

    @if ($vorschlag)
        @php
            $datumVon = $datum->format('Y-m-d');
            $saisonStart = $datum->month >= 7 ? $datum->year : $datum->year - 1;
            $saison = $saisonStart.'/'.substr($saisonStart + 1, -2);
            $alle = $vorschlag->flatten(1);
        @endphp
        <form method="POST" action="{{ route('ausgaben.store') }}" x-data="{ weg: {}, zusatz: [] }">
            @csrf
            <input type="hidden" name="turnier_id" value="{{ $turnier->id }}">
            <input type="hidden" name="datum_von" value="{{ $datumVon }}">

            <section class="karte mb-6 p-4">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="feld-label" for="titel">Titel</label>
                        <input id="titel" name="titel" required class="feld" value="{{ $turnier->name }} {{ $saison }}">
                    </div>
                    <div>
                        <label class="feld-label" for="saison">Saison</label>
                        <input id="saison" name="saison" required class="feld" value="{{ $saison }}">
                    </div>
                    <div>
                        <label class="feld-label" for="datum_bis">Letzter Turniertag</label>
                        <input type="date" id="datum_bis" name="datum_bis" class="feld" value="{{ $datum->copy()->addDay()->format('Y-m-d') }}">
                    </div>
                    <div class="md:col-span-2">
                        <label class="feld-label" for="halle_id">Halle</label>
                        <select id="halle_id" name="halle_id" class="feld">
                            <option value="">Keine Halle</option>
                            @foreach ($hallen as $h)<option value="{{ $h->id }}" @selected($turnier->halle_id === $h->id)>{{ $h->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </section>

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm"><span x-data="{ get n() { return {{ $alle->count() }} - Object.values(weg).filter(Boolean).length + zusatz.length } }"><span class="zahl text-xl" x-text="n">{{ $alle->count() }}</span> <span x-text="n === 1 ? 'Aufgabe wird' : 'Aufgaben werden'">{{ $alle->count() === 1 ? 'Aufgabe wird' : 'Aufgaben werden' }}</span> erstellt.</span>
                    @php $hinweise = $alle->filter(fn ($e) => $e['erkenntnisse']->count())->count(); @endphp
                    @if ($hinweise)<span class="marke-gelb ml-2">{{ $hinweise }} mit Hinweisen vom letzten Mal</span>@endif
                </p>
            </div>

            @if ($alle->isEmpty())
                <div class="karte mb-5"><x-leer icon="vorlagen" titel="Noch nichts im Jahresplan" text="Für diese Turnierart steht nichts im Jahresplan. Du kannst das Turnier trotzdem erstellen und Aufgaben unten oder später ergänzen." /></div>
            @endif
            <p class="mb-4 border-l-4 border-gelb bg-white px-4 py-2.5 text-xs text-beton">Alles aus dem Jahresplan ist angehakt. Häkchen weg heisst: dieses Jahr auslassen. Im Jahresplan bleibt die Aufgabe trotzdem.</p>
            @foreach ($vorschlag as $ressortId => $eintraege)
                <section class="karte mb-5" x-data="{ auf: true }">
                    <button type="button" class="karte-kopf w-full" x-on:click="auf = !auf">
                        <span class="titel text-sm">{{ $ressorts[$ressortId]->name }}</span>
                        <span class="flex items-center gap-2"><span class="label">{{ $eintraege->count() }}</span><x-icon name="pfeil" class="h-4 w-4 transition-transform" ::class="auf ? 'rotate-90' : ''" /></span>
                    </button>
                    <div x-show="auf" x-collapse>
                        @foreach ($eintraege as $e)
                            @php $v = $e['vorlage']; @endphp
                            <div class="border-b border-nacht/5 px-4 py-3 last:border-0 transition-opacity" :class="weg[{{ $v->id }}] ? 'opacity-40' : ''">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                                    <label class="flex flex-1 cursor-pointer items-start gap-3 min-w-0">
                                        <input type="checkbox" class="mt-1 h-5 w-5" checked x-on:change="weg[{{ $v->id }}] = !$event.target.checked">
                                        <input type="hidden" name="auswahl[{{ $v->id }}][aktion]" :value="weg[{{ $v->id }}] ? 'weglassen' : 'uebernehmen'" value="uebernehmen">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-bold">{{ $v->titel }}</span>
                                            <span class="text-xs text-beton">{{ $v->zeitpunktText() }} @if ($v->turniere->isNotEmpty()) | nur {{ $v->turnierartText() }} @endif @if ($e['aufwand_vorjahr']) | letztes Mal {{ \App\Support\Aufwand::text($e['aufwand_vorjahr']) }} @endif</span>
                                        </span>
                                    </label>
                                    <div class="grid grid-cols-2 gap-2 lg:w-[380px]">
                                        <input type="date" name="auswahl[{{ $v->id }}][faellig_am]" value="{{ $e['faellig_am']->format('Y-m-d') }}" class="feld min-h-[38px] py-1 text-xs" aria-label="Fällig am">
                                        <select name="auswahl[{{ $v->id }}][verantwortlich_id]" class="feld min-h-[38px] py-1 text-xs" aria-label="Verantwortlich">
                                            <option value="">Noch offen</option>
                                            @foreach ($personen as $p)<option value="{{ $p->id }}" @selected($e['verantwortlich_id'] === $p->id)>{{ $p->name }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                                @foreach ($e['erkenntnisse'] as $erk)
                                    <div class="ml-8 mt-2 flex items-start gap-2 border-l-2 border-gelb bg-kreide px-3 py-2 text-xs">
                                        <x-icon name="idee" class="h-3.5 w-3.5 shrink-0" />
                                        <span><span class="font-bold">{{ \App\Models\Erkenntnis::VORSCHLAEGE[$erk->vorschlag] }}:</span> {{ $erk->text }} <span class="text-beton">({{ $erk->user?->name }})</span></span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section class="karte mb-6">
                <div class="karte-kopf"><h2 class="titel text-sm">Zusätzliche Aufgaben</h2>
                    @if ($ressorts->isNotEmpty())<button type="button" class="knopf-rahmen knopf-klein" x-on:click="zusatz.push({})"><x-icon name="plus" class="h-3.5 w-3.5" /> Hinzufügen</button>@endif</div>
                <template x-for="(z, i) in zusatz" :key="i">
                    <div class="grid gap-2 border-b border-nacht/5 p-3 sm:grid-cols-[1fr_180px_160px_44px]">
                        <input :name="`zusatz[${i}][titel]`" class="feld" placeholder="Neue Aufgabe für dieses Turnier" aria-label="Neue Aufgabe für dieses Turnier">
                        <select :name="`zusatz[${i}][ressort_id]`" class="feld" aria-label="Ressort">
                            @foreach ($ressorts as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                        </select>
                        <input type="date" :name="`zusatz[${i}][faellig_am]`" class="feld">
                        <button type="button" class="knopf-leise" x-on:click="zusatz.splice(i, 1)" aria-label="Entfernen"><x-icon name="x" class="h-4 w-4" /></button>
                    </div>
                </template>
                @if ($ressorts->isEmpty())
                    <p class="px-4 py-4 text-sm text-beton">Aufgaben gehören zu einem Ressort. Lege unter Ressorts zuerst eure Ressorts an. Du kannst das Turnier auch jetzt ohne Aufgaben erstellen und sie später ergänzen.</p>
                @else
                    <p x-show="zusatz.length === 0" class="px-4 py-4 text-sm text-beton">Neue Aufgaben, die es bisher nicht gab. Sie kommen nur dieses Mal, ausser du nimmst sie später in den Jahresplan.</p>
                @endif
            </section>

            <div class="sticky bottom-20 z-20 flex justify-end lg:bottom-4">
                <button class="knopf-rot px-8"><x-icon name="check" class="h-4 w-4" /> Turnier erstellen</button>
            </div>
        </form>
    @endif
    @endif
</x-app>
