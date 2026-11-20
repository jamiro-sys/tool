<x-app titel="Preisliste">
    <x-seitenkopf titel="Preisliste" ueber="Festwirtschaft" :zurueck="route('festwirtschaft.index')">
        <x-slot:aktionen><button type="button" onclick="window.print()" class="knopf-rot"><x-icon name="drucker" class="h-4 w-4" /> Drucken</button></x-slot:aktionen>
    </x-seitenkopf>
    <div class="karte p-6 sm:p-10">
        @foreach ($gruppen as $gruppe => $produkte)
            <h2 class="titel mb-3 mt-6 border-b-2 border-rot pb-1 text-lg first:mt-0">{{ $gruppe }}</h2>
            <table class="w-full text-sm">
                @foreach ($produkte as $p)
                    <tr class="border-b border-nacht/5">
                        <td class="py-2 pr-3"><span class="font-bold">{{ $p->name }}</span>@if ($p->einheit) <span class="text-beton">{{ $p->einheit }}</span>@endif
                            @if ($p->allergene)<span class="block text-xs text-beton">Enthält: {{ implode(', ', $p->allergene) }}</span>@endif</td>
                        <td class="zahl w-24 py-2 text-right text-base">{{ $p->preis !== null ? number_format($p->preis, 2, '.', "'") : '' }}</td>
                    </tr>
                @endforeach
            </table>
        @endforeach
        <p class="mt-8 text-xs text-beton">Preise in CHF.</p>
    </div>
</x-app>
