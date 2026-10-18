<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neteisingas užklausos metodas']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$amount = intval($input['amount'] ?? 30);
$giftTarget = trim($input['giftTarget'] ?? 'friend'); // 'friend' arba 'myself'
$buyerName = htmlspecialchars(trim($input['buyerName'] ?? ''));
$buyerPhone = htmlspecialchars(trim($input['buyerPhone'] ?? ''));
$buyerEmail = filter_var(trim($input['buyerEmail'] ?? ''), FILTER_VALIDATE_EMAIL);

$recipientName = htmlspecialchars(trim($input['recipientName'] ?? ''));
$friendEmail = filter_var(trim($input['friendEmail'] ?? ''), FILTER_VALIDATE_EMAIL);
$greeting = htmlspecialchars(trim($input['greeting'] ?? ''));

if (!$buyerName || !$buyerPhone || !$buyerEmail) {
    echo json_encode(['success' => false, 'message' => 'Prašome užpildyti savo kontaktinius duomenis (vardą, telefoną, el. paštą).']);
    exit;
}

if ($giftTarget === 'friend') {
    if (!$recipientName || !$friendEmail) {
        echo json_encode(['success' => false, 'message' => 'Prašome nurodyti dovanos gavėjo (draugo) vardą ir el. paštą.']);
        exit;
    }
}

$code = 'TH-KUP-' . rand(1000, 9999);
$createdDate = date('Y-m-d H:i');
$expiresAt = date('Y-m-d', strtotime('+6 months'));

// Jei perka sau: gavėjas yra pats pirkėjas, sveikinimo nenaudojame
if ($giftTarget === 'myself') {
    $targetName = $buyerName;
    $targetEmail = $buyerEmail;
    $greeting = '';
    $targetLabel = 'Pirkta sau / įteiks pats';
} else {
    $targetName = $recipientName;
    $targetEmail = $friendEmail;
    $targetLabel = 'Dovana draugui (išsiųsta tiesiai)';
}

$coupon = [
    'code' => $code,
    'amount' => $amount,
    'target_type' => $targetLabel,
    'buyer' => "$buyerName ($buyerPhone)",
    'buyer_email' => $buyerEmail,
    'recipient' => $targetName,
    'recipient_email' => $targetEmail,
    'greeting' => $greeting,
    'created_at' => $createdDate,
    'expires_at' => $expiresAt,
    'status' => 'Aktyvus'
];

require_once __DIR__ . '/email_helper.php';

$dataFile = __DIR__ . '/kuponai.json';
$dataDbFile = __DIR__ . '/kuponai_db.json';

// Dvifazis saugojimas (apsauga nuo perrašymo)
$all = [];
if (file_exists($dataDbFile)) {
    $all = json_decode(@file_get_contents($dataDbFile), true) ?: [];
} elseif (file_exists($dataFile)) {
    $all = json_decode(@file_get_contents($dataFile), true) ?: [];
}
$all[] = $coupon;
$jsonStr = json_encode(array_values($all), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
@file_put_contents($dataFile, $jsonStr, LOCK_EX);
@file_put_contents($dataDbFile, $jsonStr, LOCK_EX);

$isFriend = ($giftTarget === 'friend');

// 1. Dovanų kupono HTML laiškas gavėjui (draugui arba pirkėjui, jei pirko sau)
$subVoucher = $isFriend 
    ? "🎁 Jums dovanų kuponas nuo $buyerName! [$code] ($amount €)"
    : "Jūsų ThrowHouse Šiauliai dovanų kuponas [$code] ($amount €)";
$voucherHtml = renderVoucherHtml($coupon);
$voucherSent = sendThrowHouseMail($targetEmail, $subVoucher, $voucherHtml, 'throwhousee@gmail.com', 'ThrowHouse Dovanų Kuponai');

// 2. Jei pirkta draugui – pirkimo patvirtinimas pirkėjui
$receiptSent = false;
if ($isFriend && $buyerEmail !== $targetEmail) {
    $subBuyerReceipt = "Dovanų kupono pirkimo patvirtinimas [$code] – ThrowHouse Šiauliai";
    $receiptHtml = renderVoucherBuyerReceiptHtml($coupon);
    $receiptSent = sendThrowHouseMail($buyerEmail, $subBuyerReceipt, $receiptHtml, 'throwhousee@gmail.com', 'ThrowHouse Šiauliai');
}

// 3. Pranešimas administratoriui
$subAdmin = "🎁 Naujas dovanų kuponas [$code] ($amount €) - $buyerName";
$adminHtml = renderVoucherAdminHtml($coupon);
$adminSent = sendThrowHouseMail('throwhousee@gmail.com', $subAdmin, $adminHtml, $buyerEmail, 'ThrowHouse Kuponai');

// Žurnalas diagnostikai
$logEntry = date('Y-m-d H:i:s') . " | Kuponas: $code | $amount € | Tipas: $targetLabel | Pirkejas: $buyerName ($buyerEmail) | Gavejas: $targetName ($targetEmail) | AdminMail: " . ($adminSent ? 'OK' : 'FAIL') . " | VoucherMail: " . ($voucherSent ? 'OK' : 'FAIL') . "\n";
@file_put_contents(__DIR__ . '/kuponas_log.txt', $logEntry, FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => 'Dovanų kuponas sėkmingai sugeneruotas ir išsiųstas!',
    'coupon' => $coupon
]);
