@props(['name' => 'tage_vorher', 'wert' => 14, 'id' => null, 'klein' => false])
{{-- Zeitpunkt vor dem Turnier in Tagen, Wochen oder Monaten (Monat = 30 Tage); gesendet werden Tage. --}}
@php
    $t = (int) $wert;
    [$zahl, $einheit] = $t !== 0 && $t % 30 === 0 ? [$t / 30, 30] : ($t !== 0 && $t % 7 === 0 ? [$t / 7, 7] : [$t, 1]);
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }} x-data="{ zahl: {{ $zahl }}, einheit: {{ $einheit }}, get tage() { return Math.round((Number(this.zahl) || 0) * this.einheit) } }">
    <input type="number" step="1" inputmode="numeric" x-model.number="zahl" value="{{ $zahl }}" @if ($id) id="{{ $id }}" @endif
           class="feld {{ $klein ? 'min-h-[38px] w-16 py-1' : 'w-20' }} text-right" aria-label="Anzahl" required>
    <select x-model.number="einheit" class="feld {{ $klein ? 'min-h-[38px] w-[6.5rem] py-1' : 'w-32' }}" aria-label="Einheit">
        @foreach ([1 => 'Tage', 7 => 'Wochen', 30 => 'Monate'] as $k => $l)<option value="{{ $k }}" @selected($einheit === $k)>{{ $l }}</option>@endforeach
    </select>
    <span class="whitespace-nowrap text-xs text-beton">vorher</span>
    <input type="hidden" name="{{ $name }}" :value="tage" value="{{ $t }}">
</div>
