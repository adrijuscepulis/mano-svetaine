<?php
// kuponas_print.php - ThrowHouse dovanų kuponas (spausdinimui ir PDF atsisiuntimui)
// Atvaizduoja ir generuoja oficialų Canva 2-jų pusių dovanų kupono formatą.
// Apsauga: jei kupono kodas nenurodytas arba neegzistuoja, vartotojas nukreipiamas arba mato aiškią klaidą (nėra jokių atsitiktinių kuponų!).
header('Content-Type: text/html; charset=utf-8');

$code = trim($_GET['code'] ?? '');

// 1. Jei kodas nenurodytas visai – nukreipiame į pagrindinio puslapio dovanų kuponų sekciją
if (empty($code)) {
    header('Location: index.html#kuponai');
    exit;
}

$dataFile = __DIR__ . '/kuponai.json';
$dataDbFile = __DIR__ . '/kuponai_db.json';

$coupons = [];
if (file_exists($dataDbFile)) {
    $coupons = json_decode(@file_get_contents($dataDbFile), true) ?: [];
}
if (empty($coupons) && file_exists($dataFile)) {
    $coupons = json_decode(@file_get_contents($dataFile), true) ?: [];
}

$coupon = null;
foreach ($coupons as $c) {
    if (!empty($c['code']) && strcasecmp($c['code'], $code) === 0) {
        $coupon = $c;
        break;
    }
}

// 2. Jei kuponas nerastas – rodome 404 klaidos puslapį
if (!$coupon) {
    http_response_code(404);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <title>Kuponas nerastas – ThrowHouse Šiauliai</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 2rem 1rem;
      background: #0f1115; color: #fff;
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      text-align: center;
    }
    .card {
      background: #181b20; border: 1px solid rgba(184, 20, 27, 0.3);
      border-radius: 16px; padding: 2.5rem 2rem; max-width: 520px; width: 100%;
      box-shadow: 0 15px 35px rgba(0,0,0,0.5);
    }
    h1 { font-family: 'Oswald', sans-serif; font-size: 2.2rem; color: #b8141b; margin: 0 0 1rem; }
    p { color: #94a3b8; font-size: 1rem; line-height: 1.6; margin: 0 0 1.5rem; }
    .code-badge { font-family: monospace; background: #23272f; color: #f87171; padding: 0.3rem 0.75rem; border-radius: 6px; }
    .btn {
      display: inline-block; background: linear-gradient(135deg, #7f1d1d 0%, #b8141b 100%);
      color: #ffffff; font-weight: 800; padding: 0.85rem 1.6rem; border-radius: 8px;
      text-decoration: none; transition: transform 0.2s; margin: 0.35rem;
    }
    .btn:hover { transform: translateY(-2px); }
    .btn-secondary { background: #23272f; color: #fff; border: 1px solid rgba(255,255,255,0.15); }
  </style>
</head>
<body>
  <div class="card">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">⚠️</div>
    <h1>Kuponas nerastas</h1>
    <p>
      Dovanų kuponas su kodu <span class="code-badge"><?= htmlspecialchars($code) ?></span> sistemoje neegzistuoja arba nuoroda yra neteisinga.
    </p>
    <p style="font-size: 0.88rem; color: #64748b;">
      Jei neseniai įsigijote kuponą, pasitikrinkite savo el. paštą arba susisiekite su mumis: <strong style="color: #cbd5e1;">info@throwhouse.lt</strong> / <strong style="color: #cbd5e1;">+370 619 88009</strong>.
    </p>
    <div style="margin-top: 1.5rem;">
      <a href="index.html#kuponai" class="btn">🎁 Įsigyti kuponą</a>
      <a href="index.html" class="btn btn-secondary">🌐 Pagrindinis puslapis</a>
    </div>
  </div>
</body>
</html>
<?php
    exit;
}

// 3. Jei kuponas neapmokėtas arba atšauktas
$cStatus = $coupon['status'] ?? 'Aktyvus';
if (stripos($cStatus, 'Laukiama') !== false || stripos($cStatus, 'Atsaukta') !== false || stripos($cStatus, 'Neapmoketa') !== false) {
    http_response_code(403);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <title>Kuponas neaktyvus – ThrowHouse Šiauliai</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 2rem 1rem;
      background: #0f1115; color: #fff;
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      text-align: center;
    }
    .card {
      background: #181b20; border: 1px solid rgba(184, 20, 27, 0.3);
      border-radius: 16px; padding: 2.5rem 2rem; max-width: 520px; width: 100%;
      box-shadow: 0 15px 35px rgba(0,0,0,0.5);
    }
    h1 { font-family: 'Oswald', sans-serif; font-size: 2.2rem; color: #d31b23; margin: 0 0 1rem; }
    p { color: #94a3b8; font-size: 1rem; line-height: 1.6; margin: 0 0 1.5rem; }
    .btn {
      display: inline-block; background: linear-gradient(135deg, #7f1d1d 0%, #b8141b 100%);
      color: #ffffff; font-weight: 800; padding: 0.85rem 1.6rem; border-radius: 8px;
      text-decoration: none; transition: transform 0.2s; margin: 0.35rem;
    }
    .btn-secondary { background: #23272f; color: #fff; border: 1px solid rgba(255,255,255,0.15); }
  </style>
</head>
<body>
  <div class="card">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">⏳</div>
    <h1>Kuponas neaktyvus</h1>
    <p>
      Dovanų kuponas <strong><?= htmlspecialchars($coupon['code'] ?? '') ?></strong> nėra aktyvus (būsena: <span style="color: #f87171;"><?= htmlspecialchars($cStatus) ?></span>).
    </p>
    <p style="font-size: 0.88rem; color: #64748b;">
      Kuponas aktyvuojamas tik po sėkmingo apmokėjimo. Jei manote, kad įvyko klaida, susisiekite su mumis: <strong style="color: #cbd5e1;">info@throwhouse.lt</strong> / <strong style="color: #cbd5e1;">+370 619 88009</strong>.
    </p>
    <div style="margin-top: 1.5rem;">
      <a href="index.html#kuponai" class="btn">🎁 Įsigyti kuponą</a>
      <a href="index.html" class="btn btn-secondary">🌐 Pagrindinis puslapis</a>
    </div>
  </div>
</body>
</html>
<?php
    exit;
}

// 4. Kuponas rastas ir aktyvus – paruošiame duomenis
$cCode = htmlspecialchars($coupon['code'] ?? 'TH-KUP-0000');
$cAmount = htmlspecialchars($coupon['amount'] ?? 50);
$cExpires = htmlspecialchars($coupon['expires_at'] ?? date('Y-m-d', strtotime('+6 months')));
$cDate = htmlspecialchars($coupon['created_at'] ?? date('Y-m-d'));
$cBuyer = htmlspecialchars($coupon['buyer'] ?? '');
$cBuyerClean = trim(preg_replace('/\s*\(.*?\)/', '', $cBuyer));
if (empty($cBuyerClean)) $cBuyerClean = '-';
$cRecipient = htmlspecialchars($coupon['recipient'] ?? '');
if (empty($cRecipient)) $cRecipient = '-';

$isUsed = (stripos($cStatus, 'Panaudotas') !== false);
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';

// Tiesioginis PDF atsisiuntimas
if (isset($_GET['download'])) {
    require_once __DIR__ . '/pdf_helper.php';
    $pdf = generateThrowHouseCouponPdf($cCode, $cAmount, $cExpires, $cBuyerClean, $cRecipient);
    $filename = "ThrowHouse_Kuponas_" . preg_replace('/[^A-Za-z0-9\-]/', '', $cCode) . ".pdf";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf;
    exit;
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <title>Dovanų kuponas <?= $cCode ?> – ThrowHouse Šiauliai</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192x192.png">
  <link rel="shortcut icon" href="/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }

    body {
      margin: 0;
      padding: 0;
      background: #0f1115;
      color: #0f172a;
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      -webkit-font-smoothing: antialiased;
    }

    /* VIRŠUTINĖ VALDYMO JUOSTA */
    .action-bar {
      width: 100%;
      background: #181b20;
      border-bottom: 2px solid #b8141b;
      padding: 0.9rem 1.75rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    }

    .action-btn {
      background: linear-gradient(135deg, #7f1d1d 0%, #b8141b 50%, #d31b23 100%);
      color: #ffffff;
      font-weight: 800;
      font-size: 0.95rem;
      padding: 0.75rem 1.4rem;
      border-radius: 8px;
      border: 1px solid rgba(255, 237, 213, 0.25);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      box-shadow: 0 4px 14px rgba(184, 20, 27, 0.35);
      transition: all 0.2s ease;
    }
    .action-btn:hover {
      background: linear-gradient(135deg, #9a3412 0%, #dc2626 50%, #fbbf24 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(184, 20, 27, 0.45);
    }

    .action-btn-secondary {
      background: rgba(255, 255, 255, 0.1);
      color: #ffffff;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: none;
    }
    .action-btn-secondary:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-1px);
    }

    .voucher-stage {
      padding: 2.5rem 1rem 4rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2rem;
      width: 100%;
      max-width: 900px;
    }

    .notice-card {
      width: 100%;
      background: #1e222b;
      border: 1px solid rgba(184, 20, 27, 0.3);
      border-left: 4px solid #b8141b;
      border-radius: 8px;
      padding: 1rem 1.25rem;
      color: #e2e8f0;
      font-size: 0.92rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .used-banner {
      width: 100%;
      background: rgba(239, 68, 68, 0.15);
      border: 2px solid #ef4444;
      border-radius: 8px;
      padding: 0.85rem 1.25rem;
      color: #fca5a5;
      font-weight: 700;
      text-align: center;
      font-size: 0.95rem;
    }

    /* KUPONO TICKET DIZAINAS (Canva 210mm x 99mm) */
    .ticket-card {
      width: 100%;
      max-width: 794px; /* Atitinka 210mm */
      aspect-ratio: 210 / 99;
      background: #f1ede2;
      border-radius: 20px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 20px 45px rgba(0, 0, 0, 0.4);
      display: flex;
      color: #121619;
      border: 2px solid #23272d;
    }

    /* Kairysis išlenktas juodas fonas */
    .ticket-card::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 130px;
      background: #1b1e22;
      border-top-right-radius: 120px;
      border-bottom-right-radius: 120px;
      z-index: 1;
    }

    /* Vidinis rėmelis */
    .ticket-inner-border {
      position: absolute;
      top: 14px;
      left: 14px;
      right: 14px;
      bottom: 14px;
      border: 1.5px solid #243026;
      border-radius: 16px;
      z-index: 2;
      pointer-events: none;
    }

    .ticket-content {
      position: relative;
      z-index: 3;
      width: 100%;
      height: 100%;
      padding: 1.4rem 2rem 1.2rem 8.5rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .t-header {
      text-align: center;
    }
    .t-title {
      font-family: 'Oswald', sans-serif;
      font-size: 2.6rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 2px;
      line-height: 1;
      margin: 0;
      color: #101416;
    }
    .t-subtitle {
      font-size: 0.95rem;
      letter-spacing: 1.5px;
      color: #2b3137;
      margin-top: 2px;
      font-weight: 600;
    }

    /* Laukelių tinklelis */
    .t-fields-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem 2rem;
      margin-top: 0.5rem;
    }

    .t-row {
      display: flex;
      align-items: baseline;
      border-bottom: 1.5px solid #2b3137;
      padding-bottom: 6px;
    }
    .t-label {
      font-size: 1rem;
      font-weight: 500;
      color: #1a1e22;
      white-space: nowrap;
      margin-right: 0.75rem;
    }
    .t-value {
      font-weight: 800;
      color: #101416;
      font-size: 1.15rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .t-value-amount {
      font-size: 1.35rem;
      color: #101416;
      font-weight: 900;
    }
    .t-value-code {
      font-family: 'Space Mono', monospace;
      font-size: 1.2rem;
      letter-spacing: 1px;
    }

    .t-center-row {
      grid-column: span 2;
      display: flex;
      align-items: baseline;
      border-bottom: 1.5px solid #2b3137;
      padding-bottom: 6px;
      width: 70%;
      margin: 0 auto;
    }

    .t-footer {
      text-align: right;
      font-size: 0.75rem;
      color: #4b5563;
      font-style: italic;
      margin-top: 0.25rem;
    }

    /* GALINĖ PUSĖ (BACK COVER) */
    .ticket-card-back {
      background: linear-gradient(135deg, #ddd8cb 0%, #c8c2b3 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .ticket-card-back::before {
      display: none;
    }
    .back-big-watermark {
      position: absolute;
      font-family: 'Oswald', sans-serif;
      font-size: 11rem;
      font-weight: 900;
      color: rgba(18, 22, 25, 0.12);
      transform: rotate(-15deg);
      user-select: none;
      pointer-events: none;
      white-space: nowrap;
    }
    .back-center-brand {
      position: relative;
      z-index: 3;
      transform: rotate(-12deg);
      text-align: center;
    }
    .back-center-brand h2 {
      font-family: 'Oswald', sans-serif;
      font-size: 3rem;
      font-weight: 900;
      letter-spacing: 5px;
      text-transform: uppercase;
      color: #121619;
      margin: 0;
    }
    .back-center-brand span {
      display: block;
      font-size: 1.1rem;
      letter-spacing: 4px;
      font-weight: 700;
      color: #3b434a;
      margin-top: 4px;
    }

    /* SPAUSDINIMO TAISYKLĖS */
    @media print {
      body {
        background: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        margin: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      .no-print, .action-bar, .notice-card, .used-banner {
        display: none !important;
      }
      .voucher-stage {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
        gap: 0 !important;
      }
      .ticket-card {
        max-width: 210mm !important;
        width: 210mm !important;
        height: 99mm !important;
        page-break-after: always !important;
        box-shadow: none !important;
        border: 1px solid #111 !important;
        margin: 0 auto !important;
      }
      @page {
        size: 210mm 99mm;
        margin: 0;
      }
    }

    @media (max-width: 650px) {
      .ticket-card {
        aspect-ratio: auto;
        min-height: 280px;
      }
      .ticket-card::before { width: 50px; }
      .ticket-content { padding: 1.2rem 1.2rem 1.2rem 4.5rem; }
      .t-title { font-size: 1.8rem; }
      .t-fields-grid { grid-template-columns: 1fr; gap: 0.5rem; }
      .t-center-row { grid-column: span 1; width: 100%; }
      .action-bar { flex-direction: column; text-align: center; }
    }
  </style>
</head>
<body>

  <!-- VIRŠUTINĖ JUOSTA -->
  <div class="action-bar no-print">
    <div>
      <div style="font-weight: 800; font-size: 1.05rem; color: #fff;">
        ThrowHouse Dovanų Kuponas: <span style="color: #b8141b;"><?= $cCode ?></span>
      </div>
      <div style="font-size: 0.78rem; color: #94a3b8;">
        Oficialus Canva šablonas su automatiniu duomenų užpildymu
      </div>
    </div>
    <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
      <a href="kuponas_print.php?code=<?= urlencode($cCode) ?>&download=1" class="action-btn">
        📥 Atsisiųsti PDF (oficialus šablonas)
      </a>
      <button onclick="window.print()" class="action-btn action-btn-secondary">
        🖨️ Spausdinti
      </button>
      <a href="index.html" class="action-btn action-btn-secondary">
        🌐 ThrowHouse.lt
      </a>
    </div>
  </div>

  <div class="voucher-stage">

    <?php if ($isUsed): ?>
      <div class="used-banner no-print">
        ⚠️ DĖMESIO: Šis dovanų kuponas jau yra pažymėtas kaip PANAUDOTAS.
      </div>
    <?php endif; ?>

    <div class="notice-card no-print">
      <div>
        <strong>🎫 Kuponas paruoštas!</strong>
        Šis dovanų kuponas yra užpildytas pagal oficialų ThrowHouse Canva 2-jų pusių šabloną. Galite bet kada atsisiųsti PDF failą arba atsispausdinti.
      </div>
      <div>
        <a href="kuponas_print.php?code=<?= urlencode($cCode) ?>&download=1" style="color: #b8141b; font-weight: 700; text-decoration: underline;">
          Atsisiųsti PDF &rarr;
        </a>
      </div>
    </div>

    <!-- 1 PUSĖ: PRIEKINĖ KORTELĖ (FRONT) -->
    <div class="ticket-card">
      <div class="ticket-inner-border"></div>
      <div class="ticket-content">
        <div class="t-header">
          <h1 class="t-title">DOVANŲ KUPONAS</h1>
          <div class="t-subtitle">ThrowHouse ŠIAULIAI</div>
        </div>

        <div class="t-fields-grid">
          <!-- Vertė -->
          <div class="t-row">
            <span class="t-label">Vertė:</span>
            <span class="t-value t-value-amount"><?= $cAmount ?> €</span>
          </div>

          <!-- Kupono nr. -->
          <div class="t-row">
            <span class="t-label">Kupono nr.:</span>
            <span class="t-value t-value-code"><?= $cCode ?></span>
          </div>

          <!-- Įsigijimo data -->
          <div class="t-row">
            <span class="t-label">Įsigijimo data:</span>
            <span class="t-value"><?= $cDate ?></span>
          </div>

          <!-- Dovanoja -->
          <div class="t-row">
            <span class="t-label">Dovanoja:</span>
            <span class="t-value"><?= $cBuyerClean ?></span>
          </div>

          <!-- Kam skirta -->
          <div class="t-center-row">
            <span class="t-label">Kam skirta:</span>
            <span class="t-value"><?= $cRecipient ?></span>
          </div>
        </div>

        <div class="t-footer">
          Kuponas galioja 6 mėnesius nuo įsigijimo datos
        </div>
      </div>
    </div>

    <!-- 2 PUSĖ: GALINĖ PUSĖ (BACK COVER) -->
    <div class="ticket-card ticket-card-back">
      <div class="ticket-inner-border"></div>
      <div class="back-big-watermark">THROW HOUSE</div>
      <div class="back-center-brand">
        <h2>THROW HOUSE</h2>
        <span>ŠIAULIAI</span>
      </div>
    </div>

  </div>

  <?php if ($autoPrint): ?>
    <script>
      window.addEventListener('load', function() {
        setTimeout(function() {
          window.print();
        }, 500);
      });
    </script>
  <?php endif; ?>

</body>
</html>
