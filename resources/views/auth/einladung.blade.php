<x-gast titel="Einladung">
    <p class="label text-rot">Einladung</p>
    <h1 class="titel mt-1 text-4xl">Hallo {{ strtok($user->name, ' ') }}</h1>
    <p class="mt-2 text-sm text-beton">Schön, dass du im OK dabei bist. Wähle ein Passwort, dann kann es losgehen.</p>

    <form method="POST" action="{{ route('einladung', $token) }}" class="karte mt-6 space-y-4 p-5">
        @csrf
        <div><p class="feld-label">E-Mail</p><p class="text-sm font-bold">{{ $user->email }}</p></div>
        <div>
            <label class="feld-label" for="password">Passwort (mind. 8 Zeichen)</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="feld">
            @error('password')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="feld-label" for="password_confirmation">Passwort wiederholen</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="feld">
        </div>
        <button class="knopf-rot w-full">Los geht's</button>
    </form>
</x-gast>
