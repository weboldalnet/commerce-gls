@extends('admin.layouts.layout')
@section('title', 'GLS beállítások')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="header-box my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-0">GLS beállítások</h1>
                        <p class="text-muted small mb-0">MyGLS API integráció konfigurálása</p>
                    </div>
                    <div>
                        <button type="button" id="gls-test-connection-btn" class="btn btn-warning font-weight-bold">
                            <i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése
                        </button>
                        <div id="gls-test-connection-result" class="mt-2 mb-0 d-none"></div>
                    </div>
                </div>
            </div>

            @include('admin.webshop.partials.alerts')

            <form action="{{ route('admin.webshop.gls.settings.update') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Modul állapota</div>
                        <div class="content-box bordered mb-3">
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" id="enabled" name="enabled"
                                       @if(filter_var($glsSettings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                                <label class="custom-control-label fw-600" for="enabled">GLS modul engedélyezve</label>
                            </div>
                            <div class="alert alert-info mb-0 py-2 px-3 small">
                                <i class="fa fa-info-circle mr-1"></i>
                                A bekapcsoláshoz ki kell tölteni az ügyfélszámot, a felhasználónevet és a jelszót.
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Környezet</div>
                        <div class="content-box bordered mb-3">
                            <div class="form-group mb-3">
                                <label class="fw-600">API környezet</label>
                                <select name="environment" id="environment" class="form-control">
                                    <option value="test" @if(($glsSettings['environment'] ?? 'test') === 'test') selected @endif>Teszt (sandbox)</option>
                                    <option value="prod" @if(($glsSettings['environment'] ?? '') === 'prod') selected @endif>Éles</option>
                                </select>
                                <span class="text-muted fs-14">Teszt módban az api.test.mygls.* végpont hívódik.</span>
                            </div>
                            <div class="form-group mb-0">
                                <label class="fw-600">Ország</label>
                                <select name="country" class="form-control">
                                    @foreach(['hu' => 'Magyarország', 'sk' => 'Szlovákia', 'cz' => 'Csehország', 'ro' => 'Románia', 'si' => 'Szlovénia', 'hr' => 'Horvátország'] as $code => $label)
                                        <option value="{{ $code }}" @if(($glsSettings['country'] ?? 'hu') === $code) selected @endif>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <span class="text-muted fs-14">A MyGLS végpont országfüggő.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="header-box product-info mb-1">Hitelesítés (MyGLS)</div>
                <div class="content-box bordered mb-3">
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="fw-600">Ügyfélszám (Client Number)</label>
                                <input type="text" name="client_number" class="form-control"
                                       value="{{ $glsSettings['client_number'] ?? '' }}" placeholder="pl. 100000001">
                                <span class="text-muted fs-14">A GLS szerződésedhez tartozó ügyfélszám.</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="fw-600">Felhasználónév</label>
                                <input type="text" name="username" class="form-control"
                                       value="{{ $glsSettings['username'] ?? '' }}" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="fw-600">Jelszó</label>
                                <input type="password" name="password" class="form-control"
                                       value="{{ $glsSettings['password'] ?? '' }}" autocomplete="new-password">
                                <span class="text-muted fs-14">Titkosítva tárolódik. Üresen hagyva a korábbi marad.</span>
                            </div>
                        </div>
                    </div>
                </div>

                @php
                    // A MyGLS nem ad díjkalkulációt, ezért a fix díj az egyetlen forrás.
                    $glsHasRate = isset($glsSettings['rate']) && $glsSettings['rate'] !== '' && is_numeric($glsSettings['rate']);
                    $glsEnabled = filter_var($glsSettings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                @endphp

                <div class="header-box product-info mb-1">Szállítási díjak</div>
                <div class="content-box bordered mb-3">
                    @if($glsEnabled && !$glsHasRate)
                        <div class="alert alert-warning py-2 px-3 small">
                            <i class="fa fa-exclamation-triangle mr-1"></i>
                            A GLS modul be van kapcsolva, de nincs megadva szállítási díj –
                            a pénztár jelenleg <strong>0 Ft</strong> szállítási díjjal számol.
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="fw-600">Házhoz szállítás díja</label>
                                <input type="text" name="rate" class="form-control"
                                       value="{{ $glsSettings['rate'] ?? '' }}" placeholder="pl. 1490">
                                <span class="text-muted fs-14">Üresen hagyva 0 díjjal számol.</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="fw-600">Csomagpont díja</label>
                                <input type="text" name="parcel_shop_rate" class="form-control"
                                       value="{{ $glsSettings['parcel_shop_rate'] ?? '' }}" placeholder="pl. 990">
                                <span class="text-muted fs-14">Üresen a házhoz szállítás díja érvényes.</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="fw-600">Ingyenes szállítás felett</label>
                                <input type="text" name="free_above" class="form-control"
                                       value="{{ $glsSettings['free_above'] ?? '' }}" placeholder="pl. 25000">
                                <span class="text-muted fs-14">Ekkora kosárérték felett nincs díj. Üresen nincs ilyen határ.</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="fw-600">Pénznem</label>
                                <select name="currency" class="form-control">
                                    @foreach(['HUF' => 'HUF – forint', 'EUR' => 'EUR – euró', 'RON' => 'RON – lej', 'CZK' => 'CZK – korona', 'PLN' => 'PLN – złoty'] as $v => $l)
                                        <option value="{{ $v }}" @if(($glsSettings['currency'] ?? 'HUF') === $v) selected @endif>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-0 py-2 px-3 small">
                        <i class="fa fa-info-circle mr-1"></i>
                        A MyGLS API nem ad díjkalkulációt – a díjszabás szerződésfüggő –, ezért
                        a webshop az itt megadott fix díjakkal számol.
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Csomag beállítások</div>
                        <div class="content-box bordered mb-3">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Címkeformátum</label>
                                        <select name="label_format" class="form-control">
                                            @foreach(['A4' => 'A4', 'A5' => 'A5', 'Thermo' => 'Hőnyomtató'] as $v => $l)
                                                <option value="{{ $v }}" @if(($glsSettings['label_format'] ?? 'A5') === $v) selected @endif>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Alapértelmezett súly (kg)</label>
                                        <input type="text" name="default_item_weight" class="form-control"
                                               value="{{ $glsSettings['default_item_weight'] ?? '1' }}">
                                        <span class="text-muted fs-14">Ha a terméknél nincs megadva súly.</span>
                                    </div>
                                </div>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="parcel_shop_delivery_enabled" name="parcel_shop_delivery_enabled"
                                       @if(filter_var($glsSettings['parcel_shop_delivery_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                                <label class="custom-control-label" for="parcel_shop_delivery_enabled">Csomagpontos kézbesítés</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-0">
                                <input type="checkbox" class="custom-control-input" id="cod_enabled" name="cod_enabled"
                                       @if(filter_var($glsSettings['cod_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                                <label class="custom-control-label" for="cod_enabled">Utánvét GLS-en keresztül</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Feladó adatai (a címkére kerül)</div>
                        <div class="content-box bordered mb-3">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Név / cégnév</label>
                                        <input type="text" name="sender_name" class="form-control" value="{{ $glsSettings['sender_name'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Kapcsolattartó</label>
                                        <input type="text" name="sender_contact_name" class="form-control" value="{{ $glsSettings['sender_contact_name'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Telefonszám</label>
                                        <input type="text" name="sender_phone" class="form-control" value="{{ $glsSettings['sender_phone'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">E-mail cím</label>
                                        <input type="text" name="sender_email" class="form-control" value="{{ $glsSettings['sender_email'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="fw-600">Irsz.</label>
                                        <input type="text" name="sender_zip" class="form-control" value="{{ $glsSettings['sender_zip'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-8">
                                    <div class="form-group">
                                        <label class="fw-600">Város</label>
                                        <input type="text" name="sender_city" class="form-control" value="{{ $glsSettings['sender_city'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label class="fw-600">Utca, házszám</label>
                                        <input type="text" name="sender_address" class="form-control" value="{{ $glsSettings['sender_address'] ?? '' }}">
                                        <input type="hidden" name="sender_country" value="{{ $glsSettings['sender_country'] ?? 'HU' }}">
                                        <span class="text-muted fs-14">Üresen hagyva a MyGLS fiókban beállított feladó érvényes.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3 mb-5">
                    <button type="submit" class="btn btn-primary fs-18 font-weight-bold px-5">
                        <i class="fa fa-save mr-1"></i> Beállítások mentése
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('gls-test-connection-btn');
        var out = document.getElementById('gls-test-connection-result');

        // Beágyazott visszajelzés natív alert() helyett: az blokkolja a lapot.
        function show(isSuccess, message) {
            out.className = 'mt-2 mb-0 alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
            out.textContent = message;
        }

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Tesztelés...';
            out.className = 'mt-2 mb-0 d-none';
            out.textContent = '';

            fetch('{{ route("admin.webshop.gls.test-connection") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                show(!!data.success, (data.success ? 'Sikeres kapcsolat: ' : 'Hiba: ') + (data.message || ''));
            })
            .catch(function () {
                show(false, 'Váratlan hiba történt a tesztelés során.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése';
            });
        });
    });
</script>
@endsection
