<?php
header('Content-Type: application/json; charset=utf-8');

// Jei užklausa atėjo per naršyklę įprastu GET būdu, nukreipiame į kontaktai.html
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: kontaktai.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neteisingas užklausos metodas']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$name = htmlspecialchars(trim($input['name'] ?? ''));
$phone = htmlspecialchars(trim($input['phone'] ?? ''));
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$subject = htmlspecialchars(trim($input['subject'] ?? 'Bendras klausimas'));
$message = htmlspecialchars(trim($input['message'] ?? ''));

if (!$name || !$phone || !$email || !$message) {
    echo json_encode([
        'success' => false,
        'message' => 'Prašome užpildyti visus privalomus laukus (vardą, telefoną, el. paštą ir žinutę).'
    ]);
    exit;
}

$createdDate = date('Y-m-d H:i');
$serverHost = $_SERVER['SERVER_NAME'] ?? 'violet-dove-675017.hostingersite.com';
$fromEmail = "noreply@" . $serverHost;
$dateHeader = date('r');

// =============================================================
// 1. Sukuriame stilingą HTML laišką administratoriui (Helovyno tema)
// =============================================================
$emailSubject = "=?UTF-8?B?" . base64_encode("Nauja užklausa iš svetainės [$subject] - $name") . "?=";

$adminHtmlBody = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0714; margin: 0; padding: 20px;">
  <div style="max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
    
    <!-- HEADER -->
    <div style="background-color: #120b22; color: #ffffff; padding: 24px; text-align: center; border-bottom: 4px solid #ff6a00;">
      <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #ff6a00; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #a78bfa; text-transform: uppercase; letter-spacing: 1px;">Gauta nauja kontaktų formos užklausa</p>
    </div>

    <!-- BODY -->
    <div style="padding: 24px; line-height: 1.6; font-size: 15px; color: #334155;">
      <p style="font-size: 16px; margin-top: 0; color: #0f172a;">
        Gauta nauja žinutė iš svetainės kontaktų formos:
      </p>

      <div style="background-color: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; padding: 18px; margin: 18px 0;">
        <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
          <tr>
            <td style="padding: 6px 0; color: #6b7280; width: 140px;">Užklausos tema:</td>
            <td style="padding: 6px 0; font-weight: bold; color: #7e22ce;">' . $subject . '</td>
          </tr>
          <tr>
            <td style="padding: 6px 0; color: #6b7280;">Kliento vardas:</td>
            <td style="padding: 6px 0; font-weight: bold; color: #0f172a;">' . $name . '</td>
          </tr>
          <tr>
            <td style="padding: 6px 0; color: #6b7280;">Telefono numeris:</td>
            <td style="padding: 6px 0; font-weight: bold;"><a href="tel:' . $phone . '" style="color: #ff6a00; text-decoration: none;">' . $phone . '</a></td>
          </tr>
          <tr>
            <td style="padding: 6px 0; color: #6b7280;">El. pašto adresas:</td>
            <td style="padding: 6px 0; font-weight: bold;"><a href="mailto:' . $email . '" style="color: #ff6a00; text-decoration: none;">' . $email . '</a></td>
          </tr>
          <tr>
            <td style="padding: 6px 0; color: #6b7280;">Gavimo laikas:</td>
            <td style="padding: 6px 0; color: #475569;">' . $createdDate . '</td>
          </tr>
        </table>
      </div>

      <div style="background-color: #fff7ed; border-left: 4px solid #ff6a00; padding: 14px 18px; margin: 18px 0; border-radius: 0 10px 10px 0;">
        <strong style="color: #9a3412; display: block; margin-bottom: 6px; font-size: 14px;">Kliento žinutė:</strong>
        <div style="color: #431407; font-size: 15px; white-space: pre-line;">' . nl2br($message) . '</div>
      </div>

      <div style="text-align: center; margin-top: 24px;">
        <a href="tel:' . $phone . '" style="display: inline-block; background-color: #ff6a00; color: #ffffff; text-decoration: none; font-weight: bold; padding: 12px 24px; border-radius: 8px; margin-right: 10px;">
          📞 Skambinti klientui
        </a>
        <a href="mailto:' . $email . '?subject=Atsakymas:%20' . rawurlencode($subject) . '" style="display: inline-block; background-color: #1e1b4b; color: #ffffff; text-decoration: none; font-weight: bold; padding: 12px 24px; border-radius: 8px;">
          ✉️ Atsakyti el. paštu
        </a>
      </div>
    </div>

    <!-- FOOTER -->
    <div style="background-color: #f8fafc; padding: 16px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
      Ši žinutė sugeneruota automatiškai iš ThrowHouse Šiauliai kontaktų formos.
    </div>
  </div>
</body>
</html>';

// =============================================================
// 2. Siuntimas per FormSubmit (atsarginis patikimas kanalas)
// =============================================================
$formSubmitData = [
    'Užklausos_tema' => $subject,
    'Kliento_Vardas' => $name,
    'Telefono_numeris' => $phone,
    'email' => $email,
    '_replyto' => $email,
    'Žinutė' => $message,
    'Data_Laikas' => $createdDate,
    '_subject' => "Nauja kontaktų formos užklausa [$subject] - $name",
    '_template' => "table"
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
curl_close($ch);

// =============================================================
// 3. Tiesioginis serverio PHP mail() administratoriui
// =============================================================
$adminMsgId = "<th-contact-" . time() . "-" . rand(100, 999) . "@" . $serverHost . ">";
$headersAdmin = "MIME-Version: 1.0\r\n"
    . "Date: $dateHeader\r\n"
    . "Message-ID: $adminMsgId\r\n"
    . "From: ThrowHouse Kontaktai <$fromEmail>\r\n"
    . "Reply-To: $name <$email>\r\n"
    . "Auto-Submitted: auto-generated\r\n"
    . "X-Mailer: ThrowHouse Contact Engine\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n";

@mail("throwhousee@gmail.com", $emailSubject, $adminHtmlBody, $headersAdmin, "-f" . $fromEmail);

// =============================================================
// 4. Patvirtinimo laiškas klientui
// =============================================================
$clientSub = "=?UTF-8?B?" . base64_encode("Gavome Jūsų užklausą – ThrowHouse Šiauliai") . "?=";
$clientHtml = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0714; margin: 0; padding: 20px;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
    <div style="background-color: #120b22; color: #ffffff; padding: 24px; text-align: center; border-bottom: 4px solid #ff6a00;">
      <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #ff6a00; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #a78bfa;">Užklausos patvirtinimas</p>
    </div>
    <div style="padding: 24px; line-height: 1.6; font-size: 15px; color: #334155;">
      <p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $name . '</strong>!</p>
      <p>Dėkojame, kad susisiekėte su „ThrowHouse Šiauliai“. Gavome Jūsų žinutę tema: <strong>' . $subject . '</strong>.</p>
      <p>Mūsų komanda peržiūrės Jūsų užklausą ir atsakys Jums kaip įmanoma greičiau (el. paštu arba telefonu <strong>' . $phone . '</strong>).</p>
      
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin: 18px 0; font-size: 14px; color: #475569;">
        <strong>Skubus klausimas ar Helovyno naktinė rezervacija?</strong><br>
        Drąsiai skambinkite mums tiesiogiai: <a href="tel:+37061988009" style="color: #ff6a00; font-weight: bold; text-decoration: none;">+370 619 88009</a>.
      </div>
      
      <p style="margin-bottom: 0; font-size: 14px; color: #64748b;">
        Iki susitikimo Pramonės g. 15, Šiauliai (2-ame aukšte)!
      </p>
    </div>
    <div style="background-color: #f8fafc; padding: 14px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
      © ' . date('Y') . ' ThrowHouse Šiauliai. Visos teisės saugomos.
    </div>
  </div>
</body>
</html>';

$clientMsgId = "<th-contact-reply-" . time() . "-" . rand(100, 999) . "@" . $serverHost . ">";
$headersClient = "MIME-Version: 1.0\r\n"
    . "Date: $dateHeader\r\n"
    . "Message-ID: $clientMsgId\r\n"
    . "From: ThrowHouse Šiauliai <$fromEmail>\r\n"
    . "Reply-To: throwhousee@gmail.com\r\n"
    . "Auto-Submitted: auto-generated\r\n"
    . "X-Mailer: ThrowHouse Contact Engine\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n";

@mail($email, $clientSub, $clientHtml, $headersClient, "-f" . $fromEmail);

// Žurnalas diagnostikai
$logEntry = date('Y-m-d H:i:s') . " | Kontaktai: $subject | $name ($email, $phone)\n";
@file_put_contents(__DIR__ . '/kontaktai_log.txt', $logEntry, FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => 'Dėkojame! Jūsų užklausa sėkmingai išsiųsta. Netrukus su jumis susisieksime.'
]);
