<?php
/**
 * ThrowHouse Šiauliai – Bandomųjų laiškų išsiuntimo skriptas
 * Naudojimas: atidaryti per naršyklę https://throwhouse.lt/siusti_pavyzdzius.php
 */
require_once __DIR__ . '/email_helper.php';

$targetEmail = !empty($_GET['email']) ? trim($_GET['email']) : 'adrijus.cepulis@gmail.com';

$sampleRes = [
    'id' => 'TH-TEST' . rand(100, 999),
    'name' => 'Adrijus Čepulis',
    'phone' => '+370 600 00000',
    'email' => $targetEmail,
    'activity' => 'KOMBO paketas (Kirviai + Pykčio kambarys)',
    'lane' => 'Takelis 1',
    'count' => 2,
    'date' => date('Y-m-d', strtotime('+1 day')),
    'time' => '18:00 - 20:00',
    'price' => '45 €',
    'payment' => 'Apmokėta internetu (OPAY)',
    'status' => 'Patvirtinta'
];

// 1. Patvirtinimo laiškas
$confHtml = renderNewReservationClientHtml($sampleRes);
$confSub = "Rezervacijos patvirtinimas [{$sampleRes['id']}] – ThrowHouse Šiauliai";
$confSent = sendThrowHouseMail($targetEmail, $confSub, $confHtml, 'info@throwhouse.lt', 'ThrowHouse Šiauliai');

// 2. Google atsiliepimo kvietimas
$revHtml = renderReviewRequestEmailHtml($sampleRes);
$revSub = "Ačiū, kad apsilankėte ThrowHouse Šiauliai! 🔥 Kaip sekėsi?";
$revSent = sendThrowHouseMail($targetEmail, $revSub, $revHtml, 'info@throwhouse.lt', 'ThrowHouse Šiauliai');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <title>ThrowHouse Šiauliai – Bandomųjų laiškų siuntimas</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0b0f19; color: #f8fafc; padding: 40px 20px; line-height: 1.6; }
    .card { max-width: 600px; margin: 0 auto; background: #131722; border: 1px solid #2d3748; border-radius: 12px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    h1 { color: #b8141b; margin-top: 0; font-size: 22px; }
    .status-ok { color: #34d399; font-weight: bold; background: rgba(52, 211, 153, 0.1); padding: 4px 10px; border-radius: 6px; }
    .status-fail { color: #f87171; font-weight: bold; background: rgba(248, 113, 113, 0.1); padding: 4px 10px; border-radius: 6px; }
    .btn { display: inline-block; background: #b8141b; color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: bold; margin-top: 20px; }
  </style>
</head>
<body>
  <div class="card">
    <h1>THROW HOUSE ŠIAULIAI</h1>
    <p>Bandomųjų el. laiškų siuntimas į: <strong><?= htmlspecialchars($targetEmail) ?></strong></p>
    
    <hr style="border: 0; border-top: 1px solid #2d3748; margin: 20px 0;">

    <p>
      <strong>1. Rezervacijos patvirtinimo laiškas:</strong><br>
      <?= $confSent ? '<span class="status-ok">✓ SĖKMINGAI IŠSIŲSTA</span>' : '<span class="status-fail">✕ NEPAVYKO IŠSIŲSTI</span>' ?>
    </p>

    <p>
      <strong>2. Padėkos ir Google atsiliepimo kvietimas:</strong><br>
      <?= $revSent ? '<span class="status-ok">✓ SĖKMINGAI IŠSIŲSTA</span>' : '<span class="status-fail">✕ NEPAVYKO IŠSIŲSTI</span>' ?>
    </p>

    <p style="font-size: 13px; color: #94a3b8; margin-top: 25px;">
      Patikrinkite savo pašto dėžutę (ir „Spam“ aplanką, jei laiškas nuklystų).
    </p>

    <a href="admin.php?tab=email" class="btn">➔ Grįžti į valdymo pultą (admin.php)</a>
  </div>
</body>
</html>
