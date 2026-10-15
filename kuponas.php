<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neteisingas užklausos metodas']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$amount = intval($input['amount'] ?? 25);
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

$dataFile = __DIR__ . '/kuponai.json';
$all = [];
if (file_exists($dataFile)) {
    $all = json_decode(file_get_contents($dataFile), true) ?: [];
}
$all[] = $coupon;
file_put_contents($dataFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// =============================================================
// 1. Tiesioginis dovanų kupono HTML laiškas gavėjui (arba pirkėjui, jei pirko sau)
// =============================================================
$isFriend = ($giftTarget === 'friend');

if ($isFriend) {
    $subVoucher = "=?UTF-8?B?" . base64_encode("🎁 Jums dovanų kuponas nuo $buyerName! [$code] ($amount €)") . "?=";
    $introGreeting = '<p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $targetName . '</strong>!</p>
    <p style="font-size: 15px; color: #334155;"><strong>' . $buyerName . '</strong> Jums padovanojo <strong>' . $amount . ' €</strong> vertės pramogų erdvės <strong>„ThrowHouse Šiauliai“</strong> dovanų kuponą!</p>';
} else {
    $subVoucher = "=?UTF-8?B?" . base64_encode("Jūsų ThrowHouse Šiauliai dovanų kuponas [$code] ($amount €)") . "?=";
    $introGreeting = '<p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $buyerName . '</strong>!</p>
    <p style="font-size: 15px; color: #334155;">Nuoširdžiai dėkojame, kad įsigijote pramogų erdvės <strong>„ThrowHouse Šiauliai“</strong> dovanų kuponą! Žemiau pateikiamas Jūsų kuponas:</p>';
}

$voucherHtml = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
    
    <!-- HEADER -->
    <div style="background-color: #0f172a; color: #ffffff; padding: 26px 20px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 22px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Oficialus Dovanų Kuponas</p>
    </div>

    <div style="padding: 24px; line-height: 1.6; font-size: 15px; color: #334155;">
      ' . $introGreeting . '
      
      <!-- KUPONO VIZUALINĖ KORTELĖ -->
      <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 2px dashed #f59e0b; border-radius: 14px; padding: 24px; margin: 20px 0; color: #ffffff; text-align: center;">
        <span style="font-size: 12px; font-weight: 800; letter-spacing: 2px; color: #f59e0b; text-transform: uppercase;">Dovanų Kuponas</span>
        
        <div style="margin: 14px 0 16px 0;">
          <span style="display: inline-block; background-color: #f59e0b; color: #0b0f19; font-size: 32px; font-weight: 900; padding: 6px 26px; border-radius: 30px;">
            ' . $amount . ' €
          </span>
        </div>

        <div style="background-color: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px; margin-bottom: 14px;">
          <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; display: block;">Unikalus kupono kodas:</span>
          <span style="font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: 2px; font-family: Courier, monospace;">' . $code . '</span>
        </div>

        <table style="width: 100%; font-size: 13px; color: #cbd5e1; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px; margin-top: 10px;">
          <tr>
            <td style="text-align: left; padding: 3px 0;">Gavėjas:</td>
            <td style="text-align: right; font-weight: bold; color: #ffffff;">' . $targetName . '</td>
          </tr>
          ' . ($isFriend ? '
          <tr>
            <td style="text-align: left; padding: 3px 0;">Dovanotojas:</td>
            <td style="text-align: right; font-weight: bold; color: #f59e0b;">' . $buyerName . '</td>
          </tr>' : '') . '
          <tr>
            <td style="text-align: left; padding: 3px 0;">Galioja iki:</td>
            <td style="text-align: right; font-weight: bold; color: #34d399;">' . $expiresAt . ' (6 mėn.)</td>
          </tr>
        </table>
      </div>

      ' . (!empty($greeting) ? '
      <!-- SVEIKINIMO ŽINUTĖ -->
      <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #92400e; display: block; margin-bottom: 4px; font-size: 14px;">💌 ' . $buyerName . ' Jums paliko žinutę:</strong>
        <p style="margin: 0; color: #78350f; font-style: italic; font-size: 14px;">„' . nl2br($greeting) . '“</p>
      </div>' : '') . '

      <!-- KAIP PANAUDOTI KUPONĄ (PAGAL REIKALAVIMĄ: KREIPTIS ASMENIŠKAI TELEFONU ARBA EL. PAŠTU) -->
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin: 18px 0;">
        <strong style="color: #0f172a; display: block; margin-bottom: 8px; font-size: 15px;">📌 Kaip pasinaudoti šiuo kuponu?</strong>
        <p style="margin: 0 0 10px 0; font-size: 14px; color: #334155; line-height: 1.6;">
          Dėl kupono panaudojimo ir Jums patogaus apsilankymo laiko suderinimo <strong>prašome kreiptis asmeniškai telefonu arba el. paštu</strong>:
        </p>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin: 10px 0; line-height: 1.8; font-size: 14px;">
          📞 <strong>Telefonu:</strong> <a href="tel:+37061988009" style="color: #d97706; font-weight: bold; text-decoration: none;">+370 619 88009</a><br>
          ✉️ <strong>El. paštu:</strong> <a href="mailto:throwhousee@gmail.com" style="color: #d97706; text-decoration: none;">throwhousee@gmail.com</a>
        </div>
        <p style="margin: 8px 0 0 0; font-size: 13px; color: #64748b;">
          Kreipdamiesi tiesiog nurodykite savo unikalų kupono kodą: <strong style="color: #0f172a; font-family: monospace;">' . $code . '</strong>.
        </p>
      </div>

      <!-- LOKACIJA IR PATEKIMAS -->
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin: 18px 0;">
        <strong style="color: #0f172a; display: block; margin-bottom: 8px; font-size: 15px;">📍 Kur mus rasti:</strong>
        <p style="margin: 0 0 10px 0; font-size: 14px;">
          Adresas: <strong>Pramonės g. 15, Šiauliai (2-as aukštas)</strong><br>
          Koordinatės: 55.910730, 23.315700
        </p>
        <div style="text-align: center; margin: 12px 0;">
          <a href="https://www.google.com/maps?q=55.910730,23.315700" style="display:inline-block; background-color:#2563eb; color:#ffffff; font-weight:bold; text-decoration:none; padding:10px 18px; border-radius:6px; font-size:13px; margin:4px;" target="_blank">🗺️ Google Maps žemėlapis</a>
          <a href="https://waze.com/ul?ll=55.910730,23.315700&navigate=yes" style="display:inline-block; background-color:#0284c7; color:#ffffff; font-weight:bold; text-decoration:none; padding:10px 18px; border-radius:6px; font-size:13px; margin:4px;" target="_blank">🚗 Waze navigacija</a>
        </div>
      </div>

      <!-- PATEKIMAS Į PATALPAS -->
      <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #92400e; display: block; margin-bottom: 4px; font-size: 14px;">🚪 Patekimas į patalpas:</strong>
        <p style="margin: 0; color: #78350f; font-size: 14px;">
          Atvykus prie pastato (Pramonės g. 15), maloniai prašome užeiti pro duris, pažymėtas iškaba <strong>„Floristika“</strong>. Patekus į pastato vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą – čia Jūsų lauks „ThrowHouse“ erdvė.
        </p>
      </div>

      <!-- AVALYNĖ -->
      <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #065f46; display: block; margin-bottom: 4px; font-size: 14px;">👟 Avalynė:</strong>
        <p style="margin: 0; color: #047857; font-size: 14px;">
          Saugumo sumetimais visiems dalyviams būtina patogi, visiškai uždara avalynė (sportbačiai ar auliniai batai). Su atvira avalyne dalyvauti griežtai draudžiama.
        </p>
      </div>
    </div>

    <!-- FOOTER -->
    <div style="background-color: #f1f5f9; padding: 16px 20px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0;">
      Linkime pačių geriausių įspūdžių!<br>
      <strong>„ThrowHouse Šiauliai“ komanda</strong> · Pramonės g. 15, Šiauliai · Tel. +370 619 88009
    </div>
  </div>
</body>
</html>';

// =============================================================
// 2. Jei dovana siųsta draugui – pirkėjas gauna pirkimo patvirtinimą
// =============================================================
$buyerReceiptHtml = null;
if ($isFriend) {
    $subBuyerReceipt = "=?UTF-8?B?" . base64_encode("Dovanų kuponas [$code] sėkmingai išsiųstas gavėjui $targetName") . "?=";
    $buyerReceiptHtml = '<!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"></head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
      <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0;">
        <div style="background-color: #0f172a; color: #ffffff; padding: 22px; text-align: center; border-bottom: 3px solid #10b981;">
          <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #34d399; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
          <p style="margin: 0; font-size: 13px; color: #94a3b8;">Dovanų kupono pirkimo patvirtinimas</p>
        </div>
        <div style="padding: 24px; font-size: 14px; line-height: 1.6; color: #334155;">
          <p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $buyerName . '</strong>!</p>
          <p>Nuoširdžiai dėkojame! Jūsų užsakytas dovanų kuponas buvo sėkmingai sugeneruotas ir išsiųstas tiesiai Jūsų nurodytam gavėjui:</p>
          
          <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin: 16px 0;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
              <tr><td style="padding: 6px 0; color: #64748b;">Gavėjas:</td><td style="font-weight: bold; color: #0f172a;">' . $targetName . '</td></tr>
              <tr><td style="padding: 6px 0; color: #64748b;">Gavėjo el. paštas:</td><td style="font-weight: bold; color: #2563eb;">' . $targetEmail . '</td></tr>
              <tr><td style="padding: 6px 0; color: #64748b;">Kupono kodas:</td><td style="font-weight: bold; color: #d97706; font-family: monospace;">' . $code . '</td></tr>
              <tr><td style="padding: 6px 0; color: #64748b;">Kupono vertė:</td><td style="font-weight: bold; font-size: 16px; color: #059669;">' . $amount . ' €</td></tr>
              <tr><td style="padding: 6px 0; color: #64748b;">Galioja iki:</td><td>' . $expiresAt . ' (6 mėn.)</td></tr>
            </table>
          </div>

          ' . (!empty($greeting) ? '
          <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 14px; margin: 14px 0; border-radius: 0 8px 8px 0; font-size: 13px;">
            <strong>Jūsų palinkėjimas draugui:</strong><br>
            <span style="font-style: italic; color: #78350f;">„' . nl2br($greeting) . '“</span>
          </div>' : '') . '

          <p style="font-size: 13px; color: #64748b;">
            Gavėjas el. laiške buvo informuotas, kad dėl kupono panaudojimo ir laiko suderinimo reikia kreiptis asmeniškai telefonu +370 619 88009 arba el. paštu.
          </p>
        </div>
        <div style="background-color: #f1f5f9; padding: 14px 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
          Dėkojame, kad renkatės „ThrowHouse Šiauliai“!
        </div>
      </div>
    </body>
    </html>';
}

// =============================================================
// 3. Prabangus, modernus HTML laiškas administratoriui (ne tik lentelė!)
// =============================================================
$subAdmin = "=?UTF-8?B?" . base64_encode("Naujas kuponas [$code]: $amount € - " . ($isFriend ? "Dovana ($buyerName -> $targetName)" : "Pirkėjas $buyerName")) . "?=";

$adminHtmlBody = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
    
    <!-- HEADER -->
    <div style="background-color: #0f172a; color: #ffffff; padding: 24px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8;">Gautas naujas dovanų kupono pirkimas</p>
      
      <div style="margin-top: 10px;">
        ' . ($isFriend ? '
        <span style="display:inline-block; background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; font-weight: bold; padding: 4px 14px; border-radius: 20px; font-size: 12px;">
          🎁 DOVANA DRAUGUI (IŠSIŲSTA TIESIAI)
        </span>' : '
        <span style="display:inline-block; background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: bold; padding: 4px 14px; border-radius: 20px; font-size: 12px;">
          👤 PIRKTA SAU / ĮTEIKS PATS
        </span>') . '
      </div>
    </div>

    <div style="padding: 24px; font-size: 14px; line-height: 1.6; color: #334155;">
      
      <!-- KUPONO AKCENTAS -->
      <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 12px; padding: 18px 22px; color: #ffffff; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
          <span style="font-size: 11px; color: #f59e0b; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; display: block;">Kupono kodas</span>
          <span style="font-size: 22px; font-weight: 800; font-family: monospace; letter-spacing: 2px;">' . $code . '</span>
        </div>
        <div style="text-align: right;">
          <span style="font-size: 11px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; display: block;">Vertė</span>
          <span style="font-size: 26px; font-weight: 900; color: #34d399;">' . $amount . ' €</span>
        </div>
      </div>

      <!-- PIRKĖJO DUOMENYS -->
      <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 8px; display: block;">👤 Pirkėjo kontaktai:</span>
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 18px;">
        <div style="font-size: 17px; font-weight: bold; color: #0f172a; margin-bottom: 6px;">' . $buyerName . '</div>
        <div style="color: #475569; margin-bottom: 12px; line-height: 1.8;">
          📞 Tel.: <strong>' . $buyerPhone . '</strong><br>
          ✉️ El. paštas: <strong>' . $buyerEmail . '</strong>
        </div>
        <div>
          <a href="tel:' . $buyerPhone . '" style="display:inline-block; background-color:#10b981; color:#ffffff; font-weight:bold; text-decoration:none; padding:9px 15px; border-radius:6px; font-size:13px; margin-right:8px; margin-bottom:4px;">📞 Skambinti pirkėjui</a>
          <a href="mailto:' . $buyerEmail . '" style="display:inline-block; background-color:#6366f1; color:#ffffff; font-weight:bold; text-decoration:none; padding:9px 15px; border-radius:6px; font-size:13px; margin-bottom:4px;">✉️ Rašyti laišką</a>
        </div>
      </div>

      ' . ($isFriend ? '
      <!-- GAVĖJO DUOMENYS (JEI DOVANA DRAUGUI) -->
      <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 8px; display: block;">🎁 Dovanos gavėjas (kur išsiųstas kuponas):</span>
      <div style="background-color: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; border-radius: 0 10px 10px 0; padding: 16px; margin-bottom: 18px;">
        <div style="font-size: 16px; font-weight: bold; color: #92400e; margin-bottom: 4px;">' . $targetName . '</div>
        <div style="color: #78350f; font-size: 14px; margin-bottom: 8px;">
          ✉️ El. paštas: <strong>' . $targetEmail . '</strong>
        </div>
        ' . (!empty($greeting) ? '
        <div style="background-color: #ffffff; border: 1px dashed #fcd34d; border-radius: 8px; padding: 10px 12px; margin-top: 8px; font-size: 13px; color: #78350f;">
          <strong>Palinkėjimas:</strong> <em>„' . nl2br($greeting) . '“</em>
        </div>' : '') . '
      </div>' : '') . '

      <!-- PARAMETRAI -->
      <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px;">
        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9;">
          <span style="color: #64748b;">Užsakymo laikas:</span>
          <strong>' . $createdDate . '</strong>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9;">
          <span style="color: #64748b;">Galiojimo pabaiga:</span>
          <strong style="color: #059669;">' . $expiresAt . ' (6 mėn.)</strong>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 6px 0;">
          <span style="color: #64748b;">Kupono būsena:</span>
          <strong style="color: #3b82f6;">Aktyvus</strong>
        </div>
      </div>

      <!-- VALDYMO PULTAS -->
      <div style="text-align: center; margin: 26px 0 12px 0;">
        <a href="https://violet-dove-675017.hostingersite.com/admin.php?tab=kuponai" style="display:inline-block; background-color:#0f172a; color:#ffffff; font-weight:bold; text-decoration:none; padding:12px 24px; border-radius:8px; border:1px solid #334155; font-size:14px;" target="_blank">
          ⚙️ Peržiūrėti kuponus valdymo pulte (admin.php) ➔
        </a>
        <div style="font-size:12px; color:#64748b; margin-top:8px;">
          Tiesioginė nuoroda: <a href="https://violet-dove-675017.hostingersite.com/admin.php?tab=kuponai" style="color:#d97706; word-break:break-all;">https://violet-dove-675017.hostingersite.com/admin.php?tab=kuponai</a>
        </div>
      </div>

    </div>
    <div style="background-color: #f8fafc; padding: 14px 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
      ThrowHouse Šiauliai · Dovanų kuponų valdymo sistema · ' . date('Y-m-d H:i') . '
    </div>
  </div>
</body>
</html>';

// =============================================================
// 4. FormSubmit atsarginis kanalas (užtikrina pristatymą)
// =============================================================
$plainTextAutoresponse = "Sveiki, $targetName!\n\n"
    . "Dovanų kuponas pramogų erdvėje „ThrowHouse Šiauliai“:\n"
    . "• Kodas: $code\n"
    . "• Vertė: $amount €\n"
    . "• Galioja iki: $expiresAt (6 mėn.)\n\n"
    . "📌 KAIP PANAUDOTI:\n"
    . "Dėl kupono panaudojimo ir apsilankymo laiko suderinimo prašome kreiptis asmeniškai:\n"
    . "• Tel.: +370 619 88009\n"
    . "• El. paštu: throwhousee@gmail.com\n\n"
    . "📍 Pramonės g. 15, Šiauliai (2-as a., įėjimas pro duris „Floristika“).";

$formSubmitData = [
    'Kupono_Kodas' => $code,
    'Kupono_Verte' => $amount . ' €',
    'Tipas' => $targetLabel,
    'Pirkejo_Vardas' => $buyerName,
    'Telefono_numeris' => $buyerPhone,
    'Pirkejo_El_pastas' => $buyerEmail,
    'email' => $targetEmail,
    '_replyto' => $buyerEmail,
    'Gavejas' => $targetName,
    'Gavejo_El_pastas' => $targetEmail,
    'Sveikinimas' => $greeting ?: 'Nėra',
    'Galioja_iki' => $expiresAt,
    '_subject' => "Naujas dovanų kuponas [$code] - $amount € ($targetLabel)",
    '_template' => "table",
    '_autoresponse' => $plainTextAutoresponse
];

$ch = curl_init("https://formsubmit.co/ajax/throwhousee@gmail.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($formSubmitData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Origin: https://violet-dove-675017.hostingersite.com',
    'Referer: https://violet-dove-675017.hostingersite.com/'
]);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$fsResp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// =============================================================
// 5. Tiesioginis serverio PHP mail() su anti-spam RFC antraštėmis
// =============================================================
$serverHost = $_SERVER['SERVER_NAME'] ?? 'violet-dove-675017.hostingersite.com';
$fromEmail = "noreply@" . $serverHost;
$dateHeader = date('r');

// 1) Siunčiame administratoriui
$adminMsgId = "<th-kup-adm-" . $code . "-" . time() . "@" . $serverHost . ">";
$headersAdmin = "MIME-Version: 1.0\r\n"
    . "Date: $dateHeader\r\n"
    . "Message-ID: $adminMsgId\r\n"
    . "From: ThrowHouse Kuponai <$fromEmail>\r\n"
    . "Reply-To: $buyerEmail\r\n"
    . "Auto-Submitted: auto-generated\r\n"
    . "X-Mailer: ThrowHouse Engine\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n";

@mail("throwhousee@gmail.com", $subAdmin, $adminHtmlBody, $headersAdmin, "-f" . $fromEmail);

// 2) Siunčiame kuponą gavėjui (draugui arba pirkėjui, jei pirko sau)
$voucherMsgId = "<th-kup-vch-" . $code . "-" . time() . "@" . $serverHost . ">";
$headersVoucher = "MIME-Version: 1.0\r\n"
    . "Date: $dateHeader\r\n"
    . "Message-ID: $voucherMsgId\r\n"
    . "From: ThrowHouse Šiauliai <$fromEmail>\r\n"
    . "Reply-To: throwhousee@gmail.com\r\n"
    . "Auto-Submitted: auto-generated\r\n"
    . "X-Mailer: ThrowHouse Engine\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n";

@mail($targetEmail, $subVoucher, $voucherHtml, $headersVoucher, "-f" . $fromEmail);

// 3) Jei siųsta draugui – papildomai išsiunčiame pirkimo patvirtinimą pirkėjui
if ($isFriend && $buyerReceiptHtml && $buyerEmail !== $targetEmail) {
    $receiptMsgId = "<th-kup-rcp-" . $code . "-" . time() . "@" . $serverHost . ">";
    $headersReceipt = "MIME-Version: 1.0\r\n"
        . "Date: $dateHeader\r\n"
        . "Message-ID: $receiptMsgId\r\n"
        . "From: ThrowHouse Šiauliai <$fromEmail>\r\n"
        . "Reply-To: throwhousee@gmail.com\r\n"
        . "Auto-Submitted: auto-generated\r\n"
        . "X-Mailer: ThrowHouse Engine\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n";

    @mail($buyerEmail, $subBuyerReceipt, $buyerReceiptHtml, $headersReceipt, "-f" . $fromEmail);
}

// Žurnalas diagnostikai
$logEntry = date('Y-m-d H:i:s') . " | Kuponas: $code | $amount € | Tipas: $targetLabel | Pirkejas: $buyerName ($buyerEmail) | Gavejas: $targetName ($targetEmail) | FS: $httpCode\n";
@file_put_contents(__DIR__ . '/kuponas_log.txt', $logEntry, FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => 'Dovanų kuponas sėkmingai sugeneruotas ir išsiųstas!',
    'coupon' => $coupon
]);
