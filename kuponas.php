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
    echo json_encode(['success' => false, 'message' => 'Prašome užpildyti savo kontaktinius duomenis.']);
    exit;
}

$code = 'TH-KUP-' . rand(1000, 9999);
$expiresAt = date('Y-m-d', strtotime('+12 months'));

$coupon = [
    'code' => $code,
    'amount' => $amount,
    'buyer' => "$buyerName ($buyerPhone)",
    'buyer_email' => $buyerEmail,
    'recipient' => $recipientName,
    'greeting' => $greeting,
    'created_at' => date('Y-m-d H:i'),
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

// Siunčiame el. laišką administratoriui
$toAdmin = "throwhousee@gmail.com";
$subAdmin = "=?UTF-8?B?" . base64_encode("Nupirktas dovanų kuponas [$code] - $amount €") . "?=";
$msgAdmin = "Nupirktas naujas ThrowHouse Šiauliai dovanų kuponas!\n\n"
    . "Kupono kodas: $code\n"
    . "Vertė: $amount €\n"
    . "Pirkėjas: $buyerName ($buyerPhone, $buyerEmail)\n"
    . "Gavėjas: $recipientName\n"
    . "Sveikinimas: $greeting\n"
    . "Galioja iki: $expiresAt\n";

$headers = "From: ThrowHouse Kuponai <noreply@" . ($_SERVER['SERVER_NAME'] ?? 'throwhouse.lt') . ">\r\n"
    . "Reply-To: $buyerEmail\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n";

@mail($toAdmin, $subAdmin, $msgAdmin, $headers);

// Siunčiame kuponą pirkėjui
$subClient = "=?UTF-8?B?" . base64_encode("Jūsų ThrowHouse Šiauliai Dovanų Kuponas [$code]") . "?=";
$msgClient = "Sveiki, $buyerName!\n\n"
    . "Dėkojame, kad įsigijote dovanų kuponą pramogų erdvėje ThrowHouse Šiauliai!\n\n"
    . "JŪSŲ DOVANŲ KUPONAS:\n"
    . "------------------------------------\n"
    . "KUPONO KODAS: $code\n"
    . "VERTĖ: $amount €\n"
    . "GAVĖJAS: " . ($recipientName ?: 'Dovanos turėtojas') . "\n"
    . "GALIOJA IKI: $expiresAt (12 mėnesių)\n"
    . "------------------------------------\n\n"
    . ($greeting ? "Sveikinimo žinutė: \"$greeting\"\n\n" : "")
    . "KAIP PANAUDOTI KUPONĄ:\n"
    . "1. Užsukite į https://violet-dove-675017.hostingersite.com\n"
    . "2. Išsirinkite norimą datą ir laiką rezervacijos formoje.\n"
    . "3. Pastabose arba atvykus nurodykite savo kupono kodą: $code.\n\n"
    . "📍 LOKACIJA IR ĮĖJIMAS:\n"
    . "Adresas: Pramonės g. 15, Šiauliai (2-as aukštas)\n"
    . "Koordinatės: 55.910730, 23.315700\n"
    . "Google Maps: https://www.google.com/maps?q=55.910730,23.315700\n"
    . "Waze: https://waze.com/ul?ll=55.910730,23.315700&navigate=yes\n\n"
    . "🚪 Įėjimo instrukcija: Įėjimas pro duris su užrašu „FLORISTIKA“ -> pirmos durys į kairę -> laiptais į viršų (2 aukštas).\n"
    . "Telefonas: +370 619 88009\n\n"
    . "Smagių ir nepamirštamų akimirkų linki ThrowHouse Šiauliai komanda!";

$headersClient = "From: ThrowHouse Šiauliai <throwhousee@gmail.com>\r\n"
    . "Reply-To: throwhousee@gmail.com\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n";

@mail($buyerEmail, $subClient, $msgClient, $headersClient);

echo json_encode([
    'success' => true,
    'message' => 'Dovanų kuponas sėkmingai sugeneruotas!',
    'coupon' => $coupon
]);
