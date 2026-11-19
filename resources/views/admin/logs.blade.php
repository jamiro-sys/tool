<x-app titel="Logs">
    <x-seitenkopf titel="Logs" ueber="Admin" :zurueck="route('admin.system')">
        <x-slot:aktionen>
            <form method="POST" action="{{ route('admin.logs.leeren') }}" onsubmit="return confirm('Log-Datei leeren?')">@csrf<button class="knopf-rahmen">Leeren</button></form>
        </x-slot:aktionen>
    </x-seitenkopf>
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'Alle', 'error' => 'Fehler', 'warning' => 'Warnungen', 'info' => 'Info', 'debug' => 'Debug'] as $k => $l)
            <a href="{{ route('admin.logs', $k ? ['stufe' => $k] : []) }}" class="knopf knopf-klein {{ ($stufe ?? '') === $k ? 'bg-nacht text-white' : 'border border-nacht/20 bg-white' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="karte divide-y divide-nacht/5">
        @forelse ($zeilen as $z)
            <div class="px-4 py-2.5 text-xs" x-data="{ auf: false }">
                <button type="button" class="flex w-full items-start gap-3 text-left" x-on:click="auf = !auf">
                    <span class="{{ in_array($z['stufe'], ['error', 'critical', 'alert', 'emergency']) ? 'marke-gelb' : 'marke-rahmen' }} shrink-0">{{ $z['stufe'] }}</span>
                    <span class="w-36 shrink-0 text-beton">{{ $z['zeit'] }}</span>
                    <span class="min-w-0 flex-1 break-words font-mono">{{ $z['titel'] }}</span>
                </button>
                <pre x-show="auf" x-collapse class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap bg-kreide p-2 font-mono text-[11px]">{{ $z['detail'] }}</pre>
            </div>
        @empty
            <x-leer icon="check" titel="Keine Einträge" />
        @endforelse
    </div>
</x-app>
