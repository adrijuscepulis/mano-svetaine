<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neteisingas užklausos metodas']);
    exit;
}

// Gauti JSON arba POST duomenis
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!$input) {
    $input = $_POST;
}

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

// 1. Išsaugoti rezervaciją į rezervacijos.json failą serveryje
$reservation = [
    'id' => 'TH-' . strtoupper(substr(uniqid(), -6)),
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
    'payment_status' => 'Laukiama apmokėjimo'
];

$dataFile = __DIR__ . '/rezervacijos.json';
$all = [];
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $all = json_decode($content, true) ?: [];
}
$all[] = $reservation;
file_put_contents($dataFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 2. Siunčiame pranešimą administratoriui (ThrowHouse Šiauliai)
$toAdmin = "throwhousee@gmail.com";
$subjectAdmin = "=?UTF-8?B?" . base64_encode("Nauja rezervacija [{$reservation['id']}]: $activity ($date, $time)") . "?=";

$bodyAdmin = "Gauta nauja rezervacija internetu iš ThrowHouse svetainės!\n\n"
    . "Užsakymo ID: " . $reservation['id'] . "\n"
    . "------------------------------------\n"
    . "Užsakovas: $name\n"
    . "Telefonas: $phone\n"
    . "El. paštas: $email\n\n"
    . "Pramoga: $activity $lane\n"
    . "Dalyvių skaičius: $count asm.\n"
    . "Data: $date\n"
    . "Laikas: $time val. (1 val. sesija)\n"
    . "Suma: $price\n"
    . "------------------------------------\n"
    . "Gavimo laikas: " . date('Y-m-d H:i:s') . "\n";

$headersAdmin = "From: ThrowHouse Rezervacijos <noreply@" . ($_SERVER['SERVER_NAME'] ?? 'throwhouse.lt') . ">\r\n"
    . "Reply-To: $email\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "X-Mailer: PHP/" . phpversion();

$adminSent = @mail($toAdmin, $subjectAdmin, $bodyAdmin, $headersAdmin);

// 3. Siunčiame patvirtinimo el. laišką klientui
$subjectClient = "=?UTF-8?B?" . base64_encode("Jūsų rezervacija ThrowHouse Šiauliai priimta! [{$reservation['id']}]") . "?=";

$bodyClient = "Sveiki, $name!\n\n"
    . "Dėkojame už rezervaciją pramogų erdvėje ThrowHouse Šiauliai.\n\n"
    . "JŪSŲ REZERVACIJOS DUOMENYS:\n"
    . "------------------------------------\n"
    . "Užsakymo numeris: " . $reservation['id'] . "\n"
    . "Pramoga: $activity $lane\n"
    . "Dalyvių skaičius: $count asm.\n"
    . "Data ir laikas: $date d., $time val. (trukmė: 1 valanda)\n"
    . "Mokėtina suma: $price\n"
    . "------------------------------------\n\n"
    . "LOKACIJA IR KONTAKTAI:\n"
    . "Adresas: Pramonės g. 15, Šiauliai\n"
    . "Telefonas pasiteiravimui: +370 619 88009\n"
    . "El. paštas: throwhousee@gmail.com\n\n"
    . "SVARBIOS SAUGUMO TAISYKLĖS:\n"
    . "• BŪTINA uždara avalynė (sportiniai ar auliniai batai). Su šlepetėmis ar basutėmis dalyvauti draudžiama.\n"
    . "• Visą apsauginę aprangą (šalmus, skydelius, pirštines) suteikiame vietoje.\n"
    . "• Prašome atvykti 5–10 min. prieš sesijos pradžią trumpam instruktažui.\n\n"
    . "Laukiame jūsų atvykstant!\n"
    . "ThrowHouse Šiauliai komanda";

$headersClient = "From: ThrowHouse Šiauliai <throwhousee@gmail.com>\r\n"
    . "Reply-To: throwhousee@gmail.com\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "X-Mailer: PHP/" . phpversion();

$clientSent = @mail($email, $subjectClient, $bodyClient, $headersClient);

echo json_encode([
    'success' => true,
    'message' => 'Rezervacija sėkmingai priimta ir išsiųsta į el. paštą!',
    'reservation_id' => $reservation['id'],
    'admin_notified' => $adminSent,
    'client_notified' => $clientSent
]);
