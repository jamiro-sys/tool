{{--
    Ablage als aufklappbare Ordner. Erwartet:
    $gruppen: Liste von ['ressort' => ?Ressort, 'ordner' => Collection<Ordner>, 'dokumente' => Collection<Dokument>]
    $orte: Auswahl Ablageort fuer das Verschieben; $offen: alle Ressort-Ordner offen
--}}
<div class="space-y-3">
    @foreach ($gruppen as $g)
        @php
            $rid = $g['ressort']?->id;
            $lose = $g['dokumente']->whereNull('ordner_id');
            $anzahl = $g['dokumente']->count();
        @endphp
        <details class="karte group/ressort" @if ($offen) open @endif>
            <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 hover:bg-kreide/60 [&::-webkit-details-marker]:hidden">
                <x-icon name="ordner" class="h-5 w-5 shrink-0 text-rot" />
                <span class="titel min-w-0 flex-1 truncate text-sm">{{ $g['ressort']?->name ?? 'Allgemein' }}</span>
                <span class="label">{{ $anzahl }}</span>
                <x-icon name="pfeil" class="h-4 w-4 shrink-0 text-beton transition-transform group-open/ressort:rotate-90" />
            </summary>
            <div class="border-t border-nacht/10">
                @foreach ($g['ordner'] as $o)
                    @php $inhalt = $g['dokumente']->where('ordner_id', $o->id); @endphp
                    <details class="group/ordner border-b border-nacht/5" x-data="{ umbenennen: false }">
                        <summary class="flex cursor-pointer list-none items-center gap-3 py-2.5 pl-6 pr-4 hover:bg-kreide/60 [&::-webkit-details-marker]:hidden">
                            <x-icon name="ordner" class="h-4 w-4 shrink-0 text-nacht" />
                            <span class="min-w-0 flex-1 truncate text-sm font-bold">{{ $o->name }}</span>
                            <span class="text-xs text-beton">{{ $inhalt->count() }}</span>
                            <x-icon name="pfeil" class="h-4 w-4 shrink-0 text-beton transition-transform group-open/ordner:rotate-90" />
                        </summary>
                        <div class="border-l-2 border-nacht/10 ml-7 mb-2">
                            <ul class="divide-y divide-nacht/5">
                                @forelse ($inhalt as $d)
                                    @include('dateien._eintrag', ['d' => $d])
                                @empty
                                    <li class="px-3 py-2.5 text-xs text-beton">Leer.</li>
                                @endforelse
                            </ul>
                            @can('schreiben')
                                <div class="flex flex-wrap items-center gap-2 px-3 pt-2">
                                    <button type="button" class="label hover:text-rot" x-on:click="umbenennen = !umbenennen">Umbenennen</button>
                                    <form method="POST" action="{{ route('ordner.loeschen', $o) }}" onsubmit="return confirm(@js('Ordner «'.$o->name.'» löschen? Der Inhalt bleibt im Ressort.'))">@csrf @method('DELETE')<button class="label hover:text-rot">Ordner löschen</button></form>
                                    <form x-show="umbenennen" x-cloak method="POST" action="{{ route('ordner.aendern', $o) }}" class="flex w-full gap-2">@csrf @method('PATCH')
                                        <input name="name" required maxlength="120" class="feld min-h-[38px] py-1" value="{{ $o->name }}" aria-label="Neuer Name"><button class="knopf-nacht knopf-klein">Speichern</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    </details>
                @endforeach
                <ul class="divide-y divide-nacht/5 pl-3">
                    @foreach ($lose as $d)
                        @include('dateien._eintrag', ['d' => $d])
                    @endforeach
                </ul>
                @if ($anzahl === 0 && $g['ordner']->isEmpty())
                    <p class="px-4 py-3 text-sm text-beton">Noch nichts abgelegt.</p>
                @endif
                @can('schreiben')
                    <form method="POST" action="{{ route('ordner.speichern') }}" class="flex items-center gap-2 border-t border-nacht/5 px-4 py-2.5" x-data="{ neu: false }">@csrf
                        <input type="hidden" name="ressort_id" value="{{ $rid }}">
                        <button type="button" class="label flex items-center gap-1.5 hover:text-rot" x-show="!neu" x-on:click="neu = true; $nextTick(() => $refs.name.focus())"><x-icon name="plus" class="h-3.5 w-3.5" /> Unterordner</button>
                        <input x-show="neu" x-cloak x-ref="name" name="name" required maxlength="120" class="feld min-h-[38px] flex-1 py-1" placeholder="z. B. Verträge, Pläne, Reglemente" aria-label="Name des Unterordners">
                        <button x-show="neu" x-cloak class="knopf-nacht knopf-klein">Anlegen</button>
                    </form>
                @endcan
            </div>
        </details>
    @endforeach
</div>
