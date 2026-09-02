<?php

namespace Weboldalnet\CommerceGls\Support;

use Weboldalnet\CommerceCore\Data\ShipmentRequestData;
use Weboldalnet\CommerceGls\Services\GlsSettingsService;

/**
 * A commerce-core ShipmentRequestData átfordítása MyGLS "Parcel" payloaddá.
 *
 * A mezőnevek a MyGLS ParcelService szerződését követik:
 *   Parcel:  ClientNumber, ClientReference, CODAmount, CODReference, Content,
 *            Count, DeliveryAddress, PickupAddress, PickupDate, ServiceList
 *   Address: Name, Street, HouseNumber, HouseNumberInfo, City, ZipCode,
 *            CountryIsoCode, ContactName, ContactPhone, ContactEmail
 */
class GlsParcelBuilder
{
    /**
     * Egy csomag payloadja a rendelésből.
     *
     * @param ShipmentRequestData $data
     * @param array $options  parcel_shop_id, cod_amount, content, count, pickup_date
     */
    public static function fromShipmentRequest(ShipmentRequestData $data, array $options = []): array
    {
        $shipping = is_array($data->shippingData) ? $data->shippingData : [];

        $parcel = [
            'ClientNumber' => (int) GlsSettingsService::get('client_number'),
            'ClientReference' => (string) ($data->orderNumber ?: $data->orderId),
            'Content' => self::content($data, $options),
            'Count' => (int) ($options['count'] ?? 1),
            'PickupDate' => self::jsonDate($options['pickup_date'] ?? null),
            'DeliveryAddress' => self::deliveryAddress($data, $shipping),
            'ServiceList' => [],
        ];

        // Feladó: csak akkor küldjük, ha az adminban meg van adva. Üresen a
        // MyGLS fiókban beállított feladó érvényes.
        $pickup = self::pickupAddress();
        if ($pickup !== null) {
            $parcel['PickupAddress'] = $pickup;
        }

        // Utánvét
        $codAmount = (float) ($options['cod_amount'] ?? 0);
        if ($codAmount > 0 && GlsSettingsService::getBool('cod_enabled', true)) {
            $parcel['CODAmount'] = $codAmount;
            $parcel['CODReference'] = (string) ($data->orderNumber ?: $data->orderId);
        }

        // Csomagpontos kézbesítés (PSD): a kiválasztott csomagpont azonosítójával
        $parcelShopId = $options['parcel_shop_id'] ?? ($shipping['parcel_shop_id'] ?? null);
        if ($parcelShopId && GlsSettingsService::getBool('parcel_shop_delivery_enabled', true)) {
            $parcel['ServiceList'][] = [
                'Code' => 'PSD',
                'PSDParameter' => ['StringValue' => (string) $parcelShopId],
            ];
        }

        return $parcel;
    }

    /**
     * A csomag tartalmának rövid leírása (a címkére kerül).
     */
    protected static function content(ShipmentRequestData $data, array $options): string
    {
        if (!empty($options['content'])) {
            return mb_substr((string) $options['content'], 0, 60);
        }

        $names = [];
        foreach ((array) $data->items as $item) {
            if (!empty($item['name'])) {
                $names[] = $item['name'];
            }
        }

        $content = $names ? implode(', ', $names) : ('Rendelés ' . ($data->orderNumber ?: $data->orderId));

        return mb_substr($content, 0, 60);
    }

    /**
     * Kézbesítési cím a rendelés szállítási adataiból.
     */
    protected static function deliveryAddress(ShipmentRequestData $data, array $shipping): array
    {
        [$street, $houseNumber, $houseInfo] = self::splitStreet((string) ($shipping['address'] ?? ''));

        return [
            'Name' => (string) ($shipping['name'] ?? $data->customerName ?? ''),
            'Street' => $street,
            'HouseNumber' => $houseNumber,
            'HouseNumberInfo' => (string) ($shipping['address_info'] ?? $houseInfo),
            'City' => (string) ($shipping['city'] ?? ''),
            'ZipCode' => (string) ($shipping['zip'] ?? ''),
            'CountryIsoCode' => strtoupper((string) ($shipping['country'] ?? 'HU')),
            'ContactName' => (string) ($data->customerName ?? ''),
            'ContactPhone' => (string) ($data->customerPhone ?? ''),
            'ContactEmail' => (string) ($data->customerEmail ?? ''),
        ];
    }

    /**
     * Feladó cím az admin beállításokból, vagy null, ha nincs kitöltve.
     */
    protected static function pickupAddress(): ?array
    {
        $name = (string) GlsSettingsService::get('sender_name');
        $city = (string) GlsSettingsService::get('sender_city');
        $zip = (string) GlsSettingsService::get('sender_zip');
        $address = (string) GlsSettingsService::get('sender_address');

        if ($name === '' || $city === '' || $zip === '' || $address === '') {
            return null;
        }

        [$street, $houseNumber, $houseInfo] = self::splitStreet($address);

        return [
            'Name' => $name,
            'Street' => $street,
            'HouseNumber' => $houseNumber,
            'HouseNumberInfo' => $houseInfo,
            'City' => $city,
            'ZipCode' => $zip,
            'CountryIsoCode' => strtoupper((string) (GlsSettingsService::get('sender_country') ?: 'HU')),
            'ContactName' => (string) (GlsSettingsService::get('sender_contact_name') ?: $name),
            'ContactPhone' => (string) GlsSettingsService::get('sender_phone'),
            'ContactEmail' => (string) GlsSettingsService::get('sender_email'),
        ];
    }

    /**
     * A magyar webshopok egy mezőben kérik az utcát és a házszámot, a GLS
     * viszont külön várja (Street / HouseNumber / HouseNumberInfo).
     *
     * "Rózsa u. 17."        -> ["Rózsa u.", "17", ""]
     * "Váralja utca 2/A"    -> ["Váralja utca", "2/A", ""]
     * "Petőfi u 5, 3. em."  -> ["Petőfi u", "5", "3. em."]
     *
     * @return array{0:string,1:string,2:string} utca, házszám, kiegészítés
     */
    public static function splitStreet(string $full): array
    {
        $full = trim($full);
        if ($full === '') {
            return ['', '', ''];
        }

        // Vessző utáni rész (emelet, ajtó) külön információ
        $info = '';
        if (str_contains($full, ',')) {
            $parts = explode(',', $full, 2);
            $full = trim($parts[0]);
            $info = trim($parts[1]);
        }

        // A végén álló szám (és a hozzá tapadt rövid kiegészítés, pl. 2/A) a házszám.
        // A záró pont nem része a házszámnak.
        if (preg_match('/^(.*?)\s+([0-9]+[^\s.]{0,10})\.?$/u', $full, $m)) {
            return [trim($m[1]), trim($m[2]), $info];
        }

        return [$full, '', $info];
    }

    /**
     * .NET JSON dátumformátum, amit a MyGLS vár: /Date(ezredmásodperc)/
     */
    public static function jsonDate($date = null): string
    {
        $timestamp = $date instanceof \DateTimeInterface
            ? $date->getTimestamp()
            : ($date ? strtotime((string) $date) : time());

        return '/Date(' . ($timestamp * 1000) . ')/';
    }
}
