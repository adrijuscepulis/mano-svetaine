<?php
header('Content-Type: application/json; charset=utf-8');

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

$dataFile = __DIR__ . '/rezervacijos.json';

// =========================================================================
// 1. GET užklausa: gražinti užimtų laikų žemėlapį kalendoriui ir formoms
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $all = [];
    if (file_exists($dataFile)) {
        $all = json_decode(file_get_contents($dataFile), true) ?: [];
    }

    $bookedMap = [];
    foreach ($all as $r) {
        if (($r['status'] ?? '') === 'Atšaukta') continue;
        $iso = !empty($r['iso_date']) ? $r['iso_date'] : normalizeDateToIso($r['date'] ?? '');
        $t = trim($r['time'] ?? '');
        if (!$iso || !$t) continue;

        if (!isset($bookedMap[$iso])) {
            $bookedMap[$iso] = [];
        }
        if (!isset($bookedMap[$iso][$t])) {
            $bookedMap[$iso][$t] = [
                'rage' => 0,
                'axe' => 0,
                'lanes' => [],
                'is_rage_full' => false,
                'is_axe_full' => false,
                'is_full' => false
            ];
        }

        $act = $r['activity'] ?? '';
        $l = $r['lane'] ?? '';

        if (stripos($act, 'Pykčio') !== false) {
            $bookedMap[$iso][$t]['rage']++;
        } elseif (stripos($act, 'Kirvių') !== false) {
            $bookedMap[$iso][$t]['axe']++;
            if ($l) $bookedMap[$iso][$t]['lanes'][] = $l;
        } elseif (stripos($act, 'KOMBO') !== false) {
            $bookedMap[$iso][$t]['rage']++;
            $bookedMap[$iso][$t]['axe']++;
            if ($l) $bookedMap[$iso][$t]['lanes'][] = $l;
            else $bookedMap[$iso][$t]['lanes'][] = 'Takelis 1';
        }

        if ($bookedMap[$iso][$t]['rage'] >= 1) {
            $bookedMap[$iso][$t]['is_rage_full'] = true;
        }
        if ($bookedMap[$iso][$t]['axe'] >= 2) {
            $bookedMap[$iso][$t]['is_axe_full'] = true;
        }
        if ($bookedMap[$iso][$t]['is_rage_full'] && $bookedMap[$iso][$t]['is_axe_full']) {
            $bookedMap[$iso][$t]['is_full'] = true;
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

if (!$name || !$phone || !$email || !$date || !$time) {
    echo json_encode([
        'success' => false,
        'message' => 'Prašome užpildyti visus privalomus laukus (vardą, telefoną, el. paštą, datą ir laiką).'
    ]);
    exit;
}

// Esamų rezervacijų užkrovimas
$all = [];
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $all = json_decode($content, true) ?: [];
}

// -------------------------------------------------------------
// APSAUGA NUO DVIGUBO UŽSAKYMO (SLOT CAPACITY VALIDATION)
// -------------------------------------------------------------
$currentRage = 0;
$currentAxe = 0;
$takenLanes = [];

foreach ($all as $ex) {
    if (($ex['status'] ?? '') === 'Atšaukta') continue;
    $exIso = !empty($ex['iso_date']) ? $ex['iso_date'] : normalizeDateToIso($ex['date'] ?? '');
    $exTime = trim($ex['time'] ?? '');

    if ($exIso === $isoDate && $exTime === $time) {
        $exAct = $ex['activity'] ?? '';
        $exLane = $ex['lane'] ?? '';

        if (stripos($exAct, 'Pykčio') !== false) {
            $currentRage++;
        } elseif (stripos($exAct, 'Kirvių') !== false) {
            $currentAxe++;
            if ($exLane) $takenLanes[] = $exLane;
        } elseif (stripos($exAct, 'KOMBO') !== false) {
            $currentRage++;
            $currentAxe++;
            if ($exLane) $takenLanes[] = $exLane;
            else $takenLanes[] = 'Takelis 1';
        }
    }
}

// Tikriname pagal užsakomą paslaugą:
if (stripos($activity, 'Pykčio') !== false) {
    if ($currentRage >= 1) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinktu laiku ($time val.) pykčio kambarys jau yra užimtas! Prašome pasirinkti kitą laisvą laiką."
        ]);
        exit;
    }
} elseif (stripos($activity, 'Kirvių') !== false) {
    if ($currentAxe >= 2) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinktu laiku ($time val.) visi kirvių takeliai jau yra užimti! Prašome pasirinkti kitą laisvą laiką."
        ]);
        exit;
    }
    // Jei pasirinktas konkretus takelis, bet jis jau užimtas, automatiškai nukreipiame į laisvą takelį:
    if ($lane && in_array($lane, $takenLanes)) {
        $lane = ($lane === 'Takelis 1') ? 'Takelis 2' : 'Takelis 1';
    } elseif (!$lane) {
        $lane = in_array('Takelis 1', $takenLanes) ? 'Takelis 2' : 'Takelis 1';
    }
} elseif (stripos($activity, 'KOMBO') !== false) {
    if ($currentRage >= 1 || $currentAxe >= 2) {
        echo json_encode([
            'success' => false,
            'message' => "Atsiprašome, pasirinktu laiku ($time val.) KOMBO paketo rezervuoti negalima, nes erdvė jau yra užimta! Prašome pasirinkti kitą laiką."
        ]);
        exit;
    }
    if (!$lane) {
        $lane = in_array('Takelis 1', $takenLanes) ? 'Takelis 2' : 'Takelis 1';
    }
}

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
    'status' => 'Gauta'
];

$all[] = $reservation;
file_put_contents($dataFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// -------------------------------------------------------------
// 4. Tekstinis patvirtinimas klientui (FormSubmit Autoresponse)
// -------------------------------------------------------------
$clientTextBody = "Sveiki, $name!\n\n"
    . "Nuoširdžiai dėkojame už Jūsų rezervaciją „ThrowHouse Šiauliai“ pramogų erdvėje!\n"
    . "Jūsų užsakymas yra sėkmingai priimtas ir patvirtintas.\n\n"
    . "📋 JŪSŲ REZERVACIJOS DUOMENYS:\n"
    . "• Užsakymo numeris: $reservationId\n"
    . "• Pasirinkta pramoga: $activity" . ($lane ? " ($lane)" : "") . "\n"
    . "• Dalyvių skaičius: $count asm. (trukmė: 1 val.)\n"
    . "• Numatytas laikas: $date d., $time val.\n"
    . "• Mokėtina suma: $price (atsiskaitymas vietoje atvykus)\n\n"
    . "📍 KUR MUS RASTI:\n"
    . "• Adresas: Pramonės g. 15, Šiauliai (2-as aukštas)\n"
    . "• Koordinatės: 55.910730, 23.315700\n"
    . "• Google Maps: https://www.google.com/maps?q=55.910730,23.315700\n"
    . "• Waze: https://waze.com/ul?ll=55.910730,23.315700&navigate=yes\n\n"
    . "🚪 PATEKIMAS Į PATALPAS:\n"
    . "Atvykus prie pastato (Pramonės g. 15), maloniai prašome užeiti pro duris, pažymėtas iškaba „Floristika“.\n"
    . "Patekus į pastato vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą – čia Jūsų lauks „ThrowHouse“ erdvė.\n\n"
    . "👟 SVARBU – AVALYNĖ IR APRANGA:\n"
    . "Saugumo sumetimais visiems dalyviams BŪTINA patogi, visiškai uždara avalynė (sportbačiai ar auliniai batai).\n"
    . "Su atvira avalyne (šlepetėmis, basutėmis) dalyvauti griežtai draudžiama.\n"
    . "Visą reikalingą apsauginę aprangą (kombinezonus, apsauginius šalmus su skydeliais, pirštines) suteikiame vietoje nemokamai!\n\n"
    . "⏱️ REZERVACIJOS ATŠAUKIMAS AR PERKĖLIMAS (LIKUS 24 VAL.):\n"
    . "Jeigu pasikeistų Jūsų planai, maloniai prašome apie atšaukimą arba laiko pakeitimą informuoti likus ne mažiau kaip 24 valandoms iki rezervuoto laiko pradžios.\n\n"
    . "ℹ️ Prašome atvykti likus 5–10 min. iki sesijos pradžios trumpam saugumo instruktažui.\n\n"
    . "Kilus klausimams, susisiekite: tel. +370 619 88009 arba el. paštu throwhousee@gmail.com.\n\n"
    . "Laukiame Jūsų atvykstant!\n"
    . "Pagarbiai, „ThrowHouse Šiauliai“ komanda";

// -------------------------------------------------------------
// 5. Gražus HTML laiškas klientui
// -------------------------------------------------------------
$clientHtmlBody = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0f172a; margin: 0; padding: 20px;">
  <div style="max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
    <div style="background-color: #0b0f19; color: #ffffff; padding: 26px 20px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 22px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8;">Rezervacijos patvirtinimas</p>
    </div>
    <div style="padding: 24px; line-height: 1.6; font-size: 15px; color: #334155;">
      <p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $name . '</strong>!</p>
      <p>Nuoširdžiai dėkojame už Jūsų rezervaciją! Užsakymas sėkmingai priimtas ir patvirtintas.</p>
      
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 16px 0;">
        <span style="font-weight: 700; color: #0f172a; margin-bottom: 8px; font-size: 15px; display: block;">📋 REZERVACIJOS DUOMENYS:</span>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
          <tr><td style="padding: 4px 0; color: #64748b;">Užsakymo numeris:</td><td style="font-weight: 700; color: #d97706;">' . $reservationId . '</td></tr>
          <tr><td style="padding: 4px 0; color: #64748b;">Pramoga:</td><td style="font-weight: 700;">' . $activity . ($lane ? " ($lane)" : "") . '</td></tr>
          <tr><td style="padding: 4px 0; color: #64748b;">Dalyvių skaičius:</td><td>' . $count . ' asm. (1 val. sesija)</td></tr>
          <tr><td style="padding: 4px 0; color: #64748b;">Data ir laikas:</td><td style="font-weight: 700; color: #0f172a;">' . $date . ' d., ' . $time . ' val.</td></tr>
          <tr><td style="padding: 4px 0; color: #64748b;">Mokėtina suma:</td><td style="font-weight: 700; font-size: 16px; color: #059669;">' . $price . ' (mokama vietoje)</td></tr>
        </table>
      </div>

      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 16px 0;">
        <span style="font-weight: 700; color: #0f172a; margin-bottom: 8px; font-size: 15px; display: block;">📍 KUR MUS RASTI:</span>
        <p style="margin: 0 0 10px 0; font-size: 14px;">
          Adresas: <strong>Pramonės g. 15, Šiauliai (2-as aukštas)</strong><br>
          Koordinatės: 55.910730, 23.315700
        </p>
        <div style="text-align: center; margin-top: 10px;">
          <a href="https://www.google.com/maps?q=55.910730,23.315700" style="display:inline-block; background-color:#2563eb; color:#ffffff; font-weight:bold; text-decoration:none; padding:10px 18px; border-radius:6px; font-size:13px; margin:4px;" target="_blank">🗺️ Google Maps žemėlapis</a>
          <a href="https://waze.com/ul?ll=55.910730,23.315700&navigate=yes" style="display:inline-block; background-color:#0284c7; color:#ffffff; font-weight:bold; text-decoration:none; padding:10px 18px; border-radius:6px; font-size:13px; margin:4px;" target="_blank">🚗 Waze navigacija</a>
        </div>
      </div>

      <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; margin: 16px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #92400e; display: block; margin-bottom: 4px;">🚪 PATEKIMAS Į PATALPAS:</strong>
        <p style="margin: 0; color: #78350f; font-size: 14px;">
          Atvykus prie pastato (Pramonės g. 15), maloniai prašome užeiti pro duris, pažymėtas iškaba <strong>„Floristika“</strong>. Patekus į pastato vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą – čia Jūsų lauks „ThrowHouse“ erdvė.
        </p>
      </div>

      <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 14px 16px; margin: 16px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #065f46; display: block; margin-bottom: 4px;">👟 SVARBU – AVALYNĖ IR APRANGA:</strong>
        <p style="margin: 0; color: #047857; font-size: 14px;">
          Saugumo sumetimais visiems dalyviams <strong>būtina visiškai uždara avalynė</strong> (sportbačiai ar auliniai batai). Su atvira avalyne (šlepetėmis, basutėmis) dalyvauti griežtai draudžiama. Visą apsauginę aprangą suteikiame vietoje nemokamai.
        </p>
      </div>

      <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; margin: 16px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #991b1b; display: block; margin-bottom: 4px;">⏱️ REZERVACIJOS ATŠAUKIMAS (LIKUS 24 VAL.):</strong>
        <p style="margin: 0; color: #b91c1c; font-size: 14px;">
          Jei pasikeistų Jūsų planai, maloniai prašome apie rezervacijos atšaukimą arba laiko pakeitimą informuoti <strong>likus ne mažiau kaip 24 valandoms</strong> iki sesijos pradžios.
        </p>
      </div>

      <p style="margin-top: 16px; font-size: 14px; color: #475569;">
        Prašome atvykti likus <strong>5–10 min.</strong> iki rezervuoto laiko trumpam instruktažui.
      </p>

      <div style="margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 16px; font-size: 14px;">
        <strong>Kilus klausimams:</strong><br>
        📞 Tel.: <a href="tel:+37061988009" style="color: #d97706; font-weight: 700; text-decoration:none;">+370 619 88009</a><br>
        ✉️ El. paštas: <a href="mailto:throwhousee@gmail.com" style="color: #d97706; text-decoration:none;">throwhousee@gmail.com</a>
      </div>
    </div>
    <div style="background-color: #f1f5f9; padding: 16px 20px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0;">
      Iki malonaus susitikimo!<br>
      <strong>„ThrowHouse Šiauliai“ komanda</strong> · Pramonės g. 15, Šiauliai
    </div>
  </div>
</body>
</html>';

// -------------------------------------------------------------
// 6. Modernus, tvarkingas HTML laiškas administratoriui (su 100% veikiančiais mygtukais)
// -------------------------------------------------------------
$adminHtmlBody = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0;">
    <div style="background-color: #0f172a; color: #ffffff; padding: 24px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8;">Gauta nauja rezervacija sistemoje</p>
      <div style="display:inline-block; background-color:rgba(245, 158, 11, 0.15); color:#b45309; border:1px solid rgba(245, 158, 11, 0.4); padding:4px 12px; border-radius:20px; font-weight:bold; font-size:13px; margin-top:8px;">
        Užsakymo Nr. ' . $reservationId . '
      </div>
    </div>
    <div style="padding: 24px; font-size: 14px; line-height: 1.6; color: #334155;">
      
      <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 8px; display: block;">👤 Kliento kontaktai:</span>
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
        <div style="font-size: 18px; font-weight: bold; color: #0f172a; margin-bottom: 8px;">' . $name . '</div>
        <div style="color: #475569; margin-bottom: 12px; line-height: 1.8;">
          📞 Tel.: <strong>' . $phone . '</strong><br>
          ✉️ El. paštas: <strong>' . $email . '</strong>
        </div>
        <div style="margin-top: 10px;">
          <a href="tel:' . $phone . '" style="display:inline-block; background-color:#10b981; color:#ffffff; font-weight:bold; text-decoration:none; padding:10px 16px; border-radius:6px; font-size:13px; margin-right:8px; margin-bottom:6px;">📞 Skambinti klientui</a>
          <a href="mailto:' . $email . '" style="display:inline-block; background-color:#6366f1; color:#ffffff; font-weight:bold; text-decoration:none; padding:10px 16px; border-radius:6px; font-size:13px; margin-bottom:6px;">✉️ Rašyti laišką</a>
        </div>
      </div>

      <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 8px; display: block;">📋 Užsakymo parametrai:</span>
      <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; margin-bottom: 16px;">
        <table style="width: 100%; border-collapse: collapse;">
          <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b; width: 40%;">Pramoga:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-weight: bold;">' . $activity . ($lane ? " ($lane)" : "") . '</td>
          </tr>
          <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;">Dalyviai:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-weight: bold;">' . $count . ' asm. (1 val. sesija)</td>
          </tr>
          <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;">Data:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-weight: bold;">' . $date . '</td>
          </tr>
          <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;">Laikas:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #b45309; font-weight: 800; font-size: 15px;">🕒 ' . $time . ' val.</td>
          </tr>
          <tr>
            <td style="padding: 8px 0; color: #64748b;">Mokėtina suma:</td>
            <td style="padding: 8px 0; color: #059669; font-weight: bold; font-size: 16px;">' . $price . ' <span style="font-size:12px; font-weight:normal; color:#64748b;">(vietoje)</span></td>
          </tr>
        </table>
      </div>

      <div style="text-align: center; margin: 26px 0 12px 0;">
        <a href="https://violet-dove-675017.hostingersite.com/admin.php?tab=kalendorius" style="display:inline-block; background-color:#0f172a; color:#ffffff; font-weight:bold; text-decoration:none; padding:12px 24px; border-radius:8px; border:1px solid #334155; font-size:14px;" target="_blank">
          ⚙️ Atidaryti valdymo pultą (admin.php) ➔
        </a>
        <div style="font-size:12px; color:#64748b; margin-top:8px;">
          Tiesioginė nuoroda: <a href="https://violet-dove-675017.hostingersite.com/admin.php?tab=kalendorius" style="color:#d97706; word-break:break-all;">https://violet-dove-675017.hostingersite.com/admin.php?tab=kalendorius</a>
        </div>
      </div>

    </div>
    <div style="background-color: #f8fafc; padding: 14px 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
      ThrowHouse Šiauliai · Automatinis pranešimas administratoriams · ' . date('Y-m-d H:i') . '
    </div>
  </div>
</body>
</html>';

// -------------------------------------------------------------
// 7. Siuntimas administratoriui per FormSubmit (atsarginis kanalas)
// -------------------------------------------------------------
$formSubmitData = [
    'Užsakymo_ID' => $reservationId,
    'Kliento_Vardas' => $name,
    'Telefono_numeris' => $phone,
    'Kliento_El_pastas' => $email,
    'email' => $email,
    '_replyto' => $email,
    'Pramoga' => $activity . ($lane ? " ($lane)" : ""),
    'Dalyviai' => $count . " asm.",
    'Data' => $date,
    'Laikas' => $time . " val.",
    'Moketina_suma' => $price . " (mokama vietoje)",
    '_subject' => "Nauja ThrowHouse rezervacija [$reservationId]: $name ($date $time)",
    '_template' => "table",
    '_autoresponse' => $clientTextBody
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

// -------------------------------------------------------------
// 8. Tiesioginis serverio PHP mail() su anti-spam antraštėmis
// -------------------------------------------------------------
$serverHost = $_SERVER['SERVER_NAME'] ?? 'violet-dove-675017.hostingersite.com';
$fromEmail = "noreply@" . $serverHost;
$dateHeader = date('r');
$adminMsgId = "<th-admin-" . $reservationId . "-" . time() . "@" . $serverHost . ">";
$clientMsgId = "<th-client-" . $reservationId . "-" . time() . "@" . $serverHost . ">";

$subAdmin = "=?UTF-8?B?" . base64_encode("Nauja ThrowHouse rezervacija [$reservationId]: $name ($date $time)") . "?=";
$subClient = "=?UTF-8?B?" . base64_encode("Jūsų rezervacija ThrowHouse Šiauliai [$reservationId]") . "?=";

// Administratoriaus HTML laiškas
$headersAdminHtml = "MIME-Version: 1.0\r\n"
    . "Date: $dateHeader\r\n"
    . "Message-ID: $adminMsgId\r\n"
    . "From: ThrowHouse Sistema <$fromEmail>\r\n"
    . "Reply-To: $email\r\n"
    . "Auto-Submitted: auto-generated\r\n"
    . "X-Mailer: ThrowHouse Engine\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n";

@mail("throwhousee@gmail.com", $subAdmin, $adminHtmlBody, $headersAdminHtml, "-f" . $fromEmail);

// Kliento HTML laiškas
$headersClientHtml = "MIME-Version: 1.0\r\n"
    . "Date: $dateHeader\r\n"
    . "Message-ID: $clientMsgId\r\n"
    . "From: ThrowHouse Šiauliai <$fromEmail>\r\n"
    . "Reply-To: throwhousee@gmail.com\r\n"
    . "Auto-Submitted: auto-generated\r\n"
    . "X-Mailer: ThrowHouse Engine\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n";

@mail($email, $subClient, $clientHtmlBody, $headersClientHtml, "-f" . $fromEmail);

// Žurnalas diagnostikai
$logEntry = date('Y-m-d H:i:s') . " | ID: $reservationId | Vardas: $name | Data: $isoDate $time | FS_Code: $httpCode | FS_Resp: $fsResp\n";
@file_put_contents(__DIR__ . '/rezervacija_log.txt', $logEntry, FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => 'Rezervacija sėkmingai priimta ir patvirtinta!',
    'reservation_id' => $reservationId
]);
