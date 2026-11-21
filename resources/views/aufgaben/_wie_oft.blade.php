{{-- Wie oft? Nur dieses Turnier oder jedes Jahr (mit Wahl der Turnierarten). Erwartet $a (bestehende Aufgabe oder null), optional $ausgabe --}}
@php
    $turnierarten = \App\Models\Turnier::orderBy('name')->get();
    $hier = $a?->ausgabe?->turnier_id ?? (isset($ausgabe) && $ausgabe ? $ausgabe->turnier_id : null);
    $vorlage = $a?->vorlage;
    $start = old('wiederkehrend', $vorlage && $vorlage->aktiv ? 'jedes_jahr' : 'einmalig');
    $gewaehlt = old('turniere', $vorlage ? $vorlage->turniere->pluck('id')->all() : ($hier ? [$hier] : []));
@endphp
<fieldset x-data="{ oft: @js($start) }">
    <legend class="feld-label">Wie oft?</legend>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach (['einmalig' => ['Nur dieses Turnier', 'Kommt nur jetzt vor, z. B. Musikboxen ausleihen'], 'jedes_jahr' => ['Jedes Jahr', 'Kommt in den Jahresplan und bei jedem neuen Turnier wieder']] as $k => [$t, $txt])
            <label class="flex cursor-pointer items-start gap-3 border border-nacht/20 bg-white px-3 py-2.5 has-[:checked]:border-rot has-[:checked]:bg-rot/5">
                <input type="radio" name="wiederkehrend" value="{{ $k }}" class="mt-1" x-model="oft" @checked($start === $k)>
                <span><span class="block text-sm font-bold">{{ $t }}</span>@unless ($schmal ?? false)<span class="block text-xs text-beton">{{ $txt }}</span>@endunless</span>
            </label>
        @endforeach
    </div>
    @if ($turnierarten->count() > 1)
        <div x-show="oft === 'jedes_jahr'" x-collapse x-cloak class="mt-3 border-l-4 border-rot bg-kreide/60 p-3">
            <p class="feld-label">Bei welchen Turnieren?</p>
            <x-turnierarten :turniere="$turnierarten" :gewaehlt="$gewaehlt" klein />
            <p class="mt-1.5 text-xs text-beton">Eines oder mehrere anhaken.</p>
            @error('turniere')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror
        </div>
    @endif
    @if ($vorlage && $vorlage->aktiv)
        <p x-show="oft === 'einmalig'" x-cloak class="mt-2 text-xs text-beton">Die Aufgabe kommt dann bei neuen Turnieren nicht mehr. Der Verlauf im Jahresplan bleibt erhalten.</p>
    @endif
</fieldset>
