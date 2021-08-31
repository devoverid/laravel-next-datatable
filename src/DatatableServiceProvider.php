<?php

namespace NextDatatable\Datatable;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class DatatableServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'datatable');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'datatable');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('datatable.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/datatable'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/datatable'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/datatable'),
            ], 'lang');*/

            // Registering package commands.
            // $this->commands([]);
        }

        // 
        DB::listen(function ($query) {
            $this->app->make('datatable')->addQueryLog($query);
        });
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'datatable');

        // Register the main class to use with the facade
        $this->app->singleton('datatable', function () {
            $request = app(\Illuminate\Http\Request::class);
            return new Datatable($request);
        });
    }
}
