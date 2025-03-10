<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Inicializar o SDK do Mercado Pago
        MercadoPagoConfig::setAccessToken(env('ACESS_TOKEN_MP')); // Use o token correto
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
