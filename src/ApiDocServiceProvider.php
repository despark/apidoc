<?php


namespace Despark\Apidoc;

use Illuminate\Support\ServiceProvider;

class ApiDocServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/apidoc.php' => config_path('apidoc.php'),
            __DIR__.'/public/swagger' => public_path('apidoc/swagger'),
            __DIR__.'/storage/appDoc' => storage_path('appDoc'),
        ]);
        // use the vendor configuration file as fallback
        $this->mergeConfigFrom(
            __DIR__.'/config/apidoc.php', 'apidoc'
        );
    
        $this->loadViewsFrom(__DIR__.'/resources/views', 'apidoc');
    
    
        include __DIR__.'/routes.php';
    }
    
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        
        $this->commands($this->registerCommands());
        
    }
    
    protected function registerCommands()
    {
        return [
            'Despark\Apidoc\Commands\APiDocGenerator',
        ];
    }
}