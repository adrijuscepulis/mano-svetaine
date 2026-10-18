<?php
// kuponas_print.php - Spausdinimui ir PDF išsaugojimui paruoštas „ThrowHouse Šiauliai“ dovanų kuponas
header('Content-Type: text/html; charset=utf-8');

$code = trim($_GET['code'] ?? '');
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
if (!empty($code)) {
    foreach ($coupons as $c) {
        if (!empty($c['code']) && strcasecmp($c['code'], $code) === 0) {
            $coupon = $c;
            break;
        }
    }
}

// Jei kupono pagal kodą nerado (arba bandomasis režimas)
if (!$coupon) {
    $amount = intval($_GET['amount'] ?? 50);
    $name = htmlspecialchars($_GET['name'] ?? 'Dovanų kupono turėtojas');
    $coupon = [
        'code' => !empty($code) ? htmlspecialchars($code) : 'TH-KUP-' . rand(1000, 9999),
        'amount' => $amount,
        'buyer' => $name,
        'recipient' => $name,
        'created_at' => date('Y-m-d'),
        'expires_at' => date('Y-m-d', strtotime('+6 months')),
        'status' => 'Aktyvus'
    ];
}

$cCode = htmlspecialchars($coupon['code'] ?? 'TH-KUP-0000');
$cAmount = htmlspecialchars($coupon['amount'] ?? 50);
$cExpires = htmlspecialchars($coupon['expires_at'] ?? date('Y-m-d', strtotime('+6 months')));
$cRecipient = htmlspecialchars(explode(' (', $coupon['recipient'] ?? $coupon['buyer'] ?? 'Dovanų kupono gavėjas')[0]);
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <title>Dovanų kuponas <?= $cCode ?> – ThrowHouse Šiauliai</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #ff6a00;
      --primary-glow: rgba(255, 106, 0, 0.4);
      --accent: #f59e0b;
      --bg-dark: #08050e;
      --surface: #120b1e;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      padding: 0;
      background: #08050e;
      color: #fff;
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      -webkit-font-smoothing: antialiased;
    }

    /* VIRŠUTINĖ VALDYMO JUOSTA (TIK EKRANE) */
    .action-bar {
      width: 100%;
      background: #130d22;
      border-bottom: 1px solid rgba(255, 106, 0, 0.3);
      padding: 1rem 1.5rem;
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
      background: linear-gradient(135deg, #ff6a00, #ea580c);
      color: #000;
      font-weight: 800;
      font-size: 0.95rem;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      box-shadow: 0 4px 15px rgba(255, 106, 0, 0.35);
      transition: all 0.2s;
    }
    .action-btn:hover {
      background: linear-gradient(135deg, #ff8533, #ff6a00);
      transform: translateY(-2px);
    }
    .action-btn-secondary {
      background: rgba(255,255,255,0.08);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.15);
      box-shadow: none;
    }
    .action-btn-secondary:hover {
      background: rgba(255,255,255,0.15);
      transform: translateY(-1px);
    }

    .voucher-stage {
      padding: 2.5rem 1rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 100%;
    }

    .cut-guide {
      font-size: 0.75rem;
      color: #a1a1aa;
      margin-bottom: 0.5rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* KUPONO KORTELĖ */
    .voucher-card {
      width: 100%;
      max-width: 820px;
      background: radial-gradient(circle at 15% 20%, #1f1230 0%, #0d0817 70%, #050308 100%);
      border: 2px solid #ff6a00;
      border-radius: 16px;
      padding: 2rem 2.2rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), 0 0 40px rgba(255, 106, 0, 0.2);
    }

    /* DEKORATYVINIAI KAMPELIAI */
    .voucher-card::before {
      content: '';
      position: absolute;
      top: 6px; left: 6px; right: 6px; bottom: 6px;
      border: 1px dashed rgba(255, 106, 0, 0.3);
      border-radius: 12px;
      pointer-events: none;
    }

    .corner-decor {
      position: absolute;
      width: 20px;
      height: 20px;
      border-color: #ff6a00;
      border-style: solid;
      pointer-events: none;
    }
    .corner-tl { top: 12px; left: 12px; border-width: 3px 0 0 3px; }
    .corner-tr { top: 12px; right: 12px; border-width: 3px 3px 0 0; }
    .corner-bl { bottom: 12px; left: 12px; border-width: 0 0 3px 3px; }
    .corner-br { bottom: 12px; right: 12px; border-width: 0 3px 3px 0; }

    /* KUPONO VIRŠŪNĖ */
    .v-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid rgba(255, 255, 255, 0.12);
      padding-bottom: 1.25rem;
      margin-bottom: 1.5rem;
      position: relative;
    }

    .brand-title {
      font-family: 'Outfit', sans-serif;
      font-size: 1.7rem;
      font-weight: 900;
      letter-spacing: 1.5px;
      margin: 0;
      color: #fff;
    }
    .brand-title span { color: #ff6a00; }
    .brand-sub {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #94a3b8;
      margin-top: 0.2rem;
      font-weight: 700;
    }

    .v-badge {
      background: rgba(255, 106, 0, 0.12);
      border: 1px solid #ff6a00;
      color: #ff8533;
      padding: 0.4rem 1rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    /* KŪNAS */
    .v-body {
      display: grid;
      grid-template-columns: 1.6fr 1.1fr;
      gap: 1.5rem;
      align-items: center;
      margin-bottom: 1.5rem;
      position: relative;
    }

    .v-greeting {
      font-size: 0.85rem;
      color: #a1a1aa;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 0.3rem;
    }
    .v-recipient {
      font-family: 'Outfit', sans-serif;
      font-size: 1.6rem;
      font-weight: 800;
      color: #fff;
      margin-bottom: 0.6rem;
      line-height: 1.2;
    }
    .v-activities {
      font-size: 0.9rem;
      color: #cbd5e1;
      line-height: 1.5;
    }
    .v-activities strong { color: #f59e0b; }

    /* VERTĖS IR KODO BLOKAS */
    .v-code-box {
      background: rgba(0, 0, 0, 0.55);
      border: 1px solid rgba(255, 106, 0, 0.4);
      border-radius: 12px;
      padding: 1.25rem;
      text-align: center;
      box-shadow: inset 0 0 20px rgba(0,0,0,0.7);
    }
    .v-value-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      color: #94a3b8;
      letter-spacing: 1px;
      font-weight: 700;
    }
    .v-value {
      font-family: 'Outfit', sans-serif;
      font-size: 2.8rem;
      font-weight: 900;
      color: #4ade80;
      line-height: 1;
      margin: 0.25rem 0 0.75rem 0;
      text-shadow: 0 0 15px rgba(74, 222, 128, 0.4);
    }
    .v-code-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      color: #ff8533;
      letter-spacing: 1.5px;
      font-weight: 800;
      margin-bottom: 0.3rem;
    }
    .v-code {
      font-family: 'Space Mono', monospace;
      font-size: 1.35rem;
      font-weight: 700;
      letter-spacing: 2px;
      color: #fff;
      background: rgba(255, 106, 0, 0.15);
      border: 1px dashed #ff6a00;
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      display: inline-block;
    }

    /* REKVIZITAI IR INSTRUKCIJA */
    .v-footer-info {
      border-top: 1px solid rgba(255, 255, 255, 0.12);
      padding-top: 1rem;
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 1rem;
      font-size: 0.8rem;
      color: #94a3b8;
      line-height: 1.4;
      position: relative;
    }
    .info-item strong {
      display: block;
      color: #fff;
      margin-bottom: 0.2rem;
      font-size: 0.82rem;
    }

    .v-validity {
      margin-top: 0.9rem;
      text-align: center;
      font-size: 0.75rem;
      color: #71717a;
      letter-spacing: 0.5px;
    }
    .v-validity strong { color: #f59e0b; }

    /* ============================================================ */
    /* SPAUSDINIMO (PRINT / PDF) STILIAI */
    /* ============================================================ */
    @media print {
      body {
        background: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        margin: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      .no-print, .action-bar, .cut-guide {
        display: none !important;
      }
      .voucher-stage {
        padding: 0 !important;
        margin: 0 auto !important;
      }
      .voucher-card {
        background: #0f0a18 !important;
        color: #ffffff !important;
        box-shadow: none !important;
        border: 2px solid #ff6a00 !important;
        max-width: 100% !important;
        page-break-inside: avoid !important;
        margin-top: 20px !important;
      }
      .brand-title { color: #ffffff !important; }
      .brand-title span { color: #ff6a00 !important; }
      .v-recipient { color: #ffffff !important; }
      .v-activities { color: #e2e8f0 !important; }
      .v-value { color: #4ade80 !important; }
      .v-code { color: #ffffff !important; background: rgba(255, 106, 0, 0.2) !important; }
      .info-item strong { color: #ffffff !important; }
      .info-item { color: #cbd5e1 !important; }
      @page {
        size: A4 portrait;
        margin: 1.5cm;
      }
    }

    @media (max-width: 650px) {
      .v-body { grid-template-columns: 1fr; }
      .v-footer-info { grid-template-columns: 1fr; }
      .action-bar { flex-direction: column; text-align: center; }
    }
  </style>
</head>
<body>

  <!-- VALDYMO MYGTUKAI (PASLEPIAMI SPAUSDINANT) -->
  <div class="action-bar no-print">
    <div>
      <div style="font-weight: 800; font-size: 1rem; color: #fff;">Dovanų kuponas: <span style="color: #ff6a00;"><?= $cCode ?></span></div>
      <div style="font-size: 0.78rem; color: #94a3b8;">Paruošta spausdinimui arba išsaugojimui kaip PDF failą</div>
    </div>
    <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
      <button onclick="window.print()" class="action-btn">
        🖨️ Spausdinti / Išsaugoti kaip PDF
      </button>
      <a href="index.html" class="action-btn action-btn-secondary">
        🌐 Eiti į svetainę
      </a>
      <a href="admin.php?tab=kuponai" class="action-btn action-btn-secondary">
        ⚙️ Valdymo pultas
      </a>
    </div>
  </div>

  <div class="voucher-stage">
    <div class="cut-guide no-print">
      ✂️ Kirpimo linija (spausdinant ant A4 lapo)
    </div>

    <!-- TIKRASIS SPAUSDINAMAS KUPONAS -->
    <div class="voucher-card">
      <div class="corner-decor corner-tl"></div>
      <div class="corner-decor corner-tr"></div>
      <div class="corner-decor corner-bl"></div>
      <div class="corner-decor corner-br"></div>

      <!-- VIRŠUS -->
      <div class="v-header">
        <div>
          <h1 class="brand-title">THROW<span>HOUSE</span></h1>
          <div class="brand-sub">Šiauliai · Adrenalino ir Pramogų Erdvė</div>
        </div>
        <div class="v-badge">
          🎁 Dovanų Kuponas
        </div>
      </div>

      <!-- KŪNAS -->
      <div class="v-body">
        <div>
          <div class="v-greeting">Šis kuponas priklauso:</div>
          <div class="v-recipient"><?= $cRecipient ?></div>
          <div class="v-activities">
            Suteikia teisę pasinaudoti bet kuria „ThrowHouse Šiauliai“ pramoga:<br>
            • <strong>Pykčio kambarys</strong> (streso iškrovos sesija)<br>
            • <strong>Kirvių mėtymas</strong> (taiklumo ir azarto rungtys)<br>
            • <strong>KOMBO paketas</strong> (abi pramogos kartu)
          </div>
        </div>

        <div class="v-code-box">
          <div class="v-value-label">Kupono Vertė</div>
          <div class="v-value"><?= $cAmount ?> €</div>
          <div class="v-code-label">Unikalus Kodas</div>
          <div class="v-code"><?= $cCode ?></div>
        </div>
      </div>

      <!-- APATINĖ INFORMACIJA -->
      <div class="v-footer-info">
        <div class="info-item">
          <strong>📍 Kur mus rasti:</strong>
          Pramonės g. 15, Šiauliai (2-as a.)<br>
          Įėjimas pro duris „Floristika“
        </div>
        <div class="info-item">
          <strong>📞 Rezervacija ir kontaktai:</strong>
          Tel.: +370 619 88009<br>
          El. p.: throwhousee@gmail.com
        </div>
        <div class="info-item">
          <strong>🌐 Rezervacija internetu:</strong>
          www.throwhouse.lt<br>
          Išankstinė registracija būtina
        </div>
      </div>

      <div class="v-validity">
        Kuponas galioja 6 mėnesius (iki <strong><?= $cExpires ?></strong>). Visiems dalyviams privaloma patogi, uždara avalynė. Kuponas į grynuosius pinigus nekeičiamas.
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
