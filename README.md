# GLS szállítási provider a commerce-core-hoz

Ez a csomag a GLS futárszolgálat integrációját adja a `weboldalnet/commerce-core` alapú rendszerekhez.

> **Állapot:** csomagváz. A struktúra és az elnevezések készen állnak (composer autoload,
> service provider, publish/extend parancsok, config), a tényleges GLS API integráció
> (`ShippingProviderInterface` implementáció, admin felület, útvonalak) még nincs megírva.

## Telepítés

A projekt `composer.json`-jában:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/weboldalnet/commerce-gls"
    }
]
```

```bash
composer require weboldalnet/commerce-gls:^1.0
```

A service provider Laravel package auto-discovery-vel regisztrálódik
(`Weboldalnet\CommerceGls\CommerceGlsServiceProvider`).

## Konfiguráció

Publikálás a projektbe:

```bash
php artisan commerce-gls:install --tag=commerce-gls-all
php artisan commerce-gls:extend --view=all
```

Publikálható tagek:

| tag | tartalom |
| --- | --- |
| `commerce-gls-routes` | `routes/web.php` → `routes/commerce-gls.php` |
| `commerce-gls-settings` | `settings/` → `settings/commerce-gls` |
| `commerce-gls-config` | `config/commerce-gls.php` |
| `commerce-gls-all` | mindegyik |

`.env` beállítások:

```env
COMMERCE_GLS_ENABLED=false
COMMERCE_GLS_LOG_PAYLOADS=true
```

## Névterek és fájlszerkezet

```
src/CommerceGlsServiceProvider.php          – service provider (publish, route, view betöltés)
src/Console/InstallCommerceGlsCommand.php   – commerce-gls:install
src/Console/ExtendViewsCommerceGlsCommand.php – commerce-gls:extend
src/Support/PackageHelper.php               – publish lista és view kiegészítések
config/commerce-gls.php                     – konfiguráció
routes/web.php                              – útvonalak (egyelőre üres váz)
settings/views/admin/                       – admin sidebar és package-functions blade
```
