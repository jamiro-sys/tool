@props(['wert' => 'turnier', 'klein' => false, 'name' => 'haeufigkeit', 'einmalig' => null])
{{--
    Wie oft kommt die Aufgabe? Eine Wahl mit drei klaren Moeglichkeiten:
    - Nur dieses Mal (nur wenn $einmalig gesetzt: ['name' => Feldname, 'an' => Wert wenn wiederkehrend, 'aus' => Wert wenn einmalig])
    - Bei jedem Turnier (Slot = Wahl der Turnierarten)
    - Einmal pro Saison
    Gesendet wird weiterhin $name = turnier|saison und, falls vorhanden, das Einmalig-Feld.
--}}
@php
    $wert = in_array($wert, ['einmalig', 'turnier', 'saison'], true) ? $wert : 'turnier';
    if (! $einmalig && $wert === 'einmalig') { $wert = 'turnier'; }
    $wahlen = \App\Models\Vorlage::WIE_OFT;
    if (! $einmalig) { unset($wahlen['einmalig']); }
    $gruppe = 'wie_oft_'.\Illuminate\Support\Str::random(6);
@endphp
<div {{ $attributes->merge(['class' => 'space-y-2']) }} x-data="{ oft: @js($wert) }" data-wie-oft>
    <input type="hidden" name="{{ $name }}" :value="oft === 'saison' ? 'saison' : 'turnier'" value="{{ $wert === 'saison' ? 'saison' : 'turnier' }}">
    @if ($einmalig)
        <input type="hidden" name="{{ $einmalig['name'] }}" :value="oft === 'einmalig' ? @js($einmalig['aus']) : @js($einmalig['an'])" value="{{ $wert === 'einmalig' ? $einmalig['aus'] : $einmalig['an'] }}">
    @endif
    <div class="grid gap-2 {{ $klein ? '' : (count($wahlen) === 3 ? 'md:grid-cols-3' : 'sm:grid-cols-2') }}" role="radiogroup" aria-label="Wie oft">
        @foreach ($wahlen as $k => [$titel, $text])
            <label class="flex cursor-pointer items-start gap-2.5 border border-nacht/20 bg-white px-3 {{ $klein ? 'py-2' : 'py-2.5' }} has-[:checked]:border-rot has-[:checked]:bg-rot/5">
                <input type="radio" name="{{ $gruppe }}" value="{{ $k }}" class="mt-1" x-model="oft" @checked($wert === $k)>
                <span><span class="block text-sm font-bold">{{ $titel }}</span><span class="block text-xs text-beton">{{ $text }}</span></span>
            </label>
        @endforeach
    </div>
    @if (trim($slot))
        <div x-show="oft === 'turnier'" @if ($wert !== 'turnier') x-cloak @endif>{{ $slot }}</div>
    @endif
</div>
