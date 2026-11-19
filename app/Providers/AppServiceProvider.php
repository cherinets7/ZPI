<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\Navigation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('navItems', $user ? Navigation::items($user) : []);
            $view->with('unreadNotifications', $user ? $user->unreadNotifications()->latest()->limit(8)->get() : collect());
            $view->with('institutionName', Setting::institutionName());
            $logo = null;
            try {
                $logo = Setting::getValue('logo_path');
            } catch (\Throwable) {
                $logo = null;
            }
            $view->with('institutionLogo', $logo);
        });
    }
}
