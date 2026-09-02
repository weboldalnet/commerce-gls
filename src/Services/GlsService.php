<?php

namespace Weboldalnet\CommerceGls\Services;

use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceCore\Services\ProviderLogger;

/**
 * MyGLS API kliens.
 *
 * A MyGLS JSON végpontja metódusonként külön URL-t használ
 * (…/ParcelService.svc/json/<Metodus>), a jelszót pedig SHA-512 nyers
 * byte tömbként várja – ezt itt intézzük, hogy a hívók ne találkozzanak vele.
 */
class GlsService
{
    protected $settings;

    /** @var ProviderLogger|null */
    protected $logger;

    public function __construct(GlsSettingsService $settings = null, ProviderLogger $logger = null)
    {
        $this->settings = $settings ?: new GlsSettingsService();

        try {
            $this->logger = $logger ?: app(ProviderLogger::class);
        } catch (\Throwable $e) {
            $this->logger = null;
        }
    }

    /**
     * A jelszó SHA-512 byte tömbként, ahogy a MyGLS várja.
     */
    protected function passwordBytes(): array
    {
        $password = (string) GlsSettingsService::get('password');

        return array_values(unpack('C*', hash('sha512', $password, true)));
    }

    /**
     * Minden MyGLS kérés alap-mezői.
     */
    protected function basePayload(): array
    {
        return [
            'Username' => (string) GlsSettingsService::get('username'),
            'Password' => $this->passwordBytes(),
        ];
    }

    /**
     * Nyers MyGLS hívás. Sosem dob – tömböt ad vissza
     * ['success' => bool, 'data' => array|null, 'message' => string|null].
     */
    public function call(string $method, array $payload = [], $orderId = null): array
    {
        if (!GlsSettingsService::hasCredentials()) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Hiányzó MyGLS hozzáférési adatok (ügyfélszám, felhasználónév, jelszó).',
            ];
        }

        $url = rtrim(GlsSettingsService::apiBaseUrl(), '/') . '/' . $method;
        $body = array_merge($this->basePayload(), $payload);

        $this->log($method, $payload, null, true, null, $orderId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $this->log($method, $payload, null, false, $curlError, $orderId);

            return ['success' => false, 'data' => null, 'message' => 'Hálózati hiba: ' . $curlError];
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $this->log($method, $payload, ['raw' => mb_substr($raw, 0, 500)], false, 'Nem JSON válasz', $orderId);

            return ['success' => false, 'data' => null, 'message' => 'A GLS válasza nem értelmezhető (HTTP ' . $httpCode . ').'];
        }

        // A MyGLS a hibákat metódusonként eltérő kulcs alatt adja vissza:
        // pl. GetParcelListErrors, PrintLabelsErrorList
        $errors = [];
        foreach ($json as $key => $value) {
            if (is_array($value) && $value !== [] && preg_match('/Error(s|List)?$/i', $key)) {
                $errors = $value;
                break;
            }
        }

        if ($errors !== []) {
            $first = is_array($errors[0] ?? null) ? $errors[0] : [];
            $message = trim(($first['ErrorCode'] ?? '') . ' ' . ($first['ErrorDescription'] ?? ''));
            $this->log($method, $payload, $json, false, $message, $orderId);

            return ['success' => false, 'data' => $json, 'message' => $message ?: 'Ismeretlen GLS hiba.'];
        }

        $this->log($method, $payload, $json, true, null, $orderId);

        return ['success' => true, 'data' => $json, 'message' => null];
    }

    /**
     * Címke nyomtatása (csomagfeladás). Ez hozza létre a csomagot a GLS-nél.
     *
     * @param array $parcels GlsParcelBuilder által előállított csomagok
     * @return array ['success','parcel_numbers','parcel_ids','label_pdf','message']
     */
    public function printLabels(array $parcels, $orderId = null): array
    {
        $payload = [
            'ParcelList' => array_values($parcels),
            'PrintPosition' => 1,
            'ShowPrintDialog' => false,
            // A MyGLS kötelezően kéri, hogy a hívó webshop-rendszer azonosítsa
            // magát. Enélkül a válasz: "56 Webshop engine is required!".
            // A kérés GYÖKERÉBE kell, nem a csomag objektumba.
            'WebshopEngine' => (string) GlsSettingsService::get('webshop_engine', 'weboldalnet'),
        ];

        // A címkeformátumot csak akkor küldjük, ha meg van adva
        $printer = (string) GlsSettingsService::get('label_format');
        if ($printer !== '') {
            $payload['TypeOfPrinter'] = self::mapPrinterType($printer);
        }

        $result = $this->call('PrintLabels', $payload, $orderId);

        if (!$result['success']) {
            return [
                'success' => false,
                'parcel_numbers' => [],
                'parcel_ids' => [],
                'label_pdf' => null,
                'message' => $result['message'],
            ];
        }

        $data = $result['data'] ?? [];
        $numbers = [];
        $ids = [];

        foreach ((array) ($data['PrintLabelsInfoList'] ?? []) as $info) {
            if (!empty($info['ParcelNumber'])) {
                $numbers[] = (string) $info['ParcelNumber'];
            }
            if (!empty($info['ParcelId'])) {
                $ids[] = (int) $info['ParcelId'];
            }
        }

        return [
            'success' => true,
            'parcel_numbers' => $numbers,
            'parcel_ids' => $ids,
            // A MyGLS a PDF-et byte tömbként adja vissza
            'label_pdf' => self::bytesToBinary($data['Labels'] ?? null),
            'message' => null,
        ];
    }

    /**
     * Csomag státuszainak lekérdezése.
     */
    public function getParcelStatuses(string $parcelNumber, bool $returnPod = false, string $language = 'HU'): array
    {
        return $this->call('GetParcelStatuses', [
            'ParcelNumber' => (int) $parcelNumber,
            'ReturnPOD' => $returnPod,
            'LanguageIsoCode' => $language,
        ]);
    }

    /**
     * Már kinyomtatott címkék újra letöltése (nem hoz létre új csomagot).
     */
    public function getPrintedLabels(array $parcelIds, $orderId = null): array
    {
        $result = $this->call('GetPrintedLabels', [
            'ParcelIdList' => array_values(array_map('intval', $parcelIds)),
            'PrintPosition' => 1,
            'ShowPrintDialog' => false,
        ], $orderId);

        if (!$result['success']) {
            return ['success' => false, 'label_pdf' => null, 'message' => $result['message']];
        }

        return [
            'success' => true,
            'label_pdf' => self::bytesToBinary($result['data']['Labels'] ?? null),
            'message' => null,
        ];
    }

    /**
     * Még fel nem adott csomag törlése (sztornó).
     */
    public function deleteLabels(array $parcelIds, $orderId = null): array
    {
        return $this->call('DeleteLabels', [
            'ParcelIdList' => array_values(array_map('intval', $parcelIds)),
        ], $orderId);
    }

    /**
     * Utánvét összegének módosítása egy már feladott csomagon.
     */
    public function modifyCod(string $parcelNumber, float $codAmount, string $reference = '', $orderId = null): array
    {
        return $this->call('ModifyCOD', [
            'ParcelNumber' => (int) $parcelNumber,
            'CODAmount' => $codAmount,
            'CODReference' => $reference,
        ], $orderId);
    }

    /**
     * Nyomkövetési URL egy csomagszámhoz.
     */
    public static function trackingUrl(string $parcelNumber): string
    {
        $country = strtolower((string) GlsSettingsService::get('country', 'hu')) ?: 'hu';

        return 'https://gls-group.eu/' . strtoupper($country) . '/' . $country
            . '/csomagkovetes?match=' . urlencode($parcelNumber);
    }

    /**
     * A MyGLS byte tömbként adja vissza a PDF-et – bináris sztringgé alakítjuk.
     */
    protected static function bytesToBinary($bytes): ?string
    {
        if (!is_array($bytes) || $bytes === []) {
            return null;
        }

        return implode('', array_map('chr', $bytes));
    }

    /**
     * Az adminban választható formátum leképezése a MyGLS TypeOfPrinter értékére.
     */
    protected static function mapPrinterType(string $format): string
    {
        switch (strtoupper($format)) {
            case 'A4':
                return 'A4_2x2';
            case 'THERMO':
                return 'Thermo';
            case 'A5':
            default:
                return 'A4_4x1';
        }
    }

    /**
     * Kapcsolat és hitelesítés ellenőrzése – ártalmatlan, csak lekérdez.
     */
    public function testConnection(): array
    {
        $now = time() * 1000;
        $from = $now - 24 * 3600 * 1000;

        $result = $this->call('GetParcelList', [
            'PickupDateFrom' => '/Date(' . $from . ')/',
            'PickupDateTo' => '/Date(' . $now . ')/',
        ]);

        if ($result['success']) {
            return ['success' => true, 'message' => 'Sikeres kapcsolat a MyGLS-sel.'];
        }

        return ['success' => false, 'message' => $result['message']];
    }

    /**
     * Naplózás a commerce_provider_logs táblába. Sosem buktatja el a hívást.
     * A jelszó szándékosan nem része a naplózott payloadnak.
     */
    protected function log($endpoint, $request, $response, $isSuccess, $errorMessage = null, $orderId = null): void
    {
        if (!$this->logger || !config('commerce-gls.log_payloads', true)) {
            return;
        }

        try {
            $this->logger->logResponse(
                'shipping',
                config('commerce-gls.provider_code', 'gls'),
                $endpoint,
                $request,
                $response,
                $isSuccess ? 200 : 400,
                (bool) $isSuccess,
                $errorMessage,
                is_numeric($orderId) ? (int) $orderId : null
            );
        } catch (\Throwable $e) {
            Log::warning('GLS provider log hiba: ' . $e->getMessage());
        }
    }
}
