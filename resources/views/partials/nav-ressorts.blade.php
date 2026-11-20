{{-- Ressorts in der Seitenleiste: aufklappbar mit allen angelegten Ressorts --}}
@php $ressortAktiv = request()->routeIs('ressorts.*'); $aktivId = is_object($aktivesRessort ?? null) ? $aktivesRessort->id : (int) ($aktivesRessort ?? 0); @endphp
<div x-data="{ auf: {{ $ressortAktiv ? 'true' : 'false' }} }">
    <div class="flex items-stretch">
        <a href="{{ route('ressorts.index') }}" class="nav-link flex-1 {{ request()->routeIs('ressorts.index') ? 'aktiv' : '' }}"><x-icon name="ressorts" class="w-[18px] h-[18px]" /> Ressorts</a>
        @if ($navRessorts->isNotEmpty())
            <button type="button" class="flex w-11 items-center justify-center text-white/60 hover:bg-white/5 hover:text-white" x-on:click="auf = !auf" :aria-expanded="auf" aria-label="Ressorts aufklappen">
                <x-icon name="pfeil" class="h-4 w-4 transition-transform" ::class="auf ? 'rotate-90' : ''" />
            </button>
        @endif
    </div>
    @if ($navRessorts->isNotEmpty())
        <div x-show="auf" x-collapse x-cloak class="pb-1">
            @foreach ($navRessorts as $r)
                <a href="{{ route('ressorts.show', $r) }}" class="flex items-center gap-2 border-l-[3px] py-2 pl-11 pr-4 text-[13px] {{ $aktivId === $r->id ? 'border-rot bg-white/5 font-bold text-white' : 'border-transparent text-white/65 hover:bg-white/5 hover:text-white' }}">
                    <span class="w-8 shrink-0 text-[10px] font-bold uppercase tracking-label text-white/40">{{ $r->kuerzel }}</span><span class="truncate">{{ $r->name }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
