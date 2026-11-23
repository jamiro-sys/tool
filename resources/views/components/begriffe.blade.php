@props(['offen' => false])
{{-- Kurze Erklaerung der Begriffe, ueberall gleich: Turnier, Saison, Jahresplan, Wie oft, Weglassen --}}
<section {{ $attributes->merge(['class' => 'karte']) }} x-data="{ auf: {{ $offen ? 'true' : 'false' }} }" data-begriffe>
    <button type="button" class="karte-kopf w-full text-left" x-on:click="auf = !auf" :aria-expanded="auf">
        <span class="flex items-center gap-2"><x-icon name="buch" class="h-4 w-4" /><span class="titel text-sm">So hängt alles zusammen</span></span>
        <x-icon name="pfeil" class="h-4 w-4 transition-transform" ::class="auf ? 'rotate-90' : ''" />
    </button>
    <div x-show="auf" x-collapse @unless ($offen) x-cloak @endunless class="grid gap-5 p-4 text-sm md:grid-cols-2">
        <dl class="space-y-3">
            <div><dt class="font-bold">Turnier</dt><dd class="text-beton">Ein einzelner Anlass, z. B. Hallencup Dezember 2027.</dd></div>
            <div><dt class="font-bold">Saison</dt><dd class="text-beton">Alle Turniere eines Vereinsjahres (Juli bis Juni), z. B. Dezember 2027 und Februar 2028 = Saison 2027/28.</dd></div>
            <div><dt class="font-bold">Jahresplan</dt><dd class="text-beton">Die Liste der Aufgaben, die wiederkommen. Daraus entstehen die Aufgaben jedes neuen Turniers.</dd></div>
        </dl>
        <div class="space-y-4">
            <div>
                <p class="label mb-1.5">Wie oft kommt eine Aufgabe?</p>
                <dl class="space-y-1.5">
                    @foreach (\App\Models\Vorlage::WIE_OFT as $k => [$titel, $text])
                        <div class="border-l-4 {{ $k === 'einmalig' ? 'border-beton' : 'border-rot' }} bg-kreide/60 px-3 py-1.5"><dt class="font-bold">{{ $titel }}</dt><dd class="text-xs text-beton">{{ $text }}@if ($k === 'turnier') Beispiel: Halle reservieren.@elseif ($k === 'saison') Beispiel: Sponsoren anfragen.@else Beispiel: Musikboxen ausleihen.@endif</dd></div>
                    @endforeach
                </dl>
            </div>
            <div>
                <p class="label mb-1.5">Eine Aufgabe weglassen</p>
                <dl class="space-y-1 text-xs">
                    <div><dt class="inline font-bold">Entfällt:</dt> <dd class="inline text-beton">diesmal nicht nötig. Die Aufgabe bleibt sichtbar.</dd></div>
                    <div><dt class="inline font-bold">Löschen:</dt> <dd class="inline text-beton">die Aufgabe ganz entfernen, z. B. wenn sie doppelt erfasst ist.</dd></div>
                    <div><dt class="inline font-bold">Kommt nicht mehr:</dt> <dd class="inline text-beton">im Jahresplan auf Nein. Kommt bei neuen Turnieren nicht mehr, der Verlauf bleibt.</dd></div>
                </dl>
            </div>
        </div>
    </div>
</section>
