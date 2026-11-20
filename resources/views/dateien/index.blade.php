<x-app titel="Dateien">
    <x-seitenkopf titel="Dateien" ueber="Gemeinsame Ablage">
        <x-slot:unter>Alles an einem Ort: Dateien und Links, auf Wunsch einem Ressort zugeteilt. Im Steckbrief des Ressorts erscheinen sie ebenfalls.</x-slot:unter>
    </x-seitenkopf>

    <a href="{{ route('styleguide') }}" class="karte mb-6 flex items-center gap-4 border-l-4 border-l-rot p-4 hover:border-nacht">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center bg-rot text-white"><img src="{{ asset('img/fcm-logo-negativ.svg') }}" alt="" class="h-8"></span>
        <span class="min-w-0 flex-1"><span class="titel block text-sm">FCM-Styleguide</span><span class="block text-xs text-beton">Farben, Schrift, Logos, Aufbau und Schreibweise. Immer aktuell, als PDF herunterladbar. Stand {{ config('app.version') }}</span></span>
        <x-icon name="pfeil" class="h-4 w-4 shrink-0" />
    </a>

    @can('schreiben')
        <form method="POST" action="{{ route('dateien.speichern') }}" enctype="multipart/form-data" class="karte mb-6 p-4" x-data="dateiAblage()">@csrf
            <div class="mb-3 grid grid-cols-2 gap-1 text-[11px] font-bold uppercase tracking-label sm:w-72">
                <button type="button" class="py-2" :class="art === 'datei' ? 'bg-nacht text-white' : 'border border-nacht/20'" x-on:click="art = 'datei'">Dateien</button>
                <button type="button" class="py-2" :class="art === 'link' ? 'bg-nacht text-white' : 'border border-nacht/20'" x-on:click="art = 'link'">Link</button>
            </div>
            <label x-show="art === 'datei'" class="flex min-h-[132px] cursor-pointer flex-col items-center justify-center gap-2 border-2 border-dashed px-4 py-6 text-center transition-colors"
                   :class="ueber ? 'border-rot bg-rot/5' : 'border-nacht/20 hover:border-nacht/50'"
                   x-on:dragover.prevent="ueber = true" x-on:dragleave.prevent="ueber = false" x-on:drop.prevent="ablegen($event)">
                <x-icon name="upload" class="h-7 w-7 text-beton" />
                <span class="text-sm font-bold" x-text="namen.length ? namen.length + (namen.length === 1 ? ' Datei gewählt' : ' Dateien gewählt') : 'Dateien hierher ziehen oder antippen'"></span>
                <span class="max-w-full truncate text-xs text-beton" x-text="namen.length ? namen.join(', ') : 'Bis 50 MB pro Datei, mehrere auf einmal möglich'"></span>
                <input type="file" name="dateien[]" multiple class="sr-only" x-ref="eingabe" x-on:change="gewaehlt()">
            </label>
            <input x-show="art === 'link'" x-cloak name="url" type="url" class="feld" placeholder="https:// z. B. iCloud, Google Drive, OneDrive" aria-label="Link">
            <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                <select name="ressort_id" class="feld" aria-label="Ressort"><option value="">Allgemein (kein Ressort)</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected(request('ressort') == $r->id)>{{ $r->name }}</option>@endforeach</select>
                <input name="titel" maxlength="200" class="feld" :placeholder="art === 'link' ? 'Titel des Links' : 'Titel (optional, sonst Dateiname)'" aria-label="Titel">
                <button class="knopf-rot" :disabled="art === 'datei' && ! namen.length"><x-icon name="upload" class="h-4 w-4" /> Ablegen</button>
            </div>
        </form>
    @endcan

    <form method="GET" class="mb-5 grid gap-2 sm:grid-cols-[220px_160px_1fr] sm:items-end" x-data x-on:change="$el.submit()">
        <div><label class="feld-label" for="d-ressort">Ressort</label>
            <select id="d-ressort" name="ressort" class="feld"><option value="">Alle</option><option value="allgemein" @selected($filter['ressort'] === 'allgemein')>Allgemein</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected($filter['ressort'] == $r->id)>{{ $r->name }}</option>@endforeach</select></div>
        <div><label class="feld-label" for="d-art">Art</label>
            <select id="d-art" name="art" class="feld"><option value="">Alle</option><option value="datei" @selected($filter['art'] === 'datei')>Dateien</option><option value="link" @selected($filter['art'] === 'link')>Links</option></select></div>
        <div><label class="feld-label" for="d-q">Suche</label><input id="d-q" name="q" value="{{ $filter['q'] }}" class="feld" placeholder="Titel oder Dateiname"></div>
    </form>

    <section class="karte">
        <div class="karte-kopf"><h2 class="titel text-sm">Ablage</h2><span class="label">{{ $dokumente->count() }}{{ $dokumente->count() !== $gesamt ? ' von '.$gesamt : '' }}</span></div>
        @if ($dokumente->isEmpty())
            <x-leer icon="datei" :titel="$gesamt ? 'Nichts gefunden' : 'Noch keine Dateien'" :text="$gesamt ? 'Passe die Filter an.' : 'Lege Reglemente, Pläne, Verträge oder Logos ab, damit alle sie finden.'" />
        @endif
        <ul class="divide-y divide-nacht/5">
            @foreach ($dokumente as $d)
                @php $darf = auth()->user()->can('admin') || $d->user_id === auth()->id() || ($d->ressort && auth()->user()->can('ressort-mitarbeiten', $d->ressort)); @endphp
                <li class="px-4 py-3" x-data="{ bearbeiten: false }">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-12 shrink-0 items-center justify-center text-[10px] font-extrabold uppercase tracking-label {{ $d->typ === 'datei' ? 'bg-nacht text-white' : 'border border-nacht/20 text-nacht' }}">{{ \Illuminate\Support\Str::limit($d->endung(), 4, '') }}</span>
                        <a href="{{ $d->typ === 'datei' && preg_match('/^(image\/|application\/pdf)/', (string) $d->mime) ? route('dokumente.download', [$d, 'ansehen' => 1]) : $d->link() }}" target="_blank" rel="noopener" class="min-w-0 flex-1 hover:text-rot">
                            <span class="block truncate text-sm font-bold">{{ $d->titel }}</span>
                            <span class="block truncate text-xs text-beton">{{ collect([$d->ressort?->name ?? 'Allgemein', $d->groesseText(), $d->user?->name, $d->created_at->format('d.m.Y')])->filter()->implode(' | ') }}</span>
                        </a>
                        @if ($d->typ === 'datei')<a href="{{ route('dokumente.download', $d) }}" class="knopf-leise knopf-klein" aria-label="{{ $d->titel }} herunterladen"><x-icon name="download" class="h-4 w-4" /></a>@endif
                        @can('schreiben')<button type="button" class="knopf-leise knopf-klein" x-on:click="bearbeiten = !bearbeiten" aria-label="{{ $d->titel }} bearbeiten"><x-icon name="stift" class="h-4 w-4" /></button>@endcan
                    </div>
                    @can('schreiben')
                        <div x-show="bearbeiten" x-collapse x-cloak class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                            <form method="POST" action="{{ route('dateien.aendern', $d) }}" class="flex flex-1 flex-col gap-2 sm:flex-row">@csrf @method('PATCH')
                                <input name="titel" required maxlength="200" class="feld" value="{{ $d->titel }}" aria-label="Titel">
                                <select name="ressort_id" class="feld sm:w-56" aria-label="Ressort"><option value="">Allgemein</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected($d->ressort_id === $r->id)>{{ $r->name }}</option>@endforeach</select>
                                <button class="knopf-rot">Speichern</button>
                            </form>
                            @if ($darf)
                                <form method="POST" action="{{ route('dokumente.loeschen', $d) }}" onsubmit="return confirm('{{ $d->titel }} endgültig löschen?')">@csrf @method('DELETE')<button class="knopf-leise text-beton hover:text-rot"><x-icon name="loeschen" class="h-4 w-4" /> Löschen</button></form>
                            @endif
                        </div>
                    @endcan
                </li>
            @endforeach
        </ul>
    </section>
</x-app>
