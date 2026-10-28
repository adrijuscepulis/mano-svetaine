<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

$file = __DIR__ . '/nustatymai_db.json';
if (!file_exists($file) || filesize($file) < 5) {
    $file = __DIR__ . '/nustatymai.json';
}

$data = [];
if (file_exists($file)) {
    $data = json_decode(@file_get_contents($file), true) ?: [];
}

// Jei nustatymai dar neužkrauti, naudojame numatytąjį promo
if (empty($data['promo'])) {
    $data['promo'] = [
        'enabled' => true,
        'discount' => 5,
        'title' => '🔥 ŽAIBIŠKA AKCIJA: -5 € VISOMS REZERVACIJOMS IR KUPONAMS!',
        'subtitle' => 'Šiuo metu atliekamoms rezervacijoms ir dovanų kuponams nuolaida pritaikoma automatiškai.',
        'end_time' => '2026-10-30T22:45:00+02:00',
        'apply_to_coupons' => true
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
