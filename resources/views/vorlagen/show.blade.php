<x-app :titel="$vorlage->titel">
    <x-seitenkopf :titel="$vorlage->titel" :ueber="'Jahresplan | '.$vorlage->ressort->name" :zurueck="route('vorlagen.index')">
        <x-slot:unter>{{ $vorlage->zeitpunktText() }} | {{ $vorlage->turnier ? 'nur '.$vorlage->turnier->name : 'alle Turnierarten' }} @if ($vorlage->aufwand_std) | ca. {{ \App\Support\Aufwand::text($vorlage->aufwand_std) }} @endif</x-slot:unter>
        <x-slot:aktionen>
            @can('ressort-verwalten', $vorlage->ressort)
                <a href="{{ route('vorlagen.edit', $vorlage) }}" class="knopf-rahmen" data-kuerzel="e"><x-icon name="stift" class="h-4 w-4" /> Bearbeiten</a>
                <div class="flex items-center gap-2 border border-nacht/20 bg-white py-1 pl-3 pr-1">
                    <span class="label">Jedes Jahr</span>
                    <div class="grid grid-cols-2 gap-1 text-[11px] font-bold uppercase tracking-label">
                        @if ($vorlage->aktiv)
                            <span class="bg-rot px-3 py-2 text-white">Ja</span>
                            <button type="button" class="px-3 py-2 uppercase text-beton hover:bg-nacht/5 hover:text-nacht" x-on:click="$dispatch('modal-archivieren')">Nein</button>
                        @else
                            <form method="POST" action="{{ route('vorlagen.aktiv', $vorlage) }}">@csrf<input type="hidden" name="aktiv" value="1"><button class="w-full px-3 py-2 uppercase text-beton hover:bg-nacht/5 hover:text-nacht">Ja</button></form>
                            <span class="bg-nacht px-3 py-2 text-white">Nein</span>
                        @endif
                    </div>
                </div>
            @endcan
        </x-slot:aktionen>
    </x-seitenkopf>

    @if (! $vorlage->aktiv)
        <div class="mb-6 border-l-4 border-beton bg-white px-4 py-3 text-sm">Kommt seit {{ $vorlage->deaktiviert_am?->format('d.m.Y') }} nicht mehr bei neuen Turnieren. @if ($vorlage->deaktiviert_grund) Grund: {{ $vorlage->deaktiviert_grund }} @endif</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($vorlage->beschreibung)<section class="karte p-4"><p class="label mb-2">Beschreibung</p><div class="prosa">{{ $vorlage->beschreibung }}</div></section>@endif
            <section class="karte border-l-4 {{ $vorlage->anleitung ? 'border-l-rot' : 'border-l-gelb' }} p-4">
                <p class="label mb-2 flex items-center gap-2"><x-icon name="buch" class="h-4 w-4" /> So geht's</p>
                @if ($vorlage->anleitung)
                    <div class="prosa">{{ $vorlage->anleitung }}</div>
                @else
                    <p class="text-sm text-beton">Noch keine Anleitung. Für eine gute Übergabe lohnt es sich, die wichtigsten Schritte aufzuschreiben.</p>
                @endif
            </section>
            @if ($vorlage->checkliste)
                <section class="karte p-4"><p class="label mb-2">Checkliste</p>
                    <ul class="space-y-1.5 text-sm">@foreach ($vorlage->checkliste as $p)<li class="flex gap-2"><span class="mt-1.5 h-2 w-2 shrink-0 bg-rot"></span>{{ is_array($p) ? $p['text'] : $p }}</li>@endforeach</ul>
                </section>
            @endif

            <section class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">In früheren Turnieren</h2></div>
                <div class="overflow-x-auto">
                    <table class="tabelle">
                        <thead><tr><th>Turnier</th><th>Verantwortlich</th><th>Status</th><th>Aufwand</th></tr></thead>
                        <tbody>
                            @forelse ($vorlage->aufgaben->sortByDesc(fn ($a) => $a->ausgabe->datum_von) as $a)
                                <tr><td><a href="{{ route('aufgaben.show', $a) }}" class="font-bold hover:text-rot">{{ $a->ausgabe->titel }}</a></td><td>{{ $a->verantwortlich?->name ?? '' }}</td><td><x-status :status="$a->status" /></td><td>{{ \App\Support\Aufwand::text($a->aufwand_effektiv) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-beton">Noch nie verwendet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Erkenntnisse</h2></div>
                <ul class="divide-y divide-nacht/5">
                    @forelse ($vorlage->erkenntnisse->sortByDesc('created_at') as $e)
                        <li class="px-4 py-3 text-sm">
                            <div class="flex items-center gap-2"><span class="marke-rahmen">{{ \App\Models\Erkenntnis::VORSCHLAEGE[$e->vorschlag] }}</span>@if ($e->umgesetzt_am)<span class="marke-erledigt">umgesetzt</span>@endif</div>
                            <p class="mt-1">{{ $e->text }}</p>
                            <p class="mt-0.5 text-xs text-beton">{{ $e->ausgabe?->titel }} | {{ $e->user?->name }}</p>
                        </li>
                    @empty
                        <li class="px-4 py-5 text-sm text-beton">Keine Erkenntnisse.</li>
                    @endforelse
                </ul>
            </section>
            <section class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Verlauf</h2></div>
                <ul class="divide-y divide-nacht/5">
                    @forelse ($vorlage->versionen as $ver)
                        <li class="px-4 py-3 text-xs" x-data="{ auf: false }">
                            <button type="button" class="flex w-full items-center justify-between text-left" x-on:click="auf = !auf">
                                <span><span class="font-bold">{{ $ver->user?->name ?? 'System' }}</span> <span class="text-beton">{{ $ver->created_at->format('d.m.Y') }}</span><br>{{ $ver->notiz ?? implode(', ', array_keys($ver->nachher ?? [])) }}</span>
                                <x-icon name="pfeil" class="h-3.5 w-3.5 shrink-0 transition-transform" ::class="auf ? 'rotate-90' : ''" />
                            </button>
                            <div x-show="auf" x-collapse class="mt-2 space-y-1">
                                @foreach ($ver->nachher ?? [] as $feld => $neu)
                                    <div><span class="label">{{ $feld }}</span>
                                        <div class="text-beton line-through">{{ \Illuminate\Support\Str::limit(is_array($ver->vorher[$feld] ?? null) ? json_encode($ver->vorher[$feld]) : (string) ($ver->vorher[$feld] ?? ''), 160) }}</div>
                                        <div>{{ \Illuminate\Support\Str::limit(is_array($neu) ? json_encode($neu) : (string) $neu, 160) }}</div></div>
                                @endforeach
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-5 text-xs text-beton">Seit der Erstellung unverändert.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>

    <x-modal name="archivieren" titel="Nicht mehr wiederholen">
        <form method="POST" action="{{ route('vorlagen.aktiv', $vorlage) }}" class="space-y-4">@csrf
            <input type="hidden" name="aktiv" value="0">
            <p class="text-sm">Die Aufgabe kommt bei neuen Turnieren nicht mehr. Bereits geplante Turniere und der Verlauf bleiben erhalten, und du kannst sie jederzeit wieder auf «Ja» stellen.</p>
            <div><label class="feld-label" for="grund">Grund</label><input id="grund" name="grund" class="feld" placeholder="z.B. Wird neu von der Halle erledigt"></div>
            <div class="flex justify-end"><button class="knopf-nacht">Nicht mehr wiederholen</button></div>
        </form>
    </x-modal>
</x-app>
