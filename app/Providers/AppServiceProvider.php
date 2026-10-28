<?php

namespace App\Providers;

use App\Models\Salon;
use App\Services\Branding;
use App\Services\OperationsHealth;
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
        View::composer(['errors.*', 'errors::*'], function (\Illuminate\View\View $view): void {
            $context = request()->attributes->get('error_contact');
            if (! is_array($context)) {
                try {
                    $salon = request()->attributes->get('current_salon') ?? Salon::query()->first(['name', 'phone', 'email']);
                } catch (\Throwable) {
                    $salon = null;
                }
                $context = ['errorSalonName' => $salon?->name, 'errorPhone' => $salon?->phone, 'errorEmail' => $salon?->email];
                request()->attributes->set('error_contact', $context);
            }
            $view->with($context);
        });
        View::composer('layouts.admin', function ($view): void {
            $view->with('operationWarnings', auth()->user()?->role === 'owner' ? app(OperationsHealth::class)->warnings() : []);
        });
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by($r->ip()), Limit::perMinute(5)->by(strtolower($r->input('email', '')).'|'.$r->ip())]);
        RateLimiter::for('booking', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('booking-form', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('booking-submission', fn (Request $request) => [
            Limit::perMinute(8)->by('minute:'.$request->ip()),
            Limit::perHour(30)->by('hour:'.$request->ip()),
        ]);
        RateLimiter::for('password-reset-link', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email) ? mb_strtolower(trim($email)) : '';

            return [Limit::perHour(5)->by('reset-ip:'.$request->ip()), Limit::perHour(5)->by('reset-email:'.hash('sha256', $email))];
        });
        RateLimiter::for('password-reset-save', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        View::composer(['layouts.*', 'public.*', 'admin.*', 'auth.*'], function ($view): void {
            $salon = Salon::current();
            $branding = app(Branding::class);
            $view->with('salon', $salon)->with('brand', $branding->forSalon($salon))->with('demoUrls', $branding->demoUrls())->with('demoSalesContactUrl', $branding->demoSalesContactUrl());
        });
    }
}
