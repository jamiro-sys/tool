<x-app titel="Material">
    <x-seitenkopf titel="Material und Inventar">
        <x-slot:unter>Was ihr habt, wo es liegt und in welchem Zustand. Auf- und Abbau planst du pro Ausgabe.</x-slot:unter>
    </x-seitenkopf>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="karte lg:col-span-2">
            <div class="karte-kopf"><h2 class="titel text-sm">Inventar</h2>@if ($reparieren)<span class="marke-gelb">{{ $reparieren }} nicht in Ordnung</span>@else<span class="label">{{ $material->count() }}</span>@endif</div>
            @if ($alleGruppen->count() > 1)
                <div class="flex flex-wrap gap-1.5 border-b border-nacht/10 px-4 py-2.5">
                    <a href="{{ route('material.index') }}" class="px-2.5 py-1 text-xs font-bold uppercase tracking-label {{ $filter ? 'text-beton hover:text-nacht' : 'bg-nacht text-white' }}">Alle</a>
                    @foreach ($alleGruppen as $g)<a href="{{ route('material.index', ['gruppe' => $g]) }}" class="px-2.5 py-1 text-xs font-bold uppercase tracking-label {{ $filter === $g ? 'bg-nacht text-white' : 'text-beton hover:text-nacht' }}">{{ $g }}</a>@endforeach
                </div>
            @endif
            @if ($material->isEmpty() && ! $filter)
                <x-leer icon="kiste" titel="Noch kein Material" text="Erfasse, was ihr fürs Turnier braucht. Gruppen und Standorte legst du unter Admin > Einstellungen > Eigene Listen fest." />
            @endif
            <div class="divide-y divide-nacht/5">
                @foreach ($material as $m)
                    <details class="group px-4 py-3">
                        <summary class="flex cursor-pointer list-none items-center gap-3">
                            @if ($m->foto_pfad)<img src="{{ route('material.foto', $m) }}" alt="" class="h-10 w-10 shrink-0 object-cover" loading="lazy">@endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold">{{ $m->name }}</span>
                                <span class="block truncate text-xs text-beton">{{ collect([$m->gruppe, $m->standort, $m->eigentum !== 'verein' ? \App\Models\Material::EIGENTUM[$m->eigentum].($m->eigentuemer ? ' ('.$m->eigentuemer.')' : '') : null])->filter()->implode(' | ') }}</span>
                            </span>
                            @if ($m->zustand !== 'gut')<span class="{{ $m->zustand === 'defekt' ? 'marke-offen' : 'marke-gelb' }}">{{ \App\Models\Material::ZUSTAND[$m->zustand] }}</span>@endif
                            <span class="text-right text-sm"><span class="zahl">{{ rtrim(rtrim(number_format($m->menge, 2, '.', "'"), '0'), '.') }}</span> <span class="text-xs text-beton">{{ $m->einheit }}</span></span>
                            <x-icon name="pfeil" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-90" />
                        </summary>
                        @if ($m->notizen)<p class="mt-2 text-sm text-beton">{{ $m->notizen }}</p>@endif
                        @can('schreiben') @include('material._formular', ['m' => $m]) @endcan
                    </details>
                @endforeach
                @can('schreiben')
                    <details class="group px-4 py-3" @if ($material->isEmpty() && ! $filter) open @endif>
                        <summary class="label flex cursor-pointer list-none items-center gap-2 text-rot"><x-icon name="plus" class="h-4 w-4" /> Neues Material</summary>
                        @include('material._formular', ['m' => null])
                    </details>
                @endcan
            </div>
        </section>

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Auf- und Abbau</h2></div>
            @forelse ($ausgaben as $a)
                <a href="{{ route('material.ausgabe', $a) }}" class="flex items-center justify-between border-b border-nacht/5 px-4 py-3 text-sm hover:text-rot">
                    <span><span class="block font-bold">{{ $a->titel }}</span><span class="text-xs text-beton">{{ $a->datumText() }}</span></span>
                    <x-icon name="pfeil" class="h-4 w-4" />
                </a>
            @empty
                <p class="px-4 py-3 text-xs text-beton">Sobald es eine Ausgabe gibt, stellst du hier die Einsatzliste zusammen.</p>
            @endforelse
        </section>
    </div>
</x-app>
