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
    'status' => 'Gauta (be apmokėjimo)'
];

$dataFile = __DIR__ . '/rezervacijos.json';
$all = [];
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $all = json_decode($content, true) ?: [];
}
$all[] = $reservation;
file_put_contents($dataFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 2. Siuntimas per FormSubmit (patikimiausias būdas į Gmail be DMARC blokavimo)
$formSubmitData = [
    'Užsakymo_ID' => $reservationId,
    'Kliento_Vardas' => $name,
    'Telefono_numeris' => $phone,
    'El_pastas' => $email,
    'Pasirinkta_pramoga' => $activity . ($lane ? " ($lane)" : ""),
    'Dalyviu_skaicius' => $count . " asm.",
    'Data' => $date,
    'Laikas' => $time . " val.",
    'Moketina_suma' => $price,
    '_subject' => "Nauja rezervacija [$reservationId]: $activity ($date $time)",
    '_template' => "table"
];

$ch = curl_init("https://formsubmit.co/ajax/throwhousee@gmail.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($formSubmitData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Referer: https://violet-dove-675017.hostingersite.com'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
$fsResp = curl_exec($ch);
curl_close($ch);

// 3. Papildomas standartinis PHP mail() siuntimas (atsargai)
$serverHost = $_SERVER['SERVER_NAME'] ?? 'throwhouse.lt';
$fromEmail = "noreply@" . $serverHost;

$bodyText = "Gauta nauja rezervacija!\n\n"
    . "Užsakymo ID: $reservationId\n"
    . "Užsakovas: $name\n"
    . "Telefonas: $phone\n"
    . "El. paštas: $email\n\n"
    . "Pramoga: $activity $lane\n"
    . "Dalyviai: $count asm.\n"
    . "Data: $date\n"
    . "Laikas: $time val.\n"
    . "Suma: $price\n";

$headers = "From: ThrowHouse <$fromEmail>\r\n"
    . "Reply-To: $email\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n";

@mail("throwhousee@gmail.com", "Nauja rezervacija [$reservationId]", $bodyText, $headers);

// 4. Kliento patvirtinimo laiškas
$clientBody = "Sveiki, $name!\n\n"
    . "Jūsų rezervacija ThrowHouse Šiauliai sėkmingai priimta!\n\n"
    . "REZERVACIJOS DUOMENYS:\n"
    . "Užsakymo ID: $reservationId\n"
    . "Pramoga: $activity $lane ($count asm.)\n"
    . "Data ir laikas: $date d., $time val.\n"
    . "Mokėtina suma: $price\n\n"
    . "📍 KAIP MUS RASTI:\n"
    . "Adresas: Pramonės g. 15, Šiauliai (2-as aukštas)\n"
    . "Google Maps: https://www.google.com/maps?q=55.910730,23.315700\n"
    . "Waze: https://waze.com/ul?ll=55.910730,23.315700&navigate=yes\n\n"
    . "🚪 ĮĖJIMO INSTRUKCIJA:\n"
    . "Atvykus prie pastato, įeikite pro duris su užrašu „FLORISTIKA“ -> iškart pirmos durys į KAIRĘ -> laiptais į 2-ą aukštą!\n\n"
    . "⚠️ SVARBU: Būtina uždara avalynė (sportbačiai/aulinukai).\n"
    . "Tel. pasiteiravimui: +370 619 88009\n\n"
    . "Laukiame jūsų atvykstant!\nThrowHouse Šiauliai";

@mail($email, "Jūsų rezervacija ThrowHouse Šiauliai [$reservationId]", $clientBody, $headers);

echo json_encode([
    'success' => true,
    'message' => 'Rezervacija sėkmingai išsaugota ir išsiųsta!',
    'reservation_id' => $reservationId
]);
