<?php
// =========================================================================
// ThrowHouse Šiauliai - Baro užsakymo apdorojimas & OPAY mokėjimo pradžia
// =========================================================================

date_default_timezone_set('Europe/Vilnius');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/baras_helper.php';
require_once __DIR__ . '/opay_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Neteisinga užklausos metodas.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true);
if (empty($postData)) {
    $postData = $_POST;
}

$clientName     = trim($postData['client_name'] ?? '');
$clientLocation = trim($postData['client_location'] ?? '');
$clientPhone    = trim($postData['client_phone'] ?? '');
$clientNotes    = trim($postData['client_notes'] ?? '');
$cartItems      = $postData['cart'] ?? [];
$opayChannel    = trim($postData['opay_channel'] ?? '');

if (is_string($cartItems)) {
    $cartItems = json_decode($cartItems, true) ?: [];
}

if (empty($clientName)) {
    echo json_encode(['success' => false, 'error' => 'Prašome nurodyti savo vardą.']);
    exit;
}

if (empty($clientLocation)) {
    echo json_encode(['success' => false, 'error' => 'Prašome nurodyti, kurioje erdvės vietoje esate (pvz., Pykčio kambarys, Takelis 1 ar Poilsio zona).']);
    exit;
}

if (empty($cartItems) || !is_array($cartItems)) {
    echo json_encode(['success' => false, 'error' => 'Krepšelis yra tuščias. Pasirinkite bent vieną prekę.']);
    exit;
}

// Užkrauname prekes ir perskaičiuojame sumą tik iš serverio kainų (apsauga nuo klastojimo)
$availableProducts = loadBarProducts();
$productMap = [];
foreach ($availableProducts as $p) {
    $productMap[$p['id']] = $p;
}

$validatedItems = [];
$totalCents = 0;

foreach ($cartItems as $cItem) {
    $pId = trim($cItem['id'] ?? '');
    $qty = (int)($cItem['qty'] ?? 0);

    if ($qty <= 0 || !isset($productMap[$pId])) {
        continue;
    }

    $prod = $productMap[$pId];
    if (empty($prod['active'])) {
        echo json_encode(['success' => false, 'error' => "Prekė '{$prod['name']}' šiuo metu išparduota."]);
        exit;
    }

    $unitPrice = (float)$prod['price'];
    $itemTotal = round($unitPrice * $qty, 2);
    $itemCents = (int)round($itemTotal * 100);

    $totalCents += $itemCents;

    $validatedItems[] = [
        'id'         => $prod['id'],
        'name'       => $prod['name'],
        'icon'       => $prod['icon'] ?? '🥤',
        'image'      => $prod['image'] ?? '',
        'category'   => $prod['category'] ?? 'Gėrimai',
        'qty'        => $qty,
        'unit_price' => $unitPrice,
        'total'      => $itemTotal
    ];
}

if (empty($validatedItems) || $totalCents <= 0) {
    echo json_encode(['success' => false, 'error' => 'Krepšelyje nėra galiojančių prekių.']);
    exit;
}

$orderNr = generateBarOrderNumber();
$totalEur = round($totalCents / 100, 2);

$newOrder = [
    'order_nr'        => $orderNr,
    'created_at'      => date('Y-m-d H:i:s'),
    'client_name'     => $clientName,
    'client_location' => $clientLocation,
    'client_phone'    => $clientPhone,
    'client_notes'    => $clientNotes,
    'items'           => $validatedItems,
    'total_amount'    => number_format($totalEur, 2, '.', '') . ' €',
    'total_cents'     => $totalCents,
    'status'          => 'Laukiama apmokėjimo',
    'payment_method'  => 'OPAY elektroninė bankininkystė',
    'payment_channel' => $opayChannel,
    'paid_at'         => null,
    'opay_trans_id'   => null
];

// Išsaugome užsakymą
$orders = loadBarOrders();
array_unshift($orders, $newOrder);
saveBarOrders($orders);

// Nustatome URL adresus
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'throwhouse.lt';
$redirectUrl = $protocol . $host . '/opay_return.php';
$callbackUrl = $protocol . $host . '/opay_callback.php';

// Inicijuojame OPAY mokėjimą
$opayRes = createOpayPaymentTransaction(
    $orderNr,
    $totalCents,
    $clientName,
    'info@throwhouse.lt',
    $clientPhone ?: '+37061988009',
    $redirectUrl,
    $callbackUrl,
    $opayChannel
);

if (!empty($opayRes['success']) && !empty($opayRes['redirect_url'])) {
    echo json_encode([
        'success'      => true,
        'order_nr'     => $orderNr,
        'redirect_url' => $opayRes['redirect_url']
    ]);
    exit;
} else {
    $err = $opayRes['error'] ?? 'Nepavyko inicijuoti OPAY mokėjimo. Prašome kreiptis į administratorių.';
    echo json_encode([
        'success' => false,
        'error'   => $err
    ]);
    exit;
}
