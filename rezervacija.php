<?php
date_default_timezone_set('Europe/Vilnius');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
header('X-LiteSpeed-Cache-Control: no-cache, no-store');

// Pagalbinė funkcija: lietuviškos datos konvertavimas į ISO YYYY-MM-DD
function normalizeDateToIso($dateStr) {
    $trimmed = trim($dateStr);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
        return $trimmed;
    }
    $months = [
        'sausio' => '01', 'vasario' => '02', 'kovo' => '03', 'balandžio' => '04',
        'gegužės' => '05', 'birželio' => '06', 'liepos' => '07', 'rugpjūčio' => '08',
        'rugsėjo' => '09', 'spalio' => '10', 'lapkričio' => '11', 'gruodžio' => '12'
    ];
    if (preg_match('/(\d{4})\s*m\.\s*(\S+)\s*(\d{1,2})\s*d\./ui', $trimmed, $m)) {
        $year = $m[1];
        $monthName = mb_strtolower(trim($m[2]), 'UTF-8');
        $day = str_pad($m[3], 2, '0', STR_PAD_LEFT);
        $month = $months[$monthName] ?? '01';
        return "$year-$month-$day";
    }
    $ts = strtotime($trimmed);
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
}

// Pagalbinė funkcija: kirvių takelio pavadinimo suvienodinimas
function normalizeLaneName($laneStr) {
    if (!$laneStr) return '';
    $trimmed = trim($laneStr);
    if (stripos($trimmed, '2') !== false) {
        return 'Takelis 2';
    }
    if (stripos($trimmed, '1') !== false) {
        return 'Takelis 1';
    }
    return $trimmed;
}

// Pagalbinė funkcija: darbo laiko gavimas pagal grafiką ir išimtis
function getWorkingHoursForDate($isoDate, $settings = []) {
    $dayOfWeek = (string)date('w', strtotime($isoDate));

    // 1. Konkrečios datos speciali išimtis
    if (!empty($settings['schedule']['custom_dates'][$isoDate])) {
        $cd = $settings['schedule']['custom_dates'][$isoDate];
        return [
            'enabled' => !empty($cd['enabled']),
            'start' => intval($cd['start'] ?? 16),
            'end' => intval($cd['end'] ?? 22)
        ];
    }

    // 2. Savaitės dienos grafikas
    if (isset($settings['schedule']['days'][$dayOfWeek])) {
        $sd = $settings['schedule']['days'][$dayOfWeek];
        return [
            'enabled' => !empty($sd['enabled']),
            'start' => intval($sd['start'] ?? 16),
            'end' => intval($sd['end'] ?? 22)
        ];
    }

    // 3. Standartiniai numatytieji
    if ($dayOfWeek === '0') {
        return ['enabled' => true, 'start' => 9, 'end' => 20];
    } elseif ($dayOfWeek === '5' || $dayOfWeek === '6') {
        return ['enabled' => true, 'start' => 9, 'end' => 24];
    } else {
        return ['enabled' => true, 'start' => 16, 'end' => 22];
    }
}

$dataFile   = __DIR__ . '/rezervacijos.json';
$dataDbFile = __DIR__ . '/rezervacijos_db.json';

function saveResData($file, $data) {
    if (file_exists($file)) {
        @chmod($file, 0666);
    }
    $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $res = @file_put_contents($file, $json, LOCK_EX);
    if ($res === false) {
        $res = @file_put_contents($file, $json);
    }
    if ($res !== false) {
        @chmod($file, 0666);
    }
    return $res !== false;
}

function loadAllReservations($dataFile, $dataDbFile) {
    $main = file_exists($dataFile) ? (json_decode(@file_get_contents($dataFile), true) ?: []) : [];
    $db = file_exists($dataDbFile) ? (json_decode(@file_get_contents($dataDbFile), true) ?: []) : [];

    if (count($db) > count($main)) {
        $data = $db;
        saveResData($dataFile, $data);
    } elseif (!empty($main)) {
        $data = $main;
        saveResData($dataDbFile, $data);
    } else {
        $data = !empty($db) ? $db : [];
    }
    $data = is_array($data) ? $data : [];
    cancelExpiredPendingReservations($data, $dataFile, $dataDbFile);
    return $data;
}

/**
 * Automatiškai atšaukia rezervacijas, kurių klientas neapmokėjo per 10 minučių
 */
function cancelExpiredPendingReservations(&$reservations, $dataFile, $dataDbFile) {
    $changed = false;
    $now = time();
    foreach ($reservations as &$r) {
        $st = $r['status'] ?? '';
        if ($st === 'Laukiama apmokėjimo') {
            $createdAtTs = !empty($r['created_at']) ? strtotime($r['created_at']) : 0;
            // Griežtas 10 minučių limitas (600 sek.)
            if ($createdAtTs > 0 && ($now - $createdAtTs) > 10 * 60) {
                $r['status'] = 'Atšaukta (Neapmokėta per 10 min.)';
                $changed = true;
            }
        }
    }
    unset($r);
    if ($changed) {
        saveAllReservations($dataFile, $dataDbFile, $reservations);
    }
}

function saveAllReservations($dataFile, $dataDbFile, $data) {
    $r1 = saveResData($dataFile, $data);
    $r2 = saveResData($dataDbFile, $data);
    return ($r1 || $r2);
}

// =========================================================================
// Saugumo pagalbinės funkcijos: IP nustatymas, apsauga nuo botų ir limitai
// =========================================================================
function getClientIp() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function validateLtMobilePhone($phone) {
    $clean = preg_replace('/[^\d+]/', '', trim((string)$phone));
    if (str_starts_with($clean, '86') && strlen($clean) === 9) {
        $clean = '+370' . substr($clean, 1);
    } elseif (str_starts_with($clean, '003706') && strlen($clean) === 13) {
        $clean = '+370' . substr($clean, 4);
    }
    if (!preg_match('/^\+3706\d{7}$/', $clean)) {
        return false;
    }
    $suffix = substr($clean, 5); // 7 skaitmenys po +3706
    if (preg_match('/^(\d)\1{6}$/', $suffix)) {
        return false; // Netikri pasikartojantys numeriai (0000000, 1111111 ir t.t.)
    }
    if ($suffix === '1234567' || $suffix === '7654321' || $suffix === '0123456') {
        return false;
    }
    return $clean;
}

function isDisposableOrFakeEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return true;
    $parts = explode('@', $email);
    if (count($parts) !== 2) return true;
    $domain = strtolower($parts[1]);
    $disposable = [
        'tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 
        'dispostable.com', 'sharklasers.com', 'getairmail.com', 'throwawaymail.com',
        'yopmail.com', 'trashmail.com', 'fakeinbox.com', 'mohmal.com', 'temp-mail.org',
        'temp-mail.io', 'generator.email', 'crazymailing.com', 'fakemailgenerator.com',
        'disposablemail.com', 'maildrop.cc', 'inboxkitten.com'
    ];
    if (in_array($domain, $disposable)) return true;
    if ($domain === 'asd.com' || $domain === 'test.com' || $domain === 'fake.com' || $domain === 'example.com') {
        return true;
    }
    return false;
}

function loadJsonDbFile($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(@file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveJsonDbFile($file, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    @file_put_contents($file, $json, LOCK_EX);
    @chmod($file, 0666);
}

function checkRateLimit($ip, $email, $type) {
    $file = __DIR__ . '/rate_limits_db.json';
    $limits = loadJsonDbFile($file);
    $now = time();
    $cutoff24h = $now - 86400;
    $cutoff1h = $now - 3600;

    foreach ($limits as $k => &$record) {
        $record['pin_requests'] = array_values(array_filter($record['pin_requests'] ?? [], fn($t) => $t > $cutoff1h));
        $record['cash_bookings'] = array_values(array_filter($record['cash_bookings'] ?? [], fn($t) => $t > $cutoff24h));
    }
    unset($record);

    $ipRecord = $limits[$ip] ?? ['pin_requests' => [], 'cash_bookings' => []];

    if ($type === 'pin_request') {
        if (count($ipRecord['pin_requests'] ?? []) >= 5) {
            saveJsonDbFile($file, $limits);
            return ['allowed' => false, 'message' => 'Per daug patvirtinimo kodo užklausų iš šio įrenginio. Prašome palaukti kelias minutes arba susisiekti telefonu +370 619 88009.'];
        }
    } elseif ($type === 'cash_booking') {
        if (count($ipRecord['cash_bookings'] ?? []) >= 2) {
            saveJsonDbFile($file, $limits);
            return ['allowed' => false, 'message' => 'Pasiektas rezervacijų limitas šiam įrenginiui (maks. 2 per parą). Prašome susisiekti telefonu +370 619 88009 arba apmokėti internetu.'];
        }
    }

    saveJsonDbFile($file, $limits);
    return ['allowed' => true];
}

function recordRateLimitHit($ip, $type) {
    $file = __DIR__ . '/rate_limits_db.json';
    $limits = loadJsonDbFile($file);
    if (!isset($limits[$ip])) {
        $limits[$ip] = ['pin_requests' => [], 'cash_bookings' => []];
    }
    if ($type === 'pin_request') {
        $limits[$ip]['pin_requests'][] = time();
    } elseif ($type === 'cash_booking') {
        $limits[$ip]['cash_bookings'][] = time();
    }
    saveJsonDbFile($file, $limits);
}

function savePinCode($email, $phone, $pin, $ip) {
    $file = __DIR__ . '/pincodes_db.json';
    $db = loadJsonDbFile($file);
    $now = time();
    foreach ($db as $k => $v) {
        if (($v['expires'] ?? 0) < $now) {
            unset($db[$k]);
        }
    }
    $db[strtolower(trim($email))] = [
        'pin' => trim($pin),
        'phone' => trim($phone),
        'ip' => $ip,
        'created_at' => $now,
        'expires' => $now + 600, // 10 min.
        'attempts' => 0
    ];
    saveJsonDbFile($file, $db);
}

function verifyPinCode($email, $pinInput) {
    $file = __DIR__ . '/pincodes_db.json';
    $db = loadJsonDbFile($file);
    $key = strtolower(trim($email));
    $entry = $db[$key] ?? null;

    if (!$entry) {
        return ['success' => false, 'message' => 'Patvirtinimo kodas nerastas arba pasibaigė jo galiojimas. Prašome paspausti „Siųsti dar kartą“ ir gauti naują kodą.'];
    }

    if (time() > ($entry['expires'] ?? 0)) {
        unset($db[$key]);
        saveJsonDbFile($file, $db);
        return ['success' => false, 'message' => 'Patvirtinimo kodo 10 min. galiojimo laikas baigėsi. Prašome paprašyti naujo kodo.'];
    }

    if (trim($entry['pin']) !== trim($pinInput)) {
        $entry['attempts'] = ($entry['attempts'] ?? 0) + 1;
        if ($entry['attempts'] >= 4) {
            unset($db[$key]);
            saveJsonDbFile($file, $db);
            return ['success' => false, 'message' => 'Neteisingas patvirtinimo kodas įvestas per daug kartų. Prašome paprašyti naujo kodo.'];
        }
        $db[$key] = $entry;
        saveJsonDbFile($file, $db);
        return ['success' => false, 'message' => 'Neteisingas 4 skaitmenų patvirtinimo kodas! Prašome patikrinti el. paštą ir bandyti dar kartą.'];
    }

    unset($db[$key]);
    saveJsonDbFile($file, $db);
    return ['success' => true];
}

// =========================================================================
// 1. GET užklausa: gražinti užimtų laikų žemėlapį kalendoriui ir formoms
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $all = loadAllReservations($dataFile, $dataDbFile);

    $bookedMap = [];
    foreach ($all as $r) {
        $st = $r['status'] ?? '';
        if ($st === 'Atšaukta' || strpos($st, 'Neapmokėta') !== false || strpos($st, 'Atšaukta') !== false) continue;
        if ($st === 'Laukiama apmokėjimo') {
            $createdAtTs = !empty($r['created_at']) ? strtotime($r['created_at']) : 0;
            if ($createdAtTs > 0 && (time() - $createdAtTs) > 10 * 60) {
                continue; // Laikas atlaisvinamas, jei klientas per 10 min. neapmokėjo
            }
        }
        $iso = !empty($r['iso_date']) ? $r['iso_date'] : normalizeDateToIso($r['date'] ?? '');
        $rawT = trim($r['time'] ?? '');
        if (!$iso) continue;

        $act = $r['activity'] ?? '';
        $l = $r['lane'] ?? '';
        $isBlock = (!empty($r['is_block']) || ($r['status'] ?? '') === 'Uždaryta' || str_starts_with($r['id'] ?? '', 'BLOCK-') || stripos($act, 'Uždaryt') !== false || stripos($r['name'] ?? '', 'Uždaryt') !== false || stripos($r['name'] ?? '', 'Nedirbame') !== false);

        // Jei tai visos dienos blokas (uždaryta visa diena):
        if ($isBlock && (!$rawT || stripos($rawT, 'diena') !== false || stripos($rawT, 'nedirbame') !== false)) {
            if (!isset($bookedMap[$iso])) {
                $bookedMap[$iso] = [];
            }
            for ($bh = 9; $bh <= 23; $bh++) {
                $bT = sprintf('%02d:00', $bh);
                $bookedMap[$iso][$bT] = [
                    'rage' => 1,
                    'axe' => 2,
                    'lanes' => ['Takelis 1', 'Takelis 2'],
                    'is_rage_full' => true,
                    'is_axe_full' => true,
                    'is_full' => true,
                    'is_closed' => true,
                    'is_rage_closed' => true,
                    'is_axe_closed' => true
                ];
            }
            continue;
        }

        if (!$rawT) continue;

        $startH = intval($rawT);
        $t1 = sprintf('%02d:00', $startH);
        $t2 = sprintf('%02d:00', $startH + 1);

        if (!isset($bookedMap[$iso])) {
            $bookedMap[$iso] = [];
        }
        if (!isset($bookedMap[$iso][$t1])) {
            $bookedMap[$iso][$t1] = [
                'rage' => 0, 
                'axe' => 0, 
                'lanes' => [], 
                'is_rage_full' => false, 
                'is_axe_full' => false, 
                'is_full' => false,
                'is_closed' => false,
                'is_rage_closed' => false,
                'is_axe_closed' => false
            ];
        }

        // Administratoriaus uždaryti laikai (blokuota ranka iš admin pulto – NEDIRBAME)
        if ($isBlock) {
            if (stripos($act, 'Pykčio') !== false) {
                $bookedMap[$iso][$t1]['rage'] = max($bookedMap[$iso][$t1]['rage'], 1);
                $bookedMap[$iso][$t1]['is_rage_full'] = true;
                $bookedMap[$iso][$t1]['is_rage_closed'] = true;
            } elseif (stripos($act, 'Kirvių') !== false) {
                if (stripos($l, '1') !== false) {
                    $bookedMap[$iso][$t1]['axe']++;
                    $bookedMap[$iso][$t1]['lanes'][] = 'Takelis 1';
                } elseif (stripos($l, '2') !== false) {
                    $bookedMap[$iso][$t1]['axe']++;
                    $bookedMap[$iso][$t1]['lanes'][] = 'Takelis 2';
                } else {
                    $bookedMap[$iso][$t1]['axe'] = 2;
                    $bookedMap[$iso][$t1]['is_axe_full'] = true;
                    $bookedMap[$iso][$t1]['is_axe_closed'] = true;
                }
            } else {
                // Uždarytas laikas visoms pramogoms (Nedirbame)
                $bookedMap[$iso][$t1]['rage'] = 1;
                $bookedMap[$iso][$t1]['axe'] = 2;
                $bookedMap[$iso][$t1]['is_rage_full'] = true;
                $bookedMap[$iso][$t1]['is_axe_full'] = true;
                $bookedMap[$iso][$t1]['is_full'] = true;
                $bookedMap[$iso][$t1]['is_closed'] = true;
                $bookedMap[$iso][$t1]['is_rage_closed'] = true;
                $bookedMap[$iso][$t1]['is_axe_closed'] = true;
            }
            continue;
        }

        // Įprasta lankytojų arba rankinė rezervacija:
        if (stripos($act, 'Pykčio') !== false) {
            $bookedMap[$iso][$t1]['rage']++;
        } elseif (stripos($act, 'Kirvių') !== false) {
            $bookedMap[$iso][$t1]['axe']++;
            $normL = normalizeLaneName($l);
            if (!$normL) {
                $normL = in_array('Takelis 1', $bookedMap[$iso][$t1]['lanes']) ? 'Takelis 2' : 'Takelis 1';
            }
            $bookedMap[$iso][$t1]['lanes'][] = $normL;
        } elseif (stripos($act, 'KOMBO') !== false) {
            // Pirma darome Kirvių mėtymą (1-oji valanda: $t1)
            $bookedMap[$iso][$t1]['axe']++;
            $normL = normalizeLaneName($l);
            if (!$normL) {
                $normL = in_array('Takelis 1', $bookedMap[$iso][$t1]['lanes']) ? 'Takelis 2' : 'Takelis 1';
            }
            $bookedMap[$iso][$t1]['lanes'][] = $normL;

            // Po to sekantis Pykčio kambarys (2-oji valanda: $t2)
            if (!isset($bookedMap[$iso][$t2])) {
                $bookedMap[$iso][$t2] = ['rage' => 0, 'axe' => 0, 'lanes' => [], 'is_rage_full' => false, 'is_axe_full' => false, 'is_full' => false];
            }
            $bookedMap[$iso][$t2]['rage']++;
        }
    }

    foreach ($bookedMap as $isoKey => &$hours) {
        foreach ($hours as $hKey => &$s) {
            if ($s['rage'] >= 1) $s['is_rage_full'] = true;
            if ($s['axe'] >= 2) $s['is_axe_full'] = true;
            if ($s['is_rage_full'] && $s['is_axe_full']) $s['is_full'] = true;
        }
    }

    echo json_encode(['success' => true, 'booked' => (object)$bookedMap]);
    exit;
}

// =========================================================================
// 2. POST užklausa: naujos rezervacijos priėmimas su griežtu užimtumo tikrinimu
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neteisingas užklausos metodas']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

// 1. Apsauga nuo botų: Honeypot nematomas laukas
if (!empty($input['extra_security_check'])) {
    echo json_encode(['success' => false, 'message' => 'Saugumo patikros klaida.']);
    exit;
}

$ip = getClientIp();

// 2. Veiksmas: Patvirtinimo PIN kodo prašymas į el. paštą (mokant vietoje)
if (isset($input['action']) && $input['action'] === 'request_pin') {
    // Tikriname IP limitus
    $rateCheck = checkRateLimit($ip, '', 'pin_request');
    if (!$rateCheck['allowed']) {
        echo json_encode(['success' => false, 'message' => $rateCheck['message']]);
        exit;
    }

    $rawPhone = trim($input['phone'] ?? '');
    $validPhone = validateLtMobilePhone($rawPhone);
    if (!$validPhone) {
        echo json_encode([
            'success' => false,
            'message' => 'Prašome nurodyti teisingą lietuvišką mobiliojo ryšio numerį (+370 6XX XXXXX arba 86XXXXXXX).'
        ]);
        exit;
    }

    $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email || isDisposableOrFakeEmail($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'Prašome nurodyti galiojantį asmeninį el. pašto adresą patvirtinimo kodui gauti.'
        ]);
        exit;
    }

    // Apsauga nuo per dažno to paties el. pašto spaudinėjimo (min. 25 sek. pertrauka)
    $pinsDb = loadJsonDbFile(__DIR__ . '/pincodes_db.json');
    $existingPin = $pinsDb[strtolower($email)] ?? null;
    if ($existingPin && (time() - ($existingPin['created_at'] ?? 0)) < 25) {
        echo json_encode([
            'success' => false,
            'message' => 'Patvirtinimo kodas ką tik buvo išsiųstas. Prašome palaukti 25 sek. prieš prašant naujo.'
        ]);
        exit;
    }

    $name = htmlspecialchars(trim($input['name'] ?? ''));
    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Prašome įvesti savo vardą.']);
        exit;
    }

    $pin = sprintf('%04d', mt_rand(1000, 9999));
    savePinCode($email, $validPhone, $pin, $ip);
    recordRateLimitHit($ip, 'pin_request');

    require_once __DIR__ . '/email_helper.php';
    $htmlBody = renderPinCodeEmailHtml($name, $pin);
    $sub = "ThrowHouse patvirtinimo PIN kodas: $pin";
    $sent = sendThrowHouseMail($email, $sub, $htmlBody, 'throwhousee@gmail.com', 'ThrowHouse Šiauliai');

    if ($sent) {
        echo json_encode([
            'success' => true,
            'message' => "Patvirtinimo kodas sėkmingai išsiųstas į $email!"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nepavyko išsiųsti el. laiško su kodu. Prašome pasitikrinti el. pašto adresą arba susisiekti telefonu.'
        ]);
    }
    exit;
}

// 3. Pagrindinė rezervacijos pateikimo logika
$name = htmlspecialchars(trim($input['name'] ?? ''));
$rawPhone = trim($input['phone'] ?? '');
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$activity = htmlspecialchars(trim($input['activity'] ?? ''));
$lane = normalizeLaneName(htmlspecialchars(trim($input['lane'] ?? '')));
$count = intval($input['count'] ?? 1);
$date = htmlspecialchars(trim($input['date'] ?? ''));
$isoDate = !empty($input['iso_date']) ? trim($input['iso_date']) : normalizeDateToIso($date);
$time = htmlspecialchars(trim($input['time'] ?? ''));
$price = htmlspecialchars(trim($input['price'] ?? ''));
$payMethod = htmlspecialchars(trim($input['pay_method'] ?? 'banklink'));
$opayChannel = htmlspecialchars(trim($input['opay_channel'] ?? ''));
$pinCode = trim($input['pin_code'] ?? '');

$isCash = ($payMethod === 'cash' || stripos($payMethod, 'vietoje') !== false || stripos($payMethod, 'kortele') !== false || stripos($payMethod, 'Grynaisiais') !== false);

if (!$name || !$rawPhone || !$email || !$date || !$time) {
    echo json_encode([
        'success' => false,
        'message' => 'Prašome užpildyti visus privalomus laukus (vardą, telefoną, el. paštą, datą ir laiką).'
    ]);
    exit;
}

// Griežta lietuviško mobiliojo numerio patikra
$phone = validateLtMobilePhone($rawPhone);
if (!$phone) {
    echo json_encode([
        'success' => false,
        'message' => 'Prašome nurodyti teisingą lietuvišką mobiliojo ryšio numerį (+370 6XX XXXXX arba 86XXXXXXX).'
    ]);
    exit;
}

// Griežta el. pašto patikra nuo vienkartinių / fiktyvių dėžučių
if (isDisposableOrFakeEmail($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'Prašome nurodyti galiojantį asmeninį el. pašto adresą.'
    ]);
    exit;
}

// Jei mokama grynaisiais vietoje – tikriname IP limitus ir 4 skaitmenų PIN kodą
if ($isCash) {
    $rateCheck = checkRateLimit($ip, $email, 'cash_booking');
    if (!$rateCheck['allowed']) {
        echo json_encode(['success' => false, 'message' => $rateCheck['message']]);
        exit;
    }

    if (!$pinCode || strlen($pinCode) < 4) {
        echo json_encode([
            'success' => false,
            'message' => 'Prašome suvesti 4 skaitmenų patvirtinimo kodą, kurį išsiuntėme į Jūsų el. paštą.'
        ]);
        exit;
    }

    $pinCheck = verifyPinCode($email, $pinCode);
    if (!$pinCheck['success']) {
        echo json_encode(['success' => false, 'message' => $pinCheck['message']]);
        exit;
    }
}

// Esamų rezervacijų užkrovimas
$all = loadAllReservations($dataFile, $dataDbFile);

// -------------------------------------------------------------
// APSAUGA NUO PRAĖJUSIO LAIKO, PRAEITIES DATŲ IR 2 MĖNESIŲ LIMITAS
// -------------------------------------------------------------
$todayIso = date('Y-m-d');
$currentHour = intval(date('G')); // 0-23 val. Lietuvos laiku
$maxIsoDate = date('Y-m-d', strtotime('+2 months'));

if ($isoDate < $todayIso) {
    echo json_encode([
        'success' => false,
        'message' => 'Negalima rezervuoti praeities datos. Prašome pasirinkti šiandienos arba ateities dieną.'
    ]);
    exit;
}

if ($isoDate > $maxIsoDate) {
    echo json_encode([
        'success' => false,
        'message' => 'Rezervacijos priimamos ne daugiau kaip 2 mėnesiams į priekį (iki ' . $maxIsoDate . ').'
    ]);
    exit;
}

$startHour = intval($time);
if ($isoDate === $todayIso && $startHour <= $currentHour) {
    echo json_encode([
        'success' => false,
        'message' => 'Pasirinktas laikas (' . sprintf('%02d:00', $startHour) . ' val.) jau praėjo. Prašome pasirinkti vėlesnį laiką.'
    ]);
    exit;
}

// -------------------------------------------------------------
// APSAUGA NUO DVIGUBO UŽSAKYMO (SLOT CAPACITY VALIDATION)
// -------------------------------------------------------------
$t1 = sprintf('%02d:00', $startHour);
$t2 = sprintf('%02d:00', $startHour + 1);
$tEnd = sprintf('%02d:00', $startHour + 2);
$isKombo = (stripos($activity, 'KOMBO') !== false);

$settings = [];
if (file_exists(__DIR__ . '/nustatymai_db.json')) {
    $settings = json_decode(@file_get_contents(__DIR__ . '/nustatymai_db.json'), true) ?: [];
} elseif (file_exists(__DIR__ . '/nustatymai.json')) {
    $settings = json_decode(@file_get_contents(__DIR__ . '/nustatymai.json'), true) ?: [];
}

$wh = getWorkingHoursForDate($isoDate, $settings);
if (empty($wh['enabled'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Atsiprašome, šią dieną nedirbame. Prašome pasirinkti kitą dieną.'
    ]);
    exit;
}

$startLimit = intval($wh['start']);
$endLimit = intval($wh['end']);
$maxStart = $isKombo ? ($endLimit - 2) : ($endLimit - 1);
if ($startHour < $startLimit || $startHour > $maxStart) {
    echo json_encode([
        'success' => false,
        'message' => 'Pasirinktas laikas neatitinka nustatyto darbo laiko (šią dieną dirbame nuo ' . sprintf('%02d:00', $startLimit) . ' iki ' . sprintf('%02d:00', $endLimit) . ' val.). Prašome pasirinkti galiojantį laiką.'
    ]);
    exit;
}

$slotsOccupied = [];
foreach ($all as $ex) {
    $exSt = $ex['status'] ?? '';
    if ($exSt === 'Atšaukta' || strpos($exSt, 'Neapmokėta') !== false || strpos($exSt, 'Atšaukta') !== false) continue;
    if ($exSt === 'Laukiama apmokėjimo') {
        $createdAtTs = !empty($ex['created_at']) ? strtotime($ex['created_at']) : 0;
        if ($createdAtTs > 0 && (time() - $createdAtTs) > 10 * 60) {
            continue; // Atlaisviname laiką kitiems per 10 min.
        }
    }
    $exIso = !empty($ex['iso_date']) ? $ex['iso_date'] : normalizeDateToIso($ex['date'] ?? '');
    if ($exIso !== $isoDate) continue;

    $exRawT = trim($ex['time'] ?? '');
    if (!$exRawT) continue;
    $exStartH = intval($exRawT);
    $exT1 = sprintf('%02d:00', $exStartH);
    $exT2 = sprintf('%02d:00', $exStartH + 1);
    $exAct = $ex['activity'] ?? '';
    $exLane = $ex['lane'] ?? '';
    $exIsBlock = (!empty($ex['is_block']) || ($ex['status'] ?? '') === 'Uždaryta' || str_starts_with($ex['id'] ?? '', 'BLOCK-') || stripos($exAct, 'Uždaryt') !== false || stripos($ex['name'] ?? '', 'Uždaryt') !== false || stripos($ex['name'] ?? '', 'Nedirbame') !== false);

    if (!isset($slotsOccupied[$exT1])) $slotsOccupied[$exT1] = ['rage' => 0, 'axe' => 0, 'lanes' => [], 'is_closed' => false, 'is_rage_closed' => false, 'is_axe_closed' => false];
    if (!isset($slotsOccupied[$exT2])) $slotsOccupied[$exT2] = ['rage' => 0, 'axe' => 0, 'lanes' => [], 'is_closed' => false, 'is_rage_closed' => false, 'is_axe_closed' => false];

    if ($exIsBlock) {
        if (stripos($exAct, 'Pykčio') !== false) {
            $slotsOccupied[$exT1]['rage'] = max($slotsOccupied[$exT1]['rage'], 1);
            $slotsOccupied[$exT1]['is_rage_closed'] = true;
        } elseif (stripos($exAct, 'Kirvių') !== false) {
            if (stripos($exLane, '1') !== false) {
                $slotsOccupied[$exT1]['axe']++;
                $slotsOccupied[$exT1]['lanes'][] = 'Takelis 1';
            } elseif (stripos($exLane, '2') !== false) {
                $slotsOccupied[$exT1]['axe']++;
                $slotsOccupied[$exT1]['lanes'][] = 'Takelis 2';
            } else {
                $slotsOccupied[$exT1]['axe'] = 2;
                $slotsOccupied[$exT1]['is_axe_closed'] = true;
            }
        } else {
            $slotsOccupied[$exT1]['rage'] = 1;
            $slotsOccupied[$exT1]['axe'] = 2;
            $slotsOccupied[$exT1]['is_closed'] = true;
            $slotsOccupied[$exT1]['is_rage_closed'] = true;
            $slotsOccupied[$exT1]['is_axe_closed'] = true;
        }
        continue;
    }

    if (stripos($exAct, 'Pykčio') !== false) {
        $slotsOccupied[$exT1]['rage']++;
    } elseif (stripos($exAct, 'Kirvių') !== false) {
        $slotsOccupied[$exT1]['axe']++;
        $normExL = normalizeLaneName($exLane);
        if (!$normExL) {
            $normExL = in_array('Takelis 1', $slotsOccupied[$exT1]['lanes']) ? 'Takelis 2' : 'Takelis 1';
        }
        $slotsOccupied[$exT1]['lanes'][] = $normExL;
    } elseif (stripos($exAct, 'KOMBO') !== false) {
        // 1-oji valanda (Kirviai)
        $slotsOccupied[$exT1]['axe']++;
        $normExL = normalizeLaneName($exLane);
        if (!$normExL) {
            $normExL = in_array('Takelis 1', $slotsOccupied[$exT1]['lanes']) ? 'Takelis 2' : 'Takelis 1';
        }
        $slotsOccupied[$exT1]['lanes'][] = $normExL;
        // 2-oji valanda (Pyktis)
        $slotsOccupied[$exT2]['rage']++;
    }
}

$rage1 = $slotsOccupied[$t1]['rage'] ?? 0;
$axe1 = $slotsOccupied[$t1]['axe'] ?? 0;
$lanes1 = $slotsOccupied[$t1]['lanes'] ?? [];

$rage2 = $slotsOccupied[$t2]['rage'] ?? 0;

if (stripos($activity, 'Pykčio') !== false) {
    if (!empty($slotsOccupied[$t1]['is_rage_closed']) || !empty($slotsOccupied[$t1]['is_closed'])) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinktu laiku ($t1 val.) nedirbame! Prašome pasirinkti kitą laiką."
        ]);
        exit;
    }
    if ($rage1 >= 1) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinktu laiku ($t1 val.) pykčio kambarys jau yra užimtas! Prašome pasirinkti kitą laisvą laiką."
        ]);
        exit;
    }
    $time = "$t1 - $t2";
} elseif (stripos($activity, 'Kirvių') !== false) {
    if (!empty($slotsOccupied[$t1]['is_axe_closed']) || !empty($slotsOccupied[$t1]['is_closed'])) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinktu laiku ($t1 val.) nedirbame! Prašome pasirinkti kitą laiką."
        ]);
        exit;
    }
    if ($axe1 >= 2) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinktu laiku ($t1 val.) visi kirvių takeliai jau yra užimti! Prašome pasirinkti kitą laisvą laiką."
        ]);
        exit;
    }
    $lane = normalizeLaneName($lane);
    if ($lane && in_array($lane, $lanes1)) {
        $lane = ($lane === 'Takelis 1') ? 'Takelis 2' : 'Takelis 1';
    } elseif (!$lane) {
        $lane = in_array('Takelis 1', $lanes1) ? 'Takelis 2' : 'Takelis 1';
    }
    $time = "$t1 - $t2";
} elseif ($isKombo) {
    if (!empty($slotsOccupied[$t1]['is_axe_closed']) || !empty($slotsOccupied[$t1]['is_closed']) || !empty($slotsOccupied[$t2]['is_rage_closed']) || !empty($slotsOccupied[$t2]['is_closed'])) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinktu laiku ($t1 - $tEnd val.) nedirbame! Prašome pasirinkti kitą laiką."
        ]);
        exit;
    }
    // 1-oji valanda: Kirviai
    if ($axe1 >= 2) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinkto KOMBO paketo pradžios laiku ($t1 val.) visi kirvių takeliai jau užimti! Prašome pasirinkti kitą laiką."
        ]);
        exit;
    }
    // 2-oji valanda: Pykčio kambarys
    if ($rage2 >= 1) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinkto KOMBO paketo 2-ajai daliai ($t2 val.) pykčio kambarys jau užimtas! Prašome pasirinkti kitą laiką."
        ]);
        exit;
    }
    $lane = normalizeLaneName($lane);
    if (!$lane || in_array($lane, $lanes1)) {
        $lane = in_array('Takelis 1', $lanes1) ? 'Takelis 2' : 'Takelis 1';
    }
    $time = "$t1 - $tEnd";
}

$durationLabel = $isKombo ? "2 val. sesija (1-oji val. Kirviai [$lane], 2-oji val. Pykčio kambarys)" : "1 val. sesija";

// -------------------------------------------------------------
// 3. Išsaugojimas serveryje ir OPAY apmokėjimo inicijavimas
// -------------------------------------------------------------
require_once __DIR__ . '/opay_helper.php';
require_once __DIR__ . '/email_helper.php';

$opayCfg = loadOpayConfig();
$isOpayActive = !empty($opayCfg['opay_enabled']) && !empty($opayCfg['website_id']) && !empty($opayCfg['signature_password']);

$reservationId = 'TH-' . strtoupper(substr(uniqid(), -6));

if ($isCash) {
    recordRateLimitHit($ip, 'cash_booking');
    $initialStatus = 'Laukiama patvirtinimo (Mokės vietoje)';
    $payTitle = 'Mokėti kortele vietoje';
} else {
    $initialStatus = $isOpayActive ? 'Laukiama apmokėjimo' : 'Gauta';
    $bankTitle = (!empty($opayChannel) && function_exists('getOpayChannelTitle')) ? getOpayChannelTitle($opayChannel) : '';
    $payTitle = $isOpayActive ? ('Elektroninė bankininkystė' . ($bankTitle ? " ($bankTitle)" : '')) : $payMethod;
}

$promo = $settings['promo'] ?? null;
$isPromoActive = !empty($promo['enabled']) && !empty($promo['end_time']) && (strtotime($promo['end_time']) > time());
$promoDiscount = $isPromoActive ? floatval($promo['discount'] ?? 5) : 0;

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
    'iso_date' => $isoDate,
    'time' => $time,
    'price' => $price,
    'promo_discount' => $isPromoActive ? $promoDiscount : 0,
    'payment' => $payTitle,
    'status' => $initialStatus
];

$all[] = $reservation;
saveAllReservations($dataFile, $dataDbFile, $all);

// 1. Jei pasirinktas apmokėjimas grynaisiais vietoje:
if ($isCash) {
    // 1. Laiškas klientui
    $clientHtmlBody = renderNewReservationClientHtml($reservation);
    $subClient = "Jūsų rezervacija ThrowHouse Šiauliai [$reservationId]";
    $clientSent = sendThrowHouseMail($email, $subClient, $clientHtmlBody, 'throwhousee@gmail.com', 'ThrowHouse Šiauliai');

    // 2. Laiškas administratoriui su tiesioginio skambučio mygtukais ir perspėjimu
    $adminHtmlBody = renderNewReservationAdminHtml($reservation);
    $subAdmin = "🎯 Nauja ThrowHouse rezervacija (MOKĖS VIETOJE) [$reservationId]: $name ($date $time)";
    $adminSent = sendThrowHouseMail('throwhousee@gmail.com', $subAdmin, $adminHtmlBody, $email, 'ThrowHouse Rezervacijos');

    // Žurnalas diagnostikai
    $logEntry = date('Y-m-d H:i:s') . " | ID: $reservationId | CASH | Vardas: $name | Tel: $phone | AdminMail: " . ($adminSent ? 'OK' : 'FAIL') . " | ClientMail: " . ($clientSent ? 'OK' : 'FAIL') . "\n";
    @file_put_contents(__DIR__ . '/rezervacija_log.txt', $logEntry, FILE_APPEND);

    echo json_encode([
        'success' => true,
        'opay_redirect' => false,
        'is_cash' => true,
        'message' => 'Rezervacija sėkmingai priimta! Kadangi pasirinkote mokėti kortele vietoje, instruktorius susisieks su jumis telefonu prieš atvykimą patikslinti detales.',
        'reservation_id' => $reservationId
    ]);
    exit;
}

// 2. Jei OPAY aktyvus ir pasirinktas apmokėjimas internetu:
if ($isOpayActive) {
    $priceNum = floatval(preg_replace('/[^0-9\.]/', '', str_replace(',', '.', $price)));
    $amountCents = (int)round($priceNum * 100);
    if ($amountCents <= 0) {
        $amountCents = $isPromoActive ? 2500 : 3000;
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'https://';
    $host = $_SERVER['HTTP_HOST'] ?? 'throwhouse.lt';
    $baseUrl = $protocol . $host;
    $redirectUrl = $baseUrl . '/opay_return.php';
    $callbackUrl = $baseUrl . '/opay_callback.php';

    $opayRes = createOpayPaymentTransaction(
        $reservationId,
        $amountCents,
        $name,
        $email,
        $phone,
        $redirectUrl,
        $callbackUrl,
        $opayChannel
    );

    if (!empty($opayRes['success']) && !empty($opayRes['redirect_url'])) {
        echo json_encode([
            'success' => true,
            'opay_redirect' => true,
            'redirect_url' => $opayRes['redirect_url'],
            'reservation_id' => $reservationId
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nepavyko inicijuoti OPAY apmokėjimo: ' . ($opayRes['error'] ?? 'Nežinoma klaida') . '. Prašome susisiekti tiesiogiai.'
        ]);
        exit;
    }
}

// -------------------------------------------------------------
// 4. Standartinis tiesioginis patvirtinimas (kai OPAY išjungtas)
// -------------------------------------------------------------
// 1. Reprezentatyvus HTML laiškas klientui
$clientHtmlBody = renderNewReservationClientHtml($reservation);
$subClient = "Jūsų rezervacija ThrowHouse Šiauliai [$reservationId]";
$clientSent = sendThrowHouseMail($email, $subClient, $clientHtmlBody, 'throwhousee@gmail.com', 'ThrowHouse Šiauliai');

// 2. Reprezentatyvus HTML laiškas administratoriui su tiesioginio skambučio mygtukais
$adminHtmlBody = renderNewReservationAdminHtml($reservation);
$subAdmin = "🎯 Nauja ThrowHouse rezervacija [$reservationId]: $name ($date $time)";
$adminSent = sendThrowHouseMail('throwhousee@gmail.com', $subAdmin, $adminHtmlBody, $email, 'ThrowHouse Rezervacijos');

// Žurnalas diagnostikai
$logEntry = date('Y-m-d H:i:s') . " | ID: $reservationId | Vardas: $name | Data: $isoDate $time | AdminMail: " . ($adminSent ? 'OK' : 'FAIL') . " | ClientMail: " . ($clientSent ? 'OK' : 'FAIL') . "\n";
@file_put_contents(__DIR__ . '/rezervacija_log.txt', $logEntry, FILE_APPEND);

echo json_encode([
    'success' => true,
    'opay_redirect' => false,
    'message' => 'Rezervacija sėkmingai priimta ir patvirtinta!',
    'reservation_id' => $reservationId
]);

