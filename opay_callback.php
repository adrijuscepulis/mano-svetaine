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

// =========================================================================
// 1. Jei tai dovanų kupono užsakymas (TH-KUP-...)
// =========================================================================
if (strpos($orderNr, 'TH-KUP-') === 0) {
    require_once __DIR__ . '/pdf_helper.php';

    $kupFile = __DIR__ . '/kuponai.json';
    $kupDbFile = __DIR__ . '/kuponai_db.json';

    $coupons = file_exists($kupDbFile) ? (json_decode(@file_get_contents($kupDbFile), true) ?: []) : [];
    if (empty($coupons) && file_exists($kupFile)) {
        $coupons = json_decode(@file_get_contents($kupFile), true) ?: [];
    }

    $foundKupIdx = -1;
    foreach ($coupons as $kIdx => $c) {
        if (($c['code'] ?? '') === $orderNr) {
            $foundKupIdx = $kIdx;
            break;
        }
    }

    if ($foundKupIdx === -1) {
        @file_put_contents(__DIR__ . '/opay_log.txt', date('Y-m-d H:i:s') . " | Kuponas $orderNr nerastas sistemoje.\n", FILE_APPEND);
        echo "OK";
        exit;
    }

    $coupon = &$coupons[$foundKupIdx];
    $cStatus = $coupon['status'] ?? '';

    if ($status === '1') {
        if (strpos($cStatus, 'Aktyvus') !== false && strpos($cStatus, 'OPAY') !== false) {
            echo "OK";
            exit;
        }

        $coupon['status'] = 'Aktyvus (Apmokėta per OPAY)';
        $coupon['paid_at'] = date('Y-m-d H:i:s');
        $coupon['opay_transaction_id'] = $transId;
        $coupon['payment'] = 'OPAY elektroninė bankininkystė';

        $jsonStr = json_encode(array_values($coupons), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        @file_put_contents($kupFile, $jsonStr, LOCK_EX);
        @file_put_contents($kupDbFile, $jsonStr, LOCK_EX);

        $code = $coupon['code'];
        $amount = $coupon['amount'];
        $expiresAt = $coupon['expires_at'];
        $recipientEmail = $coupon['recipient_email'] ?? $coupon['buyer_email'];
        $buyerEmail = $coupon['buyer_email'] ?? '';

        $pdfAttachment = [];
        try {
            $pdfContent = generateThrowHouseCouponPdf($code, $amount, $expiresAt);
            if (!empty($pdfContent)) {
                $pdfAttachment = [
                    [
                        'filename' => "ThrowHouse_Kuponas_{$code}.pdf",
                        'data' => $pdfContent,
                        'type' => 'application/pdf'
                    ]
                ];
            }
        } catch (\Throwable $pdfEx) {
            @file_put_contents(__DIR__ . '/opay_log.txt', date('Y-m-d H:i:s') . " | PDF_ERROR: " . $pdfEx->getMessage() . "\n", FILE_APPEND);
        }

        // 1. Kupono laiškas gavėjui su prisegtu PDF
        $subVoucher = "Jūsų ThrowHouse Šiauliai dovanų kuponas [$code] ($amount €)";
        $voucherHtml = renderVoucherHtml($coupon);
        sendThrowHouseMail($recipientEmail, $subVoucher, $voucherHtml, 'info@throwhouse.lt', 'ThrowHouse Dovanų Kuponai', $pdfAttachment);

        // 2. Jei pirkėjas pirko draugui (skirtingas el. paštas), siunčiame pirkimo patvirtinimo kvitą pirkėjui
        if (!empty($buyerEmail) && strcasecmp($buyerEmail, $recipientEmail) !== 0) {
            $subReceipt = "Dovanų kupono pirkimo patvirtinimas [$code] - ThrowHouse Šiauliai";
            $receiptHtml = renderVoucherBuyerReceiptHtml($coupon);
            sendThrowHouseMail($buyerEmail, $subReceipt, $receiptHtml, 'info@throwhouse.lt', 'ThrowHouse Kuponai');
        }

        // 3. Pranešimas administratoriui su prisegtu PDF
        $emailCfg = loadEmailConfig();
        $adminTarget = !empty($emailCfg['admin_email']) ? $emailCfg['admin_email'] : 'info@throwhouse.lt';
        $subAdmin = "🎁 Naujas APMOKĖTAS dovanų kuponas per OPAY [$code] ($amount €)";
        $adminHtml = renderVoucherAdminHtml($coupon);
        sendThrowHouseMail($adminTarget, $subAdmin, $adminHtml, $buyerEmail ?: 'info@throwhouse.lt', 'ThrowHouse Kuponai', $pdfAttachment);

        @file_put_contents(__DIR__ . '/opay_log.txt', date('Y-m-d H:i:s') . " | SĖKMĖ: Kuponas $orderNr apmokėtas per OPAY (TransID: $transId)\n", FILE_APPEND);
        echo "OK";
        exit;

    } elseif ($status === '3' || $status === '0') {
        if ($cStatus === 'Laukiama apmokėjimo') {
            $coupon['status'] = 'Atšaukta (Neapmokėta per OPAY)';
            $jsonStr = json_encode(array_values($coupons), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            @file_put_contents($kupFile, $jsonStr, LOCK_EX);
            @file_put_contents($kupDbFile, $jsonStr, LOCK_EX);
            @file_put_contents(__DIR__ . '/opay_log.txt', date('Y-m-d H:i:s') . " | Kuponas $orderNr pažymėtas kaip atšauktas (Statusas: $status)\n", FILE_APPEND);
        }
        echo "OK";
        exit;
    }

    echo "OK";
    exit;
}

// =========================================================================
// 2. Jei tai laiko rezervacijos užsakymas (TH-...)
// =========================================================================
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
        sendThrowHouseMail($clientEmail, $clientSubject, $clientHtml, 'info@throwhouse.lt', 'ThrowHouse Šiauliai');
    }

    // 2. Siunčiame informacinį HTML laišką administratoriui
    $emailCfg = loadEmailConfig();
    $adminTarget = !empty($emailCfg['admin_email']) ? $emailCfg['admin_email'] : 'info@throwhouse.lt';
    $adminHtml = renderNewReservationAdminHtml($reservation);
    $adminSubject = "💳 Naujas APMOKĖTAS užsakymas per OPAY [$orderNr]: " . ($reservation['name'] ?? '') . " (" . ($reservation['date'] ?? '') . " " . ($reservation['time'] ?? '') . ")";
    sendThrowHouseMail($adminTarget, $adminSubject, $adminHtml, $clientEmail ?: 'info@throwhouse.lt', 'ThrowHouse Rezervacijos');

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
