<?php
// kuponas_print.php - A4 formato ThrowHouse dovanų kuponas spausdinimui ir PDF išsaugojimui
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
      background: #08050e;
      color: #fff;
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      -webkit-font-smoothing: antialiased;
    }

    /* VALDYMO JUOSTA EKRANE */
    .action-bar {
      width: 100%;
      background: #110c1c;
      border-bottom: 1px solid rgba(255, 106, 0, 0.35);
      padding: 0.85rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 20px rgba(0,0,0,0.6);
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
      padding: 2rem 1rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 100%;
    }

    /* A4 FORMATO KORTELĖ */
    .a4-voucher {
      width: 100%;
      max-width: 794px; /* Standartinis A4 plotis 96dpi (210mm) */
      min-height: 1050px; /* Standartinis A4 aukštis (297mm) */
      background: #0f0b18;
      background: radial-gradient(circle at 50% 30%, #1c112e 0%, #0c0816 65%, #050308 100%);
      border: 3px solid #ff6a00;
      border-radius: 18px;
      padding: 3rem 2.8rem;
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.85), 0 0 45px rgba(255, 106, 0, 0.2);
    }

    /* VIDINIS RĖMELIS */
    .a4-voucher::before {
      content: '';
      position: absolute;
      top: 14px; left: 14px; right: 14px; bottom: 14px;
      border: 1px solid rgba(255, 106, 0, 0.4);
      border-radius: 12px;
      pointer-events: none;
    }

    .corner-accent {
      position: absolute;
      width: 32px;
      height: 32px;
      border-color: #ff6a00;
      border-style: solid;
      pointer-events: none;
    }
    .corner-tl { top: 22px; left: 22px; border-width: 3px 0 0 3px; }
    .corner-tr { top: 22px; right: 22px; border-width: 3px 3px 0 0; }
    .corner-bl { bottom: 22px; left: 22px; border-width: 0 0 3px 3px; }
    .corner-br { bottom: 22px; right: 22px; border-width: 0 3px 3px 0; }

    /* 1. VIRŠUS */
    .v-top {
      text-align: center;
      position: relative;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .v-logo {
      font-family: 'Outfit', sans-serif;
      font-size: 2.2rem;
      font-weight: 900;
      letter-spacing: 2.5px;
      margin: 0;
      color: #fff;
    }
    .v-logo span { color: #ff6a00; }
    .v-sublogo {
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 3px;
      color: #94a3b8;
      font-weight: 700;
      margin-top: 0.35rem;
    }

    /* 2. CENTRINĖ HERO DALIS: DOVANŲ KUPONAS IR SUMA */
    .v-center-hero {
      text-align: center;
      padding: 2.5rem 1rem;
      position: relative;
    }

    .v-title-badge {
      display: inline-block;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 3px;
      font-weight: 800;
      color: #ff8533;
      background: rgba(255, 106, 0, 0.12);
      border: 1px solid rgba(255, 106, 0, 0.4);
      padding: 0.4rem 1.4rem;
      border-radius: 30px;
      margin-bottom: 1.25rem;
    }

    /* AIŠKIAI IR RYŠKIAI PER VIDURĮ: DOVANŲ KUPONAS */
    .v-hero-title {
      font-family: 'Outfit', sans-serif;
      font-size: 3.6rem;
      font-weight: 900;
      letter-spacing: 3px;
      color: #ffffff;
      text-transform: uppercase;
      margin: 0 0 0.5rem 0;
      line-height: 1.1;
      text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }

    /* AIŠKIAI IR RYŠKIAI PER VIDURĮ: SUMA */
    .v-hero-amount-wrap {
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      margin: 1.5rem 0;
      background: rgba(0, 0, 0, 0.6);
      border: 2px solid #ff6a00;
      border-radius: 20px;
      padding: 1.25rem 3.5rem;
      box-shadow: 0 8px 30px rgba(255, 106, 0, 0.25);
    }
    .v-amount-lbl {
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #a1a1aa;
      font-weight: 700;
    }
    .v-hero-amount {
      font-family: 'Outfit', sans-serif;
      font-size: 5rem;
      font-weight: 900;
      color: #ff6a00;
      line-height: 1;
      margin: 0.25rem 0;
      text-shadow: 0 0 25px rgba(255, 106, 0, 0.5);
    }

    /* KODAS */
    .v-code-container {
      margin-top: 1rem;
    }
    .v-code-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #94a3b8;
      font-weight: 800;
      margin-bottom: 0.4rem;
    }
    .v-code-val {
      font-family: 'Space Mono', monospace;
      font-size: 1.7rem;
      font-weight: 700;
      letter-spacing: 3px;
      color: #ffffff;
      background: rgba(255, 255, 255, 0.05);
      border: 1px dashed rgba(255, 106, 0, 0.6);
      padding: 0.5rem 1.4rem;
      border-radius: 8px;
      display: inline-block;
    }

    /* 3. PRAMOGŲ IR SĄLYGŲ BLOKAS */
    .v-details-box {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 14px;
      padding: 1.5rem 1.8rem;
      margin: 1.5rem 0;
      position: relative;
    }

    .v-activities-title {
      font-size: 0.95rem;
      font-weight: 800;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 0.6rem;
    }
    .v-activities-list {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 0.75rem;
      margin-bottom: 1.25rem;
    }
    .act-badge {
      background: rgba(255, 106, 0, 0.08);
      border: 1px solid rgba(255, 106, 0, 0.25);
      border-radius: 8px;
      padding: 0.6rem 0.8rem;
      text-align: center;
      font-size: 0.85rem;
      color: #e2e8f0;
      font-weight: 600;
    }
    .act-badge strong { color: #f59e0b; display: block; font-size: 0.95rem; margin-bottom: 2px; }

    /* REZERVACIJA TIK ASMENIŠKAI */
    .booking-warning-box {
      background: rgba(245, 158, 11, 0.1);
      border-left: 4px solid #ff6a00;
      border-radius: 0 8px 8px 0;
      padding: 0.9rem 1.2rem;
      text-align: left;
    }
    .booking-warning-box strong {
      color: #ff8533;
      font-size: 0.95rem;
      display: block;
      margin-bottom: 0.25rem;
    }
    .booking-warning-box p {
      margin: 0;
      font-size: 0.88rem;
      color: #cbd5e1;
      line-height: 1.45;
    }
    .booking-warning-box .contact-highlight {
      color: #ffffff;
      font-weight: 800;
      font-size: 0.95rem;
    }

    /* 4. APATINĖ INFORMACIJA */
    .v-footer-grid {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      padding-top: 1.25rem;
      display: grid;
      grid-template-columns: 1.2fr 1.3fr 1fr;
      gap: 1.25rem;
      font-size: 0.82rem;
      color: #94a3b8;
      line-height: 1.45;
      position: relative;
    }
    .f-col strong {
      display: block;
      color: #fff;
      font-size: 0.85rem;
      margin-bottom: 0.25rem;
    }

    .v-legal-notes {
      margin-top: 1rem;
      text-align: center;
      font-size: 0.75rem;
      color: #71717a;
      line-height: 1.4;
    }
    .v-legal-notes strong { color: #f59e0b; }

    /* ============================================================ */
    /* TIKRAI A4 SPAUSDINIMO (PRINT / PDF) TAISYKLĖS */
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
        background: #0f0b18 !important;
        color: #ffffff !important;
        box-shadow: none !important;
        border: 3px solid #ff6a00 !important;
        border-radius: 12px !important;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        padding: 2.5rem 2.2rem !important;
      }
      .v-logo { color: #ffffff !important; }
      .v-logo span { color: #ff6a00 !important; }
      .v-hero-title { color: #ffffff !important; }
      .v-hero-amount { color: #ff6a00 !important; }
      .v-code-val { color: #ffffff !important; background: rgba(255, 106, 0, 0.2) !important; }
      .f-col strong { color: #ffffff !important; }
      .f-col { color: #cbd5e1 !important; }
      .act-badge { color: #ffffff !important; }
      .act-badge strong { color: #f59e0b !important; }

      @page {
        size: A4 portrait;
        margin: 10mm;
      }
    }

    @media (max-width: 700px) {
      .a4-voucher { padding: 1.75rem 1.25rem; min-height: auto; }
      .v-hero-title { font-size: 2.4rem; }
      .v-hero-amount { font-size: 3.5rem; }
      .v-hero-amount-wrap { padding: 1rem 1.8rem; }
      .v-activities-list { grid-template-columns: 1fr; }
      .v-footer-grid { grid-template-columns: 1fr; }
      .action-bar { flex-direction: column; text-align: center; }
    }
  </style>
</head>
<body>

  <!-- VALDYMO JUOSTA (TIK EKRANE) -->
  <div class="action-bar no-print">
    <div>
      <div style="font-weight: 800; font-size: 1rem; color: #fff;">Dovanų kuponas: <span style="color: #ff6a00;"><?= $cCode ?></span></div>
      <div style="font-size: 0.78rem; color: #94a3b8;">A4 formato failas – paruošta spausdinimui arba išsaugojimui į PDF</div>
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

    <!-- TIKRASIS A4 FORMATO KUPONAS -->
    <div class="a4-voucher">
      <div class="corner-accent corner-tl"></div>
      <div class="corner-accent corner-tr"></div>
      <div class="corner-accent corner-bl"></div>
      <div class="corner-accent corner-br"></div>

      <!-- 1. VIRŠUS -->
      <div class="v-top">
        <h1 class="v-logo">THROW<span>HOUSE</span></h1>
        <div class="v-sublogo">Šiauliai · Adrenalino ir Pramogų Erdvė</div>
      </div>

      <!-- 2. CENTRINĖ HERO DALIS: DOVANŲ KUPONAS IR SUMA -->
      <div class="v-center-hero">
        <div class="v-title-badge">Oficialus Dovanų Sertifikatas</div>
        
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
            Streso iškrovos sesija
          </div>
          <div class="act-badge">
            <strong>Kirvių mėtymas</strong>
            Taiklumo rungtys
          </div>
          <div class="act-badge">
            <strong>KOMBO paketas</strong>
            Abi pramogos kartu
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

      <!-- 4. APATINIAI REKVIZITAI -->
      <div>
        <div class="v-footer-grid">
          <div class="f-col">
            <strong>📍 Kur mus rasti:</strong>
            Pramonės g. 15, Šiauliai (2-as aukštas)<br>
            Įėjimas pro duris pažymėtas „Floristika“
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
          Saugumui užtikrinti visiems dalyviams <strong>būtina visiškai uždara avalynė</strong> (sportbačiai). Su atvira avalyne dalyvauti draudžiama.<br>
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
