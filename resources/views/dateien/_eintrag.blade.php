{{-- Ein Eintrag der Ablage. Erwartet $d, $orte (Auswahl Ablageort), optional $pfad (Ordnerpfad anzeigen) --}}
@php $darf = auth()->user()->can('admin') || $d->user_id === auth()->id() || ($d->ressort && auth()->user()->can('ressort-mitarbeiten', $d->ressort)); @endphp
<li class="py-2.5 pl-3 pr-2 sm:pr-3" x-data="{ bearbeiten: false }">
    <div class="flex items-center gap-3">
        <span class="flex h-9 w-11 shrink-0 items-center justify-center text-[10px] font-extrabold uppercase tracking-label {{ $d->typ === 'datei' ? 'bg-nacht text-white' : 'border border-nacht/20 text-nacht' }}">{{ \Illuminate\Support\Str::limit($d->endung(), 4, '') }}</span>
        <x-datei-link :d="$d" class="min-w-0 flex-1 hover:text-rot">
            <span class="block truncate text-sm font-bold">{{ $d->titel }}</span>
            <span class="block truncate text-xs text-beton">{{ collect([($pfad ?? false) ? ($d->ordner ? $d->ordner->pfad() : ($d->ressort?->name ?? 'Allgemein')) : null, $d->groesseText(), $d->user?->name, $d->created_at->format('d.m.Y')])->filter()->implode(' | ') }}</span>
        </x-datei-link>
        @if ($d->typ === 'datei')<a href="{{ route('dokumente.download', $d) }}" class="knopf-leise knopf-klein" aria-label="{{ $d->titel }} herunterladen"><x-icon name="download" class="h-4 w-4" /></a>@endif
        @can('schreiben')<button type="button" class="knopf-leise knopf-klein" x-on:click="bearbeiten = !bearbeiten" aria-label="{{ $d->titel }} bearbeiten"><x-icon name="stift" class="h-4 w-4" /></button>@endcan
    </div>
    @can('schreiben')
        <div x-show="bearbeiten" x-collapse x-cloak class="mt-3 flex flex-col gap-2 lg:flex-row lg:items-center">
            <form method="POST" action="{{ route('dateien.aendern', $d) }}" class="flex flex-1 flex-col gap-2 sm:flex-row">@csrf @method('PATCH')
                <input name="titel" required maxlength="200" class="feld" value="{{ $d->titel }}" aria-label="Titel">
                <select name="ort" class="feld sm:w-64" aria-label="Ablageort">
                    @foreach ($orte as $gruppe => $liste)
                        <optgroup label="{{ $gruppe }}">@foreach ($liste as $wert => $name)<option value="{{ $wert }}" @selected($wert === ($d->ordner_id ? 'o:'.$d->ordner_id : 'r:'.($d->ressort_id ?? 0)))>{{ $name }}</option>@endforeach</optgroup>
                    @endforeach
                </select>
                <button class="knopf-rot">Speichern</button>
            </form>
            @if ($darf)
                <form method="POST" action="{{ route('dokumente.loeschen', $d) }}" onsubmit="return confirm(@js($d->titel.' endgültig löschen?'))">@csrf @method('DELETE')<button class="knopf-leise text-beton hover:text-rot"><x-icon name="loeschen" class="h-4 w-4" /> Löschen</button></form>
            @endif
        </div>
    @endcan
</li>
