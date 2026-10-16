<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDashboards();
    }

    /**
     * Dashboard gates.
     *
     * DVLA-006 and DVLA-007: intentionally open to every logged-in user.
     */
    protected function configureDashboards(): void
    {
        Gate::define('viewPulse', function (User $user) {
            return true;
        });

        Gate::define('viewLogViewer', function (User $user) {
            return true;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // DVLA-012, DVLA-013: short passwords allowed, leaked passwords not rejected
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(6)
            : null,
        );
    }
}
