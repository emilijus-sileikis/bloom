<?php

namespace Roland\Crud;

use Illuminate\Support\ServiceProvider;

class CrudServiceProvider extends ServiceProvider
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

    	$stubsPath = resource_path('views/stubs');

    	if (!File::exists($stubsPath)) {
        	// If the 'stubs' directory doesn't exist, create it
        	File::makeDirectory($stubsPath, 0755, true);
    	}

        $this->publishes([
            __DIR__ . '/Config/bloomConfig.php' => config_path('bloomConfig.php'),
        ]);

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        
          if ($this->app->runningInConsole()) {
                $this->commands([
                    Commands\BloomInstall::class,
                    Commands\BloomCreate::class,
                    Commands\BloomDelete::class,
                    
                ]);
            }

     }

}