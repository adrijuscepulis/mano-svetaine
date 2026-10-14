<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neteisingas užklausos metodas']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$name = htmlspecialchars(trim($input['name'] ?? ''));
$phone = htmlspecialchars(trim($input['phone'] ?? ''));
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$activity = htmlspecialchars(trim($input['activity'] ?? ''));
$lane = htmlspecialchars(trim($input['lane'] ?? ''));
$count = intval($input['count'] ?? 1);
$date = htmlspecialchars(trim($input['date'] ?? ''));
$time = htmlspecialchars(trim($input['time'] ?? ''));
$price = htmlspecialchars(trim($input['price'] ?? ''));

if (!$name || !$phone || !$email || !$date || !$time) {
    echo json_encode([
        'success' => false,
        'message' => 'Prašome užpildyti visus privalomus laukus (vardą, telefoną, el. paštą, datą ir laiką).'
    ]);
    exit;
}

// 1. Išsaugoti rezervaciją į failą serveryje
$reservationId = 'TH-' . strtoupper(substr(uniqid(), -6));
$reservation = [
    'id' => $reservationId,
    'created_at' => date('Y-m-d H:i:s'),
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'activity' => $activity,
    'lane' => $lane,
    'count' => $count,
    'date' => $date,
    'time' => $time,
    'price' => $price,
    'status' => 'Gauta (be išankstinio apmokėjimo)'
];

$dataFile = __DIR__ . '/rezervacijos.json';
$all = [];
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $all = json_decode($content, true) ?: [];
}
$all[] = $reservation;
file_put_contents($dataFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// -------------------------------------------------------------
// A. Mandagus ir išsamus tekstinis laiškas klientui (Autoresponse)
// -------------------------------------------------------------
$clientTextBody = "Sveiki, $name!\n\n"
    . "Nuoširdžiai dėkojame už Jūsų rezervaciją „ThrowHouse Šiauliai“ pramogų erdvėje!\n"
    . "Jūsų užsakymas yra sėkmingai priimtas ir patvirtintas.\n\n"
    . "📋 JŪSŲ REZERVACIJOS DUOMENYS:\n"
    . "• Užsakymo numeris: $reservationId\n"
    . "• Pasirinkta pramoga: $activity" . ($lane ? " ($lane)" : "") . "\n"
    . "• Dalyvių skaičius: $count asm. (trukmė: 1 val.)\n"
    . "• Numatytas laikas: $date d., $time val.\n"
    . "• Mokėtina suma: $price (atsiskaitymas vietoje atvykus)\n\n"
    . "📍 KUR MUS RASTI:\n"
    . "• Adresas: Pramonės g. 15, Šiauliai (2-as aukštas)\n"
    . "• Koordinatės: 55.910730, 23.315700\n"
    . "• Google Maps navigacija: https://www.google.com/maps?q=55.910730,23.315700\n"
    . "• Waze navigacija: https://waze.com/ul?ll=55.910730,23.315700&navigate=yes\n\n"
    . "🚪 PATEKIMAS Į PATALPAS:\n"
    . "Atvykus prie pastato (Pramonės g. 15), maloniai prašome užeiti pro duris, pažymėtas iškaba „Floristika“.\n"
    . "Patekus į pastato vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą – čia Jūsų lauks „ThrowHouse“ erdvė.\n\n"
    . "👟 SVARBU – AVALYNĖ IR APRANGA:\n"
    . "Saugumo sumetimais visiems dalyviams BŪTINA patogi, visiškai uždara avalynė (sportbačiai ar auliniai batai).\n"
    . "Su atvira avalyne (šlepetėmis, basutėmis) dalyvauti griežtai draudžiama.\n"
    . "Visą reikalingą apsauginę aprangą (kombinezonus, apsauginius šalmus su skydeliais, pirštines) suteikiame vietoje nemokamai!\n\n"
    . "⏱️ REZERVACIJOS ATŠAUKIMAS AR PERKĖLIMAS (LIKUS 24 VAL.):\n"
    . "Jeigu pasikeistų Jūsų planai, maloniai prašome apie atšaukimą arba laiko pakeitimą informuoti likus ne mažiau kaip 24 valandoms iki rezervuoto laiko pradžios.\n\n"
    . "ℹ️ REKOMENDACIJA: Prašome atvykti likus 5–10 min. iki sesijos pradžios trumpam saugumo instruktažui.\n\n"
    . "Kilus klausimams, susisiekite su mumis:\n"
    . "• Telefonu: +370 619 88009\n"
    . "• El. paštu: throwhousee@gmail.com\n\n"
    . "Laukiame Jūsų atvykstant!\n"
    . "Pagarbiai,\n"
    . "„ThrowHouse Šiauliai“ komanda";

// -------------------------------------------------------------
// B. Gražus HTML laiškas klientui
// -------------------------------------------------------------
$clientHtmlBody = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #0f172a; margin: 0; padding: 20px; }
  .card { max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.25); border: 1px solid #e2e8f0; }
  .header { background: #0b0f19; color: #ffffff; padding: 28px 24px; text-align: center; border-bottom: 3px solid #f59e0b; }
  .header h1 { margin: 0 0 6px 0; font-size: 24px; color: #f59e0b; letter-spacing: 1px; }
  .header p { margin: 0; font-size: 14px; color: #94a3b8; }
  .content { padding: 26px 24px; line-height: 1.6; font-size: 15px; color: #334155; }
  .box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 16px 0; }
  .box-title { font-weight: 700; color: #0f172a; margin-bottom: 10px; font-size: 15px; display: block; }
  .alert-door { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; margin: 16px 0; border-radius: 0 8px 8px 0; }
  .alert-shoes { background: #f0fdf4; border-left: 4px solid #10b981; padding: 14px 16px; margin: 16px 0; border-radius: 0 8px 8px 0; }
  .alert-cancel { background: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; margin: 16px 0; border-radius: 0 8px 8px 0; }
  .btn-row { margin: 14px 0 6px 0; text-align: center; }
  .btn { display: inline-block; background: #f59e0b; color: #000000; font-weight: 700; text-decoration: none; padding: 10px 18px; border-radius: 6px; margin: 4px 6px; font-size: 13px; }
  .footer { background: #f1f5f9; padding: 18px 24px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>THROW HOUSE ŠIAULIAI</h1>
      <p>Rezervacijos patvirtinimas</p>
    </div>
    <div class="content">
      <p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $name . '</strong>!</p>
      <p>Nuoširdžiai dėkojame už Jūsų rezervaciją! Jūsų užsakymas yra sėkmingai priimtas ir patvirtintas.</p>
      
      <div class="box">
        <span class="box-title">📋 REZERVACIJOS DUOMENYS:</span>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
          <tr><td style="padding: 5px 0; color: #64748b;">Užsakymo numeris:</td><td style="font-weight: 700; color: #d97706;">' . $reservationId . '</td></tr>
          <tr><td style="padding: 5px 0; color: #64748b;">Pramoga:</td><td style="font-weight: 700;">' . $activity . ($lane ? " ($lane)" : "") . '</td></tr>
          <tr><td style="padding: 5px 0; color: #64748b;">Dalyvių skaičius:</td><td>' . $count . ' asm. (1 val. sesija)</td></tr>
          <tr><td style="padding: 5px 0; color: #64748b;">Data ir laikas:</td><td style="font-weight: 700; color: #0f172a;">' . $date . ' d., ' . $time . ' val.</td></tr>
          <tr><td style="padding: 5px 0; color: #64748b;">Mokėtina suma:</td><td style="font-weight: 700; font-size: 16px; color: #059669;">' . $price . ' (mokama vietoje)</td></tr>
        </table>
      </div>

      <div class="box">
        <span class="box-title">📍 KUR MUS RASTI:</span>
        <p style="margin: 0 0 10px 0; font-size: 14px;">
          Adresas: <strong>Pramonės g. 15, Šiauliai (2-as aukštas)</strong><br>
          Koordinatės: 55.910730, 23.315700
        </p>
        <div class="btn-row">
          <a href="https://www.google.com/maps?q=55.910730,23.315700" class="btn" style="background:#2563eb; color:#fff;" target="_blank">🗺️ Google Maps žemėlapis</a>
          <a href="https://waze.com/ul?ll=55.910730,23.315700&navigate=yes" class="btn" style="background:#0284c7; color:#fff;" target="_blank">🚗 Waze navigacija</a>
        </div>
      </div>

      <div class="alert-door">
        <strong style="color: #92400e; display: block; margin-bottom: 4px;">🚪 PATEKIMAS Į PATALPAS:</strong>
        <p style="margin: 0; color: #78350f; font-size: 14px;">
          Atvykus prie pastato (Pramonės g. 15), maloniai prašome užeiti pro duris, pažymėtas iškaba <strong>„Floristika“</strong>. Patekus į pastato vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą – čia Jūsų lauks „ThrowHouse“ erdvė.
        </p>
      </div>

      <div class="alert-shoes">
        <strong style="color: #065f46; display: block; margin-bottom: 4px;">👟 SVARBU – AVALYNĖ IR APRANGA:</strong>
        <p style="margin: 0; color: #047857; font-size: 14px;">
          Saugumo sumetimais visiems dalyviams <strong>būtina visiškai uždara avalynė</strong> (sportbačiai ar auliniai batai). Su atvira avalyne (šlepetėmis, basutėmis) dalyvauti griežtai draudžiama. Visą apsauginę aprangą (kombinezonus, šalmus su apsauginiais skydeliais, pirštines) suteikiame vietoje nemokamai.
        </p>
      </div>

      <div class="alert-cancel">
        <strong style="color: #991b1b; display: block; margin-bottom: 4px;">⏱️ REZERVACIJOS ATŠAUKIMAS (LIKUS 24 VAL.):</strong>
        <p style="margin: 0; color: #b91c1c; font-size: 14px;">
          Jei pasikeistų Jūsų planai, maloniai prašome apie rezervacijos atšaukimą arba laiko pakeitimą informuoti <strong>likus ne mažiau kaip 24 valandoms</strong> iki sesijos pradžios.
        </p>
      </div>

      <p style="margin-top: 16px; font-size: 14px; color: #475569;">
        Prašome atvykti likus <strong>5–10 min.</strong> iki rezervuoto laiko trumpam instruktažui.
      </p>

      <div style="margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 16px; font-size: 14px;">
        <strong>Kilus bet kokiems klausimams, susisiekite:</strong><br>
        📞 Telefonas: <a href="tel:+37061988009" style="color: #d97706; font-weight: 700; text-decoration:none;">+370 619 88009</a><br>
        ✉️ El. paštas: <a href="mailto:throwhousee@gmail.com" style="color: #d97706; text-decoration:none;">throwhousee@gmail.com</a>
      </div>
    </div>
    <div class="footer">
      Iki malonaus susitikimo!<br>
      <strong>„ThrowHouse Šiauliai“ komanda</strong><br>
      Pramonės g. 15, Šiauliai
    </div>
  </div>
</body>
</html>';

// -------------------------------------------------------------
// 2. Siuntimas administratoriui per FormSubmit (be _cc klientui!)
// -------------------------------------------------------------
// Suteikiame administratoriui aiškią lentelę su visais kontaktais,
// o klientas gauna TIK mandagų _autoresponse laišką (be techninių lentelių).
$formSubmitData = [
    'Užsakymo_ID' => $reservationId,
    'Kliento_Vardas' => $name,
    'Telefono_numeris' => $phone,
    'Kliento_El_pastas' => $email,
    'email' => $email,
    '_replyto' => $email,
    'Pasirinkta_pramoga' => $activity . ($lane ? " ($lane)" : ""),
    'Dalyviu_skaicius' => $count . " asm.",
    'Data' => $date,
    'Laikas' => $time . " val.",
    'Moketina_suma' => $price . " (mokama vietoje)",
    '_subject' => "Nauja ThrowHouse rezervacija [$reservationId]: $name ($date $time)",
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
// 3. Siuntimas tiesioginiu serverio PHP mail()
// -------------------------------------------------------------
$serverHost = $_SERVER['SERVER_NAME'] ?? 'violet-dove-675017.hostingersite.com';
$fromEmail = "noreply@" . $serverHost;

// A. Administratoriaus pranešimas
$adminNoticeText = "ThrowHouse Šiauliai | GAUTA NAUJA REZERVACIJA\n\n"
    . "Užsakymo ID: $reservationId\n"
    . "Klientas: $name\n"
    . "Telefonas: $phone\n"
    . "El. paštas: $email\n\n"
    . "Pramoga: $activity " . ($lane ? "($lane)" : "") . "\n"
    . "Dalyviai: $count asm.\n"
    . "Data: $date\n"
    . "Laikas: $time val.\n"
    . "Mokėtina suma: $price\n"
    . "Statusas: Gauta (mokėjimas vietoje)\n";

$headersAdmin = "From: ThrowHouse Rezervacijos <$fromEmail>\r\n"
    . "Reply-To: $email\r\n"
    . "X-Mailer: PHP/" . phpversion() . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n";

@mail("throwhousee@gmail.com", "Nauja rezervacija [$reservationId] - $name ($date $time)", $adminNoticeText, $headersAdmin, "-f" . $fromEmail);

// B. Kliento išsamus HTML laiškas
$headersClientHtml = "MIME-Version: 1.0\r\n"
    . "From: ThrowHouse Šiauliai <$fromEmail>\r\n"
    . "Reply-To: throwhousee@gmail.com\r\n"
    . "X-Mailer: PHP/" . phpversion() . "\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n";

@mail($email, "Jūsų rezervacija ThrowHouse Šiauliai [$reservationId]", $clientHtmlBody, $headersClientHtml, "-f" . $fromEmail);

// Žurnalas diagnostikai
$logEntry = date('Y-m-d H:i:s') . " | ID: $reservationId | Vardas: $name | Tel: $phone | FS_Code: $httpCode | FS_Resp: $fsResp\n";
@file_put_contents(__DIR__ . '/rezervacija_log.txt', $logEntry, FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => 'Rezervacija sėkmingai išsaugota ir patvirtinimas išsiųstas!',
    'reservation_id' => $reservationId
]);
