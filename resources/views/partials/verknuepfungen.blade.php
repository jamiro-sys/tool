{{-- Links zu externen Werkzeugen. Erwartet $bezug (Model) und $typ (ausgabe|ressort|aufgabe). --}}
@php $links = $bezug->verknuepfungen; @endphp
<section class="karte" x-data="{ neu: false }">
    <div class="karte-kopf">
        <h2 class="titel text-sm">Links</h2>
        @can('schreiben')
            <button type="button" class="knopf-leise knopf-klein" x-on:click="neu = !neu" aria-label="Link hinzufügen"><x-icon name="plus" class="h-4 w-4" /></button>
        @endcan
    </div>
    @if ($links->isEmpty())
        <p class="px-4 py-3 text-xs text-beton">Noch keine Links. Hier kommen z. B. der Spielplan auf turnieragenda.ch oder der Schichtplan auf helfereinsatz.ch hin.</p>
    @else
        <ul class="divide-y divide-nacht/5">
            @foreach ($links as $l)
                <li class="flex items-center gap-3 px-4 py-2.5">
                    <x-icon name="link" class="h-4 w-4 shrink-0 text-beton" />
                    <a href="{{ $l->url }}" target="_blank" rel="noopener" class="min-w-0 flex-1 hover:text-rot">
                        <span class="block truncate text-sm font-bold">{{ $l->titel }}</span>
                        <span class="block truncate text-xs text-beton">{{ $l->werkzeug ? \App\Models\Verknuepfung::WERKZEUGE[$l->werkzeug] : $l->host() }}</span>
                    </a>
                    @can('schreiben')
                        <form method="POST" action="{{ route('verknuepfungen.loeschen', $l) }}" onsubmit="return confirm('Link entfernen?')">@csrf @method('DELETE')
                            <button class="knopf-leise knopf-klein text-beton hover:text-rot" aria-label="Link entfernen"><x-icon name="x" class="h-4 w-4" /></button>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
    @can('schreiben')
        <form method="POST" action="{{ route('verknuepfungen.speichern') }}" x-show="neu" x-collapse x-cloak class="space-y-2 border-t border-nacht/10 p-4">@csrf
            <input type="hidden" name="bezug_typ" value="{{ $typ }}"><input type="hidden" name="bezug_id" value="{{ $bezug->id }}">
            <input name="titel" required maxlength="120" class="feld" placeholder="Titel, z. B. Spielplan">
            <input name="url" type="url" required class="feld" placeholder="https://">
            <select name="werkzeug" class="feld"><option value="">Werkzeug (optional)</option>@foreach (\App\Models\Verknuepfung::WERKZEUGE as $k => $w)<option value="{{ $k }}">{{ $w }}</option>@endforeach</select>
            <button class="knopf-rot knopf-klein">Speichern</button>
        </form>
    @endcan
</section>
