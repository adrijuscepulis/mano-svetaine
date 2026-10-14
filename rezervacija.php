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

// Mandagus, aiškus ir reprezentatyvus patvirtinimo tekstas klientui
$clientBody = "Gerb. $name,\n\n"
    . "Nuoširdžiai dėkojame už Jūsų rezervaciją „ThrowHouse Šiauliai“ pramogų erdvėje!\n"
    . "Jūsų užsakymas yra sėkmingai gautas ir užregistruotas sistemoje.\n\n"
    . "REZERVACIJOS DUOMENYS:\n"
    . "• Užsakymo numeris: $reservationId\n"
    . "• Pasirinkta pramoga: $activity" . ($lane ? " ($lane)" : "") . "\n"
    . "• Dalyvių skaičius: $count asm. (trukmė: 1 val.)\n"
    . "• Numatytas laikas: $date d., $time val.\n"
    . "• Mokėtina suma: $price (atsiskaitymas atvykus vietoje)\n\n"
    . "VIETA IR NAVIGACIJA:\n"
    . "• Adresas: Pramonės g. 15, Šiauliai (2-as aukštas)\n"
    . "• Google Maps nuoroda: https://www.google.com/maps?q=55.910730,23.315700\n"
    . "• Waze nuoroda: https://waze.com/ul?ll=55.910730,23.315700&navigate=yes\n\n"
    . "PATEKIMAS Į PATALPAS:\n"
    . "Atvykus prie pastato, maloniai prašome užeiti pro duris, pažymėtas iškaba „Floristika“. Patekus į vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą – čia Jūsų lauks „ThrowHouse“ erdvė.\n\n"
    . "SVARBI INFORMACIJA DALYVIAMS:\n"
    . "• Saugumo sumetimais visiems dalyviams būtina patogi, visiškai uždara avalynė (sportbačiai arba auliniai batai).\n"
    . "• Visas apsaugos priemones (specialią aprangą, apsauginius šalmus su skydeliais ir pirštines) suteikiame vietoje nemokamai.\n"
    . "• Rekomenduojame atvykti likus 5–10 min. iki sesijos pradžios trumpam instruktažui.\n\n"
    . "Jei kiltų klausimų ar pasikeistų planai, susisiekite telefonu: +370 619 88009 arba el. paštu: throwhousee@gmail.com.\n\n"
    . "Laukiame Jūsų atvykstant!\n"
    . "Pagarbiai,\n"
    . "„ThrowHouse Šiauliai“ komanda";

// 2. Siuntimas per FormSubmit (patikimiausias būdas į Gmail be DMARC blokavimo)
$formSubmitData = [
    'Užsakymo_ID' => $reservationId,
    'Kliento_Vardas' => $name,
    'Telefono_numeris' => $phone,
    'El_pastas' => $email,
    'email' => $email,
    '_replyto' => $email,
    'Pasirinkta_pramoga' => $activity . ($lane ? " ($lane)" : ""),
    'Dalyviu_skaicius' => $count . " asm.",
    'Data' => $date,
    'Laikas' => $time . " val.",
    'Moketina_suma' => $price,
    'Patekimas_i_patalpas' => 'Pramonės g. 15 (2 a.). Įėjimas pro duris „Floristika“, 1-os durys kairėje ir laiptais į viršų.',
    '_subject' => "Nauja ThrowHouse rezervacija [$reservationId]: $name ($date $time)",
    '_template' => "table",
    '_autoresponse' => $clientBody
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

// 3. Papildomas standartinis PHP mail() siuntimas (atsargai)
$serverHost = $_SERVER['SERVER_NAME'] ?? 'throwhouse.lt';
$fromEmail = "noreply@" . $serverHost;

$bodyAdminText = "Nauja rezervacija ThrowHouse Šiauliai!\n\n"
    . "Užsakymo numeris: $reservationId\n"
    . "Klientas: $name\n"
    . "Telefonas: $phone\n"
    . "El. paštas: $email\n\n"
    . "Pramoga: $activity " . ($lane ? "($lane)" : "") . "\n"
    . "Dalyviai: $count asm.\n"
    . "Data: $date\n"
    . "Laikas: $time val.\n"
    . "Mokėtina suma: $price (atsiskaitymas vietoje)\n";

$headersAdmin = "From: ThrowHouse Rezervacijos <$fromEmail>\r\n"
    . "Reply-To: $email\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n";

@mail("throwhousee@gmail.com", "Nauja rezervacija [$reservationId] - $name ($date $time)", $bodyAdminText, $headersAdmin);

$headersClient = "From: ThrowHouse Šiauliai <throwhousee@gmail.com>\r\n"
    . "Reply-To: throwhousee@gmail.com\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n";

@mail($email, "Jūsų rezervacija ThrowHouse Šiauliai [$reservationId]", $clientBody, $headersClient);

// Žurnalas diagnostikai
$logEntry = date('Y-m-d H:i:s') . " | ID: $reservationId | Vardas: $name | Tel: $phone | FS_Code: $httpCode | FS_Resp: $fsResp\n";
@file_put_contents(__DIR__ . '/rezervacija_log.txt', $logEntry, FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => 'Rezervacija sėkmingai išsaugota ir patvirtinimas išsiųstas!',
    'reservation_id' => $reservationId
]);
