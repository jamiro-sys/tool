{{-- Felder fuer neue oder bestehende Aufgabe --}}
@php $a = $aufgabe ?? null; @endphp
<div class="space-y-4">
    <div>
        <label class="feld-label" for="titel">Titel</label>
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
            <label class="feld-label" for="faellig_am">Fällig am</label>
            <input id="faellig_am" type="date" name="faellig_am" class="feld" value="{{ old('faellig_am', $a?->faellig_am?->format('Y-m-d')) }}">
        </div>
        <div>
            <label class="feld-label" for="verantwortlich_id">Verantwortlich</label>
            <select id="verantwortlich_id" name="verantwortlich_id" class="feld">
                <option value="">Noch offen</option>
                @foreach ($personen as $p)
                    <option value="{{ $p->id }}" @selected(old('verantwortlich_id', $a?->verantwortlich_id) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="feld-label" for="begleitung_id">Begleitung (Einarbeitung)</label>
            <select id="begleitung_id" name="begleitung_id" class="feld">
                <option value="">Keine</option>
                @foreach ($personen as $p)
                    <option value="{{ $p->id }}" @selected(old('begleitung_id', $a?->begleitung_id) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="feld-label" for="prioritaet">Priorität</label>
            <select id="prioritaet" name="prioritaet" class="feld">
                @foreach (\App\Models\Vorlage::PRIORITAETEN as $k => $l)
                    <option value="{{ $k }}" @selected(old('prioritaet', $a?->prioritaet ?? 'normal') === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="feld-label" for="aufwand_geplant">Aufwand geplant</label>
            <x-aufwand name="aufwand_geplant" id="aufwand_geplant" :wert="old('aufwand_geplant', $a?->aufwand_geplant)" />
        </div>
    </div>
    @if ($a ? auth()->user()->can('ressort-verwalten', $a->ressort) : auth()->user()->can('vorlagen-verwalten'))
        @include('aufgaben._wie_oft', ['a' => $a])
    @endif
    <div>
        <label class="feld-label" for="beschreibung">Beschreibung</label>
        <textarea id="beschreibung" name="beschreibung" class="feld" placeholder="Worum geht es?">{{ old('beschreibung', $a?->beschreibung) }}</textarea>
    </div>
    @if ($mitDetails ?? false)
        <div>
            <label class="feld-label" for="anleitung">Anleitung: So geht's</label>
            <textarea id="anleitung" name="anleitung" class="feld" rows="6">{{ old('anleitung', $a?->anleitung) }}</textarea>
        </div>
        <div>
            <label class="feld-label" for="checkliste_text">Checkliste (eine Zeile pro Punkt)</label>
            <textarea id="checkliste_text" name="checkliste_text" class="feld" rows="4">{{ old('checkliste_text', collect($a?->checkliste ?? [])->pluck('text')->implode("\n")) }}</textarea>
        </div>
    @endif
</div>
