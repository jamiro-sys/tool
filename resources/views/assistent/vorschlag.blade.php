<x-app titel="Vorschläge von Claude">
    <x-seitenkopf titel="Vorschläge prüfen" :ueber="$art === 'jahresplan' ? 'Claude | Jahresplan' : 'Claude | '.$ressort->name" :zurueck="$zurueck">
        <x-slot:unter>{{ count($vorschlaege) }} {{ count($vorschlaege) === 1 ? 'Vorschlag' : 'Vorschläge' }}{{ $ausgabe ? ' für '.$ausgabe->titel : '' }}. Anpassen, abwählen, was nicht passt, dann übernehmen.</x-slot:unter>
    </x-seitenkopf>

    @if ($hinweis)
        <p class="mb-5 border-l-4 border-gelb bg-white px-4 py-3 text-sm"><span class="label mb-1 block">Hinweis von Claude</span>{{ $hinweis }}</p>
    @endif

    <form method="POST" action="{{ route('assistent.uebernehmen') }}" x-data="{ anzahl: {{ count($vorschlaege) }} }">@csrf
        <input type="hidden" name="art" value="{{ $art }}">
        @if ($ressort)<input type="hidden" name="ressort_id" value="{{ $ressort->id }}">@endif
        <div class="space-y-4">
            @foreach ($vorschlaege as $i => $v)
                @php $p = 'zeilen['.$i.']'; @endphp
                <section class="karte p-4 transition-opacity" x-data="{ nehmen: true, mehr: false }" :class="nehmen ? '' : 'opacity-50'">
                    <div class="flex items-start gap-3">
                        <input type="hidden" name="{{ $p }}[nehmen]" :value="nehmen ? 1 : 0" value="1">
                        <input type="checkbox" class="mt-3 h-5 w-5 shrink-0" checked x-on:change="nehmen = $event.target.checked; anzahl += nehmen ? 1 : -1" aria-label="Übernehmen">
                        <div class="min-w-0 flex-1 space-y-3">
                            <input name="{{ $p }}[titel]" value="{{ $v['titel'] }}" maxlength="200" class="feld font-bold" aria-label="Titel">
                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                @if ($art === 'jahresplan')
                                    <div><label class="feld-label">Ressort</label>
                                        <select name="{{ $p }}[ressort_id]" class="feld" aria-label="Ressort">@foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected($v['ressort_id'] === $r->id)>{{ $r->name }}</option>@endforeach</select>
                                        @if (! $v['ressort_id'])<p class="mt-1 text-xs font-bold text-rot">Ressort bitte wählen</p>@endif</div>
                                @endif
                                <div><label class="feld-label">Wann</label>
                                    <x-wann :prefix="$p" :tage="$v['tage_vorher']" :bezug="$v['bezug']" :aufgabe="$art === 'ressort'" :von="$ausgabe?->datum_von" :bis="$ausgabe?->datum_bis" klein />
                                    @if ($v['tage_vorher'] === null)<p class="mt-1 text-xs font-bold text-rot">Zeitpunkt bitte prüfen</p>@endif</div>
                                <div><label class="feld-label">Aufwand</label><x-aufwand :name="$p.'[aufwand]'" :wert="$v['aufwand']" klein /></div>
                                <div><label class="feld-label">Priorität</label>
                                    <select name="{{ $p }}[prioritaet]" class="feld min-h-[38px] py-1" aria-label="Priorität">@foreach (\App\Models\Vorlage::PRIORITAETEN as $k => $l)<option value="{{ $k }}" @selected($v['prioritaet'] === $k)>{{ $l }}</option>@endforeach</select></div>
                            </div>
                            @if ($art === 'jahresplan' || $darfPlan)
                                <div><p class="feld-label">Wie oft?</p>
                                    <x-haeufigkeit :name="$p.'[haeufigkeit]'" :wert="$art === 'ressort' && ! $v['jedes_jahr'] ? 'einmalig' : (($v['pro_saison'] ?? false) ? 'saison' : 'turnier')"
                                        :einmalig="$art === 'ressort' ? ['name' => $p.'[jedes_jahr]', 'an' => 1, 'aus' => 0] : null">
                                        @if ($turniere->count() > 1)<p class="feld-label mt-1">Bei welchen Turnieren?</p><x-turnierarten :turniere="$turniere" :gewaehlt="$v['turniere']" :prefix="$p" klein />@endif
                                    </x-haeufigkeit>
                                </div>
                            @endif
                            <button type="button" class="label hover:text-rot" x-on:click="mehr = !mehr" x-text="mehr ? 'Weniger' : 'Beschreibung und Checkliste{{ ($v['beschreibung'] || $v['checkliste']) ? ' (ausgefüllt)' : '' }}'"></button>
                            <div x-show="mehr" x-collapse x-cloak class="grid gap-3 md:grid-cols-2">
                                <div><label class="feld-label">Beschreibung</label><textarea name="{{ $p }}[beschreibung]" rows="3" class="feld">{{ $v['beschreibung'] }}</textarea></div>
                                <div><label class="feld-label">Checkliste (eine Zeile pro Punkt)</label><textarea name="{{ $p }}[checkliste]" rows="3" class="feld">{{ implode("\n", $v['checkliste']) }}</textarea></div>
                            </div>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
        <div class="sticky bottom-20 z-20 mt-6 flex items-center justify-end gap-2 lg:bottom-4">
            <a href="{{ $zurueck }}" class="knopf-rahmen bg-white">Verwerfen</a>
            <button class="knopf-rot" :disabled="anzahl === 0"><x-icon name="check" class="h-4 w-4" /> <span x-text="anzahl === 1 ? '1 Aufgabe übernehmen' : anzahl + ' Aufgaben übernehmen'"></span></button>
        </div>
    </form>
</x-app>
