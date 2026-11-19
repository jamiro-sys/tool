<x-app titel="Rundmeldungen">
    <x-seitenkopf titel="Rundmeldungen" ueber="Admin">
        <x-slot:unter>Kurze Infos an das ganze OK oder ein Ressort. Zustellung gemäss den Einstellungen jeder Person.</x-slot:unter>
    </x-seitenkopf>
    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" class="karte space-y-4 p-4">@csrf
            <div><label class="feld-label" for="titel">Titel</label><input id="titel" name="titel" required maxlength="150" class="feld" placeholder="z.B. OK-Sitzung am Dienstag"></div>
            <div><label class="feld-label" for="text">Nachricht</label><textarea id="text" name="text" required maxlength="2000" class="feld" rows="5"></textarea></div>
            <div><label class="feld-label" for="ressort_id">An</label><select id="ressort_id" name="ressort_id" class="feld"><option value="">Alle aktiven Personen</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}">Ressort {{ $r->name }}</option>@endforeach</select></div>
            <button class="knopf-rot w-full"><x-icon name="senden" class="h-4 w-4" /> Senden</button>
        </form>
        <section class="karte lg:col-span-2">
            <div class="karte-kopf"><h2 class="titel text-sm">Gesendet</h2></div>
            <ul class="divide-y divide-nacht/5">
                @forelse ($meldungen as $m)
                    <li class="px-4 py-3"><div class="flex items-center justify-between gap-3"><span class="text-sm font-bold">{{ $m->titel }}</span><span class="text-xs text-beton">{{ $m->created_at->format('d.m.Y H:i') }}</span></div>
                        <p class="mt-1 text-sm">{{ $m->text }}</p>
                        <p class="mt-1 text-xs text-beton">{{ $m->user?->name }} an {{ $m->ressort ? 'Ressort '.$m->ressort->name : 'alle' }} ({{ $m->empfaenger }})</p></li>
                @empty
                    <li><x-leer icon="senden" titel="Noch keine Rundmeldung" /></li>
                @endforelse
            </ul>
        </section>
    </div>
</x-app>
