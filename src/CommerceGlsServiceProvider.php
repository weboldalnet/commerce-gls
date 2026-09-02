<?php

namespace Weboldalnet\CommerceGls;

use Illuminate\Support\ServiceProvider;
use Weboldalnet\CommerceGls\Support\PackageHelper;
use Weboldalnet\CommerceGls\Console\ExtendViewsCommerceGlsCommand;
use Weboldalnet\CommerceGls\Console\InstallCommerceGlsCommand;
use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceGls\Services\GlsService;
use Weboldalnet\CommerceGls\Services\GlsSettingsService;
use Weboldalnet\CommerceGls\Providers\GlsShippingProvider;
use Weboldalnet\CommerceCore\Services\ProviderLogger;
use Weboldalnet\CommerceCore\Managers\ShippingManager;

class CommerceGlsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // route-ok
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../settings/views', PackageHelper::PACKAGE_PREFIX);

        // migrációk (a csomag maga tölti be, ahogy a commerce-core és a webshop is)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Provider regisztráció a commerce-core-ba.
        // Két szállítási módot kínálunk: házhoz szállítás és csomagpont.
        try {
            $manager = $this->app->make(ShippingManager::class);

            $homeCode = config('commerce-gls.provider_code', 'gls');
            $shopCode = config('commerce-gls.parcel_shop_code', 'gls_parcel_shop');

            // Telepített integrációként mindig bejelentkezünk, hogy a webshop
            // beállítófelületén akkor is látszódjon (és onnan visszakapcsolható
            // legyen), ha a modul épp ki van kapcsolva.
            $manager->registerAvailable($homeCode, [
                'name' => config('commerce-gls.default_shipping_method_label', 'GLS futárszolgálat'),
                'settings_url' => '/webshop/gls/settings',
                'settings_label' => 'GLS',
                'kind' => ShippingManager::KIND_HOME_DELIVERY,
            ]);
            $manager->registerAvailable($shopCode, [
                'name' => config('commerce-gls.parcel_shop_label', 'GLS csomagpont'),
                'settings_url' => '/webshop/gls/settings',
                'settings_label' => 'GLS',
                'kind' => ShippingManager::KIND_PARCEL_SHOP,
            ]);

            if (GlsSettingsService::getBool('enabled', false)) {
                $service = $this->app->make(GlsService::class);
                $manager->register($homeCode, new GlsShippingProvider($service, $homeCode, false));

                if (GlsSettingsService::getBool('parcel_shop_delivery_enabled', true)) {
                    $manager->register($shopCode, new GlsShippingProvider($service, $shopCode, true));
                }
            }
        } catch (\Throwable $e) {
            Log::error('GLS regisztrációs hiba: ' . $e->getMessage());
        }

        $publishList = [];
        foreach (PackageHelper::PACKAGE_LIST as $name => $publish) {
            $this->publishes([
                $publish['source'] => base_path($publish['destination']),
            ], PackageHelper::PACKAGE_PREFIX . '-' . $name);

            $publishList[$publish['source']] = base_path($publish['destination']);
        }

        $this->publishes($publishList, PackageHelper::PACKAGE_PREFIX . '-all');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/commerce-gls.php', 'commerce-gls');

        $this->app->singleton(GlsSettingsService::class, function ($app) {
            return new GlsSettingsService();
        });

        $this->app->singleton(GlsService::class, function ($app) {
            return new GlsService(
                $app->make(GlsSettingsService::class),
                $app->make(ProviderLogger::class)
            );
        });

        $this->app->singleton(GlsShippingProvider::class, function ($app) {
            return new GlsShippingProvider($app->make(GlsService::class));
        });

        $this->commands([
            InstallCommerceGlsCommand::class,
        ]);

        $this->commands([
            ExtendViewsCommerceGlsCommand::class,
        ]);
    }
}
