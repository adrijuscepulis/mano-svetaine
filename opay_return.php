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

// Jei statusas 1 (sėkmingas apmokėjimas)
$isSuccess = ($status === '1');

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
        sendThrowHouseMail($clientEmail, "Jūsų rezervacija ThrowHouse Šiauliai [$orderNr] - Apmokėta", $clientHtml, 'throwhousee@gmail.com', 'ThrowHouse Šiauliai');
    }
    $adminHtml = renderNewReservationAdminHtml($reservation);
    sendThrowHouseMail('throwhousee@gmail.com', "💳 Naujas APMOKĖTAS užsakymas per OPAY [$orderNr]: " . ($reservation['name'] ?? ''), $adminHtml, $clientEmail ?: 'throwhousee@gmail.com', 'ThrowHouse Rezervacijos');
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
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isSuccess ? 'Apmokėjimas sėkmingas | ThrowHouse Šiauliai' : 'Apmokėjimas nebaigtas | ThrowHouse Šiauliai' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #08050e;
      --card-bg: #120c1f;
      --card-border: #2a1b42;
      --accent: #ff6a00;
      --accent-hover: #ff7d1a;
      --text: #f8fafc;
      --muted: #94a3b8;
      --success: #22c55e;
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
        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(255, 106, 0, 0.15), transparent),
        radial-gradient(circle at 80% 80%, rgba(138, 43, 226, 0.08), transparent);
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
      background: rgba(34, 197, 94, 0.15);
      border: 2px solid var(--success);
      color: var(--success);
    }
    .icon-cancel {
      background: rgba(239, 68, 68, 0.15);
      border: 2px solid var(--danger);
      color: var(--danger);
    }
    h1 {
      font-family: 'Outfit', sans-serif;
      font-size: 1.85rem;
      font-weight: 800;
      color: #fff;
      margin-bottom: 0.75rem;
      letter-spacing: -0.02em;
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
      background: linear-gradient(135deg, var(--accent), #e05500);
      color: #fff;
      box-shadow: 0 4px 15px rgba(255, 106, 0, 0.35);
    }
    .btn-primary:hover {
      background: linear-gradient(135deg, var(--accent-hover), #ff6a00);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 106, 0, 0.45);
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
            <span class="info-val" style="color:#4ade80; font-size:1.1rem;"><?= htmlspecialchars($reservation['price']) ?></span>
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

    <?php else: ?>
      <div class="icon-badge icon-cancel">✕</div>
      <h1>Apmokėjimas nebaigtas</h1>
      <p class="subtitle">
        Apmokėjimas buvo atšauktas arba bankas nepatvirtino operacijos. Jūsų rezervuotas laikas nebuvo patvirtintas.
      </p>

      <div class="btn-wrap">
        <a href="index.html#rezervacija" class="btn btn-primary">Bandyti rezervuoti iš naujo ➔</a>
        <a href="tel:+37061988009" class="btn btn-secondary">📞 Susisiekti telefonu (+370 619 88009)</a>
      </div>

      <div class="notice">
        Jeigu iš Jūsų sąskaitos pinigai buvo nuskaičiuoti, maloniai prašome nedelsiant susisiekti tel. +370 619 88009 arba el. paštu throwhousee@gmail.com ir mes rankiniu būdu patvirtinsime Jūsų rezervaciją.
      </div>
    <?php endif; ?>
  </div>

</body>
</html>
