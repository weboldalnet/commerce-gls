<?php

namespace Weboldalnet\CommerceGls\Providers;

use Illuminate\Support\Facades\Storage;
use Weboldalnet\CommerceCore\Contracts\ShippingProviderInterface;
use Weboldalnet\CommerceCore\Data\ShipmentCreateResult;
use Weboldalnet\CommerceCore\Data\ShipmentRequestData;
use Weboldalnet\CommerceCore\Data\ShippingRateRequestData;
use Weboldalnet\CommerceCore\Data\ShippingRateResult;
use Weboldalnet\CommerceCore\Status\ShippingStatus;
use Weboldalnet\CommerceGls\Services\GlsService;
use Weboldalnet\CommerceGls\Services\GlsSettingsService;
use Weboldalnet\CommerceGls\Support\GlsParcelBuilder;

class GlsShippingProvider implements ShippingProviderInterface
{
    protected $service;

    /** A szállítási mód kódja (gls vagy gls_parcel_shop) */
    protected $code;

    /** Csomagpontos kézbesítés-e ez a példány */
    protected $isParcelShop;

    public function __construct(GlsService $service = null, string $code = null, bool $isParcelShop = false)
    {
        $this->service = $service ?: app(GlsService::class);
        $this->code = $code ?: config('commerce-gls.provider_code', 'gls');
        $this->isParcelShop = $isParcelShop;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getName()
    {
        if ($this->isParcelShop) {
            return config('commerce-gls.parcel_shop_label', 'GLS csomagpont');
        }

        return config('commerce-gls.default_shipping_method_label', 'GLS futárszolgálat');
    }

    /**
     * Csomagpontos mód-e – a pénztár ez alapján dönti el, kell-e a választó.
     */
    public function isParcelShop(): bool
    {
        return $this->isParcelShop;
    }

    /**
     * A szállítási mód jellege: ez alapján csoportosítja az admin és a pénztár
     * (házhoz szállítás vs. csomagpont).
     */
    public function getKind(): string
    {
        return $this->isParcelShop
            ? \Weboldalnet\CommerceCore\Managers\ShippingManager::KIND_PARCEL_SHOP
            : \Weboldalnet\CommerceCore\Managers\ShippingManager::KIND_HOME_DELIVERY;
    }

    /**
     * Szállítási díj.
     *
     * A MyGLS API nem ad díjkalkulációt – a díjszabás szerződésfüggő –, ezért az
     * adminban (Webshop → GLS) megadott fix díjjal és ingyenes-határral számolunk.
     */
    public function calculate(ShippingRateRequestData $data)
    {
        $currency = (string) (GlsSettingsService::get('currency') ?: 'HUF');
        $rate = $this->resolveRate();

        // Ingyenes-határ: csak akkor él, ha ténylegesen meg van adva.
        $freeAbove = self::toAmount(GlsSettingsService::get('free_above'));
        $isFree = $freeAbove !== null && (float) $data->cartTotal >= $freeAbove;

        if ($rate === null) {
            // Nincs megadva díj: nem találunk ki árat, de jelezzük, mert így a
            // vásárló ingyen kapja a szállítást.
            \Illuminate\Support\Facades\Log::warning(
                'GLS: nincs beállítva szállítási díj, a rendelés 0 díjjal számol.',
                ['shipping_method' => $this->getCode()]
            );
        }

        $amount = ($isFree || $rate === null) ? 0.0 : $rate;

        return ShippingRateResult::success([
            'provider' => $this->getCode(),
            'shipping_method' => $this->getCode(),
            'rate' => $amount,
            'currency' => $currency,
            'is_free' => $isFree || $rate === null,
            'message' => $this->rateMessage($rate, $isFree, $amount, $currency),
        ]);
    }

    /**
     * A módhoz tartozó fix díj, vagy null, ha nincs megadva.
     *
     * Csomagpontos módnál a saját díj az elsődleges; ha az nincs kitöltve, a
     * házhoz szállítás díja érvényes – így elég egyetlen díjat megadni.
     */
    protected function resolveRate(): ?float
    {
        if ($this->isParcelShop) {
            $parcelShopRate = self::toAmount(GlsSettingsService::get('parcel_shop_rate'));

            if ($parcelShopRate !== null) {
                return $parcelShopRate;
            }
        }

        return self::toAmount(GlsSettingsService::get('rate'));
    }

    /**
     * Beállítás-érték pénzösszeggé alakítása.
     * Üres/nem szám érték esetén null – vagyis "nincs megadva", nem 0 Ft.
     */
    protected static function toAmount($value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected function rateMessage(?float $rate, bool $isFree, float $amount, string $currency): string
    {
        if ($isFree) {
            return 'Ingyenes szállítás.';
        }

        if ($rate === null) {
            return 'Nincs beállítva GLS szállítási díj, ezért a rendszer 0 díjjal számol.';
        }

        return 'GLS szállítási díj: ' . rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',') . ' ' . $currency;
    }

    /**
     * Csomagfeladás: címke generálása a MyGLS-nél, majd a PDF letárolása.
     */
    public function createShipment(ShipmentRequestData $data)
    {
        if (!GlsSettingsService::hasCredentials()) {
            return ShipmentCreateResult::failure([
                'status' => ShippingStatus::FAILED,
                'provider' => $this->getCode(),
                'message' => 'Hiányzó MyGLS hozzáférési adatok. Töltsd ki a Webshop → GLS beállításokat.',
            ]);
        }

        $shipping = is_array($data->shippingData) ? $data->shippingData : [];
        $parcelShopId = $data->extra['parcel_shop_id'] ?? ($shipping['parcel_shop_id'] ?? null);

        // Csomagpontos módnál az azonosító kötelező – enélkül a GLS nem tudja,
        // melyik átvevőpontra kell vinni a csomagot.
        if ($this->isParcelShop && !$parcelShopId) {
            return ShipmentCreateResult::failure([
                'status' => ShippingStatus::FAILED,
                'provider' => $this->getCode(),
                'message' => 'Csomagpontos szállításhoz hiányzik a kiválasztott átvevőpont azonosítója.',
            ]);
        }

        $parcel = GlsParcelBuilder::fromShipmentRequest($data, [
            'cod_amount' => $data->extra['cod_amount'] ?? 0,
            'parcel_shop_id' => $this->isParcelShop ? $parcelShopId : null,
            'count' => $data->extra['parcel_count'] ?? 1,
        ]);

        $result = $this->service->printLabels([$parcel], $data->orderId);

        if (!$result['success']) {
            return ShipmentCreateResult::failure([
                'status' => ShippingStatus::FAILED,
                'provider' => $this->getCode(),
                'message' => $result['message'] ?: 'A GLS címke létrehozása sikertelen.',
            ]);
        }

        $parcelNumber = $result['parcel_numbers'][0] ?? null;
        $labelPath = $this->storeLabel($result['label_pdf'], $data, $parcelNumber);

        return ShipmentCreateResult::success([
            'status' => ShippingStatus::PREPARED,
            'provider' => $this->getCode(),
            'tracking_number' => $parcelNumber,
            'tracking_url' => $parcelNumber ? GlsService::trackingUrl($parcelNumber) : null,
            'label_path' => $labelPath,
            'message' => $parcelNumber
                ? 'GLS csomag feladva, címke elkészült.'
                : 'A GLS elfogadta a feladást, de nem adott vissza csomagszámot.',
            'raw_response' => [
                'parcel_numbers' => $result['parcel_numbers'],
                'parcel_ids' => $result['parcel_ids'],
            ],
            'extra' => ['parcel_ids' => $result['parcel_ids']],
        ]);
    }

    public function getTrackingUrl($trackingNumber)
    {
        return $trackingNumber ? GlsService::trackingUrl((string) $trackingNumber) : null;
    }

    /**
     * A címke PDF letárolása a privát tárhelyre.
     * A fájl csak hitelesített admin route-on keresztül érhető el.
     */
    protected function storeLabel(?string $pdf, ShipmentRequestData $data, ?string $parcelNumber): ?string
    {
        if (!$pdf) {
            return null;
        }

        $basePath = trim((string) config('commerce-gls.storage.base_path', 'private/commerce-gls'), '/');
        $labelDir = trim((string) config('commerce-gls.storage.label_path', 'labels'), '/');

        $name = $parcelNumber ?: ($data->orderNumber ?: $data->orderId);

        // A teljes, 'local' diszkhez képesti útvonalat tároljuk, hogy a címke
        // letöltése provider-független lehessen az admin szállítmány-listában.
        $path = $basePath . '/' . $labelDir . '/gls-'
            . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $name) . '.pdf';

        try {
            Storage::disk('local')->put($path, $pdf);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('GLS címke mentése sikertelen: ' . $e->getMessage());

            return null;
        }

        return $path;
    }
}
