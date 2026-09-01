<?php
/**
 * GLS szállítási provider konfiguráció.
 *
 * Csomagváz: a tényleges GLS API beállítások (kliens azonosító, felhasználó,
 * jelszó, környezet) a szállítási funkció fejlesztésekor kerülnek ide.
 */
return [
    'enabled' => env('COMMERCE_GLS_ENABLED', false),

    'provider_code' => 'gls',

    'default_shipping_method_label' => 'GLS futárszolgálat',

    'log_payloads' => env('COMMERCE_GLS_LOG_PAYLOADS', true),
];
