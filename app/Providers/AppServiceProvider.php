<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
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

        // DVLA-018: off by default, because with every model unguarded the toolkit reports
        // only models.unguarded and DVLA-019 would not show up. Enable with DVLA_UNGUARD_MODELS=true.
        if (env('DVLA_UNGUARD_MODELS', false)) {
            Model::unguard();
        }
    }

    /**
     * Dashboard gates.
     *
     * DVLA-006: intentionally open to every logged-in user.
     * DVLA-007: Log Viewer has no gate on purpose; config/log-viewer.php sets
     * require_auth_in_production to false, so it is open to everyone.
     */
    protected function configureDashboards(): void
    {
        Gate::define('viewPulse', function (User $user) {
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
