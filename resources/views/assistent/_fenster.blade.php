{{-- Fenster "Mit Claude erfassen". Erwartet $art ('jahresplan' | 'ressort'), optional $ressort --}}
@php $bereit = app(\App\Services\AufgabenAssistent::class)->bereit(); @endphp
<x-modal name="assistent" titel="Mit Claude erfassen" :auf="$errors->has('assistent') || ($errors->any() && old('art') === $art)">
    @if (! $bereit)
        <p class="text-sm">Der Claude-Assistent ist noch nicht eingerichtet.</p>
        @can('admin')<a href="{{ route('admin.system') }}#claude" class="knopf-rot mt-4">Schlüssel unter Admin > System eintragen</a>@else<p class="mt-2 text-sm text-beton">Bitte bei der Admin-Person melden.</p>@endcan
    @else
        <form method="POST" action="{{ route('assistent.vorschlag') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ laeuft: false, namen: [] }" x-on:submit="laeuft = true">@csrf
            <input type="hidden" name="art" value="{{ $art }}">
            @if (! empty($ressort))<input type="hidden" name="ressort_id" value="{{ $ressort->id }}">@endif
            <p class="text-sm text-beton">
                {{ $art === 'jahresplan' ? 'Füge Notizen, eine Liste oder ein altes Protokoll ein. Claude macht daraus Aufgaben für den Jahresplan, mit Ressort und Zeitpunkt.' : 'Füge Notizen oder eine Liste ein. Claude macht daraus Aufgaben für '.$ressort->name.'.' }}
                Du prüfst alles, bevor etwas gespeichert wird.
            </p>
            @error('assistent')<p class="border-l-4 border-gelb bg-gelb/10 px-3 py-2 text-sm">{{ $message }}</p>@enderror
            <div><label class="feld-label" for="a-text">Text</label>
                <textarea id="a-text" name="text" rows="8" class="feld" placeholder="z. B.&#10;Halle reservieren, 10 Monate vorher&#10;Schiris aufbieten 6 Wochen vorher, ca. 1 Std.&#10;Abrechnung 2 Wochen nach dem Turnier">{{ old('text') }}</textarea>
                @error('text')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror</div>
            <div><label class="feld-label" for="a-dateien">Dateien (optional)</label>
                <input id="a-dateien" type="file" name="dateien[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp" class="feld" x-on:change="namen = [...$event.target.files].map(f => f.name)">
                <p class="mt-1 text-xs text-beton">PDF oder Fotos, z. B. eine alte Checkliste. Bis 5 Dateien, je 15 MB.</p>
                @error('dateien.*')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror</div>
            <p class="text-xs text-beton">Text und Dateien gehen zur Auswertung an Claude (Anthropic) und werden im Tool nicht gespeichert.</p>
            <div class="flex items-center justify-end gap-2">
                <button type="button" class="knopf-leise" x-on:click="offen = false">Abbrechen</button>
                <button class="knopf-rot" :disabled="laeuft"><x-icon name="funken" class="h-4 w-4" /> <span x-text="laeuft ? 'Claude liest ...' : 'Vorschläge holen'">Vorschläge holen</span></button>
            </div>
            <p x-show="laeuft" x-cloak class="text-right text-xs text-beton">Das dauert meist 10 bis 60 Sekunden.</p>
        </form>
    @endif
</x-modal>
