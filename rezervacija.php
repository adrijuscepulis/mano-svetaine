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
    return is_array($data) ? $data : [];
}

function saveAllReservations($dataFile, $dataDbFile, $data) {
    $r1 = saveResData($dataFile, $data);
    $r2 = saveResData($dataDbFile, $data);
    return ($r1 || $r2);
}

// =========================================================================
// 1. GET užklausa: gražinti užimtų laikų žemėlapį kalendoriui ir formoms
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $all = loadAllReservations($dataFile, $dataDbFile);

    $bookedMap = [];
    foreach ($all as $r) {
        if (($r['status'] ?? '') === 'Atšaukta') continue;
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
            if ($l) $bookedMap[$iso][$t1]['lanes'][] = $l;
        } elseif (stripos($act, 'KOMBO') !== false) {
            // Pirma darome Kirvių mėtymą (1-oji valanda: $t1)
            $bookedMap[$iso][$t1]['axe']++;
            $bookedMap[$iso][$t1]['lanes'][] = $l ?: 'Takelis 1';

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

$name = htmlspecialchars(trim($input['name'] ?? ''));
$phone = htmlspecialchars(trim($input['phone'] ?? ''));
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$activity = htmlspecialchars(trim($input['activity'] ?? ''));
$lane = htmlspecialchars(trim($input['lane'] ?? ''));
$count = intval($input['count'] ?? 1);
$date = htmlspecialchars(trim($input['date'] ?? ''));
$isoDate = !empty($input['iso_date']) ? trim($input['iso_date']) : normalizeDateToIso($date);
$time = htmlspecialchars(trim($input['time'] ?? ''));
$price = htmlspecialchars(trim($input['price'] ?? ''));
$payMethod = htmlspecialchars(trim($input['pay_method'] ?? 'Elektroninė bankininkystė / Kortelė'));

if (!$name || !$phone || !$email || !$date || !$time) {
    echo json_encode([
        'success' => false,
        'message' => 'Prašome užpildyti visus privalomus laukus (vardą, telefoną, el. paštą, datą ir laiką).'
    ]);
    exit;
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

$dayOfWeek = date('w', strtotime($isoDate));
if ($dayOfWeek == 0) {
    // Sekmadienis: 09:00 - 20:00
    $maxStart = $isKombo ? 18 : 19;
    if ($startHour < 9 || $startHour > $maxStart) {
        echo json_encode([
            'success' => false,
            'message' => 'Sekmadieniais dirbame nuo 09:00 iki 20:00. Prašome pasirinkti galiojantį darbo laiką.'
        ]);
        exit;
    }
} elseif ($dayOfWeek >= 1 && $dayOfWeek <= 4) {
    // Darbo dienomis (I-IV): 16:00 - 22:00
    $maxStart = $isKombo ? 20 : 21;
    if ($startHour < 16 || $startHour > $maxStart) {
        echo json_encode([
            'success' => false,
            'message' => 'Darbo dienomis (I–IV) dirbame nuo 16:00 iki 22:00. Prašome pasirinkti galiojantį darbo laiką.'
        ]);
        exit;
    }
}

$slotsOccupied = [];
foreach ($all as $ex) {
    if (($ex['status'] ?? '') === 'Atšaukta') continue;
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
        if ($exLane) $slotsOccupied[$exT1]['lanes'][] = $exLane;
    } elseif (stripos($exAct, 'KOMBO') !== false) {
        // 1-oji valanda (Kirviai)
        $slotsOccupied[$exT1]['axe']++;
        $slotsOccupied[$exT1]['lanes'][] = $exLane ?: 'Takelis 1';
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
    if (!$lane || in_array($lane, $lanes1)) {
        $lane = in_array('Takelis 1', $lanes1) ? 'Takelis 2' : 'Takelis 1';
    }
    $time = "$t1 - $tEnd";
}

$durationLabel = $isKombo ? "2 val. sesija (1-oji val. Kirviai [$lane], 2-oji val. Pykčio kambarys)" : "1 val. sesija";

// -------------------------------------------------------------
// 3. Išsaugojimas serveryje
// -------------------------------------------------------------
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
    'iso_date' => $isoDate,
    'time' => $time,
    'price' => $price,
    'payment' => $payMethod,
    'status' => 'Gauta'
];

$all[] = $reservation;
saveAllReservations($dataFile, $dataDbFile, $all);

// -------------------------------------------------------------
// 4. Tekstinis patvirtinimas klientui (FormSubmit Autoresponse)
// -------------------------------------------------------------
$clientTextBody = "Sveiki, $name!\n\n"
    . "Nuoširdžiai dėkojame už Jūsų rezervaciją „ThrowHouse Šiauliai“ pramogų erdvėje!\n"
    . "Jūsų užsakymas yra sėkmingai priimtas ir patvirtintas.\n\n"
    . "📋 JŪSŲ REZERVACIJOS DUOMENYS:\n"
    . "• Užsakymo numeris: $reservationId\n"
    . "• Pasirinkta pramoga: $activity" . ($lane ? " ($lane)" : "") . "\n"
    . "• Trukmė: $durationLabel\n"
    . "• Dalyvių skaičius: $count asm.\n"
    . "• Numatytas laikas: $date d., $time val.\n"
    . "• Apmokėjimo būdas: $payMethod (apmokėta internetu)\n"
    . "• Suma: $price\n\n"
    . "📍 KUR MUS RASTI:\n"
    . "• Adresas: Pramonės g. 15, Šiauliai (2-as aukštas)\n"
    . "• Koordinatės: 55.910730, 23.315700\n"
    . "• Google Maps: https://www.google.com/maps?q=55.910730,23.315700\n"
    . "• Waze: https://waze.com/ul?ll=55.910730,23.315700&navigate=yes\n\n"
    . "🚪 PATEKIMAS Į PATALPAS:\n"
    . "Atvykus prie pastato (Pramonės g. 15), maloniai prašome užeiti pro duris, pažymėtas iškaba „Floristika“.\n"
    . "Patekus į pastato vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą – čia Jūsų lauks „ThrowHouse“ erdvė.\n\n"
    . "👟 SVARBU – AVALYNĖ IR APRANGA:\n"
    . "Saugumui užtikrinti visiems dalyviams BŪTINA patogi, visiškai uždara avalynė (sportbačiai ar auliniai batai).\n"
    . "Su atvira avalyne (šlepetėmis, basutėmis) dalyvauti griežtai draudžiama.\n"
    . "Visą reikalingą apsauginę aprangą (kombinezonus, apsauginius šalmus su skydeliais, pirštines) suteikiame vietoje nemokamai!\n\n"
    . "⏱️ REZERVACIJOS ATŠAUKIMAS AR PERKĖLIMAS (LIKUS 24 VAL.):\n"
    . "Jeigu pasikeistų Jūsų planai, maloniai prašome apie atšaukimą arba laiko pakeitimą informuoti likus ne mažiau kaip 24 valandoms iki rezervuoto laiko pradžios.\n\n"
    . "ℹ️ Prašome atvykti likus 5–10 min. iki sesijos pradžios trumpam saugumo instruktažui.\n\n"
    . "Kilus klausimams, susisiekite: tel. +370 619 88009 arba el. paštu throwhousee@gmail.com.\n\n"
    . "Laukiame Jūsų atvykstant!\n"
    . "Pagarbiai, „ThrowHouse Šiauliai“ komanda";

require_once __DIR__ . '/email_helper.php';

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
    'message' => 'Rezervacija sėkmingai priimta ir patvirtinta!',
    'reservation_id' => $reservationId
]);
