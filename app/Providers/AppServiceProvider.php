<?php

namespace App\Providers;

use App\Listeners\VersandProtokollieren;
use App\Models\Aufgabe;
use App\Models\Ressort;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('de_CH');

        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(\App\Models\Verknuepfung::BEZUEGE);

        \Illuminate\Support\Facades\View::composer('components.app', function ($view) {
            $user = auth()->user();
            $view->with('navAusgabe', $user ? \App\Models\Ausgabe::naechste() : null);
            $view->with('ungelesen', $user ? $user->unreadNotifications()->count() : 0);
            $view->with('navRessorts', $user ? \App\Models\Ressort::orderBy('sortierung')->orderBy('name')->get(['id', 'name', 'kuerzel']) : collect());
        });

        Event::listen(NotificationSent::class, VersandProtokollieren::class);
        Event::listen(NotificationFailed::class, VersandProtokollieren::class);

        // Admin darf alles.
        Gate::before(fn (User $user) => $user->istAdmin() ? true : null);

        Gate::define('admin', fn (User $user) => false);

        Gate::define('schreiben', fn (User $user) => ! $user->istLesend());

        Gate::define('ressort-verwalten', fn (User $user, Ressort $ressort) => $user->leitetRessort($ressort));

        Gate::define('ressort-mitarbeiten', fn (User $user, Ressort $ressort) => ! $user->istLesend() && $user->gehoertZuRessort($ressort));

        Gate::define('vorlagen-verwalten', fn (User $user) => $user->ressorts->contains(fn ($r) => $r->pivot->rolle === 'leitung'));

        Gate::define('ausgaben-verwalten', fn (User $user) => false);

        Gate::define('aufgabe-bearbeiten', function (User $user, Aufgabe $aufgabe) {
            if ($user->istLesend()) {
                return false;
            }

            return $aufgabe->verantwortlich_id === $user->id
                || $aufgabe->begleitung_id === $user->id
                || $user->gehoertZuRessort($aufgabe->ressort_id);
        });

        Gate::define('aufgabe-verwalten', fn (User $user, Aufgabe $aufgabe) => $user->leitetRessort($aufgabe->ressort_id));
    }
}
