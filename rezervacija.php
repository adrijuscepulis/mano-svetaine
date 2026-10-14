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
    'payment_status' => 'Laukiama apmokėjimo',
    'status' => 'Gauta'
];

$dataFile = __DIR__ . '/rezervacijos.json';
$all = [];
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $all = json_decode($content, true) ?: [];
}
$all[] = $reservation;
file_put_contents($dataFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 2. Siunčiame el. laišką administratoriui (ThrowHouse)
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

@mail($toAdmin, $subjectAdmin, $bodyAdmin, $headersAdmin);

// 3. Siunčiame patvirtinimo el. laišką klientui su detalia atvykimo instrukcija
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
    . "📍 TIKSLI VIETA IR KAIP MUS RASTI:\n"
    . "Adresas: Pramonės g. 15, Šiauliai (2-as aukštas)\n"
    . "Tikslios koordinatės: 55.910730, 23.315700\n"
    . "Google Maps nuoroda: https://www.google.com/maps?q=55.910730,23.315700\n"
    . "Waze navigacija: https://waze.com/ul?ll=55.910730,23.315700&navigate=yes\n\n"
    . "🚪 TIKSLI ĮĖJIMO INSTRUKCIJA:\n"
    . "1. Atvykus adresu Pramonės g. 15, ieškokite durų su užrašu „FLORISTIKA“.\n"
    . "2. Įeikite pro šias duris – iškart sukite į pirmas duris į KAIRĘ.\n"
    . "3. Kilkite laiptais į viršų (2-as aukštas) ir atsidursite ThrowHouse erdvėje!\n\n"
    . "⚠️ SVARBIOS SAUGUMO TAISYKLĖS:\n"
    . "• BŪTINA uždara avalynė (sportiniai ar auliniai batai). Su šlepetėmis ar basutėmis dalyvauti griežtai draudžiama.\n"
    . "• Visą apsauginę aprangą (šalmus, skydelius, pirštines) suteikiame vietoje.\n"
    . "• Prašome atvykti 5–10 min. prieš sesijos pradžią trumpam instruktažui.\n\n"
    . "Kilus klausimams skambinkite: +370 619 88009\n"
    . "Laukiame jūsų atvykstant!\n"
    . "ThrowHouse Šiauliai komanda";

$headersClient = "From: ThrowHouse Šiauliai <throwhousee@gmail.com>\r\n"
    . "Reply-To: throwhousee@gmail.com\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "X-Mailer: PHP/" . phpversion();

@mail($email, $subjectClient, $bodyClient, $headersClient);

echo json_encode([
    'success' => true,
    'message' => 'Rezervacija sėkmingai priimta ir išsiųsta į el. paštą!',
    'reservation_id' => $reservation['id']
]);
