<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neteisingas užklausos metodas']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$amount = intval($input['amount'] ?? 25);
$buyerName = htmlspecialchars(trim($input['buyerName'] ?? ''));
$buyerPhone = htmlspecialchars(trim($input['buyerPhone'] ?? ''));
$buyerEmail = filter_var(trim($input['buyerEmail'] ?? ''), FILTER_VALIDATE_EMAIL);
$recipientName = htmlspecialchars(trim($input['recipientName'] ?? ''));
$greeting = htmlspecialchars(trim($input['greeting'] ?? ''));

if (!$buyerName || !$buyerPhone || !$buyerEmail) {
    echo json_encode(['success' => false, 'message' => 'Prašome užpildyti savo kontaktinius duomenis (vardą, telefoną, el. paštą).']);
    exit;
}

$code = 'TH-KUP-' . rand(1000, 9999);
$createdDate = date('Y-m-d H:i');
$expiresAt = date('Y-m-d', strtotime('+12 months'));

$coupon = [
    'code' => $code,
    'amount' => $amount,
    'buyer' => "$buyerName ($buyerPhone)",
    'buyer_email' => $buyerEmail,
    'recipient' => $recipientName ?: 'Dovanos turėtojas',
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

// -------------------------------------------------------------
// 1. Tekstinis patvirtinimas klientui (FormSubmit Autoresponse)
// -------------------------------------------------------------
$clientTextBody = "Sveiki, $buyerName!\n\n"
    . "Nuoširdžiai dėkojame, kad įsigijote „ThrowHouse Šiauliai“ dovanų kuponą!\n\n"
    . "🎟️ JŪSŲ DOVANŲ KUPONAS:\n"
    . "--------------------------------------------------\n"
    . "• Kupono kodas: $code\n"
    . "• Vertė: $amount €\n"
    . "• Gavėjas: " . ($recipientName ?: 'Dovanos turėtojas') . "\n"
    . "• Galioja iki: $expiresAt (12 mėn.)\n"
    . "--------------------------------------------------\n\n"
    . ($greeting ? "💌 ASMENINIS SVEIKINIMAS:\n\"$greeting\"\n\n" : "")
    . "📌 KAIP PANAUDOTI KUPONĄ:\n"
    . "1. Apsilankykite svetainėje: https://violet-dove-675017.hostingersite.com\n"
    . "2. Išsirinkite norimą pramogą, datą ir laiką rezervacijos kalendoriuje.\n"
    . "3. Rezervacijos formos pastabose arba atvykę į vietą pateikite kupono kodą: $code.\n\n"
    . "📍 VIETA IR PATEKIMAS:\n"
    . "• Adresas: Pramonės g. 15, Šiauliai (2-as aukštas)\n"
    . "• Koordinatės: 55.910730, 23.315700\n"
    . "• Google Maps: https://www.google.com/maps?q=55.910730,23.315700\n"
    . "• Waze: https://waze.com/ul?ll=55.910730,23.315700&navigate=yes\n\n"
    . "🚪 PATEKIMAS Į PATALPAS:\n"
    . "Atvykus prie pastato (Pramonės g. 15), užeikite pro duris, pažymėtas iškaba „Floristika“.\n"
    . "Patekus į pastato vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą.\n\n"
    . "👟 Svarbu: Pramogoms būtina patogi, visiškai uždara avalynė.\n"
    . "📞 Kilus klausimams: tel. +370 619 88009 arba el. paštu throwhousee@gmail.com.\n\n"
    . "Dėkojame ir linkime nepamirštamų emocijų!\n"
    . "„ThrowHouse Šiauliai“ komanda";

// -------------------------------------------------------------
// 2. Gražus, prabangus HTML dovanų kuponas klientui (Pirkėjui)
// -------------------------------------------------------------
$clientHtmlBody = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
    
    <!-- HEADER -->
    <div style="background-color: #0f172a; color: #ffffff; padding: 26px 20px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 22px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Oficialus Dovanų Kuponas</p>
    </div>

    <div style="padding: 24px; line-height: 1.6; font-size: 15px; color: #334155;">
      <p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $buyerName . '</strong>!</p>
      <p>Nuoširdžiai dėkojame, kad įsigijote pramogų erdvės <strong>„ThrowHouse Šiauliai“</strong> dovanų kuponą. Žemiau pateikiamas Jūsų paruoštas kuponas:</p>
      
      <!-- KUPONO VIZUALINĖ KORTELĖ -->
      <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 2px dashed #f59e0b; border-radius: 14px; padding: 24px; margin: 20px 0; color: #ffffff; text-align: center;">
        <span style="font-size: 12px; font-weight: 800; letter-spacing: 2px; color: #f59e0b; text-transform: uppercase;">Dovanų Kuponas</span>
        
        <div style="margin: 14px 0 16px 0;">
          <span style="display: inline-block; background-color: #f59e0b; color: #0b0f19; font-size: 32px; font-weight: 900; padding: 6px 24px; border-radius: 30px;">
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
            <td style="text-align: right; font-weight: bold; color: #ffffff;">' . ($recipientName ?: 'Dovanos turėtojas') . '</td>
          </tr>
          <tr>
            <td style="text-align: left; padding: 3px 0;">Galioja iki:</td>
            <td style="text-align: right; font-weight: bold; color: #34d399;">' . $expiresAt . ' (12 mėn.)</td>
          </tr>
        </table>
      </div>

      ' . ($greeting ? '
      <!-- SVEIKINIMAS -->
      <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #92400e; display: block; margin-bottom: 4px; font-size: 14px;">💌 Asmeninis sveikinimas:</strong>
        <p style="margin: 0; color: #78350f; font-style: italic; font-size: 14px;">„' . nl2br($greeting) . '“</p>
      </div>' : '') . '

      <!-- KAIP PANAUDOTI KUPONĄ -->
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin: 18px 0;">
        <strong style="color: #0f172a; display: block; margin-bottom: 10px; font-size: 15px;">📌 Kaip panaudoti šį kuponą?</strong>
        <ol style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.8;">
          <li>Apsilankykite mūsų interneto svetainėje: <a href="https://violet-dove-675017.hostingersite.com" style="color: #2563eb; font-weight: bold; text-decoration: none;" target="_blank">violet-dove-675017.hostingersite.com</a></li>
          <li>Rezervacijos kalendoriuje išsirinkite norimą pramogą (Pykčio kambarį, Kirvių mėtymą arba KOMBO), datą ir laiką.</li>
          <li>Atvykus į vietą arba formos pastabose pateikite kupono kodą: <strong style="color: #d97706;">' . $code . '</strong>.</li>
        </ol>
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
          Atvykus prie pastato (Pramonės g. 15), užeikite pro duris, pažymėtas iškaba <strong>„Floristika“</strong>. Patekus į pastato vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą – čia Jūsų lauks „ThrowHouse“ erdvė.
        </p>
      </div>

      <!-- AVALYNĖ -->
      <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #065f46; display: block; margin-bottom: 4px; font-size: 14px;">👟 Avalynė:</strong>
        <p style="margin: 0; color: #047857; font-size: 14px;">
          Saugumo sumetimais visiems dalyviams būtina patogi, visiškai uždara avalynė (sportbačiai ar batai). Su atvira avalyne dalyvauti griežtai draudžiama.
        </p>
      </div>

      <div style="margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 16px; font-size: 14px;">
        <strong>Kilus klausimams:</strong><br>
        📞 Tel.: <a href="tel:+37061988009" style="color: #d97706; font-weight: 700; text-decoration:none;">+370 619 88009</a><br>
        ✉️ El. paštas: <a href="mailto:throwhousee@gmail.com" style="color: #d97706; text-decoration:none;">throwhousee@gmail.com</a>
      </div>
    </div>

    <!-- FOOTER -->
    <div style="background-color: #f1f5f9; padding: 16px 20px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0;">
      Linkime pačių geriausių įspūdžių!<br>
      <strong>„ThrowHouse Šiauliai“ komanda</strong> · Pramonės g. 15, Šiauliai
    </div>
  </div>
</body>
</html>';

// -------------------------------------------------------------
// 3. Modernus HTML laiškas administratoriui (su mygtukais)
// -------------------------------------------------------------
$adminHtmlBody = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0;">
    
    <div style="background-color: #0f172a; color: #ffffff; padding: 24px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8;">Nupirktas naujas dovanų kuponas</p>
      <div style="display:inline-block; background-color:rgba(245, 158, 11, 0.15); color:#b45309; border:1px solid rgba(245, 158, 11, 0.4); padding:4px 14px; border-radius:20px; font-weight:bold; font-size:14px; margin-top:8px;">
        Kupono kodas: ' . $code . '
      </div>
    </div>

    <div style="padding: 24px; font-size: 14px; line-height: 1.6; color: #334155;">
      
      <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 8px; display: block;">👤 Pirkėjo kontaktai:</span>
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
        <div style="font-size: 18px; font-weight: bold; color: #0f172a; margin-bottom: 8px;">' . $buyerName . '</div>
        <div style="color: #475569; margin-bottom: 12px; line-height: 1.8;">
          📞 Tel.: <strong>' . $buyerPhone . '</strong><br>
          ✉️ El. paštas: <strong>' . $buyerEmail . '</strong>
        </div>
        <div style="margin-top: 10px;">
          <a href="tel:' . $buyerPhone . '" style="display:inline-block; background-color:#10b981; color:#ffffff; font-weight:bold; text-decoration:none; padding:10px 16px; border-radius:6px; font-size:13px; margin-right:8px; margin-bottom:6px;">📞 Skambinti pirkėjui</a>
          <a href="mailto:' . $buyerEmail . '" style="display:inline-block; background-color:#6366f1; color:#ffffff; font-weight:bold; text-decoration:none; padding:10px 16px; border-radius:6px; font-size:13px; margin-bottom:6px;">✉️ Rašyti laišką</a>
        </div>
      </div>

      <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 8px; display: block;">🎟️ Kupono informacija:</span>
      <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; margin-bottom: 16px;">
        <table style="width: 100%; border-collapse: collapse;">
          <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b; width: 40%;">Kupono kodas:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #b45309; font-weight: 800; font-family: monospace; font-size: 15px;">' . $code . '</td>
          </tr>
          <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;">Vertė:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #059669; font-weight: 800; font-size: 16px;">' . $amount . ' €</td>
          </tr>
          <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;">Gavėjas:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-weight: bold;">' . ($recipientName ?: 'Dovanos gavėjas') . '</td>
          </tr>
          ' . ($greeting ? '
          <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;">Sveikinimas:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #78350f; font-style: italic;">„' . $greeting . '“</td>
          </tr>' : '') . '
          <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;">Sukurtas:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #0f172a;">' . $createdDate . '</td>
          </tr>
          <tr>
            <td style="padding: 8px 0; color: #64748b;">Galioja iki:</td>
            <td style="padding: 8px 0; color: #0f172a; font-weight: bold;">' . $expiresAt . ' (12 mėn.)</td>
          </tr>
        </table>
      </div>

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
      ThrowHouse Šiauliai · Dovanų kuponų sistema · ' . date('Y-m-d H:i') . '
    </div>
  </div>
</body>
</html>';

// -------------------------------------------------------------
// 4. Siuntimas per FormSubmit (atsarginis kanalas su autoresponse pirkėjui)
// -------------------------------------------------------------
$formSubmitData = [
    'Kupono_Kodas' => $code,
    'Kupono_Verte' => $amount . ' €',
    'Pirkejo_Vardas' => $buyerName,
    'Telefono_numeris' => $buyerPhone,
    'Pirkejo_El_pastas' => $buyerEmail,
    'email' => $buyerEmail,
    '_replyto' => $buyerEmail,
    'Gavejas' => $recipientName ?: 'Dovanos turėtojas',
    'Sveikinimas' => $greeting ?: 'Nėra',
    'Galioja_iki' => $expiresAt,
    '_subject' => "Nupirktas naujas dovanų kuponas [$code] - $amount € ($buyerName)",
    '_template' => "table",
    '_autoresponse' => $clientTextBody
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

// -------------------------------------------------------------
// 5. Tiesioginis serverio PHP mail() su anti-spam RFC antraštėmis
// -------------------------------------------------------------
$serverHost = $_SERVER['SERVER_NAME'] ?? 'violet-dove-675017.hostingersite.com';
$fromEmail = "noreply@" . $serverHost;
$dateHeader = date('r');
$adminMsgId = "<th-kup-admin-" . $code . "-" . time() . "@" . $serverHost . ">";
$clientMsgId = "<th-kup-client-" . $code . "-" . time() . "@" . $serverHost . ">";

$subAdmin = "=?UTF-8?B?" . base64_encode("Naujas dovanų kuponas [$code]: $amount € - $buyerName") . "?=";
$subClient = "=?UTF-8?B?" . base64_encode("Jūsų ThrowHouse Šiauliai dovanų kuponas [$code] ($amount €)") . "?=";

// Administratoriaus HTML laiškas
$headersAdminHtml = "MIME-Version: 1.0\r\n"
    . "Date: $dateHeader\r\n"
    . "Message-ID: $adminMsgId\r\n"
    . "From: ThrowHouse Kuponai <$fromEmail>\r\n"
    . "Reply-To: $buyerEmail\r\n"
    . "Auto-Submitted: auto-generated\r\n"
    . "X-Mailer: ThrowHouse Engine\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n";

@mail("throwhousee@gmail.com", $subAdmin, $adminHtmlBody, $headersAdminHtml, "-f" . $fromEmail);

// Kliento (Pirkėjo) HTML laiškas
$headersClientHtml = "MIME-Version: 1.0\r\n"
    . "Date: $dateHeader\r\n"
    . "Message-ID: $clientMsgId\r\n"
    . "From: ThrowHouse Šiauliai <$fromEmail>\r\n"
    . "Reply-To: throwhousee@gmail.com\r\n"
    . "Auto-Submitted: auto-generated\r\n"
    . "X-Mailer: ThrowHouse Engine\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n";

@mail($buyerEmail, $subClient, $clientHtmlBody, $headersClientHtml, "-f" . $fromEmail);

// Žurnalas diagnostikai
$logEntry = date('Y-m-d H:i:s') . " | Kuponas: $code | Suma: $amount € | Pirkejas: $buyerName ($buyerEmail) | FS_Code: $httpCode\n";
@file_put_contents(__DIR__ . '/kuponas_log.txt', $logEntry, FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => 'Dovanų kuponas sėkmingai sugeneruotas ir išsiųstas!',
    'coupon' => $coupon
]);
