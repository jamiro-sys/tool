<x-app titel="Neu im Tool">
    <x-seitenkopf titel="Neu im Tool" ueber="Updates">
        <x-slot:unter>Das OK-Tool wird automatisch aktualisiert. Hier steht, was sich geändert hat.</x-slot:unter>
    </x-seitenkopf>
    <div class="karte divide-y divide-nacht/5">
        @forelse ($deployments as $d)
            <article class="px-4 py-4">
                <div class="flex items-center gap-3"><span class="marke bg-nacht text-white">{{ $d->version }}</span><span class="text-xs text-beton">{{ $d->created_at->format('d.m.Y H:i') }}</span></div>
                @if ($d->aenderungen)<div class="prosa mt-2">{{ $d->aenderungen }}</div>@endif
            </article>
        @empty
            <x-leer icon="idee" titel="Noch keine Updates" />
        @endforelse
    </div>
</x-app>
