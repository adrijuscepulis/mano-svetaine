<?php
// =========================================================================
// OPAY Grįžtamasis Puslapis Klientui (opay_return.php)
// ThrowHouse Šiauliai
// =========================================================================

date_default_timezone_set('Europe/Vilnius');

require_once __DIR__ . '/opay_helper.php';
require_once __DIR__ . '/email_helper.php';

$inputData = !empty($_GET) ? $_GET : (!empty($_POST) ? $_POST : []);
$cfg = loadOpayConfig();
$signaturePassword = trim($cfg['signature_password'] ?? '');

$verified = false;
$parsedData = $inputData;

if (!empty($inputData['encoded'])) {
    $row = $inputData['encoded'];
    $decoded = base64_decode(strtr($row, ['-' => '+', '_' => '/', ',' => '=']));
    parse_str($decoded, $parsedData);
}

if ($signaturePassword && !empty($parsedData['password_signature'])) {
    $verifyRes = verifyOpaySignature($inputData, $signaturePassword);
    $verified = $verifyRes['valid'];
    if ($verified) {
        $parsedData = $verifyRes['data'];
    }
}

$orderNr = trim($parsedData['order_nr'] ?? '');
$status = trim((string)($parsedData['status'] ?? ''));
$transId = trim($parsedData['transaction_id'] ?? '');

$isSuccess = ($status === '1');
$isCoupon = (strpos($orderNr, 'TH-KUP-') === 0);
$isBar = (strpos($orderNr, 'TH-BAR-') === 0);
$coupon = null;
$coupIndex = -1;
$barOrder = null;
$barIndex = -1;

if ($isCoupon) {
    require_once __DIR__ . '/pdf_helper.php';
    $kupFile = __DIR__ . '/kuponai.json';
    $kupDbFile = __DIR__ . '/kuponai_db.json';

    $coupons = file_exists($kupDbFile) ? (json_decode(@file_get_contents($kupDbFile), true) ?: []) : [];
    if (empty($coupons) && file_exists($kupFile)) {
        $coupons = json_decode(@file_get_contents($kupFile), true) ?: [];
    }

    foreach ($coupons as $idx => $c) {
        if (($c['code'] ?? '') === $orderNr) {
            $coupon = $c;
            $coupIndex = $idx;
            break;
        }
    }

    // Papildomas saugiklis: jei webhook'as dar nespėjo atnaujinti, atnaujiname čia pat
    if ($isSuccess && $coupon && strpos($coupon['status'] ?? '', 'Aktyvus') === false) {
        $coupons[$coupIndex]['status'] = 'Aktyvus (Apmokėta per OPAY)';
        $coupons[$coupIndex]['paid_at'] = date('Y-m-d H:i:s');
        $coupons[$coupIndex]['opay_transaction_id'] = $transId;
        $coupons[$coupIndex]['payment'] = 'OPAY elektroninė bankininkystė';

        $jsonStr = json_encode(array_values($coupons), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        @file_put_contents($kupFile, $jsonStr, LOCK_EX);
        @file_put_contents($kupDbFile, $jsonStr, LOCK_EX);
        $coupon = $coupons[$coupIndex];

        $code = $coupon['code'];
        $amount = $coupon['amount'];
        $expiresAt = $coupon['expires_at'];
        $recipientEmail = $coupon['recipient_email'] ?? $coupon['buyer_email'];
        $buyerEmail = $coupon['buyer_email'] ?? '';

        $pdfAttachment = [];
        try {
            $pdfBuyer = $coupon['buyer'] ?? '';
            $pdfRecipient = $coupon['recipient'] ?? '';
            $pdfContent = generateThrowHouseCouponPdf($code, $amount, $expiresAt, $pdfBuyer, $pdfRecipient);
            if (!empty($pdfContent)) {
                $pdfAttachment = [
                    [
                        'filename' => "ThrowHouse_Kuponas_{$code}.pdf",
                        'data' => $pdfContent,
                        'type' => 'application/pdf'
                    ]
                ];
            }
        } catch (\Throwable $pdfEx) {}

        $subVoucher = "Jūsų ThrowHouse Šiauliai dovanų kuponas [$code] ($amount €)";
        $voucherHtml = renderVoucherHtml($coupon);
        sendThrowHouseMail($recipientEmail, $subVoucher, $voucherHtml, 'info@throwhouse.lt', 'ThrowHouse Dovanų Kuponai', $pdfAttachment);

        if (!empty($buyerEmail) && strcasecmp($buyerEmail, $recipientEmail) !== 0) {
            $subReceipt = "Dovanų kupono pirkimo patvirtinimas [$code] - ThrowHouse Šiauliai";
            $receiptHtml = renderVoucherBuyerReceiptHtml($coupon);
            sendThrowHouseMail($buyerEmail, $subReceipt, $receiptHtml, 'info@throwhouse.lt', 'ThrowHouse Kuponai');
        }

        $emailCfg = loadEmailConfig();
        $adminTarget = !empty($emailCfg['admin_email']) ? $emailCfg['admin_email'] : 'info@throwhouse.lt';
        $subAdmin = "🎁 Naujas APMOKĖTAS dovanų kuponas per OPAY [$code] ($amount €)";
        $adminHtml = renderVoucherAdminHtml($coupon);
        sendThrowHouseMail($adminTarget, $subAdmin, $adminHtml, $buyerEmail ?: 'info@throwhouse.lt', 'ThrowHouse Kuponai', $pdfAttachment);
    }

    if ($coupon && strpos($coupon['status'] ?? '', 'Aktyvus') !== false) {
        $isSuccess = true;
    }

    if (!$isSuccess && $coupon && ($coupon['status'] ?? '') === 'Laukiama apmokėjimo') {
        $coupons[$coupIndex]['status'] = 'Atšaukta (Neapmokėta)';
        $jsonStr = json_encode(array_values($coupons), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        @file_put_contents($kupFile, $jsonStr, LOCK_EX);
        @file_put_contents($kupDbFile, $jsonStr, LOCK_EX);
        $coupon = $coupons[$coupIndex];
    }

} elseif ($isBar) {
    require_once __DIR__ . '/baras_helper.php';
    $barOrders = loadBarOrders();
    foreach ($barOrders as $bIdx => $bo) {
        if (($bo['order_nr'] ?? '') === $orderNr) {
            $barOrder = $bo;
            $barIndex = $bIdx;
            break;
        }
    }

    if ($isSuccess && $barOrder && strpos($barOrder['status'] ?? '', 'Apmokėta') === false && strpos($barOrder['status'] ?? '', 'Atiduota') === false) {
        $barOrders[$barIndex]['status'] = 'Apmokėta';
        $barOrders[$barIndex]['paid_at'] = date('Y-m-d H:i:s');
        $barOrders[$barIndex]['opay_trans_id'] = $transId;
        saveBarOrders($barOrders);
        $barOrder = $barOrders[$barIndex];
    }

    if ($barOrder && ($barOrder['status'] === 'Apmokėta' || $barOrder['status'] === 'Atiduota')) {
        $isSuccess = true;
    }

    if (!$isSuccess && $barOrder && ($barOrder['status'] ?? '') === 'Laukiama apmokėjimo') {
        $barOrders[$barIndex]['status'] = 'Atšaukta (Neapmokėta)';
        saveBarOrders($barOrders);
        $barOrder = $barOrders[$barIndex];
    }

} else {
    $resFile = __DIR__ . '/rezervacijos.json';
    $resDbFile = __DIR__ . '/rezervacijos_db.json';
    $all = loadAllReservationsHelper($resFile, $resDbFile);

    $reservation = null;
    $resIndex = -1;
    foreach ($all as $idx => $r) {
        if (($r['id'] ?? '') === $orderNr) {
            $reservation = $r;
            $resIndex = $idx;
            break;
        }
    }

    // Papildomas saugiklis: jei webhook'as dar nespėjo atnaujinti, atnaujiname čia pat
    if ($isSuccess && $reservation && strpos($reservation['status'] ?? '', 'Patvirtinta') === false) {
        $all[$resIndex]['status'] = 'Patvirtinta (Apmokėta per OPAY)';
        $all[$resIndex]['paid_at'] = date('Y-m-d H:i:s');
        $all[$resIndex]['opay_transaction_id'] = $transId;
        $all[$resIndex]['payment'] = 'OPAY elektroninė bankininkystė';
        saveAllReservationsHelper($resFile, $resDbFile, $all);
        $reservation = $all[$resIndex];

        // Išsiunčiame patvirtinimus, jei dar nebuvo išsiųsti
        $clientEmail = $reservation['email'] ?? '';
        if ($clientEmail) {
            $clientHtml = renderNewReservationClientHtml($reservation);
            sendThrowHouseMail($clientEmail, "Jūsų rezervacija ThrowHouse Šiauliai [$orderNr] - Apmokėta", $clientHtml, 'info@throwhouse.lt', 'ThrowHouse Šiauliai');
        }
        $emailCfg = loadEmailConfig();
        $adminTarget = !empty($emailCfg['admin_email']) ? $emailCfg['admin_email'] : 'info@throwhouse.lt';
        $adminHtml = renderNewReservationAdminHtml($reservation);
        sendThrowHouseMail($adminTarget, "💳 Naujas APMOKĖTAS užsakymas per OPAY [$orderNr]: " . ($reservation['name'] ?? ''), $adminHtml, $clientEmail ?: 'info@throwhouse.lt', 'ThrowHouse Rezervacijos');
    }

    // Jei rezervacija jau anksčiau buvo patvirtinta
    if ($reservation && strpos($reservation['status'] ?? '', 'Patvirtinta') !== false) {
        $isSuccess = true;
    }

    // Jei mokėjimas nebuvo atliktas (atšauktas ar nutrauktas), iškart atlaisviname rezervaciją
    if (!$isSuccess && $reservation && ($reservation['status'] ?? '') === 'Laukiama apmokėjimo') {
        $all[$resIndex]['status'] = 'Atšaukta (Neapmokėta)';
        saveAllReservationsHelper($resFile, $resDbFile, $all);
        $reservation = $all[$resIndex];
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isSuccess ? 'Apmokėjimas sėkmingas | ThrowHouse Šiauliai' : 'Apmokėjimas nebaigtas | ThrowHouse Šiauliai' ?></title>
  <meta name="theme-color" content="#0c0a09">
  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192x192.png">
  <link rel="shortcut icon" href="/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700;800&family=Space+Grotesk:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0c0a09;
      --card-bg: #14110e;
      --card-border: rgba(233, 28, 37, 0.28);
      --accent: #e91c25;
      --accent-hover: #ff2e38;
      --text: #fdfaf6;
      --muted: #94a3b8;
      --success: #e91c25;
      --danger: #ef4444;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      background-image: 
        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(233, 28, 37, 0.18), transparent),
        radial-gradient(circle at 80% 80%, rgba(185, 28, 28, 0.08), transparent);
    }
    .container {
      max-width: 620px;
      width: 100%;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 2.5rem 2rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      text-align: center;
      position: relative;
    }
    .icon-badge {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      margin: 0 auto 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.5rem;
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    }
    .icon-success {
      background: rgba(233, 28, 37, 0.15);
      border: 2px solid var(--success);
      color: var(--success);
    }
    .icon-cancel {
      background: rgba(239, 68, 68, 0.15);
      border: 2px solid var(--danger);
      color: var(--danger);
    }
    h1 {
      font-family: 'Oswald', sans-serif;
      font-size: 2rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: #fff;
      margin-bottom: 0.75rem;
    }
    .subtitle {
      font-size: 1rem;
      color: var(--muted);
      line-height: 1.6;
      margin-bottom: 1.75rem;
    }
    .info-card {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.07);
      border-radius: 14px;
      padding: 1.25rem 1.5rem;
      text-align: left;
      margin-bottom: 1.75rem;
      font-size: 0.95rem;
    }
    .info-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.55rem 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .info-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .info-label {
      color: var(--muted);
      font-size: 0.9rem;
    }
    .info-val {
      font-weight: 700;
      color: #fff;
      text-align: right;
    }
    .btn-wrap {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.9rem 1.75rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: 1rem;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s ease;
      border: none;
    }
    .btn-primary {
      background: linear-gradient(135deg, #b91c1c 0%, #e91c25 50%, #ff2e38 100%);
      color: #ffffff;
      font-weight: 800;
      box-shadow: 0 4px 15px rgba(233, 28, 37, 0.35);
      border: 1px solid rgba(255, 237, 213, 0.25);
    }
    .btn-primary:hover {
      background: linear-gradient(135deg, #9a3412 0%, #dc2626 50%, #fbbf24 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(233, 28, 37, 0.45);
      color: #0c0a08;
    }
    .btn-secondary {
      background: rgba(255, 255, 255, 0.06);
      color: #fff;
      border: 1px solid var(--card-border);
    }
    .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.1);
    }
    .notice {
      margin-top: 1.5rem;
      font-size: 0.82rem;
      color: #64748b;
      line-height: 1.5;
    }
  </style>
</head>
<body>

  <div class="container">
    <?php if ($isSuccess): ?>
      <?php if ($isCoupon): ?>
        <div class="icon-badge icon-success">🎁</div>
        <h1>Dovanų kuponas apmokėtas!</h1>
        <p class="subtitle">
          Nuoširdžiai dėkojame! Jūsų dovanų kuponas sėkmingai apmokėtas. Oficialus sertifikatas PDF formatu jau išsiųstas į Jūsų nurodytą el. paštą.
        </p>

        <?php if ($coupon): ?>
          <div class="info-card">
            <div class="info-row">
              <span class="info-label">Kupono kodas:</span>
              <span class="info-val" style="color:var(--accent); font-family:monospace; font-size:1.35rem; font-weight:800;"><?= htmlspecialchars($coupon['code'] ?? $orderNr) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Kupono vertė:</span>
              <span class="info-val" style="color:var(--accent); font-size:1.25rem; font-weight:800;"><?= htmlspecialchars($coupon['amount'] ?? 30) ?> €</span>
            </div>
            <div class="info-row">
              <span class="info-label">Pramoga:</span>
              <span class="info-val"><?= htmlspecialchars($coupon['target_type'] ?? 'Dovanų kuponas') ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Pirkėjas:</span>
              <span class="info-val"><?= htmlspecialchars($coupon['buyer'] ?? '') ?></span>
            </div>
            <?php if (!empty($coupon['recipient']) && $coupon['recipient'] !== $coupon['buyer']): ?>
              <div class="info-row">
                <span class="info-label">Gavėjas:</span>
                <span class="info-val"><?= htmlspecialchars($coupon['recipient']) ?></span>
              </div>
            <?php endif; ?>
            <div class="info-row">
              <span class="info-label">Galiojimas:</span>
              <span class="info-val">6 mėn. (iki <?= htmlspecialchars($coupon['expires_at'] ?? '') ?>)</span>
            </div>
          </div>
        <?php endif; ?>

        <div class="btn-wrap">
          <a href="kuponas_print.php?code=<?= urlencode($coupon['code'] ?? $orderNr) ?>&download=1" target="_blank" class="btn btn-primary">
            📥 Atsisiųsti / Spausdinti PDF kuponą ➔
          </a>
          <a href="index.html" class="btn btn-secondary">🌐 Grįžti į ThrowHouse.lt</a>
        </div>

        <div class="notice">
          Su šiuo dovanų kuponu apsilankymo laikas derinamas asmeniškai tel. +370 619 88009 arba el. paštu info@throwhouse.lt nurodant kupono kodą.
        </div>

      <?php elseif ($isBar): ?>
        <div class="icon-badge icon-success">🥤</div>
        <h1>Užsakymas apmokėtas!</h1>
        <p class="subtitle">
          Nuoširdžiai ačiū, <strong><?= htmlspecialchars($barOrder['client_name'] ?? 'Kliente') ?></strong>! Jūsų užsakymas sėkmingai priimtas ir apmokėtas. Mūsų komanda tuoj pat paruoš Jūsų gėrimus bei užkandžius!
        </p>

        <?php if ($barOrder): ?>
          <div class="info-card">
            <div class="info-row">
              <span class="info-label">Užsakymo numeris:</span>
              <span class="info-val" style="color:var(--accent); font-family:monospace; font-size:1.15rem; font-weight:800;"><?= htmlspecialchars($barOrder['order_nr']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Užsakovas:</span>
              <span class="info-val" style="color:#fff; font-weight:800;"><?= htmlspecialchars($barOrder['client_name'] ?? '—') ?></span>
            </div>
            <?php if (!empty($barOrder['client_location']) && $barOrder['client_location'] !== 'Salėje'): ?>
              <div class="info-row">
                <span class="info-label">Pristatymo vieta:</span>
                <span class="info-val" style="color:#fff; font-weight:800;"><?= htmlspecialchars($barOrder['client_location']) ?></span>
              </div>
            <?php endif; ?>
            <div class="info-row" style="flex-direction: column; align-items: flex-start; gap: 0.35rem;">
              <span class="info-label">Užsakytos prekės:</span>
              <div style="width: 100%; font-size: 0.95rem; color: #fff; margin-top: 0.25rem;">
                <?php foreach (($barOrder['items'] ?? []) as $it): ?>
                  <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; border-bottom: 1px dashed rgba(255,255,255,0.08);">
                    <span style="display: flex; align-items: center; gap: 0.35rem;">
                      <?php if (!empty($it['image']) && file_exists(__DIR__ . '/' . $it['image'])): ?>
                        <img src="<?= htmlspecialchars($it['image']) ?>" alt="" style="width: 22px; height: 22px; object-fit: contain; border-radius: 4px; vertical-align: middle;">
                      <?php else: ?>
                        <span><?= htmlspecialchars($it['icon'] ?? '🥤') ?></span>
                      <?php endif; ?>
                      <span><?= htmlspecialchars($it['name']) ?> × <?= (int)$it['qty'] ?></span>
                    </span>
                    <span style="font-weight: 700; color: var(--accent);"><?= number_format($it['total'], 2) ?> €</span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="info-row">
              <span class="info-label">Bendra suma:</span>
              <span class="info-val" style="color:var(--accent); font-size:1.25rem; font-weight:800;"><?= htmlspecialchars($barOrder['total_amount']) ?></span>
            </div>
          </div>
        <?php endif; ?>

        <div class="btn-wrap">
          <a href="baras.php" class="btn btn-primary">Grįžti į Baro Meniu ➔</a>
          <a href="index.html" class="btn btn-secondary">🌐 Pagrindinis puslapis</a>
        </div>

        <div class="notice">
          Mėgaukitės pramoga! Jei turite papildomų klausimų ar pageidavimų, kreipkitės tiesiogiai į instruktorių.
        </div>

      <?php else: ?>
        <div class="icon-badge icon-success">✓</div>
        <h1>Apmokėjimas sėkmingas!</h1>
        <p class="subtitle">
          Dėkojame! Jūsų rezervacija patvirtinta. Patvirtinimo laiškas su visa atvykimo informacija ir instrukcija išsiųstas į Jūsų el. pašto dėžutę.
        </p>

        <?php if ($reservation): ?>
          <div class="info-card">
            <div class="info-row">
              <span class="info-label">Rezervacijos numeris:</span>
              <span class="info-val" style="color:var(--accent); font-family:monospace; font-size:1.05rem;"><?= htmlspecialchars($reservation['id']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Pramoga:</span>
              <span class="info-val"><?= htmlspecialchars($reservation['activity'] . (!empty($reservation['lane']) ? ' (' . $reservation['lane'] . ')' : '')) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Data ir laikas:</span>
              <span class="info-val"><?= htmlspecialchars($reservation['date']) ?>, <?= htmlspecialchars($reservation['time']) ?> val.</span>
            </div>
            <div class="info-row">
              <span class="info-label">Dalyvių skaičius:</span>
              <span class="info-val"><?= htmlspecialchars($reservation['count']) ?> asm.</span>
            </div>
            <div class="info-row">
              <span class="info-label">Apmokėta suma:</span>
              <span class="info-val" style="color:var(--accent); font-size:1.1rem;"><?= htmlspecialchars($reservation['price']) ?></span>
            </div>
          </div>
        <?php endif; ?>

        <div class="btn-wrap">
          <a href="index.html" class="btn btn-primary">Grįžti į ThrowHouse.lt ➔</a>
          <a href="tel:+37061988009" class="btn btn-secondary">📞 Skambinti (+370 619 88009)</a>
        </div>

        <div class="notice">
          📍 <strong>Adresas:</strong> Pramonės g. 15, Šiauliai (2-as aukštas).<br>
          👟 <strong>Apranga:</strong> Būtina patogi, visiškai uždara avalynė (sportbačiai).
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="icon-badge icon-cancel">✕</div>
      <h1>Apmokėjimas nebaigtas</h1>
      <p class="subtitle">
        <?php if ($isBar): ?>
          Apmokėjimas už baro užsakymą buvo atšauktas arba bankas nepatvirtino operacijos.
        <?php elseif ($isCoupon): ?>
          Apmokėjimas už dovanų kuponą buvo nutrauktas arba bankas nepatvirtino operacijos.
        <?php else: ?>
          Apmokėjimas buvo atšauktas arba bankas nepatvirtino operacijos. Jūsų rezervuotas laikas nebuvo patvirtintas.
        <?php endif; ?>
      </p>

      <div class="btn-wrap">
        <a href="<?= $isBar ? 'baras.php' : ($isCoupon ? 'index.html#kuponai' : 'index.html#rezervacija') ?>" class="btn btn-primary">
          <?= $isBar ? 'Bandyti užsakyti iš naujo ➔' : ($isCoupon ? 'Bandyti pirkti kuponą iš naujo ➔' : 'Bandyti rezervuoti iš naujo ➔') ?>
        </a>
        <a href="tel:+37061988009" class="btn btn-secondary">📞 Susisiekti telefonu (+370 619 88009)</a>
      </div>

      <div class="notice">
        Jeigu iš Jūsų sąskaitos pinigai buvo nuskaičiuoti, maloniai prašome nedelsiant susisiekti tel. +370 619 88009 arba el. paštu info@throwhouse.lt ir mes rankiniu būdu patvirtinsime Jūsų užsakymą.
      </div>
    <?php endif; ?>
  </div>

</body>
</html>
