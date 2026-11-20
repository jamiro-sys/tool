{{-- Formular fuer Material ($m = null fuer neues Material) --}}
<form method="POST" action="{{ route('material.speichern', $m) }}" enctype="multipart/form-data" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
    <input name="name" required maxlength="150" class="feld sm:col-span-2" value="{{ $m?->name }}" placeholder="Bezeichnung, z. B. Beamer" aria-label="Bezeichnung">
    <select name="ressort_id" class="feld" aria-label="Ressort"><option value="">Ressort wählen</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected(($m?->ressort_id ?? request('ressort')) == $r->id)>{{ $r->name }}</option>@endforeach</select>
    <input name="zweck" maxlength="255" class="feld" value="{{ $m?->zweck }}" placeholder="Wofür, z. B. Rangliste im Turnierbüro" aria-label="Wofür">
    <div class="grid grid-cols-2 gap-2">
        <input name="menge" type="number" step="any" min="0" inputmode="decimal" class="feld" value="{{ $m?->menge ?? 1 }}" aria-label="Menge">
        <input name="einheit" maxlength="40" class="feld" value="{{ $m?->einheit }}" placeholder="Einheit" aria-label="Einheit">
    </div>
    @if ($gruppen->isNotEmpty())
        <select name="gruppe" class="feld" aria-label="Gruppe"><option value="">Gruppe</option>@foreach ($gruppen as $g)<option @selected($m?->gruppe === $g)>{{ $g }}</option>@endforeach</select>
    @else
        <input name="gruppe" maxlength="120" class="feld" value="{{ $m?->gruppe }}" placeholder="Gruppe" aria-label="Gruppe">
    @endif
    @if ($standorte->isNotEmpty())
        <select name="standort" class="feld" aria-label="Standort"><option value="">Standort</option>@foreach ($standorte as $o)<option @selected($m?->standort === $o)>{{ $o }}</option>@endforeach</select>
    @else
        <input name="standort" maxlength="120" class="feld" value="{{ $m?->standort }}" placeholder="Standort" aria-label="Standort">
    @endif
    <select name="zustand" class="feld" aria-label="Zustand">@foreach (\App\Models\Material::ZUSTAND as $k => $l)<option value="{{ $k }}" @selected(($m?->zustand ?? 'gut') === $k)>{{ $l }}</option>@endforeach</select>
    <select name="eigentum" class="feld" aria-label="Eigentum">@foreach (\App\Models\Material::EIGENTUM as $k => $l)<option value="{{ $k }}" @selected(($m?->eigentum ?? 'verein') === $k)>{{ $l }}</option>@endforeach</select>
    <input name="eigentuemer" maxlength="150" class="feld sm:col-span-2" value="{{ $m?->eigentuemer }}" placeholder="Bei Halle oder geliehen: wem es gehört" aria-label="Wem es gehört">
    <textarea name="notizen" rows="2" class="feld sm:col-span-2" placeholder="Notizen, z. B. Aufbau, Mängel" aria-label="Notizen">{{ $m?->notizen }}</textarea>
    <label class="sm:col-span-2"><span class="feld-label">Foto (optional)</span><input type="file" name="foto" accept="image/*" class="feld py-2.5 text-xs"></label>
    <div class="flex justify-end sm:col-span-2"><button class="knopf-rot knopf-klein">Speichern</button></div>
</form>
@if ($m)
    <form method="POST" action="{{ route('material.loeschen', $m) }}" class="mt-2" onsubmit="return confirm('Material entfernen? Es verschwindet auch aus allen Einsatzlisten.')">@csrf @method('DELETE')<button class="knopf-leise knopf-klein text-beton hover:text-rot"><x-icon name="loeschen" class="h-4 w-4" /> Entfernen</button></form>
@endif
