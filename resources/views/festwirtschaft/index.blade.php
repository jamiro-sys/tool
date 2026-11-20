<x-app titel="Festwirtschaft">
    <x-seitenkopf titel="Festwirtschaft">
        <x-slot:unter>Eure Produkte mit Preisen und Allergenen. Einkauf und Kassen planst du pro Ausgabe.</x-slot:unter>
        <x-slot:aktionen>
            @if ($produkte->where('aktiv', true)->isNotEmpty())
                <a href="{{ route('festwirtschaft.preisliste') }}" class="knopf-rahmen"><x-icon name="drucker" class="h-4 w-4" /> Preisliste</a>
            @endif
        </x-slot:aktionen>
    </x-seitenkopf>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="karte lg:col-span-2">
            <div class="karte-kopf"><h2 class="titel text-sm">Produkte</h2><span class="label">{{ $produkte->count() }}</span></div>
            @if ($produkte->isEmpty())
                <x-leer icon="becher" titel="Noch keine Produkte" text="Erfasse, was ihr verkauft. Kategorien und Allergene legst du unter Admin > Einstellungen > Eigene Listen fest." />
            @endif
            <div class="divide-y divide-nacht/5">
                @foreach ($produkte as $p)
                    <details class="group px-4 py-3">
                        <summary class="flex cursor-pointer list-none items-center gap-3">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold {{ $p->aktiv ? '' : 'text-beton line-through' }}">{{ $p->name }}@if ($p->einheit)<span class="font-normal text-beton"> {{ $p->einheit }}</span>@endif</span>
                                <span class="block text-xs text-beton">{{ collect([$p->kategorie, $p->allergene ? 'Allergene: '.implode(', ', $p->allergene) : null])->filter()->implode(' | ') ?: 'ohne Kategorie' }}</span>
                            </span>
                            <span class="zahl text-lg">{{ $p->preis !== null ? number_format($p->preis, 2, '.', "'") : '' }}</span>
                            <x-icon name="pfeil" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-90" />
                        </summary>
                        @can('schreiben') @include('festwirtschaft._produkt', ['p' => $p]) @endcan
                    </details>
                @endforeach
                @can('schreiben')
                    <details class="group px-4 py-3" @if ($produkte->isEmpty()) open @endif>
                        <summary class="label flex cursor-pointer list-none items-center gap-2 text-rot"><x-icon name="plus" class="h-4 w-4" /> Neues Produkt</summary>
                        @include('festwirtschaft._produkt', ['p' => null])
                    </details>
                @endcan
            </div>
        </section>

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Einkauf und Kassen</h2></div>
            @forelse ($ausgaben as $a)
                <a href="{{ route('festwirtschaft.ausgabe', $a) }}" class="flex items-center justify-between border-b border-nacht/5 px-4 py-3 text-sm hover:text-rot">
                    <span><span class="block font-bold">{{ $a->titel }}</span><span class="text-xs text-beton">{{ $a->datumText() }}</span></span>
                    <x-icon name="pfeil" class="h-4 w-4" />
                </a>
            @empty
                <p class="px-4 py-3 text-xs text-beton">Sobald es eine Ausgabe gibt, planst du hier Einkauf und Kassen.</p>
            @endforelse
        </section>
    </div>
</x-app>
