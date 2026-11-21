<form method="POST" action="{{ route('admin.hallen.speichern', $h) }}" class="mt-3 space-y-2">@csrf
    <input name="name" required class="feld" value="{{ $h?->name }}" placeholder="Name der Halle">
    <div class="grid grid-cols-2 gap-2"><input name="ort" class="feld" value="{{ $h?->ort }}" placeholder="Ort"><input name="adresse" class="feld" value="{{ $h?->adresse }}" placeholder="Adresse"></div>
    <div class="grid grid-cols-2 gap-2"><input name="hallenwart" class="feld" value="{{ $h?->hallenwart }}" placeholder="Hallenwart"><input name="telefon" class="feld" value="{{ $h?->telefon }}" placeholder="Telefon"></div>
    <textarea name="notizen" class="feld" rows="2" placeholder="Schlüssel, Garderoben, Besonderheiten" aria-label="Schlüssel, Garderoben, Besonderheiten">{{ $h?->notizen }}</textarea>
    <button class="knopf-rot knopf-klein">Speichern</button>
</form>
