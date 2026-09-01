<?php

namespace Weboldalnet\CommerceGls;

use Illuminate\Support\ServiceProvider;
use Weboldalnet\CommerceGls\Support\PackageHelper;
use Weboldalnet\CommerceGls\Console\ExtendViewsCommerceGlsCommand;
use Weboldalnet\CommerceGls\Console\InstallCommerceGlsCommand;

class CommerceGlsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // route-ok
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../settings/views', PackageHelper::PACKAGE_PREFIX);

        // migrációk
        //$this->loadMigrationsFrom(__DIR__.'/../database/migrations');

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

        $this->commands([
            InstallCommerceGlsCommand::class,
        ]);

        $this->commands([
            ExtendViewsCommerceGlsCommand::class,
        ]);
    }
}
