<x-app titel="Benachrichtigungen">
    <x-seitenkopf titel="Benachrichtigungen" ueber="Meldungen">
        <x-slot:aktionen>
            <form method="POST" action="{{ route('benachrichtigungen.gelesen') }}">@csrf<button class="knopf-rahmen">Alle gelesen</button></form>
            <a href="{{ route('benachrichtigungen.einstellungen') }}" class="knopf-nacht"><x-icon name="einstellungen" class="h-4 w-4" /> Einstellungen</a>
        </x-slot:aktionen>
    </x-seitenkopf>

    <div class="karte divide-y divide-nacht/5">
        @forelse ($meldungen as $m)
            <a href="{{ route('benachrichtigungen.oeffnen', $m->id) }}" class="flex gap-3 px-4 py-3 hover:bg-kreide/60 {{ $m->read_at ? '' : 'bg-rot/[0.03]' }}">
                <span class="mt-1.5 h-2 w-2 shrink-0 {{ $m->read_at ? 'bg-transparent' : 'bg-rot' }}"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm {{ $m->read_at ? '' : 'font-bold' }}">{{ $m->data['titel'] ?? 'Meldung' }}</span>
                    <span class="block text-sm text-beton">{{ $m->data['text'] ?? '' }}</span>
                </span>
                <span class="shrink-0 text-xs text-beton">{{ $m->created_at->diffForHumans() }}</span>
            </a>
        @empty
            <x-leer icon="glocke" titel="Keine Meldungen" text="Hier erscheinen Zuweisungen, Erinnerungen und Neuigkeiten aus dem OK." />
        @endforelse
    </div>
    <div class="mt-4">{{ $meldungen->links() }}</div>
</x-app>
