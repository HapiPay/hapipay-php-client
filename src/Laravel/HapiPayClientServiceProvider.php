<?php

namespace HapiPay\Client\Laravel;

use HapiPay\Client\HapiPay;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Laravel : le client s'injecte (HapiPay $hapipay) avec la clé de
 * config/hapipay-client.php ; le middleware VerifyHapiPayWebhook protège la
 * route qui reçoit les webhooks.
 */
class HapiPayClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/hapipay-client.php', 'hapipay-client');

        $this->app->singleton(HapiPay::class, function (Application $app): HapiPay {
            $config = (array) $app['config']->get('hapipay-client');

            if (blank($config['api_key'] ?? null)) {
                throw new RuntimeException('HAPIPAY_CLIENT_API_KEY n\'est pas réglée.');
            }

            return new HapiPay((string) $config['api_key'], [
                'base_url' => (string) ($config['base_url'] ?? HapiPay::DEFAULT_BASE_URL),
                'timeout' => (float) ($config['timeout'] ?? 30),
            ]);
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../../config/hapipay-client.php' => config_path('hapipay-client.php')], 'hapipay-client');

        $this->app['router']->aliasMiddleware('hapipay.webhook', VerifyHapiPayWebhook::class);
    }
}
