<x-app titel="Protokoll">
    <x-seitenkopf titel="Protokoll" ueber="Admin">
        <x-slot:unter>Wer hat wann was geändert. Für Transparenz und Nachvollziehbarkeit.</x-slot:unter>
    </x-seitenkopf>
    <form method="GET" class="mb-4 flex flex-wrap gap-2" x-data x-on:change="$el.submit()">
        <select name="person" class="feld w-auto" aria-label="Person"><option value="">Alle Personen</option>@foreach ($personen as $p)<option value="{{ $p->id }}" @selected(request('person') == $p->id)>{{ $p->name }}</option>@endforeach</select>
        <select name="aktion" class="feld w-auto" aria-label="Bereich"><option value="">Alle Bereiche</option>@foreach ($aktionen as $a)<option value="{{ $a }}" @selected(request('aktion') === $a)>{{ \App\Models\Aktivitaet::bereich($a) }}</option>@endforeach</select>
    </form>
    <div class="karte overflow-x-auto">
        <table class="tabelle">
            <thead><tr><th>Zeit</th><th>Person</th><th>Bereich</th><th>Was</th><th class="hidden md:table-cell">IP</th></tr></thead>
            <tbody>
                @forelse ($eintraege as $e)
                    <tr><td class="whitespace-nowrap text-xs text-beton">{{ $e->created_at->format('d.m.Y H:i') }}</td><td class="whitespace-nowrap">{{ $e->user?->name ?? 'System' }}</td><td><span class="marke-rahmen">{{ \App\Models\Aktivitaet::bereich($e->aktion) }}</span></td><td>{{ $e->beschreibung }}</td><td class="hidden text-xs text-beton md:table-cell">{{ $e->ip }}</td></tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-sm text-beton">Noch keine Einträge.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $eintraege->links() }}</div>
</x-app>
