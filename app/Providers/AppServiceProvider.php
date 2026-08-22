<?php

namespace App\Providers;

use App\Models\ShopSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Keyed by email+IP (not just IP) so a shared-IP shop network doesn't lock
        // everyone out at once, and so Faker's randomized test emails naturally
        // avoid throttle bleed between unrelated feature tests.
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Shares the shop's name/address/phone/logo with every view that renders
        // shop branding, so it's set once here instead of threaded through every
        // controller that returns one of these views.
        View::composer(
            ['layouts.app', 'components.nav', 'auth.login', 'transactions.pdf', 'reports.pdf', 'pos.create'],
            fn ($view) => $view->with('shopSetting', ShopSetting::current())
        );
    }
}
