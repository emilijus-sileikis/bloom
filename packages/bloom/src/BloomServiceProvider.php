<?php

namespace Emilijus\Bloom;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class BloomServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // Define a custom tag for publishing
        $publishTag = 'bloom';

        // Publish stub files, commands, and assets to the project
        $this->publishes([
            __DIR__ . '/../resources/stubs' => resource_path('stubs'),
            __DIR__ . '/../Commands' => app_path('Console/Commands'),
            __DIR__ . '/../resources/public' => public_path('/'),
        ], $publishTag);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
