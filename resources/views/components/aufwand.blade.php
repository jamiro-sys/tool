@props(['name', 'wert' => null, 'id' => null, 'klein' => false])
{{-- Aufwand in Stunden und Minuten erfassen; gesendet wird der Wert in Stunden (Dezimal). --}}
@php $minuten = $wert === null || $wert === '' ? null : (int) round(((float) $wert) * 60); @endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }}
     x-data="{ std: {{ $minuten === null ? "''" : intdiv($minuten, 60) }}, min: {{ $minuten === null ? 0 : $minuten % 60 }},
               get wert() { return (this.std === '' || this.std === null) && ! this.min ? '' : Math.round(((Number(this.std) || 0) + this.min / 60) * 100) / 100 } }">
    <input type="number" min="0" max="999" step="1" inputmode="numeric" x-model.number="std" @if ($minuten !== null) value="{{ intdiv($minuten, 60) }}" @endif @if ($id) id="{{ $id }}" @endif
           class="feld {{ $klein ? 'min-h-[38px] w-16 py-1' : 'w-20' }} text-right" placeholder="0" aria-label="Stunden">
    <span class="text-xs text-beton">Std.</span>
    <select x-model.number="min" class="feld {{ $klein ? 'min-h-[38px] w-[4.75rem] py-1' : 'w-24' }}" aria-label="Minuten">
        @php $schritte = range(0, 55, 5); if ($minuten !== null && ! in_array($minuten % 60, $schritte, true)) { $schritte[] = $minuten % 60; sort($schritte); } @endphp
        @foreach ($schritte as $m)<option value="{{ $m }}" @selected($minuten !== null && $minuten % 60 === $m)>{{ $m }}</option>@endforeach
    </select>
    <span class="text-xs text-beton">Min.</span>
    <input type="hidden" name="{{ $name }}" :value="wert" value="{{ $wert }}">
</div>
