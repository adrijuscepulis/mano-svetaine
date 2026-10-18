<?php
// kuponas_print.php - A4 formato ThrowHouse dovanų kuponas (spausdinimui ir PDF atsisiuntimui)
// Švarus, modernus, profesionalus A4 dizainas be jokių helovyno motyvų.
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

// Jei kupono pagal kodą nerado (arba tiesioginė peržiūra)
if (!$coupon) {
    $amount = intval($_GET['amount'] ?? 50);
    $coupon = [
        'code' => !empty($code) ? htmlspecialchars($code) : 'TH-KUP-' . rand(1000, 9999),
        'amount' => $amount,
        'created_at' => date('Y-m-d'),
        'expires_at' => date('Y-m-d', strtotime('+6 months')),
        'status' => 'Aktyvus'
    ];
}

$cCode = htmlspecialchars($coupon['code'] ?? 'TH-KUP-0000');
$cAmount = htmlspecialchars($coupon['amount'] ?? 50);
$cExpires = htmlspecialchars($coupon['expires_at'] ?? date('Y-m-d', strtotime('+6 months')));
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';

// Tiesioginis PDF atsisiuntimas
if (isset($_GET['download'])) {
    require_once __DIR__ . '/pdf_helper.php';
    $pdf = generateThrowHouseCouponPdf($cCode, $cAmount, $cExpires);
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }

    body {
      margin: 0;
      padding: 0;
      background: #f1f5f9;
      color: #0f172a;
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      -webkit-font-smoothing: antialiased;
    }

    /* VIRŠUTINĖ VALDYMO JUOSTA (TIK EKRANE) */
    .action-bar {
      width: 100%;
      background: #0f172a;
      border-bottom: 2px solid #ff6a00;
      padding: 0.85rem 1.75rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .action-btn {
      background: #ff6a00;
      color: #ffffff;
      font-weight: 800;
      font-size: 0.95rem;
      padding: 0.75rem 1.4rem;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      box-shadow: 0 4px 12px rgba(255, 106, 0, 0.35);
      transition: all 0.2s ease;
    }
    .action-btn:hover {
      background: #ea580c;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(255, 106, 0, 0.45);
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
      padding: 2.5rem 1rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 100%;
    }

    /* ============================================================ */
    /* ŠVARUS, MODERNAUS STILIAUS A4 KUPONAS (ŠVIESUS IR ELEGANTIŠKAS) */
    /* ============================================================ */
    .a4-voucher {
      width: 100%;
      max-width: 794px; /* Standartinis A4 plotis 96dpi (210mm) */
      min-height: 1080px; /* Standartinis A4 aukštis (297mm) */
      background: #ffffff;
      color: #0f172a;
      border: 3px solid #1e293b;
      border-radius: 16px;
      padding: 3.5rem 3.2rem;
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
    }

    /* VIDINIS AKCENTO RĖMELIS */
    .a4-voucher::before {
      content: '';
      position: absolute;
      top: 14px; left: 14px; right: 14px; bottom: 14px;
      border: 1.5px solid #ff6a00;
      border-radius: 12px;
      pointer-events: none;
    }

    /* KAMPŲ AKCENTAI */
    .corner-accent {
      position: absolute;
      width: 28px;
      height: 28px;
      border-color: #ff6a00;
      border-style: solid;
      pointer-events: none;
    }
    .corner-tl { top: 20px; left: 20px; border-width: 3px 0 0 3px; }
    .corner-tr { top: 20px; right: 20px; border-width: 3px 3px 0 0; }
    .corner-bl { bottom: 20px; left: 20px; border-width: 0 0 3px 3px; }
    .corner-br { bottom: 20px; right: 20px; border-width: 0 3px 3px 0; }

    /* 1. VIRŠŪNĖ: LOGO IR BRAND */
    .v-top {
      text-align: center;
      position: relative;
      padding-bottom: 1.25rem;
      border-bottom: 2px solid #f1f5f9;
    }
    .v-logo {
      font-family: 'Outfit', sans-serif;
      font-size: 2.6rem;
      font-weight: 900;
      letter-spacing: 2px;
      margin: 0;
      color: #0f172a;
    }
    .v-logo span { color: #ff6a00; }
    .v-sublogo {
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 3px;
      color: #64748b;
      font-weight: 700;
      margin-top: 0.35rem;
    }

    /* 2. CENTRINĖ HERO DALIS: AIŠKIAI IR RYŠKIAI DOVANŲ KUPONAS IR SUMA */
    .v-center-hero {
      text-align: center;
      padding: 2rem 1rem;
      position: relative;
    }

    .v-badge {
      display: inline-block;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 2.5px;
      font-weight: 800;
      color: #ea580c;
      background: #fff7ed;
      border: 1px solid #fed7aa;
      padding: 0.4rem 1.4rem;
      border-radius: 30px;
      margin-bottom: 1rem;
    }

    /* AIŠKIAI IR RYŠKIAI PER VIDURĮ: DOVANŲ KUPONAS */
    .v-hero-title {
      font-family: 'Outfit', sans-serif;
      font-size: 3.8rem;
      font-weight: 900;
      letter-spacing: 2px;
      color: #0f172a;
      text-transform: uppercase;
      margin: 0 0 0.5rem 0;
      line-height: 1.05;
    }

    /* AIŠKIAI IR RYŠKIAI PER VIDURĮ: SUMA */
    .v-hero-amount-wrap {
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      margin: 1.25rem 0;
      background: #fffaf5;
      border: 3px solid #ff6a00;
      border-radius: 20px;
      padding: 1.25rem 4rem;
      box-shadow: 0 8px 24px rgba(255, 106, 0, 0.12);
    }
    .v-amount-lbl {
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #64748b;
      font-weight: 800;
    }
    .v-hero-amount {
      font-family: 'Outfit', sans-serif;
      font-size: 5.2rem;
      font-weight: 900;
      color: #ff6a00;
      line-height: 1;
      margin: 0.25rem 0;
    }

    /* UNIKALUS KODAS */
    .v-code-container {
      margin-top: 1rem;
    }
    .v-code-label {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #64748b;
      font-weight: 800;
      margin-bottom: 0.4rem;
    }
    .v-code-val {
      font-family: 'Space Mono', monospace;
      font-size: 1.8rem;
      font-weight: 700;
      letter-spacing: 3px;
      color: #0f172a;
      background: #f8fafc;
      border: 2px dashed #ff6a00;
      padding: 0.6rem 1.6rem;
      border-radius: 10px;
      display: inline-block;
    }

    /* 3. PRAMOGŲ IR SĄLYGŲ BLOKAS */
    .v-details-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 1.5rem 1.75rem;
      margin: 1.5rem 0;
      position: relative;
    }

    .v-activities-title {
      font-size: 0.95rem;
      font-weight: 800;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 0.75rem;
      text-align: center;
    }
    .v-activities-list {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 0.85rem;
      margin-bottom: 1.25rem;
    }
    .act-badge {
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      padding: 0.75rem 0.6rem;
      text-align: center;
      font-size: 0.85rem;
      color: #475569;
      font-weight: 600;
    }
    .act-badge strong {
      color: #ff6a00;
      display: block;
      font-size: 1rem;
      margin-bottom: 3px;
      font-weight: 800;
    }

    /* REZERVACIJA TIK ASMENIŠKAI */
    .booking-warning-box {
      background: #fffbeb;
      border: 1px solid #fef3c7;
      border-left: 5px solid #ff6a00;
      border-radius: 0 10px 10px 0;
      padding: 1rem 1.25rem;
      text-align: left;
    }
    .booking-warning-box strong {
      color: #b45309;
      font-size: 0.95rem;
      display: block;
      margin-bottom: 0.35rem;
    }
    .booking-warning-box p {
      margin: 0;
      font-size: 0.9rem;
      color: #78350f;
      line-height: 1.5;
    }
    .booking-warning-box .contact-highlight {
      color: #0f172a;
      font-weight: 800;
      font-size: 0.95rem;
    }

    /* 4. APATINIAI REKVIZITAI */
    .v-footer-grid {
      border-top: 2px solid #f1f5f9;
      padding-top: 1.25rem;
      display: grid;
      grid-template-columns: 1.2fr 1.3fr 1fr;
      gap: 1.25rem;
      font-size: 0.85rem;
      color: #475569;
      line-height: 1.5;
      position: relative;
    }
    .f-col strong {
      display: block;
      color: #0f172a;
      font-size: 0.9rem;
      margin-bottom: 0.3rem;
      font-weight: 800;
    }

    .v-legal-notes {
      margin-top: 1.25rem;
      text-align: center;
      font-size: 0.78rem;
      color: #64748b;
      line-height: 1.45;
      border-top: 1px dashed #cbd5e1;
      padding-top: 0.85rem;
    }
    .v-legal-notes strong { color: #d97706; }

    /* ============================================================ */
    /* STANDARTINIO A4 SPAUSDINIMO (PRINT / PDF) TAISYKLĖS */
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
      .no-print, .action-bar {
        display: none !important;
      }
      .voucher-stage {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
      }
      .a4-voucher {
        width: 100% !important;
        max-width: 100% !important;
        min-height: 275mm !important;
        height: 275mm !important;
        background: #ffffff !important;
        color: #0f172a !important;
        box-shadow: none !important;
        border: 3px solid #1e293b !important;
        border-radius: 12px !important;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        padding: 2.8rem 2.4rem !important;
      }
      @page {
        size: A4 portrait;
        margin: 10mm;
      }
    }

    @media (max-width: 700px) {
      .a4-voucher { padding: 2rem 1.4rem; min-height: auto; }
      .v-hero-title { font-size: 2.6rem; }
      .v-hero-amount { font-size: 3.8rem; }
      .v-hero-amount-wrap { padding: 1rem 2rem; }
      .v-activities-list { grid-template-columns: 1fr; }
      .v-footer-grid { grid-template-columns: 1fr; }
      .action-bar { flex-direction: column; text-align: center; }
    }
  </style>
</head>
<body>

  <!-- VIRŠUTINĖ JUOSTA (TIK EKRANE) -->
  <div class="action-bar no-print">
    <div>
      <div style="font-weight: 800; font-size: 1rem; color: #fff;">Dovanų kuponas: <span style="color: #ff6a00;"><?= $cCode ?></span></div>
      <div style="font-size: 0.78rem; color: #94a3b8;">Oficialus A4 formato dovanų sertifikatas · ThrowHouse Šiauliai</div>
    </div>
    <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
      <a href="kuponas_print.php?code=<?= urlencode($cCode) ?>&download=1" class="action-btn">
        📥 Atsisiųsti PDF failą
      </a>
      <button onclick="window.print()" class="action-btn action-btn-secondary">
        🖨️ Spausdinti
      </button>
      <a href="index.html" class="action-btn action-btn-secondary">
        🌐 Eiti į svetainę
      </a>
    </div>
  </div>

  <div class="voucher-stage">

    <!-- TIKRASIS A4 FORMATO KUPONAS -->
    <div class="a4-voucher">
      <div class="corner-accent corner-tl"></div>
      <div class="corner-accent corner-tr"></div>
      <div class="corner-accent corner-bl"></div>
      <div class="corner-accent corner-br"></div>

      <!-- 1. VIRŠUS: PREKĖS ŽENKLAS -->
      <div class="v-top">
        <h1 class="v-logo">THROW<span>HOUSE</span></h1>
        <div class="v-sublogo">Šiauliai · Adrenalino ir Pramogų Erdvė</div>
      </div>

      <!-- 2. CENTRINĖ HERO DALIS: AIŠKIAI IR RYŠKIAI PER VIDURĮ DOVANŲ KUPONAS IR SUMA -->
      <div class="v-center-hero">
        <div class="v-badge">Oficialus Dovanų Sertifikatas</div>
        
        <!-- RYŠKIAI PER VIDURĮ -->
        <div class="v-hero-title">DOVANŲ KUPONAS</div>

        <!-- RYŠKI SUMA PER VIDURĮ -->
        <div class="v-hero-amount-wrap">
          <div class="v-amount-lbl">Kupono Vertė</div>
          <div class="v-hero-amount"><?= $cAmount ?> €</div>
        </div>

        <div class="v-code-container">
          <div class="v-code-label">Unikalus Registracijos Kodas</div>
          <div class="v-code-val"><?= $cCode ?></div>
        </div>
      </div>

      <!-- 3. PRAMOGŲ IR SĄLYGŲ BLOKAS -->
      <div class="v-details-box">
        <div class="v-activities-title">🎯 Šis kuponas galioja pasirinktai pramogai:</div>
        <div class="v-activities-list">
          <div class="act-badge">
            <strong>Pykčio kambarys</strong>
            1 val. streso iškrovos sesija
          </div>
          <div class="act-badge">
            <strong>Kirvių mėtymas</strong>
            1 val. taiklumo rungtys
          </div>
          <div class="act-badge">
            <strong>KOMBO paketas</strong>
            Abi pramogos kartu!
          </div>
        </div>

        <!-- REZERVACIJA TIK ASMENIŠKAI -->
        <div class="booking-warning-box">
          <strong>⚠️ DĖMESIO – REZERVACIJA TIK ASMENIŠKAI SUSISIEKUS:</strong>
          <p>
            Su šiuo dovanų kuponu apsilankymo laikas derinamas <strong>TIK asmeniškai susisiekus</strong> iš anksto.<br>
            Paskambinkite arba parašykite ir nurodykite kupono kodą <strong><?= $cCode ?></strong>:<br>
            📞 Tel.: <span class="contact-highlight">+370 619 88009</span> &nbsp;&nbsp;|&nbsp;&nbsp;
            ✉️ El. paštas: <span class="contact-highlight">throwhousee@gmail.com</span>
          </p>
        </div>
      </div>

      <!-- 4. APATINIAI REKVIZITAI IR SAUGUMAS -->
      <div>
        <div class="v-footer-grid">
          <div class="f-col">
            <strong>📍 Kur mus rasti:</strong>
            Pramonės g. 15, Šiauliai (2-as aukštas)<br>
            Įėjimas pro duris „Floristika“
          </div>
          <div class="f-col">
            <strong>🕒 Rezervacijos kontaktai:</strong>
            Tel. +370 619 88009<br>
            El. p. throwhousee@gmail.com
          </div>
          <div class="f-col">
            <strong>📅 Galiojimo terminas:</strong>
            Kuponas galioja 6 mėnesius<br>
            (iki <strong><?= $cExpires ?></strong>)
          </div>
        </div>

        <div class="v-legal-notes">
          Saugumui užtikrinti visiems dalyviams <strong>būtina patogi, visiškai uždara avalynė</strong> (sportbačiai). Su atvira avalyne dalyvauti griežtai draudžiama.<br>
          Kuponas į grynuosius pinigus nekeičiamas. „ThrowHouse Šiauliai“ © <?= date('Y') ?>
        </div>
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
