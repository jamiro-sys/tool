@php
    $ueberfaellig = $aufgabe->istUeberfaellig();
    $heute = $aufgabe->istHeuteFaellig();
    $mitAusgabe ??= false;
@endphp
<div x-data="aufgabeStatus('{{ route('aufgaben.status', $aufgabe) }}', '{{ $aufgabe->status }}')" class="relative overflow-hidden border-b border-nacht/5 last:border-0">
    {{-- Hintergrund beim Wischen --}}
    <div class="absolute inset-y-0 left-0 flex w-full items-center bg-rot pl-5 text-xs font-bold uppercase tracking-label text-white lg:hidden" aria-hidden="true">
        <x-icon name="check" class="mr-2 h-4 w-4" /> Erledigt
    </div>
    <div x-data="wischen()" x-on:touchstart.passive="start($event)" x-on:touchmove.passive="bewegen($event)" x-on:touchend="ende()" x-on:gewischt="setze('erledigt')"
         :style="dx ? `transform: translateX(${dx}px)` : ''" :class="aktiv ? '' : 'transition-transform duration-200'"
         class="relative flex items-center gap-1 bg-white pr-3">
        @include('aufgaben._haken', ['aufgabe' => $aufgabe])
        <a href="{{ route('aufgaben.show', $aufgabe) }}" class="flex min-w-0 flex-1 items-center gap-3 py-3">
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-bold transition-colors" :class="erledigt ? 'text-beton line-through' : ''">{{ $aufgabe->titel }}</span>
                <span class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-beton">
                    @unless ($ohneRessort ?? false)<span class="font-bold uppercase tracking-label text-[10px] text-nacht">{{ $aufgabe->ressort->kuerzel }}</span>@endunless
                    @if ($mitAusgabe)<span>{{ $aufgabe->ausgabe->turnier->kurz ?? $aufgabe->ausgabe->titel }}</span>@endif
                    @if ($aufgabe->checklisteFortschritt())
                        <span class="inline-flex items-center gap-1"><x-icon name="aufgaben" class="h-3 w-3" /> {{ $aufgabe->checklisteFortschritt()['erledigt'] }}/{{ $aufgabe->checklisteFortschritt()['total'] }}</span>
                    @endif
                    @if ($aufgabe->begleitung_id === auth()->id())<span class="marke-rahmen">Begleitung</span>@endif
                    @if ($aufgabe->prioritaet === 'hoch')<span class="marke-rahmen border-rot text-rot">Wichtig</span>@endif
                    @if ($aufgabe->saison_aufgabe)<span class="marke-rahmen" title="Gilt für die ganze Saison, erfasst im Turnier {{ $aufgabe->ausgabe?->titel }}">Einmal pro Saison</span>@endif
                </span>
            </span>
            <span class="flex shrink-0 flex-col items-end gap-1">
                @if ($ueberfaellig)
                    <span class="marke-gelb"><x-icon name="uhr" class="h-3 w-3" /> {{ $aufgabe->faelligText() }}</span>
                @elseif ($heute)
                    <span class="marke bg-rot text-white">Heute</span>
                @elseif ($aufgabe->istOffen())
                    <span class="text-xs font-bold text-beton">{{ $aufgabe->faelligText() }}</span>
                @endif
                @if (! ($ohnePerson ?? false))
                    <x-person :user="$aufgabe->verantwortlich" />
                @endif
            </span>
        </a>
    </div>
</div>
