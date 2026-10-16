<?php

namespace App\Providers;

use App\Models\Salon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by($r->ip()), Limit::perMinute(5)->by(strtolower($r->input('email', '')).'|'.$r->ip())]);
        RateLimiter::for('booking', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        View::composer(['layouts.*', 'public.*', 'admin.*', 'auth.*'], function ($view): void {
            $view->with('salon', Salon::current());
        });
    }
}
