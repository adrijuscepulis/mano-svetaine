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

// 1. Griežtai išjungiame akcijos laikmatį (promo)
if (!isset($data['promo']) || !is_array($data['promo'])) {
    $data['promo'] = [];
}
$data['promo']['enabled'] = false;
$data['promo']['apply_to_coupons'] = false;

// 2. Darbo laikas: I–IV griežtai Nedirbame
if (!isset($data['hours']) || !is_array($data['hours'])) {
    $data['hours'] = [];
}
$data['hours']['workdays'] = 'Nedirbame';
$data['hours']['friday'] = '09:00 - 24:00';
$data['hours']['saturday'] = '09:00 - 24:00';
$data['hours']['sunday'] = '09:00 - 20:00';

// 3. Kalendoriaus grafikas: I–IV (dienos 1, 2, 3, 4) visada uždarytos (nedirbame)
if (!isset($data['schedule']) || !is_array($data['schedule'])) {
    $data['schedule'] = ['days' => []];
}
if (!isset($data['schedule']['days']) || !is_array($data['schedule']['days'])) {
    $data['schedule']['days'] = [];
}
$dayNames = [
    '1' => 'Pirmadienis',
    '2' => 'Antradienis',
    '3' => 'Trečiadienis',
    '4' => 'Ketvirtadienis',
    '5' => 'Penktadienis',
    '6' => 'Šeštadienis',
    '0' => 'Sekmadienis'
];
foreach (['1', '2', '3', '4'] as $d) {
    $data['schedule']['days'][$d] = [
        'name' => $dayNames[$d],
        'enabled' => false,
        'start' => 0,
        'end' => 0
    ];
}
$data['schedule']['days']['5'] = ['name' => 'Penktadienis', 'enabled' => true, 'start' => 9, 'end' => 24];
$data['schedule']['days']['6'] = ['name' => 'Šeštadienis', 'enabled' => true, 'start' => 9, 'end' => 24];
$data['schedule']['days']['0'] = ['name' => 'Sekmadienis', 'enabled' => true, 'start' => 9, 'end' => 20];

// 4. Socialiniai tinklai
if (!isset($data['contacts']) || !is_array($data['contacts'])) {
    $data['contacts'] = [];
}
$data['contacts']['email'] = 'info@throwhouse.lt';
$data['contacts']['facebook_url'] = 'https://www.facebook.com/p/ThrowHouse-%C5%A0iauliai-61594304310440/';
$data['contacts']['instagram_url'] = 'https://www.instagram.com/throwhousesiauliai/';
$data['contacts']['tiktok_url'] = 'https://www.tiktok.com/@throwhousesiauliai?is_from_webapp=1&sender_device=pc';

// 5. D.U.K. (FAQ) atnaujinimas
$data['faq'] = [
    [
        'q' => 'Kiek indų suteikiama pykčio kambaryje ir ar galima užsisakyti daugiau?',
        'a' => 'Į standartinę 1 val. sesiją įskaičiuota 30 paruoštų indų (lėkštės, buteliai, keramika). Papildomus indus galima užsisakyti registruojantis arba vietoje po 0,60 € / vnt.'
    ],
    [
        'q' => 'Kiek žmonių gali dalyvauti vienoje rezervacijoje?',
        'a' => 'Vienoje rezervacijoje gali dalyvauti 1–4 asmenys (trukmė – 1 val.). Didesnėms grupėms (nuo 5 asm.) suderiname individualų pasiūlymą – susisiekite skiltyje „Kontaktai“.'
    ],
    [
        'q' => 'Kiek turite kirvių takelių ir pykčio kambarių?',
        'a' => 'Turime 2 atskirus kirvių mėtymo takelius ir 1 privatų pykčio kambarį. Vienos sesijos metu pykčio kambaryje būna tik jūsų kompanija.'
    ],
    [
        'q' => 'Kaip veikia dovanų kuponas ir kiek laiko galioja?',
        'a' => 'Kuponą su pasirinkta suma galite įsigyti tiesiogiai internetu per kelias minutes (PDF atsiunčiamas į el. paštą) arba atvykę pas mus vietoje. Kuponas galioja 6 mėnesius.'
    ],
    [
        'q' => 'Koks yra jūsų darbo laikas ir kada galima apsilankyti?',
        'a' => "V–VI: 09:00 – 24:00\nVII: 09:00 – 20:00\nApsilankymui būtina išankstinė registracija internetu."
    ],
    [
        'q' => 'Ką reikia apsirengti atvykstant?',
        'a' => 'Būtina uždara avalynė (sportbačiai ar batai) – su šlepetėmis ar basutėmis dalyvauti draudžiama. Visą apsauginę aprangą (kombinezonus, pirštines, šalmus su skydeliu) suteikiame vietoje.'
    ],
    [
        'q' => 'Ar ThrowHouse tinka bernvakariams, mergvakariams ar gimtadieniams?',
        'a' => 'Taip, organizuojame turnyrus ir šventes kompanijoms. Didesnėms grupėms suteikiame poilsio zoną užkandžiams ir gėrimams – susisiekite tiesiogiai dėl individualaus plano.'
    ]
];

// Atnaujiname ir išsaugome nustatymai_db.json serveryje, kad duomenų bazė visada būtų švari
if (file_exists(__DIR__ . '/nustatymai_db.json')) {
    @file_put_contents(__DIR__ . '/nustatymai_db.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
