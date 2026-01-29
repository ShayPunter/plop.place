<?php

namespace App\Providers;

use App\Services\CanvasService;
use App\Services\CooldownService;
use App\Services\PixelService;
use Illuminate\Support\ServiceProvider;

class CanvasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CanvasService::class, function ($app) {
            return new CanvasService();
        });

        $this->app->singleton(CooldownService::class, function ($app) {
            return new CooldownService();
        });

        $this->app->singleton(PixelService::class, function ($app) {
            return new PixelService(
                $app->make(CanvasService::class),
                $app->make(CooldownService::class)
            );
        });
    }

    public function boot(): void
    {
        // Initialize canvas on boot if running in a web context
        if (!$this->app->runningInConsole()) {
            try {
                $this->app->make(CanvasService::class)->initializeCanvas();
            } catch (\Exception $e) {
                // Redis might not be available during some operations
            }
        }
    }
}
