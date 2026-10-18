<?php
session_start();

// Administratoriaus slaptažodis (galite bet kada pakeisti)
define('ADMIN_PASSWORD', 'throw2026');

// Atsijungimas
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Prisijungimo tikrinimas
if (isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $login_error = "Neteisingas slaptažodis!";
    }
}

// Failai duomenų saugojimui
$resFile = __DIR__ . '/rezervacijos.json';
$kupFile = __DIR__ . '/kuponai.json';

// Pagalbinės funkcijos
function loadData($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveData($file, $data) {
    file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Datos konvertavimo pagalbininkas
function normalizeIso($dateStr) {
    $trimmed = trim($dateStr);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) return $trimmed;
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

// Jei prisijungęs - apdoroti veiksmus
if (!empty($_SESSION['admin_logged'])) {
    $reservations = loadData($resFile);
    $coupons = loadData($kupFile);

    // 1. Keisti rezervacijos statusą
    if (isset($_POST['action']) && $_POST['action'] === 'update_res_status') {
        $resId = $_POST['res_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? 'Patvirtinta';
        $redirectTab = $_POST['redirect_tab'] ?? 'kalendorius';
        $redirectDate = $_POST['redirect_date'] ?? '';

        foreach ($reservations as &$r) {
            if ($r['id'] === $resId) {
                $r['status'] = $newStatus;
                break;
            }
        }
        saveData($resFile, $reservations);
        header('Location: admin.php?tab=' . urlencode($redirectTab) . ($redirectDate ? '&date=' . urlencode($redirectDate) : ''));
        exit;
    }

    // 2. Ištrinti rezervaciją
    if (isset($_POST['action']) && $_POST['action'] === 'delete_res') {
        $delId = $_POST['del_id'] ?? '';
        $redirectTab = $_POST['redirect_tab'] ?? 'kalendorius';
        $redirectDate = $_POST['redirect_date'] ?? '';

        $reservations = array_values(array_filter($reservations, function($r) use ($delId) {
            return ($r['id'] ?? '') !== $delId;
        }));
        saveData($resFile, $reservations);
        header('Location: admin.php?tab=' . urlencode($redirectTab) . ($redirectDate ? '&date=' . urlencode($redirectDate) : ''));
        exit;
    }

    // 3. Rankinė rezervacija iš administratoriaus pulto (pvz. skambučiu)
    if (isset($_POST['action']) && $_POST['action'] === 'add_manual_res') {
        $mName = htmlspecialchars(trim($_POST['m_name'] ?? 'Užsakymas telefonu'));
        $mPhone = htmlspecialchars(trim($_POST['m_phone'] ?? ''));
        $mEmail = htmlspecialchars(trim($_POST['m_email'] ?? ''));
        $mActivity = htmlspecialchars(trim($_POST['m_activity'] ?? 'Pykčio kambarys'));
        $mLane = htmlspecialchars(trim($_POST['m_lane'] ?? ''));
        $mCount = intval($_POST['m_count'] ?? 1);
        $mIsoDate = htmlspecialchars(trim($_POST['m_iso_date'] ?? date('Y-m-d')));
        $mTime = htmlspecialchars(trim($_POST['m_time'] ?? '16:00'));
        $mPrice = htmlspecialchars(trim($_POST['m_price'] ?? '30 €'));

        $newId = 'TH-' . strtoupper(substr(uniqid(), -6));
        if (stripos($mActivity, 'KOMBO') !== false) {
            $startH = intval($mTime);
            $t1 = sprintf('%02d:00', $startH);
            $tEnd = sprintf('%02d:00', $startH + 2);
            $mTime = "$t1 - $tEnd";
            if (!$mLane) $mLane = 'Takelis 1';
        }

        $reservations[] = [
            'id' => $newId,
            'created_at' => date('Y-m-d H:i:s'),
            'name' => $mName,
            'phone' => $mPhone,
            'email' => $mEmail,
            'activity' => $mActivity,
            'lane' => $mLane,
            'count' => $mCount,
            'date' => $mIsoDate,
            'iso_date' => $mIsoDate,
            'time' => $mTime,
            'price' => $mPrice,
            'status' => 'Patvirtinta'
        ];
        saveData($resFile, $reservations);
        header('Location: admin.php?tab=kalendorius&date=' . urlencode($mIsoDate));
        exit;
    }

    // 4. Keisti kupono statusą (Aktyvus / Panaudotas)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_coupon') {
        $code = $_POST['coupon_code'] ?? '';
        foreach ($coupons as &$c) {
            if ($c['code'] === $code) {
                $c['status'] = ($c['status'] === 'Panaudotas') ? 'Aktyvus' : 'Panaudotas';
                $c['used_at'] = ($c['status'] === 'Panaudotas') ? date('Y-m-d H:i') : null;
                break;
            }
        }
        saveData($kupFile, $coupons);
        header('Location: admin.php?tab=kuponai');
        exit;
    }

    // 5. Rankinis kupono sukūrimas iš admin panelės
    if (isset($_POST['action']) && $_POST['action'] === 'create_coupon') {
        $amount = intval($_POST['amount'] ?? 25);
        $buyer = htmlspecialchars($_POST['buyer'] ?? 'Admin sukurtas');
        $newCode = 'TH-KUP-' . rand(1000, 9999);
        $coupons[] = [
            'code' => $newCode,
            'amount' => $amount,
            'buyer' => $buyer,
            'recipient' => htmlspecialchars($_POST['recipient'] ?? ''),
            'created_at' => date('Y-m-d H:i'),
            'expires_at' => date('Y-m-d', strtotime('+6 months')),
            'status' => 'Aktyvus'
        ];
        saveData($kupFile, $coupons);
        header('Location: admin.php?tab=kuponai');
        exit;
    }

    // 6. Uždaryti / Atidaryti konkrečią valandą (arba konkrečią pramogą)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_slot_block') {
        $targetDate = htmlspecialchars(trim($_POST['b_date'] ?? ''));
        $targetTime = htmlspecialchars(trim($_POST['b_time'] ?? ''));
        $targetType = htmlspecialchars(trim($_POST['b_target'] ?? 'all')); // 'all', 'rage', 'axe'
        $doBlock = intval($_POST['b_block'] ?? 1); // 1 = uždaryti, 0 = atidaryti
        $redirectTab = $_POST['redirect_tab'] ?? 'kalendorius';
        $redirectDate = $_POST['redirect_date'] ?? $targetDate;

        $targetStartH = intval($targetTime);
        $targetHourStr = sprintf('%02d:00', $targetStartH);

        if ($doBlock === 0) {
            // ATIDARYTI LAIKĄ: ištriname blokus
            $reservations = array_values(array_filter($reservations, function($r) use ($targetDate, $targetHourStr, $targetType) {
                $rIso = !empty($r['iso_date']) ? $r['iso_date'] : normalizeIso($r['date'] ?? '');
                if ($rIso !== $targetDate) return true;

                $rH = sprintf('%02d:00', intval($r['time'] ?? ''));
                if ($rH !== $targetHourStr) return true;

                $isBlock = (!empty($r['is_block']) || ($r['status'] ?? '') === 'Uždaryta' || stripos($r['activity'] ?? '', 'Uždaryt') !== false);
                if (!$isBlock) return true;

                $act = $r['activity'] ?? '';
                if ($targetType === 'rage') {
                    return stripos($act, 'Pykčio') === false && stripos($act, 'Visos') === false;
                }
                if ($targetType === 'axe') {
                    return stripos($act, 'Kirvių') === false && stripos($act, 'Visos') === false;
                }
                return false; // 'all' -> šalina visus šios valandos blokus
            }));
        } else {
            // UŽDARYTI LAIKĄ: sukuriame bloko įrašą
            $blockId = 'BLOCK-' . strtoupper(substr(uniqid(), -6));
            $activityTitle = 'Uždarytas laikas (Visos pramogos)';
            $lane = '';
            if ($targetType === 'rage') {
                $activityTitle = 'Pykčio kambarys (Uždaryta admin)';
            } elseif ($targetType === 'axe') {
                $activityTitle = 'Kirvių mėtymas (Uždaryta admin)';
                $lane = 'Visi takeliai';
            }

            $reservations[] = [
                'id' => $blockId,
                'created_at' => date('Y-m-d H:i:s'),
                'name' => '🔒 Uždarytas laikas (Admin)',
                'phone' => '-',
                'email' => '-',
                'activity' => $activityTitle,
                'lane' => $lane,
                'count' => 0,
                'date' => $targetDate,
                'iso_date' => $targetDate,
                'time' => $targetHourStr,
                'price' => '0 €',
                'status' => 'Uždaryta',
                'is_block' => true,
                'note' => 'Uždaryta administratoriaus rankiniu būdu'
            ];
        }

        saveData($resFile, $reservations);
        header('Location: admin.php?tab=' . urlencode($redirectTab) . '&date=' . urlencode($redirectDate));
        exit;
    }

    // 7. Uždaryti / Atidaryti visą dieną
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_day_block') {
        $targetDate = htmlspecialchars(trim($_POST['b_date'] ?? ''));
        $doBlock = intval($_POST['b_block'] ?? 1); // 1 = uždaryti dieną, 0 = atidaryti dieną
        $redirectTab = $_POST['redirect_tab'] ?? 'kalendorius';
        $redirectDate = $_POST['redirect_date'] ?? $targetDate;

        // Pirmiausia išvalome esamus šios dienos blokus
        $reservations = array_values(array_filter($reservations, function($r) use ($targetDate) {
            $rIso = !empty($r['iso_date']) ? $r['iso_date'] : normalizeIso($r['date'] ?? '');
            if ($rIso !== $targetDate) return true;
            $isBlock = (!empty($r['is_block']) || ($r['status'] ?? '') === 'Uždaryta' || stripos($r['activity'] ?? '', 'Uždaryt') !== false);
            return !$isBlock;
        }));

        if ($doBlock === 1) {
            // Pridedame blokus visoms dienos darbo valandoms
            $dayOfWeek = date('w', strtotime($targetDate));
            $startH = 16;
            $endH = 22;
            if ($dayOfWeek == 0) {
                // Sekmadienis: 09:00 - 20:00 (valandos nuo 9 iki 19)
                $startH = 9;
                $endH = 19;
            } elseif ($dayOfWeek == 5 || $dayOfWeek == 6) {
                // Penktadienis - Šeštadienis: 09:00 - 24:00 (valandos nuo 9 iki 23)
                $startH = 9;
                $endH = 23;
            }

            for ($h = $startH; $h <= $endH; $h++) {
                $hStr = sprintf('%02d:00', $h);
                $reservations[] = [
                    'id' => 'BLOCK-' . strtoupper(substr(uniqid(), -6)),
                    'created_at' => date('Y-m-d H:i:s'),
                    'name' => '🔒 Visa diena uždaryta (Admin)',
                    'phone' => '-',
                    'email' => '-',
                    'activity' => 'Uždarytas laikas (Visos pramogos)',
                    'lane' => 'Visi takeliai',
                    'count' => 0,
                    'date' => $targetDate,
                    'iso_date' => $targetDate,
                    'time' => $hStr,
                    'price' => '0 €',
                    'status' => 'Uždaryta',
                    'is_block' => true,
                    'note' => 'Visa diena uždaryta administratoriaus'
                ];
            }
        }

        saveData($resFile, $reservations);
        header('Location: admin.php?tab=' . urlencode($redirectTab) . '&date=' . urlencode($redirectDate));
        exit;
    }

    // 8. Redaguoti rezervaciją pilnai
    if (isset($_POST['action']) && $_POST['action'] === 'edit_reservation') {
        $editId = $_POST['edit_id'] ?? '';
        $redirectTab = $_POST['redirect_tab'] ?? 'kalendorius';
        $redirectDate = $_POST['redirect_date'] ?? '';

        foreach ($reservations as &$r) {
            if (($r['id'] ?? '') === $editId) {
                $r['name'] = htmlspecialchars(trim($_POST['e_name'] ?? $r['name']));
                $r['phone'] = htmlspecialchars(trim($_POST['e_phone'] ?? $r['phone']));
                $r['email'] = htmlspecialchars(trim($_POST['e_email'] ?? $r['email']));
                $r['activity'] = htmlspecialchars(trim($_POST['e_activity'] ?? $r['activity']));
                $r['lane'] = htmlspecialchars(trim($_POST['e_lane'] ?? ($r['lane'] ?? '')));
                $r['count'] = intval($_POST['e_count'] ?? ($r['count'] ?? 1));
                $newDate = htmlspecialchars(trim($_POST['e_date'] ?? $r['date']));
                $r['date'] = $newDate;
                $r['iso_date'] = normalizeIso($newDate);
                $r['time'] = htmlspecialchars(trim($_POST['e_time'] ?? $r['time']));
                $r['price'] = htmlspecialchars(trim($_POST['e_price'] ?? $r['price']));
                $r['status'] = htmlspecialchars(trim($_POST['e_status'] ?? $r['status']));
                break;
            }
        }
        saveData($resFile, $reservations);
        header('Location: admin.php?tab=' . urlencode($redirectTab) . ($redirectDate ? '&date=' . urlencode($redirectDate) : ''));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ThrowHouse Šiauliai | Valdymo Pultas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #090a0f;
      --surface: #12141c;
      --surface-2: #1a1d29;
      --border: rgba(255, 255, 255, 0.08);
      --accent: #f59e0b;
      --accent-hover: #d97706;
      --text: #f8fafc;
      --muted: #94a3b8;
      --danger: #ef4444;
      --success: #10b981;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Plus Jakarta Sans', sans-serif;
      line-height: 1.5;
      padding: 1.5rem;
    }

    .container { max-width: 1400px; margin: 0 auto; }

    /* PRISIJUNGIMAS */
    .login-box {
      max-width: 400px;
      margin: 8rem auto;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2.5rem;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    }
    .login-box h2 { font-family: 'Outfit', sans-serif; font-size: 1.6rem; margin-bottom: 0.5rem; }
    .login-box p { color: var(--muted); font-size: 0.9rem; margin-bottom: 1.5rem; }

    .form-input {
      width: 100%;
      background: rgba(0,0,0,0.3);
      border: 1px solid var(--border);
      padding: 0.75rem 1rem;
      border-radius: 8px;
      color: #fff;
      font-size: 0.95rem;
      margin-bottom: 1rem;
    }
    .form-input:focus { outline: none; border-color: var(--accent); }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.65rem 1.25rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: 0.9rem;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
      text-decoration: none;
    }
    .btn-primary { background: var(--accent); color: #000; }
    .btn-primary:hover { background: var(--accent-hover); }
    .btn-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.4); }
    .btn-danger:hover { background: var(--danger); color: #fff; }
    .btn-success { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.4); }
    .btn-secondary { background: rgba(255,255,255,0.08); color: #fff; }

    /* HEADER */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid var(--border);
      padding-bottom: 1.25rem;
      margin-bottom: 2rem;
    }
    .brand {
      font-family: 'Outfit', sans-serif;
      font-size: 1.5rem;
      font-weight: 900;
      letter-spacing: -0.02em;
    }
    .brand span { color: var(--accent); }

    /* STATISTIKOS KORTELĖS */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.25rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1.25rem;
    }
    .stat-label { color: var(--muted); font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; }
    .stat-value { font-family: 'Outfit', sans-serif; font-size: 1.8rem; font-weight: 800; color: #fff; }

    /* TABS */
    .nav-tabs {
      display: flex;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
      border-bottom: 1px solid var(--border);
      padding-bottom: 0.75rem;
    }
    .tab-btn {
      padding: 0.6rem 1.25rem;
      border-radius: 8px;
      background: transparent;
      color: var(--muted);
      font-weight: 700;
      font-size: 0.95rem;
      text-decoration: none;
      transition: all 0.2s;
    }
    .tab-btn:hover { color: #fff; background: rgba(255,255,255,0.04); }
    .tab-btn.active { background: rgba(245, 158, 11, 0.15); color: var(--accent); border: 1px solid rgba(245,158,11,0.3); }

    /* LENTELĖS */
    .table-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      overflow: hidden;
    }
    .table-head {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .table-head h3 { font-family: 'Outfit', sans-serif; font-size: 1.2rem; }
    table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
    th { padding: 0.9rem 1.25rem; background: rgba(255,255,255,0.02); color: var(--muted); font-weight: 600; border-bottom: 1px solid var(--border); }
    td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.02); }

    .badge {
      display: inline-block;
      padding: 0.25rem 0.65rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 700;
    }
    .badge-success { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
    .badge-pending { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
    .badge-canceled { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }

    /* ============================================================ */
    /* REALUS INTERAKTYVUS KALENDORIUS */
    /* ============================================================ */
    .cal-layout {
      display: grid;
      grid-template-columns: 1.15fr 1fr;
      gap: 1.5rem;
      align-items: flex-start;
    }
    .cal-month-box {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.5rem;
    }
    .cal-header-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.25rem;
    }
    .cal-header-title {
      font-family: 'Outfit', sans-serif;
      font-size: 1.3rem;
      font-weight: 800;
      color: #fff;
    }
    .cal-nav-btn {
      background: rgba(255,255,255,0.06);
      border: 1px solid var(--border);
      color: #fff;
      padding: 0.4rem 0.8rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 700;
      font-size: 0.9rem;
      transition: all 0.2s;
    }
    .cal-nav-btn:hover {
      background: var(--accent);
      color: #000;
      border-color: var(--accent);
    }
    .cal-grid-head {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      text-align: center;
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--muted);
      margin-bottom: 0.5rem;
      padding-bottom: 0.5rem;
      border-bottom: 1px solid var(--border);
    }
    .cal-grid-days {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 6px;
    }
    .cal-day-cell {
      min-height: 78px;
      padding: 6px;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      justify-content: flex-start;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.85rem;
      position: relative;
      transition: all 0.2s;
    }
    .cal-day-cell:hover {
      border-color: var(--accent);
      background: rgba(245,158,11,0.08);
    }
    .cal-day-cell.active {
      border-color: var(--accent);
      background: rgba(245,158,11,0.14);
      box-shadow: 0 0 12px rgba(245,158,11,0.25);
    }
    .cal-day-cell.today {
      border-color: #3b82f6;
    }
    .cal-day-cell.today .cal-day-num {
      color: #60a5fa;
      font-weight: 800;
    }
    .cal-day-cell.other-month {
      opacity: 0.25;
      cursor: default;
    }
    .cal-day-num {
      font-weight: 700;
      margin-bottom: 4px;
    }
    .cal-day-badges {
      display: flex;
      flex-direction: column;
      gap: 2px;
      width: 100%;
    }
    .cal-badge-pill {
      font-size: 0.68rem;
      padding: 1px 4px;
      border-radius: 4px;
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-align: center;
      display: block;
    }
    .pill-rage { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); }
    .pill-axe { background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.4); }
    .pill-kombo { background: rgba(168, 85, 247, 0.2); color: #d8b4fe; border: 1px solid rgba(168, 85, 247, 0.4); }
    .pill-total { background: rgba(245, 158, 11, 0.2); color: var(--accent); font-weight: 800; }

    /* DEŠINYSIS DIENOS SKYDELIS */
    .day-schedule-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.5rem;
    }
    .schedule-header {
      border-bottom: 1px solid var(--border);
      padding-bottom: 1rem;
      margin-bottom: 1.25rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    /* VALANDŲ STATUSO LENTELĖ */
    .hourly-grid {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 1.5rem;
      max-height: 380px;
      overflow-y: auto;
      padding-right: 4px;
    }
    .hour-row {
      display: grid;
      grid-template-columns: 75px 1.15fr 1.15fr auto;
      gap: 8px;
      padding: 8px 10px;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 8px;
      align-items: center;
      font-size: 0.85rem;
    }
    .hour-time { font-family: 'Outfit', sans-serif; font-weight: 800; color: var(--accent); }
    .slot-status {
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 0.78rem;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .status-free { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
    .status-booked { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }

    /* REZERVACIJŲ KORTELĖS */
    .res-card-item {
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 1rem;
      margin-bottom: 0.75rem;
    }
    .res-card-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 0.5rem;
    }

    /* MODAL */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.8);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .modal-box {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2rem;
      max-width: 500px;
      width: 100%;
      box-shadow: 0 25px 50px rgba(0,0,0,0.5);
    }

    @media (max-width: 1024px) {
      .cal-layout { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<?php if (empty($_SESSION['admin_logged'])): ?>
  <!-- PRISIJUNGIMO FORMA -->
  <div class="login-box">
    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔒</div>
    <h2>ThrowHouse Šiauliai</h2>
    <p>Administratoriaus prisijungimas</p>
    <?php if (!empty($login_error)): ?>
      <div style="color: #f87171; background: rgba(239,68,68,0.1); padding: 0.5rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem;">
        <?= $login_error ?>
      </div>
    <?php endif; ?>
    <form method="POST">
      <input type="password" name="login_pass" class="form-input" placeholder="Įveskite slaptažodį..." required autofocus>
      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.8rem;">Prisijungti</button>
    </form>
  </div>

<?php else: 
  // Atskiriame tikras klientų rezervacijas nuo administratoriaus uždarytų laikų (nedirbame)
  function isReservationBlock($r) {
    if (!empty($r['is_block'])) return true;
    if (($r['status'] ?? '') === 'Uždaryta') return true;
    if (str_starts_with($r['id'] ?? '', 'BLOCK-')) return true;
    $act = $r['activity'] ?? '';
    if (stripos($act, 'Uždaryt') !== false) return true;
    $name = $r['name'] ?? '';
    if (stripos($name, 'Uždarytas') !== false || stripos($name, 'Nedirbame') !== false) return true;
    return false;
  }

  $clientReservations = array_values(array_filter($reservations, fn($r) => !isReservationBlock($r)));
  $closedBlocks = array_values(array_filter($reservations, fn($r) => isReservationBlock($r)));

  $totalRes = count($clientReservations);
  $totalCoupons = count($coupons);
  $activeCoupons = count(array_filter($coupons, fn($c) => ($c['status'] ?? '') === 'Aktyvus'));
  
  $totalIncome = 0;
  foreach ($clientReservations as $r) {
    if (($r['status'] ?? '') !== 'Atšaukta') {
      $totalIncome += intval(preg_replace('/[^0-9]/', '', $r['price'] ?? '0'));
    }
  }

  $currentTab = $_GET['tab'] ?? 'kalendorius';
  $initialSelectedDate = $_GET['date'] ?? date('Y-m-d');
?>
  <!-- ADMIN NAVIGACIJA -->
  <header class="header">
    <div class="brand">THROW<span>HOUSE</span> <span style="font-size: 0.8rem; font-weight: 600; color: var(--muted); margin-left: 0.5rem;">VALDYMO PULTAS</span></div>
    <div style="display: flex; gap: 1rem; align-items: center;">
      <a href="index.html" target="_blank" style="color: var(--muted); font-size: 0.85rem; text-decoration: none;">🌐 Eiti į svetainę ➔</a>
      <a href="admin.php?logout=1" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">Atsijungti</a>
    </div>
  </header>

  <div class="container">
    
    <!-- STATISTIKA -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Klientų rezervacijos</div>
        <div class="stat-value"><?= $totalRes ?></div>
        <?php if (!empty($closedBlocks)): ?>
          <div style="font-size: 0.75rem; color: #f87171; margin-top: 4px;">🔒 Uždaryti laikai: <?= count($closedBlocks) ?> val. (nedirbame)</div>
        <?php endif; ?>
      </div>
      <div class="stat-card">
        <div class="stat-label">Bendra apyvarta (Rezervacijos)</div>
        <div class="stat-value" style="color: var(--accent);"><?= $totalIncome ?> €</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Aktyvūs dovanų kuponai</div>
        <div class="stat-value" style="color: #4ade80;"><?= $activeCoupons ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Iš viso parduota kuponų</div>
        <div class="stat-value"><?= $totalCoupons ?></div>
      </div>
    </div>

    <!-- SKILČIŲ MYGTUKAI -->
    <div class="nav-tabs">
      <a href="admin.php?tab=kalendorius" class="tab-btn <?= $currentTab === 'kalendorius' ? 'active' : '' ?>">📅 Rezervacijų kalendorius</a>
      <a href="admin.php?tab=rezervacijos" class="tab-btn <?= $currentTab === 'rezervacijos' ? 'active' : '' ?>">📋 Rezervacijų sąrašas (<?= $totalRes ?>)</a>
      <a href="admin.php?tab=kuponai" class="tab-btn <?= $currentTab === 'kuponai' ? 'active' : '' ?>">🎁 Dovanų kuponai (<?= $totalCoupons ?>)</a>
    </div>

    <!-- ======================================================= -->
    <!-- 1 TAB: INTERAKTYVUS KALENDORIUS SU UŽIMTUMO KONTROLE     -->
    <!-- ======================================================= -->
    <?php if ($currentTab === 'kalendorius'): ?>
      <div class="cal-layout">
        
        <!-- KAIRĖJE: MĖNESIO KALENDORIAUS TINKLELIS -->
        <div class="cal-month-box">
          <div class="cal-header-controls">
            <button class="cal-nav-btn" onclick="adminPrevMonth()">◄</button>
            <h3 class="cal-header-title" id="adminCalMonthLabel">Spalis 2026</h3>
            <button class="cal-nav-btn" onclick="adminNextMonth()">►</button>
            <button class="cal-nav-btn" onclick="adminGoToday()" style="background:rgba(245,158,11,0.2); color:var(--accent);">Šiandien</button>
          </div>

          <div class="cal-grid-head">
            <div>Pr</div><div>An</div><div>Tr</div><div>Kt</div><div>Pn</div><div>Št</div><div>Sk</div>
          </div>

          <div class="cal-grid-days" id="adminCalGrid"></div>

          <!-- LEGENDA IR TALPA -->
          <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 10px; border: 1px solid var(--border); margin-top: 1.5rem;">
            <div style="font-size: 0.85rem; font-weight: 700; color: #fff; margin-bottom: 0.4rem;">🏢 Talpos taisyklės:</div>
            <div style="font-size: 0.8rem; color: var(--muted); line-height: 1.6;">
              • <strong>Pykčio kambarys:</strong> 1 vieta (maks. 1 rezervacija / val.)<br>
              • <strong>Kirvių mėtymas:</strong> 2 takeliai (maks. 2 rezervacijos / val.)<br>
              • <strong>Darbo laikas:</strong> V–VI: 09–24 val. | VII: 09–20 val. | I–IV: Nedirbame (Jau greitai!)
            </div>
          </div>
        </div>

        <!-- DEŠINĖJE: PASIRINKTOS DIENOS GRAFIKAS IR VALANDOS -->
        <div class="day-schedule-card">
          <div class="schedule-header">
            <div>
              <h3 id="scheduleDateTitle" style="font-family: 'Outfit'; font-size: 1.25rem;">Pasirinkta diena</h3>
              <p id="scheduleDateSubtitle" style="color: var(--muted); font-size: 0.85rem;">Kraunama...</p>
            </div>
            <button class="btn btn-primary" onclick="openManualModal()" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
              + Užsakymas telefonu
            </button>
          </div>

          <!-- DIENOS VALDYMAS (VISOS DIENOS UŽDARYMAS / ATIDARYMAS) -->
          <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 1.25rem; align-items: center; background: rgba(0,0,0,0.3); padding: 0.65rem 0.9rem; border-radius: 8px; border: 1px solid var(--border);">
            <span style="font-size: 0.8rem; font-weight: 700; color: var(--muted);">Dienos valdymas:</span>
            <form method="POST" style="display: inline-block;">
              <input type="hidden" name="action" value="toggle_day_block">
              <input type="hidden" name="b_date" id="dayBlockDate" value="<?= htmlspecialchars($initialSelectedDate) ?>">
              <input type="hidden" name="b_block" value="1">
              <button type="submit" class="btn btn-danger" style="font-size: 0.75rem; padding: 0.3rem 0.65rem;" onclick="return confirm('Ar tikrai norite UŽDARYTI visą šią dieną? Klientai negalės rezervuoti nė vienos valandos.')">
                🔒 Uždaryti visą dieną
              </button>
            </form>
            <form method="POST" style="display: inline-block;">
              <input type="hidden" name="action" value="toggle_day_block">
              <input type="hidden" name="b_date" id="dayUnblockDate" value="<?= htmlspecialchars($initialSelectedDate) ?>">
              <input type="hidden" name="b_block" value="0">
              <button type="submit" class="btn btn-success" style="font-size: 0.75rem; padding: 0.3rem 0.65rem;" onclick="return confirm('Ar tikrai norite ATIDARYTI visus šios dienos uždarytus laikus?')">
                🔓 Atidaryti visą dieną
              </button>
            </form>
          </div>

          <h4 style="font-size: 0.9rem; color: #fff; margin-bottom: 0.6rem;">🕒 Valandinis užimtumas ir valdymas:</h4>
          <div class="hourly-grid" id="hourlyGrid"></div>

          <h4 style="font-size: 0.9rem; color: #fff; margin: 1.25rem 0 0.6rem 0;">📋 Šios dienos rezervacijos:</h4>
          <div id="dayBookingsList"></div>
        </div>

      </div>

      <!-- MODALINIS LANGAS: RANKINĖ REZERVACIJA (TELEFONU) -->
      <div id="manualModal" class="modal-overlay">
        <div class="modal-box">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-family: 'Outfit'; font-size: 1.25rem;">+ Užsakymas telefonu</h3>
            <button onclick="closeManualModal()" style="background:none; border:none; color:var(--muted); font-size:1.5rem; cursor:pointer;">✕</button>
          </div>

          <form method="POST">
            <input type="hidden" name="action" value="add_manual_res">
            <input type="hidden" name="m_iso_date" id="m_iso_date" value="<?= htmlspecialchars($initialSelectedDate) ?>">

            <div style="margin-bottom: 0.75rem;">
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Kliento vardas:</label>
              <input type="text" name="m_name" class="form-input" placeholder="Vardas Pavardė" required style="margin-bottom:0;">
            </div>

            <div style="margin-bottom: 0.75rem;">
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Telefono numeris:</label>
              <input type="text" name="m_phone" class="form-input" placeholder="+370..." required style="margin-bottom:0;">
            </div>

            <div style="margin-bottom: 0.75rem;">
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">El. paštas (neprivaloma):</label>
              <input type="email" name="m_email" class="form-input" placeholder="klientas@gmail.com" style="margin-bottom:0;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom: 0.75rem;">
              <div>
                <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Pramoga:</label>
                <select name="m_activity" id="m_activity" class="form-input" style="margin-bottom:0;" onchange="updateManualLaneSelect()">
                  <option value="Pykčio kambarys">Pykčio kambarys (30 €)</option>
                  <option value="Kirvių mėtymas">Kirvių mėtymas (30 €)</option>
                  <option value="KOMBO paketas">KOMBO paketas (50 €)</option>
                </select>
              </div>
              <div id="m_lane_wrapper" style="display:none;">
                <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Takelis:</label>
                <select name="m_lane" class="form-input" style="margin-bottom:0;">
                  <option value="Takelis 1">Takelis 1</option>
                  <option value="Takelis 2">Takelis 2</option>
                </select>
              </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom: 1.25rem;">
              <div>
                <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Valanda:</label>
                <select name="m_time" id="m_time" class="form-input" style="margin-bottom:0;"></select>
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Kaina:</label>
                <input type="text" name="m_price" id="m_price" class="form-input" value="30 €" style="margin-bottom:0;">
              </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
              Išsaugoti rezervaciją ➔
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <!-- ======================================================= -->
    <!-- 2 TAB: PILNAS REZERVACIJŲ SĄRAŠAS                        -->
    <!-- ======================================================= -->
    <?php if ($currentTab === 'rezervacijos'): ?>
      <div class="table-card">
        <div class="table-head">
          <h3>Klientų rezervacijos</h3>
          <span style="font-size: 0.85rem; color: var(--muted);">Iš viso: <?= count($clientReservations) ?></span>
        </div>
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Data ir Laikas</th>
                <th>Klientas</th>
                <th>Telefonas / El. paštas</th>
                <th>Pramoga</th>
                <th>Dalyviai</th>
                <th>Suma</th>
                <th>Būsena</th>
                <th>Veiksmas</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($clientReservations)): ?>
                <tr>
                  <td colspan="9" style="text-align: center; padding: 3rem; color: var(--muted);">Rezervacijų sąrašas tuščias.</td>
                </tr>
              <?php else: ?>
                <?php foreach (array_reverse($clientReservations) as $r): ?>
                  <tr>
                    <td><code><?= htmlspecialchars($r['id'] ?? '') ?></code></td>
                    <td><strong><?= htmlspecialchars($r['date'] ?? '') ?></strong><br><span style="color: var(--accent);"><?= htmlspecialchars($r['time'] ?? '') ?> val.</span></td>
                    <td><strong><?= htmlspecialchars($r['name'] ?? '') ?></strong></td>
                    <td>
                      <a href="tel:<?= htmlspecialchars($r['phone'] ?? '') ?>" style="color:#fff; font-weight:bold; text-decoration:none;">📞 <?= htmlspecialchars($r['phone'] ?? '') ?></a><br>
                      <a href="mailto:<?= htmlspecialchars($r['email'] ?? '') ?>" style="color: var(--muted); font-size: 0.8rem; text-decoration:none;">✉️ <?= htmlspecialchars($r['email'] ?? '') ?></a>
                    </td>
                    <td><?= htmlspecialchars($r['activity'] ?? '') ?> <?= !empty($r['lane']) ? '<br><small style="color:var(--accent);">('.htmlspecialchars($r['lane']).')</small>' : '' ?></td>
                    <td><?= htmlspecialchars($r['count'] ?? 1) ?> asm.</td>
                    <td><strong style="color: var(--accent);"><?= htmlspecialchars($r['price'] ?? '') ?></strong></td>
                    <td>
                      <span class="badge <?= ($r['status'] ?? '') === 'Įvykusi' ? 'badge-success' : (($r['status'] ?? '') === 'Atšaukta' ? 'badge-canceled' : 'badge-pending') ?>">
                        <?= htmlspecialchars($r['status'] ?? 'Gauta') ?>
                      </span>
                    </td>
                    <td>
                      <button type="button" class="btn btn-secondary" style="font-size:0.75rem; padding:0.25rem 0.5rem; margin-right:4px;" onclick='openEditModal(<?= htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>)' title="Redaguoti rezervaciją">✏️</button>
                      <form method="POST" style="display: inline-block;">
                        <input type="hidden" name="action" value="update_res_status">
                        <input type="hidden" name="redirect_tab" value="rezervacijos">
                        <input type="hidden" name="res_id" value="<?= htmlspecialchars($r['id'] ?? '') ?>">
                        <select name="new_status" onchange="this.form.submit()" style="background:#090a0f; color:#fff; border:1px solid var(--border); padding:0.3rem 0.5rem; border-radius:6px; font-size:0.8rem; cursor:pointer;">
                          <option value="Gauta" <?= ($r['status'] ?? '') === 'Gauta' ? 'selected' : '' ?>>Gauta</option>
                          <option value="Patvirtinta" <?= ($r['status'] ?? '') === 'Patvirtinta' ? 'selected' : '' ?>>Patvirtinta</option>
                          <option value="Įvykusi" <?= ($r['status'] ?? '') === 'Įvykusi' ? 'selected' : '' ?>>Įvykusi</option>
                          <option value="Atšaukta" <?= ($r['status'] ?? '') === 'Atšaukta' ? 'selected' : '' ?>>Atšaukta</option>
                          <option value="Uždaryta" <?= ($r['status'] ?? '') === 'Uždaryta' ? 'selected' : '' ?>>Uždaryta</option>
                        </select>
                      </form>
                      <form method="POST" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('Ar tikrai norite pašalinti šią rezervaciją?')">
                        <input type="hidden" name="action" value="delete_res">
                        <input type="hidden" name="redirect_tab" value="rezervacijos">
                        <input type="hidden" name="del_id" value="<?= htmlspecialchars($r['id'] ?? '') ?>">
                        <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:0.9rem;" title="Ištrinti">🗑️</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- ======================================================= -->
    <!-- 3 TAB: DOVANŲ KUPONAI                                   -->
    <!-- ======================================================= -->
    <?php if ($currentTab === 'kuponai'): ?>
      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: flex-start;">
        <div class="table-card">
          <div class="table-head">
            <h3>Dovanų kuponų apskaita</h3>
            <span style="font-size: 0.85rem; color: var(--muted);">Aktyvių: <?= $activeCoupons ?></span>
          </div>
          <div style="overflow-x: auto;">
            <table>
              <thead>
                <tr>
                  <th>Kupono kodas</th>
                  <th>Vertė</th>
                  <th>Pirkėjas / Gavėjas</th>
                  <th>Galioja iki</th>
                  <th>Būsena</th>
                  <th>Veiksmas</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($coupons)): ?>
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 3rem; color: var(--muted);">Dovanų kuponų kol kas neparduota.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach (array_reverse($coupons) as $c): ?>
                    <tr>
                      <td>
                        <strong style="font-family:'Outfit'; font-size:1.05rem; letter-spacing:0.05em; color:var(--accent);">
                          <?= htmlspecialchars($c['code'] ?? '') ?>
                        </strong>
                      </td>
                      <td><strong style="font-size: 1.1rem;"><?= htmlspecialchars($c['amount'] ?? '') ?> €</strong></td>
                      <td>
                        <strong><?= htmlspecialchars($c['buyer'] ?? 'Nenurodyta') ?></strong>
                        <?php if (!empty($c['recipient'])): ?>
                          <br><span style="font-size: 0.8rem; color: #cbd5e1;">Gavėjas: <?= htmlspecialchars($c['recipient']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($c['target_type'])): ?>
                          <br><span style="font-size: 0.75rem; color: var(--accent); font-weight: 600;"><?= htmlspecialchars($c['target_type']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($c['greeting'])): ?>
                          <br><span style="font-size: 0.75rem; color: #94a3b8; font-style: italic;">„<?= htmlspecialchars($c['greeting']) ?>“</span>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($c['expires_at'] ?? '') ?></td>
                      <td>
                        <span class="badge <?= ($c['status'] ?? '') === 'Aktyvus' ? 'badge-success' : 'badge-canceled' ?>">
                          <?= htmlspecialchars($c['status'] ?? 'Aktyvus') ?>
                        </span>
                      </td>
                      <td>
                        <form method="POST">
                          <input type="hidden" name="action" value="toggle_coupon">
                          <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($c['code'] ?? '') ?>">
                          <button type="submit" class="btn <?= ($c['status'] ?? '') === 'Aktyvus' ? 'btn-danger' : 'btn-success' ?>" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                            <?= ($c['status'] ?? '') === 'Aktyvus' ? 'Pažymėti panaudotu' : 'Atstatyti aktyvų' ?>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- RANKINIS KUPONO SUKŪRIMAS -->
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1.75rem;">
          <h3 style="font-family: 'Outfit'; font-size: 1.25rem; margin-bottom: 0.5rem;">Sukurti kuponą vietoje</h3>
          <p style="color: var(--muted); font-size: 0.85rem; margin-bottom: 1.25rem;">
            Jei klientas perka dovanų kuponą tiesiogiai pas jus salėje, galite greitai sugeneruoti kodą rankiniu būdu.
          </p>

          <form method="POST">
            <input type="hidden" name="action" value="create_coupon">
            
            <div style="margin-bottom: 1rem;">
              <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--muted); margin-bottom: 0.3rem;">Kupono vertė:</label>
              <select name="amount" class="form-input" style="margin-bottom: 0;">
                <option value="30">30 € (Pramogos kuponas)</option>
                <option value="50">50 € (KOMBO kuponas)</option>
                <option value="100">100 € (VIP šventė)</option>
              </select>
            </div>

            <div style="margin-bottom: 1rem;">
              <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--muted); margin-bottom: 0.3rem;">Pirkėjo vardas / tel.:</label>
              <input type="text" name="buyer" class="form-input" placeholder="Vardas Pavardė" style="margin-bottom: 0;" required>
            </div>

            <div style="margin-bottom: 1.5rem;">
              <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--muted); margin-bottom: 0.3rem;">Kam dovanojama (neprivaloma):</label>
              <input type="text" name="recipient" class="form-input" placeholder="Gavėjo vardas" style="margin-bottom: 0;">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
              + Sugeneruoti kuponą
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <!-- MODALINIS LANGAS: REZERVACIJOS REDAGAVIMAS -->
    <div id="editModal" class="modal-overlay">
      <div class="modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
          <h3 style="font-family: 'Outfit'; font-size: 1.25rem;">✏️ Redaguoti rezervaciją</h3>
          <button onclick="closeEditModal()" style="background:none; border:none; color:var(--muted); font-size:1.5rem; cursor:pointer;">✕</button>
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="edit_reservation">
          <input type="hidden" name="redirect_tab" value="<?= htmlspecialchars($currentTab) ?>">
          <input type="hidden" name="redirect_date" id="e_redirect_date" value="<?= htmlspecialchars($initialSelectedDate) ?>">
          <input type="hidden" name="edit_id" id="e_id">

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom: 0.75rem;">
            <div>
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Kliento vardas:</label>
              <input type="text" name="e_name" id="e_name" class="form-input" required style="margin-bottom:0;">
            </div>
            <div>
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Telefonas:</label>
              <input type="text" name="e_phone" id="e_phone" class="form-input" style="margin-bottom:0;">
            </div>
          </div>

          <div style="margin-bottom: 0.75rem;">
            <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">El. paštas:</label>
            <input type="email" name="e_email" id="e_email" class="form-input" style="margin-bottom:0;">
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom: 0.75rem;">
            <div>
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Pramoga:</label>
              <select name="e_activity" id="e_activity" class="form-input" style="margin-bottom:0;" onchange="updateEditLaneVisibility()">
                <option value="Pykčio kambarys">Pykčio kambarys</option>
                <option value="Kirvių mėtymas">Kirvių mėtymas</option>
                <option value="KOMBO paketas">KOMBO paketas</option>
                <option value="Uždarytas laikas (Visos pramogos)">Uždarytas laikas (Visos)</option>
              </select>
            </div>
            <div id="e_lane_box">
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Takelis:</label>
              <select name="e_lane" id="e_lane" class="form-input" style="margin-bottom:0;">
                <option value="">Nėra</option>
                <option value="Takelis 1">Takelis 1</option>
                <option value="Takelis 2">Takelis 2</option>
                <option value="Visi takeliai">Visi takeliai</option>
              </select>
            </div>
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom: 0.75rem;">
            <div>
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Data (YYYY-MM-DD):</label>
              <input type="date" name="e_date" id="e_date" class="form-input" required style="margin-bottom:0;">
            </div>
            <div>
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Laikas (valanda):</label>
              <input type="text" name="e_time" id="e_time" class="form-input" placeholder="pvz. 16:00 arba 16:00 - 18:00" required style="margin-bottom:0;">
            </div>
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:0.75rem; margin-bottom: 1.25rem;">
            <div>
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Dalyviai:</label>
              <input type="number" name="e_count" id="e_count" min="1" max="50" class="form-input" style="margin-bottom:0;">
            </div>
            <div>
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Kaina:</label>
              <input type="text" name="e_price" id="e_price" class="form-input" style="margin-bottom:0;">
            </div>
            <div>
              <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.2rem;">Būsena:</label>
              <select name="e_status" id="e_status" class="form-input" style="margin-bottom:0;">
                <option value="Gauta">Gauta</option>
                <option value="Patvirtinta">Patvirtinta</option>
                <option value="Įvykusi">Įvykusi</option>
                <option value="Atšaukta">Atšaukta</option>
                <option value="Uždaryta">Uždaryta</option>
              </select>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
            💾 Išsaugoti pakeitimus ➔
          </button>
        </form>
      </div>
    </div>

  </div>

  <!-- KALENDORIAUS JAVASCRIPT VALDIKLIS -->
  <script>
    const ALL_RESERVATIONS = <?= json_encode($reservations, JSON_UNESCAPED_UNICODE) ?>;
    
    // Datos normalizavimo funkcija
    function getIso(r) {
      if (r.iso_date && /^\d{4}-\d{2}-\d{2}$/.test(r.iso_date)) return r.iso_date;
      if (r.date && /^\d{4}-\d{2}-\d{2}$/.test(r.date)) return r.date;
      if (r.date) {
        const months = {
          'sausio':'01','vasario':'02','kovo':'03','balandžio':'04','gegužės':'05',
          'birželio':'06','liepos':'07','rugpjūčio':'08','rugsėjo':'09','spalio':'10',
          'lapkričio':'11','gruodžio':'12'
        };
        const m = r.date.match(/(\d{4})\s*m\.\s*(\S+)\s*(\d{1,2})\s*d\./i);
        if (m) {
          const y = m[1];
          const mon = months[m[2].toLowerCase()] || '01';
          const d = String(m[3]).padStart(2, '0');
          return `${y}-${mon}-${d}`;
        }
      }
      return '';
    }

    // Papildome objektus normalizuota data
    ALL_RESERVATIONS.forEach(r => { r.computed_iso = getIso(r); });

    // Pagalbinė funkcija: ar įrašas yra administratoriaus uždarytas laikas (nedirbame blokas)
    function isBlockItem(r) {
      if (!r) return false;
      if (r.is_block) return true;
      if (r.status === 'Uždaryta') return true;
      if (String(r.id || '').startsWith('BLOCK-')) return true;
      const act = String(r.activity || '').toLowerCase();
      if (act.includes('uždaryt')) return true;
      const name = String(r.name || '').toLowerCase();
      if (name.includes('uždarytas') || name.includes('nedirbame')) return true;
      return false;
    }

    const LT_MONTHS_NAMES = [
      'Sausis', 'Vasaris', 'Kovas', 'Balandis', 'Gegužė', 'Birželis',
      'Liepa', 'Rugpjūtis', 'Rugsėjis', 'Spalis', 'Lapkritis', 'Gruodis'
    ];
    const LT_WEEKDAYS = ['Sekmadienis', 'Pirmadienis', 'Antradienis', 'Trečiadienis', 'Ketvirtadienis', 'Penktadienis', 'Šeštadienis'];

    let adminCalDate = new Date();
    let selectedIsoDate = "<?= htmlspecialchars($initialSelectedDate) ?>";

    // Mėnesio perjungimas
    function adminPrevMonth() {
      adminCalDate.setMonth(adminCalDate.getMonth() - 1);
      renderAdminCalendar();
    }
    function adminNextMonth() {
      adminCalDate.setMonth(adminCalDate.getMonth() + 1);
      renderAdminCalendar();
    }
    function adminGoToday() {
      adminCalDate = new Date();
      const todayIso = adminCalDate.toISOString().split('T')[0];
      selectDate(todayIso);
    }

    function renderAdminCalendar() {
      const grid = document.getElementById('adminCalGrid');
      if (!grid) return;
      grid.innerHTML = '';

      const y = adminCalDate.getFullYear();
      const m = adminCalDate.getMonth();

      document.getElementById('adminCalMonthLabel').textContent = `${LT_MONTHS_NAMES[m]} ${y}`;

      const firstDay = new Date(y, m, 1);
      let startOffset = firstDay.getDay() - 1;
      if (startOffset === -1) startOffset = 6;

      const daysInMonth = new Date(y, m + 1, 0).getDate();
      const todayIso = (new Date()).toISOString().split('T')[0];

      // Tušti langeliai mėnesio pradžiai
      for (let i = 0; i < startOffset; i++) {
        const blank = document.createElement('div');
        blank.className = 'cal-day-cell other-month';
        grid.appendChild(blank);
      }

      for (let day = 1; day <= daysInMonth; day++) {
        const iso = `${y}-${String(m + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const cell = document.createElement('div');
        cell.className = 'cal-day-cell';
        if (iso === selectedIsoDate) cell.classList.add('active');
        if (iso === todayIso) cell.classList.add('today');

        // Rezervacijos šiai dienai (ne atšauktos)
        const dayBookings = ALL_RESERVATIONS.filter(r => r.computed_iso === iso && r.status !== 'Atšaukta');

        let badgesHtml = '';
        if (dayBookings.length > 0) {
          const blocks = dayBookings.filter(r => isBlockItem(r));
          const realBookings = dayBookings.filter(r => !isBlockItem(r));

          // Klientų rezervacijos (TIK JEI YRA TIKRŲ REZERVACIJŲ!)
          if (realBookings.length > 0) {
            let hasRage = realBookings.some(r => (r.activity || '').includes('Pykčio'));
            let hasAxe = realBookings.some(r => (r.activity || '').includes('Kirvių'));
            let hasKombo = realBookings.some(r => (r.activity || '').includes('KOMBO'));

            badgesHtml += `<span class="cal-badge-pill pill-total">${realBookings.length} rez.</span>`;
            if (hasRage) badgesHtml += `<span class="cal-badge-pill pill-rage">💥 Pyktis</span>`;
            if (hasAxe) badgesHtml += `<span class="cal-badge-pill pill-axe">🪓 Kirviai</span>`;
            if (hasKombo) badgesHtml += `<span class="cal-badge-pill pill-kombo">⚡ KOMBO</span>`;
          }

          // Uždaryti laikai / Nedirbame rodomi atskirai (neskaičiuojami prie bendro "X rez." skaičiaus)
          if (blocks.length > 0) {
            badgesHtml += `<span class="cal-badge-pill" style="background:rgba(239,68,68,0.25); color:#fca5a5; border:1px solid rgba(239,68,68,0.4);">🔒 ${blocks.length >= 7 ? 'Nedirbame' : blocks.length + ' val. nedirbame'}</span>`;
          }
        }

        cell.innerHTML = `
          <div class="cal-day-num">${day}</div>
          <div class="cal-day-badges">${badgesHtml}</div>
        `;

        cell.onclick = () => { selectDate(iso); };
        grid.appendChild(cell);
      }
    }

    function selectDate(iso) {
      selectedIsoDate = iso;
      const parts = iso.split('-');
      if (parts.length === 3) {
        adminCalDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
      }
      const bDate = document.getElementById('dayBlockDate');
      const uDate = document.getElementById('dayUnblockDate');
      if (bDate) bDate.value = iso;
      if (uDate) uDate.value = iso;

      renderAdminCalendar();
      renderDaySchedule(iso);
    }

    function renderDaySchedule(iso) {
      const titleEl = document.getElementById('scheduleDateTitle');
      const subEl = document.getElementById('scheduleDateSubtitle');
      const hourlyEl = document.getElementById('hourlyGrid');
      const listEl = document.getElementById('dayBookingsList');
      if (!titleEl || !hourlyEl) return;

      const d = new Date(iso + 'T00:00:00');
      const weekday = LT_WEEKDAYS[d.getDay()];
      titleEl.textContent = `📅 ${iso} (${weekday})`;

      const dayBookings = ALL_RESERVATIONS.filter(r => r.computed_iso === iso);
      const activeBookings = dayBookings.filter(r => r.status !== 'Atšaukta');

      const blocks = activeBookings.filter(r => isBlockItem(r));
      const clientBookings = activeBookings.filter(r => !isBlockItem(r));

      let dayTotal = 0;
      clientBookings.forEach(r => {
        dayTotal += parseInt((r.price || '0').replace(/[^0-9]/g, '')) || 0;
      });

      subEl.innerHTML = `
        <span style="color:#60a5fa; font-weight:600;">Klientų rezervacijos: ${clientBookings.length}</span>
        ${blocks.length > 0 ? ` • <span style="color:#f87171; font-weight:600;">🔒 Nedirbame: ${blocks.length} val.</span>` : ''}
        • <span style="color:var(--accent);">Numatoma apyvarta: ${dayTotal} €</span>
      `;

      // Nustatome darbo valandas pagal savaitės dieną:
      // I-IV: 16:00 - 22:00 (iki 23:00) | V-VI: 09:00 - 23:00 (iki 24:00) | VII: 09:00 - 19:00 (iki 20:00)
      const dayOfWeek = d.getDay();
      let startH = 16;
      let endH = 22;
      if (dayOfWeek === 0) {
        // Sekmadienis: 09:00 - 20:00
        startH = 9;
        endH = 19;
      } else if (dayOfWeek === 5 || dayOfWeek === 6) {
        // Penktadienis - Šeštadienis: 09:00 - 24:00
        startH = 9;
        endH = 23;
      }

      // Valandų tinklelis su tiesioginiu atidarymo / uždarymo valdymu
      hourlyEl.innerHTML = '';
      for (let h = startH; h <= endH; h++) {
        const hourStr = (h < 10 ? '0' : '') + h + ':00';
        
        let rageBooking = null;
        let axe1Booking = null;
        let axe2Booking = null;
        let allBlockBooking = null;

        activeBookings.forEach(r => {
          const act = r.activity || '';
          const l = r.lane || '';
          const sH = parseInt(r.time, 10);
          const startStr = (sH < 10 ? '0' : '') + sH + ':00';
          const nextStr = ((sH + 1) < 10 ? '0' : '') + (sH + 1) + ':00';
          const isBlk = isBlockItem(r);

          if (isBlk && act.includes('Visos')) {
            if (hourStr === startStr) allBlockBooking = r;
          } else if (act.includes('Pykčio')) {
            if (hourStr === startStr) rageBooking = r;
          } else if (act.includes('Kirvių')) {
            if (hourStr === startStr) {
              if (l.includes('2')) axe2Booking = r;
              else if (l.includes('Visi')) { axe1Booking = r; axe2Booking = r; }
              else axe1Booking = r;
            }
          } else if (act.includes('KOMBO')) {
            if (hourStr === startStr) {
              if (l.includes('2')) axe2Booking = r;
              else axe1Booking = r;
            }
            if (hourStr === nextStr) {
              rageBooking = r;
            }
          }
        });

        if (allBlockBooking) {
          if (!rageBooking) rageBooking = allBlockBooking;
          if (!axe1Booking) axe1Booking = allBlockBooking;
          if (!axe2Booking) axe2Booking = allBlockBooking;
        }

        const isRageBlocked = rageBooking && isBlockItem(rageBooking);
        const isAxe1Blocked = axe1Booking && isBlockItem(axe1Booking);
        const isAxe2Blocked = axe2Booking && isBlockItem(axe2Booking);
        const isWholeHourBlocked = isRageBlocked && (isAxe1Blocked && isAxe2Blocked || (allBlockBooking !== null));

        // Pykčio kambario langelis
        let rageHtml = '';
        if (isRageBlocked) {
          rageHtml = `
            <div class="slot-status" style="background:rgba(239,68,68,0.18); color:#fca5a5; border:1px solid rgba(239,68,68,0.4); display:flex; justify-content:space-between; align-items:center;">
              <span>💥 🔒 Nedirbame (Uždaryta)</span>
              <form method="POST" style="margin:0; display:inline;">
                <input type="hidden" name="action" value="toggle_slot_block">
                <input type="hidden" name="b_date" value="${iso}">
                <input type="hidden" name="b_time" value="${hourStr}">
                <input type="hidden" name="b_target" value="rage">
                <input type="hidden" name="b_block" value="0">
                <button type="submit" class="btn btn-success" style="font-size:0.68rem; padding:2px 6px;" title="Atidaryti pykčio kambarį šią valandą">🔓 Atidaryti</button>
              </form>
            </div>
          `;
        } else if (rageBooking) {
          rageHtml = `
            <div class="slot-status status-booked" title="Užsakovas: ${rageBooking.name} (${rageBooking.phone})">
              💥 Užimta: ${rageBooking.name.split(' ')[0]} ${rageBooking.activity.includes('KOMBO') ? '(KOMBO)' : ''}
            </div>
          `;
        } else {
          rageHtml = `
            <div class="slot-status status-free" style="display:flex; justify-content:space-between; align-items:center;">
              <span>💥 Laisva</span>
              <form method="POST" style="margin:0; display:inline;">
                <input type="hidden" name="action" value="toggle_slot_block">
                <input type="hidden" name="b_date" value="${iso}">
                <input type="hidden" name="b_time" value="${hourStr}">
                <input type="hidden" name="b_target" value="rage">
                <input type="hidden" name="b_block" value="1">
                <button type="submit" class="btn btn-danger" style="font-size:0.68rem; padding:2px 6px;" title="Uždaryti pykčio kambarį (Nedirbame)">🔒 Uždaryti</button>
              </form>
            </div>
          `;
        }

        // Kirvių takelių langelis
        let axeHtml = '';
        if (isAxe1Blocked && isAxe2Blocked) {
          axeHtml = `
            <div class="slot-status" style="background:rgba(239,68,68,0.18); color:#fca5a5; border:1px solid rgba(239,68,68,0.4); display:flex; justify-content:space-between; align-items:center;">
              <span>🪓 🔒 Nedirbame (Uždaryta)</span>
              <form method="POST" style="margin:0; display:inline;">
                <input type="hidden" name="action" value="toggle_slot_block">
                <input type="hidden" name="b_date" value="${iso}">
                <input type="hidden" name="b_time" value="${hourStr}">
                <input type="hidden" name="b_target" value="axe">
                <input type="hidden" name="b_block" value="0">
                <button type="submit" class="btn btn-success" style="font-size:0.68rem; padding:2px 6px;" title="Atidaryti kirvių takelius šią valandą">🔓 Atidaryti</button>
              </form>
            </div>
          `;
        } else {
          const axeFull = (axe1Booking && axe2Booking);
          const axeText = axeFull ? 'Užimti abu' :
            (axe1Booking ? (axe1Booking.name.split(' ')[0] + ' | T2 laisvas') :
            (axe2Booking ? ('T1 laisvas | ' + axe2Booking.name.split(' ')[0]) : '2 tak. laisvi'));
          axeHtml = `
            <div class="slot-status ${axe1Booking || axe2Booking ? 'status-booked' : 'status-free'}" style="display:flex; justify-content:space-between; align-items:center;">
              <span style="overflow:hidden; text-overflow:ellipsis;">🪓 ${axeText}</span>
              <form method="POST" style="margin:0; display:inline;">
                <input type="hidden" name="action" value="toggle_slot_block">
                <input type="hidden" name="b_date" value="${iso}">
                <input type="hidden" name="b_time" value="${hourStr}">
                <input type="hidden" name="b_target" value="axe">
                <input type="hidden" name="b_block" value="1">
                <button type="submit" class="btn btn-danger" style="font-size:0.68rem; padding:2px 6px;" title="Uždaryti kirvių takelius (Nedirbame)">🔒 Uždaryti</button>
              </form>
            </div>
          `;
        }

        // Visos valandos bendras mygtukas
        let hourBtn = '';
        if (isWholeHourBlocked) {
          hourBtn = `
            <form method="POST" style="margin:0; display:inline;">
              <input type="hidden" name="action" value="toggle_slot_block">
              <input type="hidden" name="b_date" value="${iso}">
              <input type="hidden" name="b_time" value="${hourStr}">
              <input type="hidden" name="b_target" value="all">
              <input type="hidden" name="b_block" value="0">
              <button type="submit" class="btn btn-success" style="font-size:0.75rem; padding:0.3rem 0.65rem;" title="Atidaryti visas pramogas šią valandą">
                🔓 Atidaryti
              </button>
            </form>
          `;
        } else {
          hourBtn = `
            <form method="POST" style="margin:0; display:inline;">
              <input type="hidden" name="action" value="toggle_slot_block">
              <input type="hidden" name="b_date" value="${iso}">
              <input type="hidden" name="b_time" value="${hourStr}">
              <input type="hidden" name="b_target" value="all">
              <input type="hidden" name="b_block" value="1">
              <button type="submit" class="btn btn-danger" style="font-size:0.75rem; padding:0.3rem 0.65rem;" title="Uždaryti visą šią valandą (Nedirbame)">
                🔒 Nedirbame
              </button>
            </form>
          `;
        }

        const row = document.createElement('div');
        row.className = 'hour-row';
        row.innerHTML = `
          <div class="hour-time">${hourStr}</div>
          ${rageHtml}
          ${axeHtml}
          <div>${hourBtn}</div>
        `;
        hourlyEl.appendChild(row);
      }

      // Dienos sąrašas: atskiriame klientų rezervacijas nuo uždarytų laikų
      listEl.innerHTML = '';
      if (dayBookings.length === 0) {
        listEl.innerHTML = `<div style="color:var(--muted); font-size:0.85rem; padding:1rem; text-align:center;">Šią dieną rezervacijų ar uždarytų laikų nėra. Visi laikai laisvi!</div>`;
      } else {
        let clientHtml = '';
        if (clientBookings.length === 0) {
          clientHtml = `<div style="color:var(--muted); font-size:0.85rem; padding:0.6rem; background:rgba(255,255,255,0.02); border-radius:6px; margin-bottom:0.75rem;">Klientų rezervacijų šią dieną nėra.</div>`;
        } else {
          clientBookings.forEach(r => {
            clientHtml += `
              <div class="res-card-item">
                <div class="res-card-top">
                  <div>
                    <strong style="font-size:1rem; color:#fff;">${r.name}</strong>
                    <div style="font-size:0.85rem; color:var(--accent); font-weight:700; margin-top:2px;">
                      🕒 ${r.time} val. • <code>${r.id}</code>
                    </div>
                  </div>
                  <span class="badge ${r.status === 'Įvykusi' ? 'badge-success' : (r.status === 'Atšaukta' ? 'badge-canceled' : 'badge-pending')}">
                    ${r.status}
                  </span>
                </div>
                
                <div style="font-size:0.85rem; color:var(--muted); line-height:1.6; margin-bottom:0.75rem;">
                  • <strong>Pramoga:</strong> ${r.activity} ${r.lane ? '(' + r.lane + ')' : ''} (${r.count || 1} asm., ${r.activity.includes('KOMBO') ? '2 val. sesija' : '1 val.'})<br>
                  • <strong>Kontaktai:</strong> <a href="tel:${r.phone}" style="color:#34d399; font-weight:bold; text-decoration:none;">📞 ${r.phone}</a> | <a href="mailto:${r.email}" style="color:#60a5fa; text-decoration:none;">✉️ ${r.email}</a><br>
                  • <strong>Mokėtina suma:</strong> <strong style="color:var(--accent);">${r.price}</strong>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border); padding-top:0.6rem; flex-wrap:wrap; gap:0.4rem;">
                  <div>
                    <button type="button" class="btn btn-secondary" style="font-size:0.75rem; padding:0.25rem 0.6rem; margin-right:4px;" onclick='openEditModal(${JSON.stringify(r)})'>
                      ✏️ Redaguoti
                    </button>
                    <form method="POST" style="display:inline-block;">
                      <input type="hidden" name="action" value="update_res_status">
                      <input type="hidden" name="redirect_tab" value="kalendorius">
                      <input type="hidden" name="redirect_date" value="${iso}">
                      <input type="hidden" name="res_id" value="${r.id}">
                      <select name="new_status" onchange="this.form.submit()" style="background:#090a0f; color:#fff; border:1px solid var(--border); padding:0.25rem 0.5rem; border-radius:6px; font-size:0.8rem;">
                        <option value="Gauta" ${r.status === 'Gauta' ? 'selected' : ''}>Gauta</option>
                        <option value="Patvirtinta" ${r.status === 'Patvirtinta' ? 'selected' : ''}>Patvirtinta</option>
                        <option value="Įvykusi" ${r.status === 'Įvykusi' ? 'selected' : ''}>Įvykusi</option>
                        <option value="Atšaukta" ${r.status === 'Atšaukta' ? 'selected' : ''}>Atšaukta (atlaisvina laiką)</option>
                      </select>
                    </form>
                  </div>

                  <form method="POST" style="display:inline-block;" onsubmit="return confirm('Ar tikrai norite visam laikui ištrinti šią rezervaciją?')">
                    <input type="hidden" name="action" value="delete_res">
                    <input type="hidden" name="redirect_tab" value="kalendorius">
                    <input type="hidden" name="redirect_date" value="${iso}">
                    <input type="hidden" name="del_id" value="${r.id}">
                    <button type="submit" style="background:none; border:none; color:#ef4444; font-size:0.85rem; cursor:pointer;" title="Ištrinti rezervaciją">🗑️ Ištrinti</button>
                  </form>
                </div>
              </div>
            `;
          });
        }

        let blocksHtml = '';
        if (blocks.length > 0) {
          blocks.forEach(r => {
            blocksHtml += `
              <div class="res-card-item" style="border-left: 3px solid #ef4444; background: rgba(239, 68, 68, 0.06); margin-bottom: 0.6rem;">
                <div class="res-card-top">
                  <div>
                    <strong style="font-size:0.95rem; color:#fca5a5;">🔒 Nedirbame (Uždarytas laikas)</strong>
                    <div style="font-size:0.85rem; color:var(--accent); font-weight:700; margin-top:2px;">
                      🕒 ${r.time} val. • <code>${r.id}</code>
                    </div>
                  </div>
                  <span class="badge badge-canceled">Nedirbame</span>
                </div>
                <div style="font-size:0.85rem; color:var(--muted); line-height:1.5; margin-bottom:0.5rem;">
                  • <strong>Pramoga:</strong> ${r.activity} ${r.lane ? '(' + r.lane + ')' : ''}<br>
                  • <strong>Pastaba:</strong> ${r.note || 'Rankiniu būdu uždarytas administratoriaus'}
                </div>
                <div style="display:flex; justify-content:flex-end; border-top:1px solid var(--border); padding-top:0.5rem;">
                  <form method="POST" style="display:inline-block; margin:0;">
                    <input type="hidden" name="action" value="delete_res">
                    <input type="hidden" name="redirect_tab" value="kalendorius">
                    <input type="hidden" name="redirect_date" value="${iso}">
                    <input type="hidden" name="del_id" value="${r.id}">
                    <button type="submit" class="btn btn-success" style="font-size:0.8rem; padding:0.3rem 0.75rem;">
                      🔓 Atidaryti laiką (Pašalinti bloką)
                    </button>
                  </form>
                </div>
              </div>
            `;
          });
        }

        listEl.innerHTML = `
          <div style="margin-bottom:0.6rem; font-weight:600; font-size:0.88rem; color:#fff;">📋 Klientų rezervacijos (${clientBookings.length}):</div>
          ${clientHtml}
          ${blocks.length > 0 ? `
            <div style="margin:1.2rem 0 0.6rem 0; font-weight:600; font-size:0.88rem; color:#fca5a5;">🔒 Uždaryti laikai / Nedirbame (${blocks.length} val.):</div>
            ${blocksHtml}
          ` : ''}
        `;
      }
    }

    // Modalinis langas: Užsakymas telefonu
    function openManualModal() {
      document.getElementById('m_iso_date').value = selectedIsoDate;
      populateManualTimes();
      document.getElementById('manualModal').style.display = 'flex';
    }
    function closeManualModal() {
      document.getElementById('manualModal').style.display = 'none';
    }

    function updateManualLaneSelect() {
      const act = document.getElementById('m_activity').value;
      const laneWrap = document.getElementById('m_lane_wrapper');
      const prcInput = document.getElementById('m_price');
      laneWrap.style.display = (act.includes('Kirvių') || act.includes('KOMBO')) ? 'block' : 'none';
      if (act.includes('KOMBO')) prcInput.value = '50 €';
      else prcInput.value = '30 €';
      populateManualTimes();
    }

    function populateManualTimes() {
      const timeSelect = document.getElementById('m_time');
      if (!timeSelect) return;
      timeSelect.innerHTML = '';
      const d = new Date(selectedIsoDate + 'T00:00:00');
      const isKombo = (document.getElementById('m_activity')?.value || '').includes('KOMBO');

      const dayOfWeek = d.getDay();
      let startH = 16;
      let closingH = 23;
      if (dayOfWeek === 0) {
        // Sekmadienis: 09:00 - 20:00
        startH = 9;
        closingH = 20;
      } else if (dayOfWeek === 5 || dayOfWeek === 6) {
        // Penktadienis - Šeštadienis: 09:00 - 24:00
        startH = 9;
        closingH = 24;
      }
      let endH = isKombo ? (closingH - 2) : (closingH - 1);

      for (let h = startH; h <= endH; h++) {
        const hourStr = (h < 10 ? '0' : '') + h + ':00';
        const hEnd = isKombo ? (h + 2) : (h + 1);
        const endStr = (hEnd < 10 ? '0' : '') + hEnd + ':00';
        const opt = document.createElement('option');
        opt.value = hourStr;
        opt.textContent = isKombo ? `${hourStr} - ${endStr} val. (2 val.)` : `${hourStr} - ${endStr} val.`;
        timeSelect.appendChild(opt);
      }
    }

    // Modalinis langas: Rezervacijos redagavimas
    function openEditModal(r) {
      document.getElementById('e_id').value = r.id || '';
      document.getElementById('e_name').value = r.name || '';
      document.getElementById('e_phone').value = r.phone || '';
      document.getElementById('e_email').value = r.email || '';
      
      const act = r.activity || '';
      const actSelect = document.getElementById('e_activity');
      if (act.includes('Pykčio')) actSelect.value = 'Pykčio kambarys';
      else if (act.includes('Kirvių')) actSelect.value = 'Kirvių mėtymas';
      else if (act.includes('KOMBO')) actSelect.value = 'KOMBO paketas';
      else actSelect.value = 'Uždarytas laikas (Visos pramogos)';

      document.getElementById('e_lane').value = r.lane || '';
      document.getElementById('e_count').value = r.count || 1;
      document.getElementById('e_date').value = r.iso_date || r.date || selectedIsoDate;
      document.getElementById('e_time').value = r.time || '';
      document.getElementById('e_price').value = r.price || '';
      document.getElementById('e_status').value = r.status || 'Patvirtinta';
      document.getElementById('e_redirect_date').value = selectedIsoDate;

      updateEditLaneVisibility();
      document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    function updateEditLaneVisibility() {
      const act = document.getElementById('e_activity').value;
      const box = document.getElementById('e_lane_box');
      if (box) {
        box.style.display = (act.includes('Kirvių') || act.includes('KOMBO')) ? 'block' : 'none';
      }
    }

    // Inicializacija
    if (document.getElementById('adminCalGrid')) {
      renderAdminCalendar();
      renderDaySchedule(selectedIsoDate);
    }
  </script>
<?php endif; ?>

</body>
</html>
