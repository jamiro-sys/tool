<x-gast titel="Anmelden">
    <p class="label text-rot">Organisationskomitee</p>
    <h1 class="titel mt-1 text-4xl">Anmelden</h1>
    <p class="mt-2 text-sm text-beton">Willkommen zurück im OK-Tool.</p>

    @if (session('erfolg'))<div class="mt-5 border-l-4 border-rot bg-white px-4 py-3 text-sm">{{ session('erfolg') }}</div>@endif

    <form method="POST" action="{{ route('anmelden') }}" class="karte mt-6 space-y-4 p-5">
        @csrf
        <div>
            <label class="feld-label" for="email">E-Mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="feld">
            @error('email')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="feld-label" for="password">Passwort</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="feld">
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="merken" value="1" checked> Angemeldet bleiben</label>
        <button class="knopf-rot w-full">Anmelden</button>
    </form>
    <a href="{{ route('password.request') }}" class="label mt-4 inline-block hover:text-rot">Passwort vergessen?</a>
</x-gast>
