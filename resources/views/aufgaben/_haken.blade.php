{{-- Abhak-Knopf, erwartet umgebendes x-data="aufgabeStatus(...)" --}}
@php $darf = auth()->user()->can('aufgabe-bearbeiten', $aufgabe); @endphp
<button type="button" @if ($darf) x-on:click.stop.prevent="umschalten()" @else disabled @endif
        class="group relative flex h-11 w-11 shrink-0 items-center justify-center" :aria-pressed="erledigt" aria-label="Als erledigt markieren">
    <span class="flex h-7 w-7 items-center justify-center border-2 transition-colors duration-200"
          :class="erledigt ? 'border-rot bg-rot' : 'border-nacht/30 bg-white {{ $darf ? 'group-hover:border-rot' : '' }}'">
        <svg x-show="erledigt" x-cloak viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 6 9 17l-5-5" stroke-dasharray="24" stroke-dashoffset="24" class="animate-haken" />
        </svg>
    </span>
</button>
