<?php
// =========================================================================
// ThrowHouse Šiauliai - Spausdinamas QR stovelis / skrajutė barui
// =========================================================================

date_default_timezone_set('Europe/Vilnius');
$barUrl = 'https://throwhouse.lt/baras.php';
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode($barUrl) . '&format=png&margin=1';
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Spausdinamas QR stovelis | ThrowHouse Baras</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #111;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 2rem 1rem;
    }
    .top-actions {
      max-width: 500px;
      width: 100%;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.65rem 1.25rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: 0.92rem;
      cursor: pointer;
      text-decoration: none;
      border: none;
    }
    .btn-print {
      background: #e58e26;
      color: #0c0a09;
    }
    .btn-back {
      background: rgba(255, 255, 255, 0.1);
      color: #cbd5e1;
    }

    /* Kortelė / Stovelis */
    .stand-card {
      width: 100%;
      max-width: 440px;
      background: #000;
      border: 4px solid #e58e26;
      border-radius: 20px;
      padding: 2.25rem 2rem;
      text-align: center;
      box-shadow: 0 20px 50px rgba(0,0,0,0.8);
      position: relative;
      overflow: hidden;
    }
    .stand-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0; height: 8px;
      background: linear-gradient(90deg, #e58e26, #f59e0b, #e58e26);
    }
    .brand {
      font-family: 'Oswald', sans-serif;
      font-size: 1.6rem;
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 0.2rem;
    }
    .brand span { color: #e58e26; }
    .tagline {
      font-size: 0.85rem;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 2px;
      font-weight: 700;
      margin-bottom: 1.25rem;
    }
    .main-title {
      font-family: 'Oswald', sans-serif;
      font-size: 2rem;
      font-weight: 800;
      line-height: 1.15;
      color: #fff;
      text-transform: uppercase;
      margin-bottom: 0.4rem;
    }
    .main-title span {
      color: #e58e26;
    }
    .sub-title {
      font-size: 0.95rem;
      color: #cbd5e1;
      margin-bottom: 1.25rem;
      line-height: 1.4;
    }

    /* QR rėmelis */
    .qr-box {
      background: #fff;
      padding: 1.15rem;
      border-radius: 16px;
      display: inline-block;
      margin-bottom: 1.25rem;
      box-shadow: 0 10px 30px rgba(229, 142, 38, 0.35);
    }
    .qr-img {
      width: 220px;
      height: 220px;
      display: block;
    }

    .qr-url-badge {
      display: inline-block;
      background: rgba(229, 142, 38, 0.15);
      border: 1px solid rgba(229, 142, 38, 0.35);
      color: #e58e26;
      font-family: 'Oswald', sans-serif;
      font-size: 1.15rem;
      font-weight: 700;
      padding: 0.35rem 1rem;
      border-radius: 20px;
      margin-bottom: 1.5rem;
      letter-spacing: 0.5px;
    }

    /* Instrukcijos */
    .steps-list {
      text-align: left;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      padding: 1rem 1.15rem;
      margin-bottom: 1.25rem;
    }
    .step-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.88rem;
      margin-bottom: 0.65rem;
      color: #e2e8f0;
    }
    .step-item:last-child { margin-bottom: 0; }
    .step-num {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: #e58e26;
      color: #000;
      font-weight: 800;
      font-size: 0.8rem;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .card-footer {
      font-size: 0.75rem;
      color: #94a3b8;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      padding-top: 0.85rem;
    }

    /* Print styles */
    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .top-actions {
        display: none !important;
      }
      .stand-card {
        box-shadow: none;
        border: 3px solid #000;
        color: #000;
        background: #fff;
        max-width: 100%;
        page-break-inside: avoid;
      }
      .brand, .main-title {
        color: #000 !important;
      }
      .brand span, .main-title span {
        color: #d97706 !important;
      }
      .tagline, .sub-title, .card-footer {
        color: #4b5563 !important;
      }
      .steps-list {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        color: #111827;
      }
      .step-item {
        color: #111827;
      }
      .qr-url-badge {
        color: #000;
        border-color: #000;
        background: #f3f4f6;
      }
    }
  </style>
</head>
<body>

  <div class="top-actions" style="max-width: 560px; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
    <a href="admin.php?tab=baras" class="btn btn-back">⬅️ Grįžti į Valdymą</a>
    <div style="display: flex; gap: 0.5rem;">
      <a href="baras_qr.png" download="throwhouse_baras_qr.png" class="btn" style="background: rgba(229,142,38,0.2); color: #e58e26; border: 1px solid rgba(229,142,38,0.4);">
        💾 Atsisiųsti tik QR kodą (PNG)
      </a>
      <button type="button" class="btn btn-print" onclick="window.print()">🖨️ Spausdinti stovelį</button>
    </div>
  </div>

  <div class="stand-card">
    <div class="brand">THROW<span>HOUSE</span></div>
    <div class="tagline">Pramonės g. 15, Šiauliai</div>

    <h1 class="main-title">Gėrimai & Užkandžiai <span>Telefonu</span></h1>
    <p class="sub-title">Užsisakykite be eilių tiesiai iš savo sofos ar takelio!</p>

    <div class="qr-box">
      <a href="baras_qr.png" download="throwhouse_baras_qr.png" title="Paspauskite, kad atsisiųstumėte gryną QR kodą (PNG)">
        <img src="<?= htmlspecialchars($qrApiUrl) ?>" alt="QR kodas užsakymui" class="qr-img">
      </a>
    </div>

    <div>
      <div class="qr-url-badge">🌐 throwhouse.lt/baras.php</div>
    </div>

    <div class="steps-list">
      <div class="step-item">
        <span class="step-num">1</span>
        <span>Nuskenuokite QR kodą savo telefono kamera</span>
      </div>
      <div class="step-item">
        <span class="step-num">2</span>
        <span>Išsirinkite gėrimus / užkandžius ir įveskite savo vardą</span>
      </div>
      <div class="step-item">
        <span class="step-num">3</span>
        <span>Apmokėkite per el. banką – paruošime akimirksniu!</span>
      </div>
    </div>

    <div class="card-footer">
      Saugus atsiskaitymas internetu • Jokio laukimo prie baro
    </div>
  </div>

</body>
</html>