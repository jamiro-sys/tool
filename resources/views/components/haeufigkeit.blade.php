@props(['wert' => 'turnier', 'klein' => false, 'name' => 'haeufigkeit'])
{{-- Jedes Jahr: bei jedem (gewaehlten) Turnier oder einmal pro Saison. Der Slot (Turnierarten) erscheint nur bei "bei jedem Turnier". --}}
@php $wert = $wert === 'saison' ? 'saison' : 'turnier'; @endphp
<div {{ $attributes->merge(['class' => 'space-y-2']) }} x-data="{ h: @js($wert) }">
    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Wie oft im Jahr">
        @foreach (\App\Models\Vorlage::HAEUFIGKEIT as $k => $l)
            <label class="flex cursor-pointer items-center gap-2 border border-nacht/20 bg-white {{ $klein ? 'min-h-[44px] px-3 text-sm' : 'px-3 py-2.5 text-sm font-bold' }} has-[:checked]:border-rot has-[:checked]:bg-rot/5">
                <input type="radio" name="{{ $name }}" value="{{ $k }}" x-model="h" @checked($wert === $k)> {{ $l }}
            </label>
        @endforeach
    </div>
    <p x-show="h === 'saison'" x-cloak class="text-xs text-beton">Kommt nur einmal pro Saison, im zuerst angelegten Turnier (z. B. Dezember), und erscheint im anderen Turnier der Saison mit.</p>
    @if (trim($slot))
        <div x-show="h === 'turnier'" @if ($wert === 'saison') x-cloak @endif>{{ $slot }}</div>
    @endif
</div>
