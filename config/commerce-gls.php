<?php
/**
 * GLS (MyGLS) szállítási provider konfiguráció.
 *
 * A hitelesítő adatok az .env-ből jönnek alapértelmezésként, de az admin
 * felületen megadott (titkosítva tárolt) értékek felülírják őket – ugyanaz a
 * minta, mint a commerce-szamlazzhu csomagnál.
 */
return [
    'enabled' => env('COMMERCE_GLS_ENABLED', false),

    'provider_code' => 'gls',

    'default_shipping_method_label' => 'GLS futárszolgálat',

    // Csomagpontos kézbesítés külön szállítási módként
    'parcel_shop_code' => 'gls_parcel_shop',
    'parcel_shop_label' => 'GLS csomagpont',

    /*
    |--------------------------------------------------------------------------
    | Környezet és ország
    |--------------------------------------------------------------------------
    |
    | environment: 'test' (sandbox) vagy 'prod' (éles)
    | country: a MyGLS végpont országkódja (hu, sk, cz, ro, si, hr)
    |
    */
    'environment' => env('COMMERCE_GLS_ENVIRONMENT', 'test'),
    'country' => env('COMMERCE_GLS_COUNTRY', 'hu'),

    /*
    |--------------------------------------------------------------------------
    | MyGLS API hitelesítés
    |--------------------------------------------------------------------------
    |
    | A MyGLS a jelszót SHA-512 byte tömbként várja – ezt a szolgáltatás réteg
    | végzi, ide a nyers jelszó kerül.
    |
    | Az admin felületen megadott értékek – titkosítva tárolva – felülírják
    | ezeket, ugyanúgy, mint a Számlázz.hu csomagnál. Így éles környezetben
    | nem kell .env-hez nyúlni a hozzáférés megadásához.
    |
    */
    'authentication' => [
        'client_number' => env('COMMERCE_GLS_CLIENT_NUMBER', ''),
        'username' => env('COMMERCE_GLS_USERNAME', ''),
        'password' => env('COMMERCE_GLS_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | MyGLS végpontok
    |--------------------------------------------------------------------------
    |
    | Az ország- és környezetfüggő alap-URL. A szolgáltatás réteg ebből és a
    | hívott metódus nevéből állítja össze a végleges végpontot.
    |
    */
    'endpoints' => [
        'test' => 'https://api.test.mygls.{country}/ParcelService.svc/json/',
        'prod' => 'https://api.mygls.{country}/ParcelService.svc/json/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Alapértelmezett szállítási beállítások
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        // Címkeformátum: A4, A5 vagy Thermo
        'label_format' => 'A5',
        // Ha a terméknél nincs megadva súly, ez az érték érvényes darabonként (kg)
        'default_item_weight' => 1.0,
        // Csomagpontos kézbesítés engedélyezése (külön GLS szolgáltatás!)
        'parcel_shop_delivery_enabled' => true,
        // Utánvét engedélyezése GLS-en keresztül
        'cod_enabled' => true,
        'currency' => env('COMMERCE_GLS_CURRENCY', 'HUF'),

        /*
        | Fix szállítási díjak.
        |
        | A MyGLS API nem ad díjkalkulációt (a díjszabás szerződésfüggő), ezért
        | a webshop fix díjjal számol. Ezek CSAK alapértelmezések: az admin
        | felületen (Webshop → GLS) megadott érték mindig erősebb.
        |
        | Üresen hagyva a rendszer nem talál ki árat – 0 Ft-tal számol –, ezért
        | a beállítófelület figyelmeztet, ha a modul be van kapcsolva, de nincs
        | díj megadva.
        */
        // Házhoz szállítás díja
        'rate' => env('COMMERCE_GLS_RATE', null),
        // Csomagpontos szállítás díja (üresen a házhoz szállítás díja érvényes)
        'parcel_shop_rate' => env('COMMERCE_GLS_PARCEL_SHOP_RATE', null),
        // E kosárérték felett ingyenes a szállítás (üresen nincs ilyen határ)
        'free_above' => env('COMMERCE_GLS_FREE_ABOVE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feladó (a címkére kerül)
    |--------------------------------------------------------------------------
    |
    | Az admin felületen felülbírálható. Üresen hagyva a MyGLS fiókban
    | beállított feladó adatok érvényesek.
    |
    */
    'sender' => [
        'name' => '',
        'contact_name' => '',
        'phone' => '',
        'email' => '',
        'zip' => '',
        'city' => '',
        'address' => '',
        'country' => 'HU',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tárolási útvonalak (Laravel Storage-on belül)
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'base_path' => 'private/commerce-gls',
        'label_path' => 'labels',
        'log_path' => 'logs',
    ],

    'log_payloads' => env('COMMERCE_GLS_LOG_PAYLOADS', true),
];
