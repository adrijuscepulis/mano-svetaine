<?php
// =========================================================================
// OPAY Webhook Callback (Server-to-Server pranešimų apdorojimas)
// ThrowHouse Šiauliai
// =========================================================================

date_default_timezone_set('Europe/Vilnius');
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/opay_helper.php';
require_once __DIR__ . '/email_helper.php';

$rawPost = $_POST;
$rawGet = $_GET;
$rawBody = file_get_contents('php://input');

$logEntry = date('Y-m-d H:i:s') . " | Webhook gautas. POST: " . json_encode($rawPost, JSON_UNESCAPED_UNICODE) . " | GET: " . json_encode($rawGet, JSON_UNESCAPED_UNICODE) . "\n";
@file_put_contents(__DIR__ . '/opay_log.txt', $logEntry, FILE_APPEND);

$cfg = loadOpayConfig();
$signaturePassword = trim($cfg['signature_password'] ?? '');

if (!$signaturePassword) {
    @file_put_contents(__DIR__ . '/opay_log.txt', date('Y-m-d H:i:s') . " | KLAIDA: OPAY pasirašymo slaptažodis nenustatytas serveryje!\n", FILE_APPEND);
    echo "FAIL: NOT_CONFIGURED";
    exit;
}

$inputData = !empty($rawPost) ? $rawPost : (!empty($rawGet) ? $rawGet : []);
if (empty($inputData) && !empty($rawBody)) {
    parse_str($rawBody, $inputData);
}

// Parašo patikra
$verify = verifyOpaySignature($inputData, $signaturePassword);
if (!$verify['valid']) {
    $err = date('Y-m-d H:i:s') . " | KLAIDA: Parašo patikra nepavyko! " . ($verify['error'] ?? '') . "\n";
    @file_put_contents(__DIR__ . '/opay_log.txt', $err, FILE_APPEND);
    echo "FAIL: INVALID_SIGNATURE";
    exit;
}

$data = $verify['data'];
$orderNr = trim($data['order_nr'] ?? '');
$status  = trim((string)($data['status'] ?? ''));
$transId = trim($data['transaction_id'] ?? '');

if (!$orderNr) {
    echo "OK";
    exit;
}

$resFile = __DIR__ . '/rezervacijos.json';
$resDbFile = __DIR__ . '/rezervacijos_db.json';
$all = loadAllReservationsHelper($resFile, $resDbFile);

$foundIndex = -1;
foreach ($all as $idx => $r) {
    if (($r['id'] ?? '') === $orderNr) {
        $foundIndex = $idx;
        break;
    }
}

if ($foundIndex === -1) {
    @file_put_contents(__DIR__ . '/opay_log.txt', date('Y-m-d H:i:s') . " | Pranešimas gautas, bet užsakymas $orderNr nerastas sistemoje.\n", FILE_APPEND);
    echo "OK";
    exit;
}

$reservation = &$all[$foundIndex];
$currentStatus = $reservation['status'] ?? '';

// Statusai:
// '1' = Sėkmingai apmokėta
if ($status === '1') {
    if (strpos($currentStatus, 'Patvirtinta') !== false && strpos($currentStatus, 'OPAY') !== false) {
        // Jau patvirtinta anksčiau
        echo "OK";
        exit;
    }

    $reservation['status'] = 'Patvirtinta (Apmokėta per OPAY)';
    $reservation['paid_at'] = date('Y-m-d H:i:s');
    $reservation['opay_transaction_id'] = $transId;
    $reservation['payment'] = 'OPAY elektroninė bankininkystė';

    saveAllReservationsHelper($resFile, $resDbFile, $all);

    // 1. Siunčiame patvirtinimo HTML laišką klientui
    $clientEmail = $reservation['email'] ?? '';
    if ($clientEmail) {
        $clientHtml = renderNewReservationClientHtml($reservation);
        $clientSubject = "Jūsų rezervacija ThrowHouse Šiauliai [$orderNr] - Apmokėta";
        sendThrowHouseMail($clientEmail, $clientSubject, $clientHtml, 'throwhousee@gmail.com', 'ThrowHouse Šiauliai');
    }

    // 2. Siunčiame informacinį HTML laišką administratoriui
    $adminHtml = renderNewReservationAdminHtml($reservation);
    $adminSubject = "💳 Naujas APMOKĖTAS užsakymas per OPAY [$orderNr]: " . ($reservation['name'] ?? '') . " (" . ($reservation['date'] ?? '') . " " . ($reservation['time'] ?? '') . ")";
    sendThrowHouseMail('throwhousee@gmail.com', $adminSubject, $adminHtml, $clientEmail ?: 'throwhousee@gmail.com', 'ThrowHouse Rezervacijos');

    @file_put_contents(__DIR__ . '/opay_log.txt', date('Y-m-d H:i:s') . " | SĖKMĖ: Užsakymas $orderNr patvirtintas ir apmokėtas per OPAY (TransID: $transId)\n", FILE_APPEND);

    echo "OK";
    exit;

} elseif ($status === '3' || $status === '0') {
    // 3 = Atšaukta, 0 = Pasibaigė laikas
    if ($currentStatus === 'Laukiama apmokėjimo') {
        $reservation['status'] = 'Atšaukta (Neapmokėta per OPAY)';
        saveAllReservationsHelper($resFile, $resDbFile, $all);
        @file_put_contents(__DIR__ . '/opay_log.txt', date('Y-m-d H:i:s') . " | Užsakymas $orderNr pažymėtas kaip atšauktas (Statusas: $status)\n", FILE_APPEND);
    }
    echo "OK";
    exit;
}

// Bet kokiu atveju grąžiname 'OK', kaip reikalauja OPAY specifikacija
echo "OK";
exit;
