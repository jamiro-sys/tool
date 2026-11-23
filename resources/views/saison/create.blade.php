<x-app titel="Neue Saison planen">
    <x-seitenkopf titel="Neue Saison planen" ueber="Turniere" :zurueck="route('ausgaben.index')">
        <x-slot:unter>Daten aller Turniere der Saison wählen. Danach prüfst du nacheinander die Aufgaben aus dem Jahresplan, zuerst das frühere Turnier.</x-slot:unter>
    </x-seitenkopf>

    @if ($zeilen->isEmpty())
        <section class="karte">
            <x-leer icon="pokal" titel="Zuerst Turnierarten anlegen" text="Eine Saison besteht aus Turnieren verschiedener Turnierarten, z. B. Hallencup Dezember und Hallencup Februar. Lege sie unter Admin > Einstellungen an.">
                @can('admin')<a href="{{ route('admin.einstellungen') }}" class="knopf-rot mt-4"><x-icon name="einstellungen" class="h-4 w-4" /> Zu den Einstellungen</a>@endcan
            </x-leer>
        </section>
    @else
        <form method="POST" action="{{ route('saison.store') }}" class="space-y-5">
            @csrf
            <section class="karte p-4">
                <div class="grid gap-4 sm:grid-cols-[200px_1fr] sm:items-end">
                    <div>
                        <label class="feld-label" for="saison">Saison</label>
                        <input id="saison" name="saison" required class="feld" value="{{ old('saison', $saison) }}" placeholder="2027/28" pattern="\d{4}/\d{2}">
                    </div>
                    <p class="text-xs text-beton">Eine Saison läuft von Juli bis Juni. Dezember 2027 und Februar 2028 gehören zur Saison 2027/28.
                        <a href="{{ route('saison.create', ['saison' => \App\Support\Saison::naechste($saison)]) }}" class="label ml-1 hover:text-rot">Spätere Saison</a></p>
                </div>
                @error('saison')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror
                @error('turniere')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror
            </section>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($zeilen as $z)
                    @php $t = $z['turnier']; $id = $t->id; @endphp
                    @if ($z['vorhanden'])
                        <section class="karte p-4">
                            <p class="titel text-sm">{{ $t->name }}</p>
                            <p class="mt-2 text-sm text-beton">In dieser Saison schon geplant: <a href="{{ route('ausgaben.show', $z['vorhanden']) }}" class="font-bold text-nacht hover:text-rot">{{ $z['vorhanden']->titel }}</a>, {{ $z['vorhanden']->datumText() }}.</p>
                        </section>
                    @else
                        <section class="karte p-4" x-data="{ mit: {{ old("turniere.$id.mit", '1') ? 'true' : 'false' }} }" data-turnierart="{{ $t->name }}">
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="hidden" name="turniere[{{ $id }}][mit]" value="0">
                                <input type="checkbox" name="turniere[{{ $id }}][mit]" value="1" class="h-5 w-5" x-model="mit">
                                <span><span class="titel block text-sm">{{ $t->name }}</span>
                                    <span class="text-xs text-beton">{{ $t->halle?->name ?? 'Halle offen' }}@if ($z['letzte']) | zuletzt {{ $z['letzte']->datumText() }}@endif</span></span>
                            </label>
                            <div x-show="mit" x-collapse class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="feld-label" for="von-{{ $id }}">Erster Turniertag</label>
                                    <input type="date" id="von-{{ $id }}" name="turniere[{{ $id }}][datum_von]" class="feld" value="{{ old("turniere.$id.datum_von", $z['von']?->format('Y-m-d')) }}">
                                    @error("turniere.$id.datum_von")<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="feld-label" for="bis-{{ $id }}">Letzter Turniertag</label>
                                    <input type="date" id="bis-{{ $id }}" name="turniere[{{ $id }}][datum_bis]" class="feld" value="{{ old("turniere.$id.datum_bis", $z['bis']?->format('Y-m-d')) }}">
                                    @error("turniere.$id.datum_bis")<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror
                                </div>
                                @if ($z['von'])<p class="text-xs text-beton sm:col-span-2">Vorschlag: ein Jahr nach dem letzten Turnier, gleicher Wochentag. Bitte mit dem Hallenplan abgleichen.</p>@endif
                            </div>
                        </section>
                    @endif
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('ausgaben.index') }}" class="knopf-leise">Abbrechen</a>
                <button class="knopf-rot">Weiter zu den Aufgaben <x-icon name="pfeil" class="h-4 w-4" /></button>
            </div>
        </form>
    @endif
</x-app>
