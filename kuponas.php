<?php
// =========================================================================
// ThrowHouse Šiauliai - Dovanų kuponų pirkimas ir apdorojimas (kuponas.php)
// =========================================================================

date_default_timezone_set('Europe/Vilnius');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neteisingas užklausos metodas']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$amount = intval($input['amount'] ?? 30);
if ($amount <= 0) $amount = 30;

$title = htmlspecialchars(trim($input['title'] ?? ($amount >= 50 ? 'KOMBO Kuponas' : 'Pramogos Kuponas')));
$buyerName = htmlspecialchars(trim($input['buyerName'] ?? ''));
$buyerPhone = htmlspecialchars(trim($input['buyerPhone'] ?? ''));
$buyerEmail = filter_var(trim($input['buyerEmail'] ?? ''), FILTER_VALIDATE_EMAIL);

$isDirectGift = !empty($input['isDirectGift']);
$recipientName = htmlspecialchars(trim($input['recipientName'] ?? ''));
$recipientEmail = filter_var(trim($input['recipientEmail'] ?? ''), FILTER_VALIDATE_EMAIL);
$greeting = htmlspecialchars(trim($input['greeting'] ?? ''));

$opayChannel = htmlspecialchars(trim($input['opay_channel'] ?? ''));

// Būtini laukai
if (!$buyerName || !$buyerPhone || !$buyerEmail) {
    echo json_encode(['success' => false, 'message' => 'Prašome užpildyti savo kontaktinius duomenis (vardą, telefoną, el. paštą).']);
    exit;
}

if ($isDirectGift && (!$recipientName || !$recipientEmail)) {
    echo json_encode(['success' => false, 'message' => 'Jei siunčiate dovaną tiesiai gavėjui, nurodykite gavėjo vardą ir el. pašto adresą.']);
    exit;
}

require_once __DIR__ . '/opay_helper.php';
require_once __DIR__ . '/email_helper.php';
require_once __DIR__ . '/pdf_helper.php';

// Tikriname akciją
$settings = [];
if (file_exists(__DIR__ . '/nustatymai_db.json')) {
    $settings = json_decode(@file_get_contents(__DIR__ . '/nustatymai_db.json'), true) ?: [];
} elseif (file_exists(__DIR__ . '/nustatymai.json')) {
    $settings = json_decode(@file_get_contents(__DIR__ . '/nustatymai.json'), true) ?: [];
}
$promo = $settings['promo'] ?? null;
$isPromoActive = !empty($promo['enabled']) && !empty($promo['end_time']) && ($promo['apply_to_coupons'] !== false) && (strtotime($promo['end_time']) > time());
$promoDiscount = $isPromoActive ? floatval($promo['discount'] ?? 5) : 0;
$paidPrice = max(0, $amount - $promoDiscount);

$dataFile = __DIR__ . '/kuponai.json';
$dataDbFile = __DIR__ . '/kuponai_db.json';

// Užkrauname esamus kuponus
$all = [];
if (file_exists($dataDbFile)) {
    $all = json_decode(@file_get_contents($dataDbFile), true) ?: [];
} elseif (file_exists($dataFile)) {
    $all = json_decode(@file_get_contents($dataFile), true) ?: [];
}
$all = is_array($all) ? $all : [];

// Unikalaus kodo generavimas
$existingCodes = [];
foreach ($all as $c) {
    if (!empty($c['code'])) $existingCodes[$c['code']] = true;
}

do {
    $code = 'TH-KUP-' . rand(1000, 9999);
} while (isset($existingCodes[$code]));

$createdDate = date('Y-m-d H:i');
$expiresAt = date('Y-m-d', strtotime('+6 months'));

$opayCfg = loadOpayConfig();
$isOpayActive = !empty($opayCfg['opay_enabled']) && !empty($opayCfg['website_id']) && !empty($opayCfg['signature_password']);

$coupon = [
    'id' => $code,
    'code' => $code,
    'amount' => $amount,
    'paid_price' => $paidPrice,
    'promo_discount' => $promoDiscount,
    'target_type' => $title,
    'buyer' => "$buyerName ($buyerPhone)",
    'buyer_email' => $buyerEmail,
    'recipient' => ($isDirectGift && $recipientName) ? $recipientName : $buyerName,
    'recipient_email' => ($isDirectGift && $recipientEmail) ? $recipientEmail : $buyerEmail,
    'greeting' => ($isDirectGift && $greeting) ? $greeting : '',
    'created_at' => $createdDate,
    'expires_at' => $expiresAt,
    'status' => $isOpayActive ? 'Laukiama apmokėjimo' : 'Aktyvus',
    'payment' => $isOpayActive ? 'OPAY elektroninė bankininkystė' : 'Tiesioginis'
];

// Įrašome į JSON failus
$all[] = $coupon;
$jsonStr = json_encode(array_values($all), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
@file_put_contents($dataFile, $jsonStr, LOCK_EX);
@file_put_contents($dataDbFile, $jsonStr, LOCK_EX);

// =========================================================================
// 1. Jei OPAY aktyvus: nukreipiame pirkėją į elektroninę bankininkystę
// =========================================================================
if ($isOpayActive) {
    $amountCents = (int)round($paidPrice * 100);
    if ($amountCents <= 0) {
        $amountCents = (int)round($amount * 100);
    }

    $baseUrl = getThrowHouseBaseUrl();
    $redirectUrl = $baseUrl . '/opay_return.php';
    $callbackUrl = $baseUrl . '/opay_callback.php';

    $opayRes = createOpayPaymentTransaction(
        $code,
        $amountCents,
        $buyerName,
        $buyerEmail,
        $buyerPhone,
        $redirectUrl,
        $callbackUrl,
        $opayChannel
    );

    if (!empty($opayRes['success']) && !empty($opayRes['redirect_url'])) {
        echo json_encode([
            'success' => true,
            'opay_redirect' => true,
            'redirect_url' => $opayRes['redirect_url'],
            'code' => $code
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nepavyko inicijuoti OPAY apmokėjimo: ' . ($opayRes['error'] ?? 'Nežinoma klaida') . '. Prašome susisiekti tiesiogiai.'
        ]);
        exit;
    }
}

// =========================================================================
// 2. Standartinis tiesioginis patvirtinimas (kai OPAY išjungtas)
// =========================================================================
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
    @file_put_contents(__DIR__ . '/kuponas_log.txt', date('Y-m-d H:i:s') . " | PDF_ERROR: " . $pdfEx->getMessage() . "\n", FILE_APPEND);
}

// 1. Laiškas gavėjui (su prisegtu PDF kuponu)
$targetEmail = ($isDirectGift && $recipientEmail) ? $recipientEmail : $buyerEmail;
$subVoucher = "Jūsų ThrowHouse Šiauliai dovanų kuponas [$code] ($amount €)";
$voucherHtml = renderVoucherHtml($coupon);
sendThrowHouseMail($targetEmail, $subVoucher, $voucherHtml, 'throwhousee@gmail.com', 'ThrowHouse Dovanų Kuponai', $pdfAttachment);

// 2. Jei siuntė draugui, pirkėjas gauna pirkimo kvitą
if ($isDirectGift && $recipientEmail && strcasecmp($buyerEmail, $recipientEmail) !== 0) {
    $subReceipt = "Dovanų kupono pirkimo patvirtinimas [$code] - ThrowHouse Šiauliai";
    $receiptHtml = renderVoucherBuyerReceiptHtml($coupon);
    sendThrowHouseMail($buyerEmail, $subReceipt, $receiptHtml, 'throwhousee@gmail.com', 'ThrowHouse Kuponai');
}

// 3. Pranešimas administratoriui
$subAdmin = "🎁 Naujas dovanų kuponas [$code] ($amount €) - $buyerName";
$adminHtml = renderVoucherAdminHtml($coupon);
sendThrowHouseMail('throwhousee@gmail.com', $subAdmin, $adminHtml, $buyerEmail, 'ThrowHouse Kuponai', $pdfAttachment);

echo json_encode([
    'success' => true,
    'opay_redirect' => false,
    'message' => 'Dovanų kuponas sėkmingai sugeneruotas ir išsiųstas!',
    'coupon' => $coupon
]);
