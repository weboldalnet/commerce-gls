<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GLS beállítások tábla.
 *
 * A MyGLS hozzáférés (ügyfélszám, felhasználónév, jelszó) és a feladó adatai
 * az admin felületről is megadhatók legyenek, ne csak .env-ből – így éles
 * környezetben nem kell fájlhoz nyúlni. A jelszó titkosítva tárolódik.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('public.commerce_gls_settings')) {
            Schema::create('public.commerce_gls_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                // string, boolean, integer, json, encrypted
                $table->string('type')->default('string');
                $table->string('group')->default('general');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public.commerce_gls_settings');
    }
};
