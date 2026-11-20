<x-app titel="Ressorts">
    <x-seitenkopf titel="Ressorts" ueber="Organisation">
        <x-slot:unter>Jedes Ressort hat einen Steckbrief mit Personen, Aufgaben, Kontakten und Wissen. So bleibt alles übergabebereit.</x-slot:unter>
        <x-slot:aktionen>
            @can('admin')<button type="button" class="knopf-rot" x-on:click="$dispatch('modal-ressort')"><x-icon name="plus" class="h-4 w-4" /> Ressort</button>@endcan
        </x-slot:aktionen>
    </x-seitenkopf>

    @if ($ressorts->isEmpty())
        <section class="karte mb-6">
            <x-leer icon="ressorts" titel="Noch keine Ressorts" text="Ressorts teilen die Arbeit im OK auf, z. B. Spielbetrieb, Festwirtschaft oder Sponsoring. Jede Aufgabe und jede Vorlage gehört zu einem Ressort.">
                @can('admin')<button type="button" class="knopf-rot mt-4" x-data x-on:click="$dispatch('modal-ressort')"><x-icon name="plus" class="h-4 w-4" /> Erstes Ressort anlegen</button>@endcan
            </x-leer>
        </section>
    @endif
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($ressorts as $r)
            @php $f = $fortschritt[$r->id] ?? null; $leitung = $r->leitung(); $nachfolge = $r->nachfolge(); @endphp
            <a href="{{ route('ressorts.show', $r) }}" class="karte group flex flex-col p-4 hover:border-nacht animate-einblenden" style="animation-delay: {{ $loop->index * 40 }}ms">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="label text-rot">{{ $r->kuerzel }}</span>
                        <h2 class="titel mt-1 text-lg group-hover:text-rot">{{ $r->name }}</h2>
                    </div>
                    <div class="flex -space-x-1">@foreach ($r->mitglieder->take(4) as $m)<x-person :user="$m" class="ring-2 ring-white" />@endforeach</div>
                </div>
                <p class="mt-2 line-clamp-2 text-sm text-beton">{{ $r->auftrag ?: 'Noch kein Auftrag beschrieben.' }}</p>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="marke-rahmen">{{ $leitung ? 'Leitung: '.$leitung->name : 'Leitung offen' }}</span>
                    @if ($nachfolge)<span class="marke bg-nacht text-white">Nachfolge: {{ strtok($nachfolge->name, ' ') }}</span>@endif
                </div>
                <div class="mt-auto space-y-3 pt-4">
                    @if ($f)
                        <div>
                            <div class="flex justify-between text-xs"><span class="label">{{ $ausgabe->turnier->kurz }} {{ $ausgabe->saison }}</span><span class="font-bold">{{ $f['erledigt'] }}/{{ $f['total'] }}</span></div>
                            <x-fortschritt :prozent="$f['prozent']" class="mt-1.5" />
                        </div>
                    @endif
                    @include('ressorts._ampel', ['stand' => $r->wissensstand()])
                </div>
            </a>
        @endforeach
    </div>

    @can('admin')
        <x-modal name="ressort" titel="Neues Ressort">
            <form method="POST" action="{{ route('ressorts.store') }}" class="space-y-4">@csrf
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2"><label class="feld-label" for="r-name">Name</label><input id="r-name" name="name" required class="feld"></div>
                    <div><label class="feld-label" for="r-k">Kürzel</label><input id="r-k" name="kuerzel" required maxlength="8" class="feld uppercase"></div>
                </div>
                <div><label class="feld-label" for="r-a">Auftrag</label><textarea id="r-a" name="auftrag" class="feld"></textarea></div>
                <div class="flex justify-end"><button class="knopf-rot">Erstellen</button></div>
            </form>
        </x-modal>
    @endcan
</x-app>
