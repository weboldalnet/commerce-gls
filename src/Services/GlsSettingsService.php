<?php

namespace Weboldalnet\CommerceGls\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Weboldalnet\CommerceGls\Models\GlsSetting;

/**
 * GLS beállítások: az adatbázisban tárolt (adminból szerkesztett) érték az
 * elsődleges, hiányában a config/.env alapértelmezés érvényes.
 */
class GlsSettingsService
{
    protected static $cacheKey = 'commerce_gls_settings';
    protected static $typeCacheKey = 'commerce_gls_setting_types';

    /**
     * A lapos beállítás-kulcsok leképezése a beágyazott config útvonalakra.
     * Enélkül a config (és így az .env) sosem tudna alapértelmezést adni.
     */
    protected const CONFIG_PATH_MAP = [
        'client_number' => 'authentication.client_number',
        'username' => 'authentication.username',
        'password' => 'authentication.password',
        'label_format' => 'defaults.label_format',
        'default_item_weight' => 'defaults.default_item_weight',
        'parcel_shop_delivery_enabled' => 'defaults.parcel_shop_delivery_enabled',
        'cod_enabled' => 'defaults.cod_enabled',
        'currency' => 'defaults.currency',
        'rate' => 'defaults.rate',
        'free_above' => 'defaults.free_above',
        'parcel_shop_rate' => 'defaults.parcel_shop_rate',
        'sender_name' => 'sender.name',
        'sender_contact_name' => 'sender.contact_name',
        'sender_phone' => 'sender.phone',
        'sender_email' => 'sender.email',
        'sender_zip' => 'sender.zip',
        'sender_city' => 'sender.city',
        'sender_address' => 'sender.address',
        'sender_country' => 'sender.country',
    ];

    /**
     * Az admin beállítófelületen szerkeszthető kulcsok.
     * Ezekre a DB érték hiányában is a tényleges (config/.env) értéket mutatjuk.
     */
    public static function viewKeys(): array
    {
        return [
            'enabled', 'environment', 'country',
            'client_number', 'username', 'password',
            'label_format', 'default_item_weight', 'currency', 'rate', 'free_above', 'parcel_shop_rate',
            'parcel_shop_delivery_enabled', 'cod_enabled',
            'sender_name', 'sender_contact_name', 'sender_phone', 'sender_email',
            'sender_zip', 'sender_city', 'sender_address', 'sender_country',
        ];
    }

    /** Titkosítva tárolandó kulcsok */
    public static function encryptedKeys(): array
    {
        return ['password'];
    }

    /** Logikai (checkbox) kulcsok */
    public static function booleanKeys(): array
    {
        return ['enabled', 'parcel_shop_delivery_enabled', 'cod_enabled'];
    }

    public static function all(): array
    {
        try {
            return Cache::rememberForever(self::$cacheKey, function () {
                return GlsSetting::all()->pluck('value', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Kulcs => típus térkép egyetlen lekérdezésből, cache-elve.
     */
    protected static function types(): array
    {
        try {
            return Cache::rememberForever(self::$typeCacheKey, function () {
                return GlsSetting::all()->pluck('type', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function configDefault($key, $default = null)
    {
        // Előbb lapos kulcs (pl. enabled, environment), utána a leképezett útvonal.
        $flat = config('commerce-gls.' . $key);
        if ($flat !== null) {
            return $flat;
        }

        if (isset(self::CONFIG_PATH_MAP[$key])) {
            $mapped = config('commerce-gls.' . self::CONFIG_PATH_MAP[$key]);
            if ($mapped !== null && $mapped !== '') {
                return $mapped;
            }
        }

        return $default;
    }

    public static function get($key, $default = null)
    {
        $settings = self::all();
        $hasDbValue = array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '';
        $value = $hasDbValue ? $settings[$key] : self::configDefault($key, $default);

        $type = self::types()[$key] ?? null;

        // A titkosítás csak a DB-ben tárolt értékre vonatkozik, a config/.env értéke nyers.
        if ($hasDbValue && $type === 'encrypted' && $value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                return $value;
            }
        }

        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    public static function getBool($key, $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function save($key, $value, $type = 'string', $group = 'general'): void
    {
        if ($type === 'encrypted' && $value) {
            $value = Crypt::encryptString($value);
        }

        GlsSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group]
        );

        self::clearCache();
    }

    /**
     * Van-e elegendő adat a MyGLS hívásokhoz?
     */
    public static function hasCredentials(): bool
    {
        return (string) self::get('client_number') !== ''
            && (string) self::get('username') !== ''
            && (string) self::get('password') !== '';
    }

    /**
     * Meg van-e adva a feladó címe?
     *
     * A MyGLS a PrintLabels hívásnál KÖTELEZŐEN kéri a feladót (PickupAddress) –
     * enélkül a válasz: "13 18 - Pickup Country". Nem igaz tehát, hogy üresen a
     * MyGLS fiókban beállított feladó érvényes.
     */
    public static function hasSenderAddress(): bool
    {
        foreach (['sender_name', 'sender_zip', 'sender_city', 'sender_address'] as $key) {
            if ((string) self::get($key) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * A MyGLS végpont alap-URL-je a környezet és az ország alapján.
     */
    public static function apiBaseUrl(): string
    {
        $environment = self::get('environment', 'test') === 'prod' ? 'prod' : 'test';
        $country = strtolower((string) self::get('country', 'hu')) ?: 'hu';

        $template = config('commerce-gls.endpoints.' . $environment, '');

        return str_replace('{country}', $country, $template);
    }

    public static function clearCache(): void
    {
        Cache::forget(self::$cacheKey);
        Cache::forget(self::$typeCacheKey);
    }
}
