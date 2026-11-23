{{-- Wie oft? Nur dieses Mal, bei jedem Turnier (mit Wahl der Turnierarten) oder einmal pro Saison. Erwartet $a (bestehende Aufgabe oder null), optional $ausgabe --}}
@php
    $turnierarten = \App\Models\Turnier::orderBy('name')->get();
    $hier = $a?->ausgabe?->turnier_id ?? (isset($ausgabe) && $ausgabe ? $ausgabe->turnier_id : null);
    $vorlage = $a?->vorlage;
    $jaehrlich = old('wiederkehrend', $vorlage && $vorlage->aktiv ? 'jedes_jahr' : 'einmalig') === 'jedes_jahr';
    $start = ! $jaehrlich ? 'einmalig' : old('haeufigkeit', $vorlage?->haeufigkeit ?? ($a?->saison_aufgabe ? 'saison' : 'turnier'));
    $gewaehlt = old('turniere', $vorlage ? $vorlage->turniere->pluck('id')->all() : ($hier ? [$hier] : []));
@endphp
<fieldset>
    <legend class="feld-label">Wie oft?</legend>
    <x-haeufigkeit :wert="$start" :einmalig="['name' => 'wiederkehrend', 'an' => 'jedes_jahr', 'aus' => 'einmalig']" :klein="$schmal ?? false">
        @if ($turnierarten->count() > 1)
            <div class="border-l-4 border-rot bg-kreide/60 p-3">
                <p class="feld-label">Bei welchen Turnieren?</p>
                <x-turnierarten :turniere="$turnierarten" :gewaehlt="$gewaehlt" klein />
            </div>
        @endif
    </x-haeufigkeit>
    @error('turniere')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror
    @if ($vorlage && $vorlage->aktiv)
        <p class="mt-2 text-xs text-beton">Mit «Nur dieses Mal» kommt die Aufgabe bei neuen Turnieren nicht mehr. Der Verlauf bleibt erhalten.</p>
    @endif
</fieldset>
