{{-- Formular fuer einen Einkaufsposten ($e = null fuer neuen Posten) --}}
<form method="POST" action="{{ route('festwirtschaft.posten', [$ausgabe, $e]) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
    @if ($produkte->isNotEmpty())
        <select name="produkt_id" class="feld" aria-label="Produkt"><option value="">Produkt (optional)</option>@foreach ($produkte as $p)<option value="{{ $p->id }}" @selected($e?->produkt_id === $p->id)>{{ $p->name }}</option>@endforeach</select>
    @endif
    <input name="bezeichnung" maxlength="150" class="feld" value="{{ $e?->bezeichnung }}" placeholder="Bezeichnung, z. B. Brötli" aria-label="Bezeichnung">
    <input name="einheit" maxlength="40" class="feld" value="{{ $e?->einheit }}" placeholder="Einheit, z. B. Stück, Kiste" aria-label="Einheit">
    <input name="lieferant" maxlength="150" class="feld" value="{{ $e?->lieferant }}" placeholder="Lieferant" aria-label="Lieferant">
    <input name="bestellt" type="number" step="any" min="0" inputmode="decimal" class="feld" value="{{ $e?->bestellt }}" placeholder="Menge bestellt" aria-label="Menge bestellt">
    <input name="verbraucht" type="number" step="any" min="0" inputmode="decimal" class="feld" value="{{ $e?->verbraucht }}" placeholder="Verbraucht (nach dem Turnier)" aria-label="Verbraucht">
    <input name="notiz" maxlength="255" class="feld sm:col-span-2" value="{{ $e?->notiz }}" placeholder="Notiz" aria-label="Notiz">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="eingekauft" value="1" @checked($e?->eingekauft)> Eingekauft oder bestellt</label>
    <div class="flex justify-end"><button class="knopf-rot knopf-klein">Speichern</button></div>
</form>
