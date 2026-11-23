<x-app titel="Turniere">
    <x-seitenkopf titel="Turniere" ueber="Planung">
        <x-slot:unter>Eine Saison umfasst die Turniere eines Vereinsjahres, z. B. Dezember und Februar. Jedes Turnier entsteht aus dem Jahresplan.</x-slot:unter>
        <x-slot:aktionen>
            @can('ausgaben-verwalten')
                <a href="{{ route('ausgaben.create') }}" class="knopf-leise"><x-icon name="plus" class="h-4 w-4" /> Einzelnes Turnier</a>
                <a href="{{ route('saison.create') }}" class="knopf-rot" data-kuerzel="n"><x-icon name="kalender" class="h-4 w-4" /> Neue Saison planen</a>
            @endcan
        </x-slot:aktionen>
    </x-seitenkopf>

    <div class="grid gap-4 md:grid-cols-2 mb-8">
        @foreach ($turniere as $t)
            <div class="karte flex items-center gap-4 p-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center bg-nacht text-xs font-extrabold text-white">{{ $t->kurz }}</span>
                <div class="min-w-0">
                    <p class="titel text-sm">{{ $t->name }}</p>
                    <p class="text-xs text-beton flex items-center gap-1"><x-icon name="halle" class="h-3.5 w-3.5" /> {{ $t->halle?->name ?? 'Halle offen' }} {{ $t->halle?->ort ? ', '.$t->halle->ort : '' }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if ($ausgaben->isEmpty())
        <div class="karte"><x-leer icon="pokal" titel="Noch kein Turnier" text="Plane die erste Saison: Daten der Turniere wählen, dann schlägt der Assistent alle Aufgaben aus dem Jahresplan vor." /></div>
    @else
        <div class="space-y-8">
            @foreach ($ausgaben->groupBy('saison') as $saison => $turnierListe)
                @php
                    $total = $turnierListe->sum('total');
                    $erl = $turnierListe->sum('erledigt');
                    $sp = $total ? (int) round($erl / $total * 100) : 0;
                    $fehlend = $turniere->reject(fn ($t) => $turnierListe->contains('turnier_id', $t->id));
                @endphp
                <section data-saison="{{ $saison }}">
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-3 border-b-2 border-nacht pb-2">
                        <div>
                            <p class="label">Saison</p>
                            <h2 class="titel text-xl">{{ $saison }}</h2>
                        </div>
                        <div class="flex w-56 items-center gap-2"><x-fortschritt :prozent="$sp" /><span class="label w-10 text-right">{{ $sp }}%</span></div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach ($turnierListe->sortBy('datum_von') as $a)
                            @php $p = $a->total ? (int) round($a->erledigt / $a->total * 100) : 0; @endphp
                            <a href="{{ route('ausgaben.show', $a) }}" class="karte group block p-4 hover:border-nacht">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center bg-nacht text-xs font-extrabold text-white">{{ $a->turnier->kurz }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block titel text-sm group-hover:text-rot">{{ $a->titel }}</span>
                                        <span class="block text-xs text-beton">{{ $a->datumText() }}{{ $a->halle ? ' | '.$a->halle->name : '' }}</span>
                                    </span>
                                    <span class="{{ $a->status === 'abgeschlossen' ? 'marke-rahmen' : ($a->status === 'rueckblick' ? 'marke-gelb' : 'marke-in_arbeit') }}">{{ \App\Models\Ausgabe::STATUS[$a->status] }}</span>
                                </div>
                                <div class="mt-3 flex items-center gap-2"><x-fortschritt :prozent="$p" /><span class="label w-24 text-right">{{ $a->erledigt }}/{{ $a->total }}</span></div>
                            </a>
                        @endforeach
                        @can('ausgaben-verwalten')
                            @foreach ($fehlend as $t)
                                <a href="{{ route('ausgaben.create', ['turnier_id' => $t->id, 'saison' => $saison]) }}" class="flex items-center gap-3 border-2 border-dashed border-nacht/20 p-4 text-sm text-beton hover:border-rot hover:text-rot">
                                    <x-icon name="plus" class="h-4 w-4" /> {{ $t->name }} {{ $saison }} noch anlegen
                                </a>
                            @endforeach
                        @endcan
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-app>
