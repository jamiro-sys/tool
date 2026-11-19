<x-app titel="Ausgaben">
    <x-seitenkopf titel="Ausgaben" ueber="Turniere">
        <x-slot:unter>Zwei Turniere pro Saison, jedes in seiner Halle. Jede Ausgabe entsteht aus den Vorlagen.</x-slot:unter>
        <x-slot:aktionen>
            @can('ausgaben-verwalten')
                <a href="{{ route('ausgaben.create') }}" class="knopf-rot" data-kuerzel="n"><x-icon name="plus" class="h-4 w-4" /> Neue Ausgabe</a>
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
        <div class="karte"><x-leer icon="pokal" titel="Noch keine Ausgabe" text="Lege die erste Ausgabe an. Der Assistent schlägt alle Aufgaben aus den Vorlagen vor." /></div>
    @else
        <div class="karte overflow-x-auto">
            <table class="tabelle">
                <thead><tr><th>Ausgabe</th><th>Datum</th><th class="hidden sm:table-cell">Halle</th><th>Status</th><th class="w-40">Fortschritt</th></tr></thead>
                <tbody>
                    @foreach ($ausgaben as $a)
                        @php $p = $a->total ? round($a->erledigt / $a->total * 100) : 0; @endphp
                        <tr class="hover:bg-kreide/60">
                            <td><a href="{{ route('ausgaben.show', $a) }}" class="font-bold hover:text-rot">{{ $a->titel }}</a><div class="text-xs text-beton">{{ $a->turnier->name }}</div></td>
                            <td class="whitespace-nowrap">{{ $a->datum_von->format('d.m.Y') }}</td>
                            <td class="hidden sm:table-cell">{{ $a->halle?->name }}</td>
                            <td><span class="{{ $a->status === 'abgeschlossen' ? 'marke-rahmen' : ($a->status === 'rueckblick' ? 'marke-gelb' : 'marke-in_arbeit') }}">{{ \App\Models\Ausgabe::STATUS[$a->status] }}</span></td>
                            <td><div class="flex items-center gap-2"><x-fortschritt :prozent="$p" /><span class="label w-10 text-right">{{ $p }}%</span></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app>
