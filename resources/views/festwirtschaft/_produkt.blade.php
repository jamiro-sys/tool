{{-- Formular fuer ein Produkt ($p = null fuer neues Produkt) --}}
<form method="POST" action="{{ route('festwirtschaft.produkt', $p) }}" class="mt-3 space-y-2">@csrf
    <input name="name" required maxlength="120" class="feld" value="{{ $p?->name }}" placeholder="Name, z. B. Bratwurst" aria-label="Name">
    <div class="grid grid-cols-2 gap-2">
        <input name="preis" type="number" step="0.05" min="0" inputmode="decimal" class="feld" value="{{ $p?->preis }}" placeholder="Preis CHF" aria-label="Preis CHF">
        <input name="einheit" maxlength="40" class="feld" value="{{ $p?->einheit }}" placeholder="Einheit, z. B. 3 dl" aria-label="Einheit">
    </div>
    @if ($kategorien->isNotEmpty())
        <select name="kategorie" class="feld" aria-label="Kategorie"><option value="">Kategorie</option>@foreach ($kategorien as $k)<option @selected($p?->kategorie === $k)>{{ $k }}</option>@endforeach</select>
    @endif
    @if ($allergene->isNotEmpty())
        <fieldset><legend class="label mb-1">Allergene</legend>
            <div class="flex flex-wrap gap-x-4 gap-y-1">@foreach ($allergene as $a)<label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="allergene[]" value="{{ $a }}" @checked(in_array($a, $p?->allergene ?? [], true))> {{ $a }}</label>@endforeach</div>
        </fieldset>
    @endif
    <textarea name="notizen" rows="2" class="feld" placeholder="Notizen, z. B. Zubereitung oder Lagerung" aria-label="Notizen">{{ $p?->notizen }}</textarea>
    @if ($p)<label class="flex items-center gap-2 text-sm"><input type="hidden" name="aktiv" value="0"><input type="checkbox" name="aktiv" value="1" @checked($p->aktiv)> Im Angebot</label>@endif
    <div class="flex items-center justify-between">
        <button class="knopf-rot knopf-klein">Speichern</button>
    </div>
</form>
@if ($p)
    <form method="POST" action="{{ route('festwirtschaft.produkt.loeschen', $p) }}" class="mt-2" onsubmit="return confirm('Produkt entfernen?')">@csrf @method('DELETE')<button class="knopf-leise knopf-klein text-beton hover:text-rot"><x-icon name="loeschen" class="h-4 w-4" /> Entfernen</button></form>
@endif
