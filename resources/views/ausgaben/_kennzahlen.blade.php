{{-- Kennzahlen der Ausgabe. Namen kommen aus der eigenen Liste unter Admin > Einstellungen. --}}
<section class="karte">
    <div class="karte-kopf"><h2 class="titel text-sm">Kennzahlen</h2>@if ($vorher)<span class="label">Vergleich {{ $vorher->titel }}</span>@endif</div>
    @if ($kennzahlNamen->isEmpty())
        <p class="px-4 py-3 text-xs text-beton">Lege zuerst unter Admin &gt; Einstellungen &gt; Eigene Listen fest, welche Kennzahlen ihr festhalten wollt, z. B. angemeldete Teams oder besetzte Schichten.</p>
    @else
        <form method="POST" action="{{ route('ausgaben.kennzahlen', $ausgabe) }}" class="divide-y divide-nacht/5">@csrf
            @foreach ($kennzahlNamen as $name)
                @php $ist = $ausgabe->kennzahlen->firstWhere('name', $name)?->wert; $alt = $vorher?->kennzahlen->firstWhere('name', $name)?->wert; @endphp
                <label class="flex items-center gap-3 px-4 py-2">
                    <span class="min-w-0 flex-1 text-sm">{{ $name }}@if ($alt !== null)<span class="block text-xs text-beton">zuletzt {{ rtrim(rtrim(number_format($alt, 2, '.', "'"), '0'), '.') }}</span>@endif</span>
                    <input name="werte[{{ $name }}]" type="number" step="any" inputmode="decimal" class="feld w-28 py-2 text-right" value="{{ $ist !== null ? rtrim(rtrim(number_format($ist, 2, '.', ''), '0'), '.') : '' }}" @cannot('schreiben') disabled @endcannot>
                </label>
            @endforeach
            @can('schreiben')<div class="flex justify-end px-4 py-3"><button class="knopf-rot knopf-klein">Speichern</button></div>@endcan
        </form>
    @endif
</section>
