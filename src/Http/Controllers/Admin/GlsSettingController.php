<?php

namespace Weboldalnet\CommerceGls\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Weboldalnet\CommerceGls\Services\GlsSettingsService;
use Weboldalnet\CommerceGls\Services\GlsService;

class GlsSettingController extends Controller
{
    public function index()
    {
        // FIGYELEM: a változó neve nem lehet $settings – a platform admin layoutja
        // egy globálisan megosztott $settings modellt használ, azt felülírnánk.
        $glsSettings = GlsSettingsService::all();

        // A DB-ben még nem szereplő mezőknél is a tényleges (config/.env) érték látszódjon
        foreach (GlsSettingsService::viewKeys() as $key) {
            if (!array_key_exists($key, $glsSettings)) {
                $glsSettings[$key] = GlsSettingsService::get($key);
            }
        }

        // Titkosított mezők maszkolása
        foreach (GlsSettingsService::encryptedKeys() as $key) {
            if (!empty($glsSettings[$key])) {
                $glsSettings[$key] = '********';
            }
        }

        return view('commerce-gls::admin.settings', compact('glsSettings'));
    }

    public function update(Request $request)
    {
        $data = $request->all();
        $booleanKeys = GlsSettingsService::booleanKeys();
        $encryptedKeys = GlsSettingsService::encryptedKeys();

        foreach ($data as $key => $value) {
            if ($key === '_token') {
                continue;
            }

            $type = 'string';

            if (in_array($key, $booleanKeys, true)) {
                $type = 'boolean';
                $value = ($value === 'on' || $value === '1' || $value === true);
            } elseif (in_array($key, $encryptedKeys, true)) {
                $type = 'encrypted';
                // A maszkolt értéket nem mentjük vissza
                if ($value === '********') {
                    continue;
                }
            }

            GlsSettingsService::save($key, $value, $type);
        }

        // A be nem küldött checkboxok kikapcsoltnak számítanak
        foreach ($booleanKeys as $key) {
            if (!isset($data[$key])) {
                GlsSettingsService::save($key, false, 'boolean');
            }
        }

        return redirect()->back()->with('success', 'GLS beállítások sikeresen mentve.');
    }

    /**
     * Kapcsolat tesztelése a MyGLS API felé.
     */
    public function testConnection(GlsService $service)
    {
        try {
            return response()->json($service->testConnection());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba a kapcsolódáskor: ' . $e->getMessage(),
            ]);
        }
    }
}
