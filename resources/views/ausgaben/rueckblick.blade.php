<x-app :titel="'Rückblick '.$ausgabe->titel">
    <x-seitenkopf titel="Rückblick" :ueber="$ausgabe->titel" :zurueck="route('ausgaben.show', $ausgabe)">
        <x-slot:unter>Was lief gut, was machen wir nächstes Mal anders? Pro Aufgabe entscheidest du, ob sie beim nächsten Mal wieder kommt. Erkenntnisse fliessen in den Jahresplan.</x-slot:unter>
    </x-seitenkopf>

    {{-- Ablauf in drei Schritten --}}
    <ol class="mb-6 grid gap-3 md:grid-cols-3">
        @foreach (['Aufwand erfassen' => 'Wie lange hat jede Aufgabe wirklich gebraucht?', 'Kommt wieder?' => 'Pro Aufgabe Ja oder Nein: kommt sie beim nächsten Mal wieder?', 'Erkenntnisse festhalten' => 'Was machen wir anders? Mit der Glühbirne direkt in den Jahresplan.'] as $t => $txt)
            <li class="karte flex gap-3 p-4">
                <span class="zahl flex h-9 w-9 shrink-0 items-center justify-center bg-rot text-lg text-white">{{ $loop->iteration }}</span>
                <span><span class="block text-sm font-bold uppercase tracking-label">{{ $t }}</span><span class="text-xs text-beton">{{ $txt }}</span></span>
            </li>
        @endforeach
    </ol>

    <form method="GET" class="mb-5 flex flex-wrap gap-2" x-data x-on:change="$el.submit()">
        <select name="ressort" class="feld w-auto" aria-label="Ressort">
            <option value="">Alle Ressorts</option>
            @foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected($ressortId === $r->id)>{{ $r->name }}</option>@endforeach
        </select>
    </form>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <form method="POST" action="{{ route('ausgaben.aufwand', $ausgabe) }}">
                @csrf
                @foreach ($aufgaben as $gruppe)
                    <section class="karte mb-5">
                        <div class="karte-kopf"><h2 class="titel text-sm">{{ $gruppe->first()->ressort->name }}</h2><span class="label">{{ $gruppe->where('status', 'erledigt')->count() }}/{{ $gruppe->count() }} erledigt</span></div>
                        @foreach ($gruppe as $a)
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-nacht/5 px-4 py-3 last:border-0" x-data="{ notiz: false }">
                                <div class="min-w-[240px] flex-1">
                                    <a href="{{ route('aufgaben.show', $a) }}" class="text-sm font-bold hover:text-rot">{{ $a->titel }}</a>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-beton">
                                        <x-status :status="$a->status" />
                                        <span>{{ $a->verantwortlich?->name ?? 'ohne Person' }}</span>
                                        @foreach ($a->erkenntnisse as $e)<span class="marke-rahmen" title="{{ $e->text }}">{{ \App\Models\Erkenntnis::VORSCHLAEGE[$e->vorschlag] }}</span>@endforeach
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2" x-data="jahresplanSchalter(@js(route('aufgaben.naechstes-jahr', $a)), {{ $a->vorlage?->aktiv ? 'true' : 'false' }}, 'wert')">
                                    <span class="label whitespace-nowrap">Kommt wieder?</span>
                                    @can('ressort-verwalten', $a->ressort)
                                        <div class="grid grid-cols-2 border border-nacht/20 text-[11px] font-bold uppercase tracking-label" role="group" aria-label="Kommt wieder: {{ $a->titel }}">
                                            <button type="button" class="px-3 py-1.5 uppercase" :class="wert ? 'bg-rot text-white' : 'text-beton hover:bg-nacht/5'" x-on:click="setze(true)" :aria-pressed="wert">Ja</button>
                                            <button type="button" class="px-3 py-1.5 uppercase" :class="wert ? 'text-beton hover:bg-nacht/5' : 'bg-nacht text-white'" x-on:click="setze(false)" :aria-pressed="!wert">Nein</button>
                                        </div>
                                    @else
                                        <span class="text-xs font-bold" x-text="wert ? 'Ja' : 'Nein'"></span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-beton whitespace-nowrap">Plan {{ \App\Support\Aufwand::text($a->aufwand_geplant, '?') }}</span>
                                    <x-aufwand :name="'aufwand['.$a->id.']'" :wert="$a->aufwand_effektiv" klein />
                                    <button type="button" class="knopf-rahmen knopf-klein" aria-label="Erkenntnis festhalten" x-on:click="$dispatch('erkenntnis', { id: {{ $a->id }}, titel: @js($a->titel), vorlage: {{ $a->vorlage_id ? 'true' : 'false' }} })"><x-icon name="idee" class="h-3.5 w-3.5" /></button>
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endforeach
                @can('schreiben')
                    <div class="sticky bottom-20 z-20 flex justify-end lg:bottom-4"><button class="knopf-nacht">Aufwände speichern</button></div>
                @endcan
            </form>
        </div>

        <aside class="space-y-6">
            <section class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Aufwand in Stunden</h2></div>
                <div class="h-64 p-4"><canvas x-data x-init="diagramm($el, 'balken', { labels: {{ json_encode($aufwand->pluck('ressort')) }}, horizontal: true, reihen: [{ name: 'Geplant', werte: {{ json_encode($aufwand->pluck('geplant')) }}, farbe: '#A9AEB8' }, { name: 'Effektiv', werte: {{ json_encode($aufwand->pluck('effektiv')) }}, farbe: '#B8161C' }] })"></canvas></div>
            </section>

            <section class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Erkenntnisse</h2><span class="label">{{ $erkenntnisse->whereNull('umgesetzt_am')->count() }} offen</span></div>
                <ul class="divide-y divide-nacht/5">
                    @forelse ($erkenntnisse as $e)
                        <li class="px-4 py-3" x-data="{ auf: false }">
                            <div class="flex items-center gap-2">
                                <span class="{{ $e->vorschlag === 'streichen' ? 'marke-gelb' : 'marke-rahmen' }}">{{ \App\Models\Erkenntnis::VORSCHLAEGE[$e->vorschlag] }}</span>
                                @if ($e->umgesetzt_am)<span class="marke-erledigt">umgesetzt</span>@endif
                                <span class="ml-auto text-xs text-beton">{{ $e->ressort->kuerzel }}</span>
                            </div>
                            <p class="mt-1.5 text-sm">{{ $e->text }}</p>
                            <p class="mt-1 text-xs text-beton">{{ $e->aufgabe?->titel ?? $e->vorlage?->titel }} | {{ $e->user?->name }}</p>
                            @if (! $e->umgesetzt_am)
                                @can('ressort-verwalten', $e->ressort)
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @if ($e->vorschlag === 'anpassen' && $e->vorlage)
                                            <button type="button" class="knopf-rot knopf-klein" x-on:click="auf = !auf">Im Jahresplan anpassen</button>
                                        @else
                                            <form method="POST" action="{{ route('erkenntnisse.uebernehmen', $e) }}">@csrf
                                                <button class="knopf-rot knopf-klein">{{ ['streichen' => 'Kommt nicht mehr', 'neu' => 'In den Jahresplan', 'beibehalten' => 'Erledigt', 'anpassen' => 'Übernehmen'][$e->vorschlag] }}</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('erkenntnisse.destroy', $e) }}" onsubmit="return confirm('Diese Erkenntnis verwerfen?')">@csrf @method('DELETE')<button class="knopf-leise knopf-klein">Verwerfen</button></form>
                                    </div>
                                    @if ($e->vorschlag === 'anpassen' && $e->vorlage)
                                        <form method="POST" action="{{ route('erkenntnisse.uebernehmen', $e) }}" x-show="auf" x-collapse class="mt-3 space-y-2 border-t border-nacht/10 pt-3">@csrf
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <div><label class="feld-label">Wann</label><x-wann :tage="$e->vorlage->tage_vorher" :bezug="$e->vorlage->bezug" klein /></div>
                                                <div><label class="feld-label">Aufwand</label><x-aufwand name="aufwand_std" :wert="$e->aufgabe?->aufwand_effektiv ?? $e->vorlage->aufwand_std" klein /></div>
                                            </div>
                                            <div><label class="feld-label">Anleitung</label><textarea name="anleitung" class="feld" rows="5">{{ trim(($e->vorlage->anleitung ?? '')."\n\nHinweis ".$ausgabe->saison.': '.$e->text) }}</textarea></div>
                                            <button class="knopf-rot knopf-klein">Im Jahresplan speichern</button>
                                        </form>
                                    @endif
                                @endcan
                            @endif
                        </li>
                    @empty
                        <li><x-leer icon="idee" titel="Noch keine Erkenntnisse" text="Klicke bei einer Aufgabe auf die Glühbirne." /></li>
                    @endforelse
                </ul>
            </section>

            @if ($ohneVorlage->count())
                <section class="karte border-l-4 border-l-gelb p-4">
                    <p class="titel text-sm">{{ $ohneVorlage->count() }} einmalige Aufgaben</p>
                    <p class="mt-1 text-xs text-beton">Kommen sie beim nächsten Mal wieder? Dann bei der Aufgabe auf Ja tippen.</p>
                </section>
            @endif

            @can('ausgaben-verwalten')
                @if ($ausgabe->status !== 'abgeschlossen')
                    <form method="POST" action="{{ route('ausgaben.update', $ausgabe) }}">@csrf @method('PATCH')
                        <input type="hidden" name="status" value="abgeschlossen">
                        <button class="knopf-nacht w-full">Turnier abschliessen</button>
                    </form>
                @endif
            @endcan
        </aside>
    </div>

    {{-- Erkenntnis erfassen --}}
    <div x-data="{ offen: false, id: null, titel: '', vorlage: true }" x-on:erkenntnis.window="offen = true; id = $event.detail.id; titel = $event.detail.titel; vorlage = $event.detail.vorlage" x-cloak>
        <div x-show="offen" x-transition.opacity class="fixed inset-0 z-50 bg-nacht/60" x-on:click="offen = false"></div>
        <form x-show="offen" x-transition method="POST" action="{{ route('erkenntnisse.store') }}" class="fixed inset-x-0 bottom-0 z-50 space-y-3 bg-white p-4 sm:inset-auto sm:left-1/2 sm:top-[15vh] sm:w-full sm:max-w-md sm:-translate-x-1/2">
            @csrf
            <input type="hidden" name="aufgabe_id" :value="id">
            <p class="label text-rot">Erkenntnis</p>
            <p class="titel text-base" x-text="titel"></p>
            <textarea name="text" required class="feld" rows="3" placeholder="Was war gut, was ändern wir?" aria-label="Was war gut, was ändern wir?"></textarea>
            <div class="grid grid-cols-2 gap-2">
                @foreach (\App\Models\Erkenntnis::VORSCHLAEGE as $k => $l)
                    <label class="flex cursor-pointer items-center gap-2 border border-nacht/20 px-3 py-2 text-xs font-bold has-[:checked]:border-rot has-[:checked]:bg-rot/5" @if ($k === 'neu') x-show="!vorlage" @else x-show="vorlage || '{{ $k }}' === 'beibehalten'" @endif>
                        <input type="radio" name="vorschlag" value="{{ $k }}" @checked($k === 'anpassen')> {{ $l }}
                    </label>
                @endforeach
            </div>
            <div class="flex justify-end gap-2"><button type="button" class="knopf-leise" x-on:click="offen = false">Abbrechen</button><button class="knopf-rot">Festhalten</button></div>
        </form>
    </div>
</x-app>
