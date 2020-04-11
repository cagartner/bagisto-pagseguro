<?php

namespace Cagartner\Pagseguro\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class PagseguroServiceProvider
 * @package Cagartner\Pagseguro\Providers
 */
class PagseguroServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        include __DIR__ . '/../Http/routes.php';

        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'pagseguro');

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/system.php', 'core'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/paymentmethods.php', 'paymentmethods'
        );
    }
}
