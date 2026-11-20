<x-app :titel="$vorlage->exists ? 'Vorlage bearbeiten' : 'Neue Vorlage'">
    <x-seitenkopf :titel="$vorlage->exists ? $vorlage->titel : 'Neue Vorlage'" ueber="Vorlage" :zurueck="$vorlage->exists ? route('vorlagen.show', $vorlage) : route('vorlagen.index')" />

    @if ($ressorts->isEmpty())
        <x-ohne-ressort class="mb-6" />
    @endif
    <form method="POST" action="{{ $vorlage->exists ? route('vorlagen.update', $vorlage) : route('vorlagen.store') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($vorlage->exists) @method('PUT') @endif
        <div class="space-y-4 lg:col-span-2">
            <div class="karte space-y-4 p-4">
                <div><label class="feld-label" for="titel">Titel</label><input id="titel" name="titel" required class="feld" value="{{ old('titel', $vorlage->titel) }}" placeholder="z.B. Schiedsrichter aufbieten"></div>
                <div><label class="feld-label" for="beschreibung">Kurzbeschreibung</label><textarea id="beschreibung" name="beschreibung" class="feld" rows="3">{{ old('beschreibung', $vorlage->beschreibung) }}</textarea></div>
                <div>
                    <label class="feld-label" for="anleitung">Anleitung: So geht's</label>
                    <p class="mb-2 text-xs text-beton">Schreib so, dass jemand Neues die Aufgabe ohne Rückfrage erledigen kann: Wen kontaktieren? Wo liegen Unterlagen? Worauf achten?</p>
                    <textarea id="anleitung" name="anleitung" class="feld" rows="10">{{ old('anleitung', $vorlage->anleitung) }}</textarea>
                </div>
                <div><label class="feld-label" for="checkliste_text">Checkliste (eine Zeile pro Punkt)</label><textarea id="checkliste_text" name="checkliste_text" class="feld" rows="5">{{ old('checkliste_text', collect($vorlage->checkliste ?? [])->implode("\n")) }}</textarea></div>
            </div>
        </div>
        <div class="space-y-4">
            <div class="karte space-y-4 p-4">
                <div><label class="feld-label" for="ressort_id">Ressort</label>
                    <select id="ressort_id" name="ressort_id" class="feld" required>@foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected(old('ressort_id', $vorlage->ressort_id) == $r->id)>{{ $r->name }}</option>@endforeach</select></div>
                <div><label class="feld-label" for="turnier_id">Gilt für</label>
                    <select id="turnier_id" name="turnier_id" class="feld"><option value="">Alle Turnierarten</option>@foreach ($turniere as $t)<option value="{{ $t->id }}" @selected(old('turnier_id', $vorlage->turnier_id) == $t->id)>Nur {{ $t->name }}</option>@endforeach</select></div>
                <div x-data="{ t: {{ (int) old('tage_vorher', $vorlage->tage_vorher) }} }">
                    <label class="feld-label" for="tage_vorher">Fällig (Tage vor dem Turnier)</label>
                    <input id="tage_vorher" type="number" name="tage_vorher" required class="feld" x-model.number="t" min="-120" max="400">
                    <p class="mt-1 text-xs text-beton" x-text="t > 0 ? (t % 7 === 0 && t >= 14 ? (t/7) + ' Wochen vorher' : t + ' Tage vorher') : (t === 0 ? 'am Turniertag' : Math.abs(t) + ' Tage nach dem Turnier')"></p>
                </div>
                <div><label class="feld-label" for="aufwand_std">Aufwand (Std.)</label><input id="aufwand_std" type="number" step="0.5" min="0" name="aufwand_std" class="feld" value="{{ old('aufwand_std', $vorlage->aufwand_std) }}"></div>
                <div><label class="feld-label" for="prioritaet">Priorität</label>
                    <select id="prioritaet" name="prioritaet" class="feld">@foreach (\App\Models\Vorlage::PRIORITAETEN as $k => $l)<option value="{{ $k }}" @selected(old('prioritaet', $vorlage->prioritaet) === $k)>{{ $l }}</option>@endforeach</select></div>
                <div><label class="feld-label" for="aktiv_seit">Neu seit Saison</label><input id="aktiv_seit" name="aktiv_seit" class="feld" value="{{ old('aktiv_seit', $vorlage->aktiv_seit) }}" placeholder="2026/27"></div>
                @if ($vorlage->exists)
                    <div><label class="feld-label" for="notiz">Warum die Änderung? (Verlauf)</label><input id="notiz" name="notiz" class="feld" placeholder="optional"></div>
                @endif
            </div>
            <button class="knopf-rot w-full">Speichern</button>
        </div>
    </form>
</x-app>
