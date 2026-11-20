@props(['tage' => 14, 'bezug' => 'beginn', 'datum' => null, 'aufgabe' => false, 'von' => null, 'bis' => null, 'id' => null, 'klein' => false, 'zeile' => false])
{{--
    "Wann" als Abstand zum Turnier: vor Turnierbeginn, am ersten/letzten Turniertag, nach Turnierende.
    Bei Aufgaben zusaetzlich fixes Datum oder ohne Termin. Sendet tage_vorher, bezug (und faellig_am bei fixem Datum).
--}}
@php
    $tage = $tage === null || $tage === '' ? null : (int) $tage;
    $bezug = $bezug === 'ende' ? 'ende' : 'beginn';
    $modus = match (true) {
        $tage === null => $aufgabe ? ($datum ? 'fix' : 'ohne') : 'vor',
        $tage > 0 => 'vor',
        $tage === 0 => $bezug === 'ende' ? 'letzter' : 'erster',
        default => 'nach',
    };
    $betrag = $tage === null ? 14 : abs($tage);
    [$zahl, $einheit] = $betrag !== 0 && $betrag % 30 === 0 ? [$betrag / 30, 30] : ($betrag !== 0 && $betrag % 7 === 0 ? [$betrag / 7, 7] : [$betrag ?: 1, 1]);
    $datumWert = $datum instanceof \DateTimeInterface ? $datum->format('Y-m-d') : $datum;
    $optionen = ['vor' => 'vor Turnierbeginn', 'erster' => 'am ersten Turniertag', 'letzter' => 'am letzten Turniertag', 'nach' => 'nach Turnierende'] + ($aufgabe ? ['fix' => 'fixes Datum', 'ohne' => 'ohne Termin'] : []);
@endphp
<div {{ $attributes->merge(['class' => $zeile ? 'flex flex-wrap items-center gap-1.5' : 'space-y-2']) }}
     x-data="{
        modus: @js($modus), zahl: {{ $zahl }}, einheit: {{ $einheit }},
        von: @js($von instanceof \DateTimeInterface ? $von->format('Y-m-d') : $von), bis: @js($bis instanceof \DateTimeInterface ? $bis->format('Y-m-d') : $bis),
        get betrag() { return Math.max(0, Math.round((Number(this.zahl) || 0) * this.einheit)) },
        get tage() { return { vor: this.betrag, erster: 0, letzter: 0, nach: -this.betrag }[this.modus] ?? '' },
        get bezug() { return ['letzter', 'nach'].includes(this.modus) ? 'ende' : 'beginn' },
        get vorschau() {
            if (!this.von || this.tage === '') return '';
            const basis = new Date(((this.bezug === 'ende' && this.bis) ? this.bis : this.von) + 'T12:00:00');
            basis.setDate(basis.getDate() - this.tage);
            return basis.toLocaleDateString('de-CH', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        },
     }">
    <input type="hidden" name="wann_gesendet" value="1">
    <input type="hidden" name="tage_vorher" :value="tage" value="{{ $tage }}">
    <input type="hidden" name="bezug" :value="bezug" value="{{ $bezug }}">
 <select x-model="modus" class="feld {{ $klein ? 'min-h-[38px] py-1' : '' }} {{ $zeile ? 'w-auto' : '' }}" @if ($id) id="{{ $id }}" @endif aria-label="Wann">
        @foreach ($optionen as $k => $l)<option value="{{ $k }}" @selected($modus === $k)>{{ $l }}</option>@endforeach
    </select>
    <div x-show="modus === 'vor' || modus === 'nach'" class="flex items-center gap-1.5">
        <input type="number" min="0" step="1" inputmode="numeric" x-model.number="zahl" value="{{ $zahl }}" class="feld {{ $klein ? 'min-h-[38px] py-1' : '' }} w-16 shrink-0 text-right" aria-label="Anzahl">
        <select x-model.number="einheit" class="feld {{ $klein ? 'min-h-[38px] py-1' : '' }} {{ $zeile ? 'w-32' : 'min-w-0 flex-1' }}" aria-label="Einheit">
            @foreach ([1 => 'Tage', 7 => 'Wochen', 30 => 'Monate'] as $k => $l)<option value="{{ $k }}" @selected($einheit === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    @if ($aufgabe)
        <div x-show="modus === 'fix'" x-cloak><input type="date" name="faellig_am" class="feld" value="{{ $datumWert }}" :disabled="modus !== 'fix'" aria-label="Fixes Datum"></div>
    @endif
    <p x-show="vorschau" x-cloak class="text-xs font-bold text-nacht {{ $zeile ? 'w-full' : '' }}">= <span x-text="vorschau"></span></p>
</div>
