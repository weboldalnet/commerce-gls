<?php

namespace Weboldalnet\CommerceGls\Support;

class PackageHelper
{
    const PACKAGE_NAME = 'GLS szállítási modul';
    const PACKAGE_PREFIX = 'commerce-gls';

    const PACKAGE_LIST = [
        'routes' => [
            'name' => 'routes | routes/web.php',
            'source' => __DIR__.'/../../routes/web.php',
            'destination' => '/routes/commerce-gls.php',
        ],
        'settings' => [
            'name' => 'settings | settings/',
            'source' => __DIR__.'/../../settings',
            'destination' => '/settings/commerce-gls',
        ],
        'config' => [
            'name' => 'config | config/commerce-gls.php',
            'source' => __DIR__.'/../../config/commerce-gls.php',
            'destination' => '/config/commerce-gls.php',
        ],
    ];

    const PACKAGE_VIEW_EXTENDS = [
        'sidebar' => [
            'view_path' => '/resources/views/admin/package-container/admin-p-sidebar.blade.php',
            'include' => "@include('" . self::PACKAGE_PREFIX . "::admin.sidebar')"
        ],
        'package-settings' => [
            'view_path' => '/resources/views/admin/package-settings/package-settings-container.blade.php',
            'include' => "@include('" . self::PACKAGE_PREFIX . "::admin.package-functions')"
        ],
    ];
}
