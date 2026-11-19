<x-gast titel="Neues Passwort">
    <p class="label text-rot">Zugang</p>
    <h1 class="titel mt-1 text-4xl">Neues Passwort</h1>
    <form method="POST" action="{{ route('password.update') }}" class="karte mt-6 space-y-4 p-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label class="feld-label" for="email">E-Mail</label><input id="email" type="email" name="email" required class="feld" value="{{ old('email', $email) }}">
            @error('email')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror</div>
        <div><label class="feld-label" for="password">Neues Passwort</label><input id="password" type="password" name="password" required class="feld" autocomplete="new-password">
            @error('password')<p class="mt-1 text-xs font-bold text-rot">{{ $message }}</p>@enderror</div>
        <div><label class="feld-label" for="password_confirmation">Wiederholen</label><input id="password_confirmation" type="password" name="password_confirmation" required class="feld" autocomplete="new-password"></div>
        <button class="knopf-rot w-full">Speichern</button>
    </form>
</x-gast>
