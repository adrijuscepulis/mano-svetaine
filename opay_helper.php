<?php
// =========================================================================
// OPAY Mokėjimų Integracijos Pagalbinis Modulis (ThrowHouse Šiauliai)
// Oficiali specifikacija: OPAY Public API v1 (gateway.opay.lt)
// =========================================================================

date_default_timezone_set('Europe/Vilnius');

$opayConfigFile   = __DIR__ . '/opay_config.json';
$opayConfigDbFile = __DIR__ . '/opay_config_db.json';

/**
 * Užkrauna OPAY konfigūraciją iš failų su dvigubu saugumu
 */
function loadOpayConfig() {
    global $opayConfigFile, $opayConfigDbFile;

    $defaults = [
        'opay_enabled'       => false,
        'sandbox'            => false,
        'website_id'         => '8W2228Y2ZH',
        'signature_password' => ''
    ];

    $cfg1 = file_exists($opayConfigFile) ? (json_decode(@file_get_contents($opayConfigFile), true) ?: []) : [];
    $cfg2 = file_exists($opayConfigDbFile) ? (json_decode(@file_get_contents($opayConfigDbFile), true) ?: []) : [];

    $merged = array_merge($defaults, $cfg1, $cfg2);
    return $merged;
}

/**
 * Išsaugo OPAY konfigūraciją į abu failus
 */
function saveOpayConfig($cfg) {
    global $opayConfigFile, $opayConfigDbFile;

    $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (file_exists($opayConfigFile)) @chmod($opayConfigFile, 0666);
    if (file_exists($opayConfigDbFile)) @chmod($opayConfigDbFile, 0666);

    $r1 = @file_put_contents($opayConfigFile, $json, LOCK_EX);
    if ($r1 === false) $r1 = @file_put_contents($opayConfigFile, $json);

    $r2 = @file_put_contents($opayConfigDbFile, $json, LOCK_EX);
    if ($r2 === false) $r2 = @file_put_contents($opayConfigDbFile, $json);

    if (file_exists($opayConfigFile)) @chmod($opayConfigFile, 0666);
    if (file_exists($opayConfigDbFile)) @chmod($opayConfigDbFile, 0666);

    return ($r1 !== false || $r2 !== false);
}

/**
 * Sukuria simetrinį parašą (password_signature) pagal OPAY taisykles:
 * Visi parametrai sujungiami paeiliui 'raktasReikšmė' be skyriklių,
 * gale pridedamas pasirašymo slaptažodis ir apskaičiuojamas md5().
 */
function buildOpaySignature(array $params, $signaturePassword) {
    $str = '';
    foreach ($params as $key => $value) {
        $str .= (string)$key . (string)$value;
    }
    return md5($str . (string)$signaturePassword);
}

/**
 * Inicijuoja mokėjimo transakciją per OPAY Public API v1
 *
 * @param string $orderNr Užsakymo ID (pvz. TH-A1B2C3)
 * @param int $amountCents Suma centais (pvz. 3000 = 30.00 EUR)
 * @param string $clientName Kliento vardas
 * @param string $clientEmail Kliento el. paštas
 * @param string $clientPhone Kliento telefonas
 * @param string $redirectUrl Grįžtamasis adresas naršyklei po apmokėjimo
 * @param string $callbackUrl Server-to-server webhook pranešimų adresas
 * @param string $passThroughChannel Pasirinktas bankas (pvz. pis_swedbank, pis_seb ir kt.)
 * @return array Rezultatas ['success' => bool, 'redirect_url' => string, 'error' => string]
 */
function createOpayPaymentTransaction($orderNr, $amountCents, $clientName, $clientEmail, $clientPhone, $redirectUrl, $callbackUrl, $passThroughChannel = '') {
    $cfg = loadOpayConfig();

    if (empty($cfg['opay_enabled'])) {
        return ['success' => false, 'error' => 'OPAY mokėjimai yra išjungti nustatymuose.'];
    }

    $websiteId = trim($cfg['website_id'] ?? '');
    $signaturePassword = trim($cfg['signature_password'] ?? '');

    if (!$websiteId || !$signaturePassword) {
        return ['success' => false, 'error' => 'Nenurodytas OPAY Svetainės ID arba Pasirašymo slaptažodis.'];
    }

    $isSandbox = !empty($cfg['sandbox']);
    $endpoint = $isSandbox 
        ? 'https://sandbox-api.opay.eu/api/createtransaction/' 
        : 'https://gateway.opay.lt/api/createtransaction/';

    // Pagrindiniai OPAY parametrai griežta tvarka
    $params = [
        'standard'            => 'opay_8.1',
        'website_id'          => $websiteId,
        'order_nr'            => (string)$orderNr,
        'amount'              => (int)$amountCents,
        'currency'            => 'EUR',
        'country'             => 'LT',
        'language'            => 'lit',
        'redirect_url'        => $redirectUrl,
        'web_service_url'     => $callbackUrl,
        'redirect_on_success' => 1
    ];

    if (!empty($clientEmail)) {
        $params['c_email'] = trim($clientEmail);
    }
    if (!empty($clientName)) {
        $params['c_name'] = trim($clientName);
    }
    if (!empty($clientPhone)) {
        $params['c_mobile_nr'] = trim($clientPhone);
    }

    // Tiesioginis nukreipimas į pirkėjo pasirinktą banką
    if (!empty($passThroughChannel)) {
        $params['pass_through_channel_name'] = trim($passThroughChannel);
        $params['pass_through_only'] = 0;
    }

    // Apskaičiuojame skaitmeninį parašą
    $params['password_signature'] = buildOpaySignature($params, $signaturePassword);

    // Siunčiame užklausą cURL metodu
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($resp === false || !empty($curlError)) {
        $logMsg = date('Y-m-d H:i:s') . " | cURL Klaida: $curlError | Endpoint: $endpoint | Order: $orderNr\n";
        @file_put_contents(__DIR__ . '/opay_log.txt', $logMsg, FILE_APPEND);
        return ['success' => false, 'error' => "Nepavyko prisijungti prie OPAY serverio: $curlError"];
    }

    $logEntry = date('Y-m-d H:i:s') . " | CreateTransaction ($orderNr) | HTTP $httpCode | Resp: " . substr($resp, 0, 500) . "\n";
    @file_put_contents(__DIR__ . '/opay_log.txt', $logEntry, FILE_APPEND);

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return ['success' => false, 'error' => "Neteisingas OPAY serverio atsakymas (HTTP $httpCode): " . substr($resp, 0, 200)];
    }

    // Patikriname gautą nukreipimo URL
    $redirectOut = $json['response']['result']['redirect_url'] ?? $json['response']['redirect_url'] ?? $json['redirect_url'] ?? '';
    $transId = $json['response']['result']['transaction_id'] ?? $json['response']['transaction_id'] ?? $json['transaction_id'] ?? '';

    if (!empty($redirectOut)) {
        return [
            'success'        => true,
            'redirect_url'   => $redirectOut,
            'transaction_id' => $transId
        ];
    }

    // Klaidos pranešimo formavimas
    $err = 'OPAY mokėjimo klaida';
    if (!empty($json['errors'])) {
        $err .= ': ' . (is_array($json['errors']) ? json_encode($json['errors'], JSON_UNESCAPED_UNICODE) : $json['errors']);
    } elseif (!empty($json['message'])) {
        $err .= ': ' . $json['message'];
    } elseif (!empty($json['response']['message'])) {
        $err .= ': ' . $json['response']['message'];
    }

    return ['success' => false, 'error' => $err, 'raw' => $json];
}

/**
 * Patikrina įeinančio OPAY pranešimo (callback ar return) parašą
 *
 * @param array $inputData $_POST arba $_GET masyvas
 * @param string $signaturePassword Slaptasis pasirašymo slaptažodis
 * @return array ['valid' => bool, 'data' => array, 'error' => string]
 */
function verifyOpaySignature(array $inputData, $signaturePassword) {
    $parameters = [];

    // OPAY gali siųsti duomenis 'encoded' parametre base64 pavidalu
    if (!empty($inputData['encoded'])) {
        $row = $inputData['encoded'];
        $decoded = base64_decode(strtr($row, ['-' => '+', '_' => '/', ',' => '=']));
        parse_str($decoded, $parameters);
    } else {
        $parameters = $inputData;
    }

    if (empty($parameters)) {
        return ['valid' => false, 'data' => [], 'error' => 'Tušti duomenys'];
    }

    $receivedSignature = $parameters['password_signature'] ?? '';
    if (!$receivedSignature) {
        return ['valid' => false, 'data' => $parameters, 'error' => 'Trūksta password_signature lauko'];
    }

    // Pašaliname parašų laukus prieš skaičiavimą
    $paramsToSign = $parameters;
    unset($paramsToSign['password_signature'], $paramsToSign['rsa_signature']);

    $calculatedSignature = buildOpaySignature($paramsToSign, $signaturePassword);

    $isValid = hash_equals(strtolower($calculatedSignature), strtolower($receivedSignature));

    return [
        'valid' => $isValid,
        'data'  => $parameters,
        'error' => $isValid ? '' : 'Nesutampa apskaičiuotas ir gautas skaitmeninis parašas'
    ];
}

/**
 * Pagalbinė funkcija: užkrauna visas rezervacijas iš failų
 */
function loadAllReservationsHelper($dataFile, $dataDbFile) {
    $main = file_exists($dataFile) ? (json_decode(@file_get_contents($dataFile), true) ?: []) : [];
    $db = file_exists($dataDbFile) ? (json_decode(@file_get_contents($dataDbFile), true) ?: []) : [];

    if (count($db) > count($main)) {
        $data = $db;
        saveReservationsHelper($dataFile, $data);
    } elseif (!empty($main)) {
        $data = $main;
        saveReservationsHelper($dataDbFile, $data);
    } else {
        $data = !empty($db) ? $db : [];
    }
    return is_array($data) ? $data : [];
}

/**
 * Pagalbinė funkcija: išsaugo visas rezervacijas
 */
function saveReservationsHelper($file, $data) {
    if (file_exists($file)) @chmod($file, 0666);
    $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $res = @file_put_contents($file, $json, LOCK_EX);
    if ($res === false) $res = @file_put_contents($file, $json);
    if ($res !== false) @chmod($file, 0666);
    return $res !== false;
}

function saveAllReservationsHelper($dataFile, $dataDbFile, $data) {
    $r1 = saveReservationsHelper($dataFile, $data);
    $r2 = saveReservationsHelper($dataDbFile, $data);
    return ($r1 || $r2);
}

/**
 * Grąžina žmogui suprantamą banko pavadinimą pagal OPAY kanalo raktą
 */
function getOpayChannelTitle($channelName) {
    $titles = [
        'pis_swedbank' => 'Swedbank',
        'pis_seb'      => 'SEB',
        'pis_dnb'      => 'Luminor',
        'pis_citadele' => 'Citadele',
        'pis_sb'       => 'Artea',
        'pis_medbank'  => 'Urbo',
        'pis_lku'      => 'Lietuvos centrinė kredito unija (LKU)',
        'pis_paysera'  => 'Paysera',
        'pis_revolut'  => 'Revolut',
        'pis_wise'     => 'Wise',
        'pis_n26'      => 'N26',
        ''             => 'OPAY (Kitas būdas)'
    ];
    return $titles[$channelName] ?? ($channelName ?: 'OPAY');
}
