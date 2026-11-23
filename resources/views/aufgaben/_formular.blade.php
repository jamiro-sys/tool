{{-- Felder fuer neue oder bestehende Aufgabe: das Wichtigste sichtbar, der Rest unter "Weitere Angaben" --}}
@php
    $a = $aufgabe ?? null;
    $checklisteText = old('checkliste_text', collect($a?->checkliste ?? [])->pluck('text')->implode("\n"));
    $mehrOffen = $errors->hasAny(['beschreibung', 'anleitung', 'checkliste_text', 'aufwand_geplant', 'aufwand_effektiv', 'begleitung_id'])
        || ($a && ($a->beschreibung || $a->anleitung || $a->checkliste || $a->begleitung_id || $a->aufwand_geplant || $a->prioritaet === 'hoch'));
    $wannAusgabe = $a?->ausgabe ?? ($ausgabe ?? null);
@endphp
<div class="space-y-4">
    <div>
        <label class="feld-label" for="titel">Was ist zu tun?</label>
        <input id="titel" name="titel" class="feld" required maxlength="200" value="{{ old('titel', $a?->titel) }}" placeholder="z.B. Pokale bestellen">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="feld-label" for="ressort_id">Ressort</label>
            <select id="ressort_id" name="ressort_id" class="feld" required>
                @foreach ($ressorts as $r)
                    <option value="{{ $r->id }}" @selected(old('ressort_id', $a?->ressort_id ?? ($vorRessort ?? request('ressort'))) == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="feld-label" for="verantwortlich_id">Wer macht es?</label>
            <select id="verantwortlich_id" name="verantwortlich_id" class="feld">
                <option value="">Noch offen</option>
                @foreach ($personen as $p)
                    <option value="{{ $p->id }}" @selected(old('verantwortlich_id', $a?->verantwortlich_id) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="feld-label" for="wann">Wann?</label>
        <x-wann id="wann" aufgabe zeile
            :tage="old('wann_gesendet') ? old('tage_vorher') : ($a ? $a->tage_vorher : 14)"
            :bezug="old('bezug', $a?->bezug ?? 'beginn')"
            :datum="old('faellig_am', $a?->faellig_am)"
            :von="$wannAusgabe?->datum_von" :bis="$wannAusgabe?->datum_bis" />
    </div>
    @if ($a ? auth()->user()->can('ressort-verwalten', $a->ressort) : auth()->user()->can('vorlagen-verwalten'))
        @include('aufgaben._wie_oft', ['a' => $a, 'schmal' => true])
    @endif

    <div x-data="{ mehr: {{ $mehrOffen ? 'true' : 'false' }} }" class="border-t border-nacht/10 pt-3">
        <button type="button" class="flex w-full items-center justify-between py-1 text-left" x-on:click="mehr = !mehr" :aria-expanded="mehr">
            <span class="label text-nacht">Weitere Angaben</span>
            <span class="flex items-center gap-2 text-xs text-beton"><span x-show="!mehr">Beschreibung, Checkliste, Aufwand ...</span><x-icon name="pfeil" class="h-4 w-4 transition-transform" ::class="mehr ? 'rotate-90' : ''" /></span>
        </button>
        <div x-show="mehr" x-collapse {{ $mehrOffen ? '' : 'x-cloak' }} class="space-y-4 pt-3">
            <label class="flex cursor-pointer items-center gap-3 border border-nacht/20 bg-white px-3 py-2.5 has-[:checked]:border-rot has-[:checked]:bg-rot/5">
                <input type="hidden" name="prioritaet" value="{{ $a?->prioritaet === 'tief' ? 'tief' : 'normal' }}">
                <input type="checkbox" name="prioritaet" value="hoch" class="h-4 w-4" @checked(old('prioritaet', $a?->prioritaet) === 'hoch')>
                <span class="text-sm font-bold">Wichtig</span><span class="text-xs text-beton">wird in Listen rot markiert</span>
            </label>
            <div>
                <label class="feld-label" for="beschreibung">Beschreibung</label>
                <textarea id="beschreibung" name="beschreibung" class="feld" rows="3" placeholder="Worum geht es?">{{ old('beschreibung', $a?->beschreibung) }}</textarea>
            </div>
            <div>
                <label class="feld-label" for="checkliste_text">Checkliste <span class="normal-case tracking-normal font-normal text-beton">(eine Zeile pro Punkt)</span></label>
                <textarea id="checkliste_text" name="checkliste_text" class="feld" rows="3" placeholder="Offerte einholen&#10;Bestellen&#10;Lieferung prüfen">{{ $checklisteText }}</textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="feld-label" for="aufwand_geplant">Aufwand geplant</label>
                    <x-aufwand name="aufwand_geplant" id="aufwand_geplant" :wert="old('aufwand_geplant', $a?->aufwand_geplant)" />
                </div>
                @if ($a)
                    <div>
                        <label class="feld-label" for="aufwand_effektiv">Aufwand effektiv</label>
                        <x-aufwand name="aufwand_effektiv" id="aufwand_effektiv" :wert="old('aufwand_effektiv', $a->aufwand_effektiv)" />
                    </div>
                @endif
                <div>
                    <label class="feld-label" for="begleitung_id">Begleitung (Einarbeitung)</label>
                    <select id="begleitung_id" name="begleitung_id" class="feld">
                        <option value="">Keine</option>
                        @foreach ($personen as $p)
                            <option value="{{ $p->id }}" @selected(old('begleitung_id', $a?->begleitung_id) == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="feld-label" for="anleitung">Anleitung: So geht's</label>
                <textarea id="anleitung" name="anleitung" class="feld" rows="4" placeholder="Schritte, Kontakte, Tipps für die nächste Person">{{ old('anleitung', $a?->anleitung) }}</textarea>
            </div>
        </div>
    </div>

    @if ($a && $a->vorlage?->aktiv && auth()->user()->can('ressort-verwalten', $a->ressort))
        <label class="flex cursor-pointer items-start gap-3 border border-nacht/20 bg-white px-3 py-2.5 has-[:checked]:border-rot has-[:checked]:bg-rot/5">
            <input type="hidden" name="jahresplan_mit" value="0">
            <input type="checkbox" name="jahresplan_mit" value="1" class="mt-1 h-4 w-4" @checked(old('jahresplan_mit', '1') === '1')>
            <span><span class="block text-sm font-bold">Auch im Jahresplan übernehmen</span>
                <span class="block text-xs text-beton">Gilt dann auch für alle künftigen Turniere. Ohne Häkchen gilt die Änderung nur hier.</span></span>
        </label>
    @endif
</div>
