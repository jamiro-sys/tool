{{-- Turnierwahl in der Kopfzeile: zeigt das gewaehlte Turnier, Wechsel fuehrt zur gleichen Ansicht des anderen Turniers --}}
@php
    $navTurniere ??= collect();
    $ansicht = request()->route()?->getName();
    $proSaison = $navTurniere->groupBy('saison')->sortKeysDesc();
    $leiste ??= false;
@endphp
<div x-data="{ offen: false }" class="relative" data-turnierwahl>
    @if ($leiste)
        <button type="button" x-on:click="offen = !offen" x-on:click.outside="offen = false" :aria-expanded="offen" aria-haspopup="true"
                class="flex min-h-[44px] w-full items-center gap-2 px-4 text-left" title="Turnier wählen">
            <span class="text-[10px] font-bold uppercase tracking-label text-white/60">Turnier</span>
            <span class="min-w-0 flex-1 truncate text-xs font-bold uppercase tracking-label">{{ $navAusgabe->titel }}</span>
            <x-icon name="pfeil" class="h-3.5 w-3.5 shrink-0 transition-transform" ::class="offen ? '-rotate-90' : 'rotate-90'" />
        </button>
    @else
        <button type="button" x-on:click="offen = !offen" x-on:click.outside="offen = false" :aria-expanded="offen" aria-haspopup="true"
                class="flex h-11 items-center gap-1.5 border border-white/35 px-2.5 text-left hover:bg-white/10" title="Turnier wählen">
            <span class="flex flex-col leading-none">
                <span class="text-[9px] font-bold uppercase tracking-label text-white/70">Turnier</span>
                <span class="mt-0.5 text-xs font-extrabold uppercase tracking-label">{{ $navAusgabe->kurzname() }}</span>
            </span>
            <x-icon name="pfeil" class="h-3.5 w-3.5 transition-transform" ::class="offen ? '-rotate-90' : 'rotate-90'" />
        </button>
    @endif
    <div x-show="offen" x-cloak x-transition.origin.top.right class="{{ $leiste ? 'absolute inset-x-0 top-full max-h-[70vh] shadow-none' : 'absolute right-0 mt-1 w-80 max-h-[75vh]' }} overflow-y-auto border border-nacht/10 bg-white text-nacht" role="menu">
        <p class="border-b border-nacht/10 px-4 py-2.5 text-xs text-beton">Turnier wählen. Die Ansicht wechselt gleich zum gewählten Turnier.</p>
        @foreach ($proSaison as $saison => $liste)
            <p class="label bg-kreide px-4 py-1.5">Saison {{ $saison }}</p>
            @foreach ($liste->sortBy('datum_von') as $t)
                <a href="{{ route('ausgaben.waehlen', ['ausgabe' => $t, 'ansicht' => $ansicht]) }}" role="menuitem"
                   class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-kreide {{ $t->id === $navAusgabe->id ? 'border-l-4 border-rot font-bold' : '' }}">
                    <span class="min-w-0"><span class="block truncate">{{ $t->titel }}</span><span class="block text-xs font-normal text-beton">{{ $t->datumText() }}</span></span>
                    @if ($t->id === $navAusgabe->id)<x-icon name="check" class="h-4 w-4 shrink-0 text-rot" />@endif
                </a>
            @endforeach
        @endforeach
        <a href="{{ route('ausgaben.index') }}" class="flex items-center gap-2 border-t border-nacht/10 px-4 py-2.5 text-xs font-bold uppercase tracking-label hover:bg-kreide hover:text-rot"><x-icon name="pokal" class="h-4 w-4" /> Alle Turniere</a>
    </div>
</div>
