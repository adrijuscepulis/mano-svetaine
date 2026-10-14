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

// Jei prisijungęs - apdoroti veiksmus (Statuso keitimas, kupono panaudojimas)
if (!empty($_SESSION['admin_logged'])) {
    $reservations = loadData($resFile);
    $coupons = loadData($kupFile);

    // Keisti rezervacijos statusą
    if (isset($_POST['action']) && $_POST['action'] === 'update_res_status') {
        $resId = $_POST['res_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? 'Patvirtinta';
        foreach ($reservations as &$r) {
            if ($r['id'] === $resId) {
                $r['status'] = $newStatus;
                break;
            }
        }
        saveData($resFile, $reservations);
        header('Location: admin.php?tab=rezervacijos');
        exit;
    }

    // Keisti kupono statusą (Aktyvus / Panaudotas)
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

    // Rankinis kupono sukūrimas iš admin panelės
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
            'expires_at' => date('Y-m-d', strtotime('+12 months')),
            'status' => 'Aktyvus'
        ];
        saveData($kupFile, $coupons);
        header('Location: admin.php?tab=kuponai');
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
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #090a0f;
      --surface: #121520;
      --card: #181d2c;
      --border: rgba(255, 255, 255, 0.08);
      --accent: #f59e0b;
      --accent-flame: #ff6b18;
      --green: #22c55e;
      --red: #ef4444;
      --text: #f8fafc;
      --muted: #94a3b8;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      line-height: 1.5;
    }
    a { color: inherit; text-decoration: none; }
    .header {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 1rem 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .brand { font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; }
    .brand span { color: var(--accent); }
    .btn {
      padding: 0.55rem 1.1rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.85rem;
      cursor: pointer;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.2s;
    }
    .btn-primary { background: linear-gradient(135deg, var(--accent-flame), var(--accent)); color: #000; }
    .btn-secondary { background: rgba(255,255,255,0.08); color: #fff; border: 1px solid var(--border); }
    .btn-danger { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
    .btn-success { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
    .container { max-width: 1300px; margin: 0 auto; padding: 2rem 1.5rem; }

    /* PRISIJUNGIMO LANGAS */
    .login-box {
      max-width: 400px;
      margin: 8rem auto;
      background: var(--surface);
      border: 1px solid var(--border);
      padding: 2.5rem;
      border-radius: 16px;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    }
    .login-box h2 { font-family: 'Outfit', sans-serif; font-size: 1.8rem; margin-bottom: 0.5rem; }
    .login-box p { color: var(--muted); font-size: 0.9rem; margin-bottom: 1.5rem; }
    .form-input {
      width: 100%;
      padding: 0.8rem 1rem;
      background: #090a0f;
      border: 1px solid var(--border);
      border-radius: 8px;
      color: #fff;
      font-size: 1rem;
      margin-bottom: 1rem;
    }

    /* STATS ROW */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.25rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border);
      padding: 1.5rem;
      border-radius: 14px;
      position: relative;
    }
    .stat-label { font-size: 0.8rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-value { font-family: 'Outfit', sans-serif; font-size: 2.2rem; font-weight: 800; color: #fff; margin-top: 0.4rem; }
    .stat-card.accent .stat-value { color: var(--accent); }

    /* TABS */
    .nav-tabs {
      display: flex;
      gap: 0.75rem;
      border-bottom: 1px solid var(--border);
      margin-bottom: 2rem;
      padding-bottom: 0.75rem;
    }
    .tab-btn {
      background: none;
      border: none;
      color: var(--muted);
      font-family: 'Outfit', sans-serif;
      font-size: 1.05rem;
      font-weight: 700;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .tab-btn:hover { color: #fff; }
    .tab-btn.active { background: rgba(245, 158, 11, 0.12); color: var(--accent); }

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
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 700;
    }
    .badge-success { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
    .badge-pending { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
    .badge-canceled { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }

    /* KALENDORIAUS VAIZDAS */
    .cal-layout {
      display: grid;
      grid-template-columns: 1fr 1.3fr;
      gap: 1.5rem;
    }
    .cal-month-box {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.5rem;
    }
    .cal-grid-head {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      text-align: center;
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--muted);
      margin-bottom: 0.5rem;
    }
    .cal-grid-days {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 6px;
    }
    .cal-day-cell {
      aspect-ratio: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.85rem;
      position: relative;
      transition: all 0.2s;
    }
    .cal-day-cell:hover, .cal-day-cell.active {
      border-color: var(--accent);
      background: rgba(245,158,11,0.1);
    }
    .cal-dot {
      width: 6px;
      height: 6px;
      background: var(--accent);
      border-radius: 50%;
      margin-top: 2px;
      box-shadow: 0 0 6px var(--accent);
    }

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
    .slot-item {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.9rem 1.1rem;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 10px;
      margin-bottom: 0.75rem;
    }
    .slot-time { font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 800; color: var(--accent); min-width: 80px; }
    .slot-info strong { display: block; font-size: 0.95rem; color: #fff; }
    .slot-info span { font-size: 0.85rem; color: var(--muted); }

    @media (max-width: 900px) {
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
    <p style="font-size: 0.75rem; color: #64748b; margin-top: 1.5rem;">Standartinis slaptažodis: <code>throw2026</code></p>
  </div>

<?php else: 
  // Skaičiuojama statistika
  $totalRes = count($reservations);
  $totalCoupons = count($coupons);
  $activeCoupons = count(array_filter($coupons, fn($c) => ($c['status'] ?? '') === 'Aktyvus'));
  
  $totalIncome = 0;
  foreach ($reservations as $r) {
    $p = intval(preg_replace('/[^0-9]/', '', $r['price'] ?? '0'));
    $totalIncome += $p;
  }
  foreach ($coupons as $c) {
    $totalIncome += intval($c['amount'] ?? 0);
  }

  $currentTab = $_GET['tab'] ?? 'kalendorius';
?>
  <!-- ADMIN NAVIGACIJA -->
  <header class="header">
    <div class="brand">THROW<span>HOUSE</span> <span style="font-size: 0.8rem; font-weight: 600; color: var(--muted); margin-left: 0.5rem;">VALDYMO PULTAS</span></div>
    <div style="display: flex; gap: 1rem; align-items: center;">
      <a href="index.html" target="_blank" class="btn btn-secondary">Peržiūrėti svetainę ↗</a>
      <a href="admin.php?logout=1" class="btn btn-danger">Atsijungti</a>
    </div>
  </header>

  <div class="container">
    <!-- STATISTIKOS KORTELĖS -->
    <div class="stats-row">
      <div class="stat-card accent">
        <div class="stat-label">Bendra apyvarta</div>
        <div class="stat-value"><?= $totalIncome ?> €</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Visos rezervacijos</div>
        <div class="stat-value"><?= $totalRes ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Aktyvūs dovanų kuponai</div>
        <div class="stat-value" style="color: var(--green);"><?= $activeCoupons ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Viso parduota kuponų</div>
        <div class="stat-value"><?= $totalCoupons ?></div>
      </div>
    </div>

    <!-- SKILČIŲ MYGTUKAI -->
    <div class="nav-tabs">
      <a href="admin.php?tab=kalendorius" class="tab-btn <?= $currentTab === 'kalendorius' ? 'active' : '' ?>">📅 Rezervacijų kalendorius</a>
      <a href="admin.php?tab=rezervacijos" class="tab-btn <?= $currentTab === 'rezervacijos' ? 'active' : '' ?>">📋 Rezervacijų sąrašas (<?= $totalRes ?>)</a>
      <a href="admin.php?tab=kuponai" class="tab-btn <?= $currentTab === 'kuponai' ? 'active' : '' ?>">🎁 Dovanų kuponai (<?= $totalCoupons ?>)</a>
    </div>

    <!-- 1 TAB: KALENDORIUS -->
    <?php if ($currentTab === 'kalendorius'): ?>
      <div class="cal-layout">
        <div class="cal-month-box">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-family: 'Outfit'; font-size: 1.3rem;">Rezervacijų grafikas</h3>
            <span style="font-size: 0.85rem; color: var(--accent); font-weight: 700;">Šiandiena: <?= date('Y-m-d') ?></span>
          </div>

          <p style="font-size: 0.85rem; color: var(--muted); margin-bottom: 1.25rem;">
            Spauskite ant bet kurios rezervacijos dešinėje arba peržiūrėkite užimtus laikus.
          </p>

          <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 10px; border: 1px solid var(--border);">
            <div style="font-size: 0.85rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">🏢 Erdvių talpa:</div>
            <div style="font-size: 0.825rem; color: var(--muted); line-height: 1.6;">
              • <strong>Kirvių mėtymas:</strong> 2 takeliai (iki 2 rezervacijų per valandą)<br>
              • <strong>Pykčio kambarys:</strong> 1 kambarys (1 rezervacija per valandą)<br>
              • <strong>Darbo laikas:</strong> I–IV 16–23 val. | V–VII 09–24 val.
            </div>
          </div>
        </div>

        <div class="day-schedule-card">
          <div class="schedule-header">
            <div>
              <h3 style="font-family: 'Outfit'; font-size: 1.3rem;">Artimiausios rezervacijos</h3>
              <p style="color: var(--muted); font-size: 0.85rem;">Naujausios internetu gautos registracijos</p>
            </div>
            <span class="badge badge-pending"><?= count($reservations) ?> vnt.</span>
          </div>

          <?php if (empty($reservations)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--muted);">
              Šiuo metu naujų rezervacijų dar nėra. Kai klientai užpildys formą svetainėje, jos iškart atsiras čia!
            </div>
          <?php else: ?>
            <?php foreach (array_reverse(array_slice($reservations, -10)) as $r): ?>
              <div class="slot-item">
                <div class="slot-time"><?= htmlspecialchars($r['time'] ?? '') ?></div>
                <div class="slot-info" style="flex: 1;">
                  <strong><?= htmlspecialchars($r['name'] ?? '') ?> (<?= htmlspecialchars($r['phone'] ?? '') ?>)</strong>
                  <span><?= htmlspecialchars($r['activity'] ?? '') ?> <?= !empty($r['lane']) ? '• ' . htmlspecialchars($r['lane']) : '' ?> • <?= htmlspecialchars($r['count'] ?? '1') ?> asm.</span>
                  <div style="font-size: 0.8rem; color: var(--accent); margin-top: 2px;">
                    📅 <?= htmlspecialchars($r['date'] ?? '') ?> • Suma: <strong><?= htmlspecialchars($r['price'] ?? '') ?></strong>
                  </div>
                </div>
                <div>
                  <span class="badge <?= ($r['status'] ?? '') === 'Įvykusi' ? 'badge-success' : 'badge-pending' ?>">
                    <?= htmlspecialchars($r['status'] ?? 'Gauta') ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- 2 TAB: REZERVACIJOS -->
    <?php if ($currentTab === 'rezervacijos'): ?>
      <div class="table-card">
        <div class="table-head">
          <h3>Visos rezervacijos</h3>
          <span style="font-size: 0.85rem; color: var(--muted);">Iš viso: <?= count($reservations) ?></span>
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
              <?php if (empty($reservations)): ?>
                <tr>
                  <td colspan="9" style="text-align: center; padding: 3rem; color: var(--muted);">Rezervacijų sąrašas tuščias.</td>
                </tr>
              <?php else: ?>
                <?php foreach (array_reverse($reservations) as $r): ?>
                  <tr>
                    <td><code><?= htmlspecialchars($r['id'] ?? '') ?></code></td>
                    <td><strong><?= htmlspecialchars($r['date'] ?? '') ?></strong><br><span style="color: var(--accent);"><?= htmlspecialchars($r['time'] ?? '') ?> val.</span></td>
                    <td><strong><?= htmlspecialchars($r['name'] ?? '') ?></strong></td>
                    <td><?= htmlspecialchars($r['phone'] ?? '') ?><br><span style="color: var(--muted); font-size: 0.8rem;"><?= htmlspecialchars($r['email'] ?? '') ?></span></td>
                    <td><?= htmlspecialchars($r['activity'] ?? '') ?> <?= !empty($r['lane']) ? '<br><small style="color:var(--accent);">('.htmlspecialchars($r['lane']).')</small>' : '' ?></td>
                    <td><?= htmlspecialchars($r['count'] ?? 1) ?> asm.</td>
                    <td><strong style="color: var(--accent);"><?= htmlspecialchars($r['price'] ?? '') ?></strong></td>
                    <td>
                      <span class="badge <?= ($r['status'] ?? '') === 'Įvykusi' ? 'badge-success' : (($r['status'] ?? '') === 'Atšaukta' ? 'badge-canceled' : 'badge-pending') ?>">
                        <?= htmlspecialchars($r['status'] ?? 'Gauta') ?>
                      </span>
                    </td>
                    <td>
                      <form method="POST" style="display: inline-block;">
                        <input type="hidden" name="action" value="update_res_status">
                        <input type="hidden" name="res_id" value="<?= htmlspecialchars($r['id'] ?? '') ?>">
                        <select name="new_status" onchange="this.form.submit()" style="background:#090a0f; color:#fff; border:1px solid var(--border); padding:0.3rem 0.5rem; border-radius:6px; font-size:0.8rem; cursor:pointer;">
                          <option value="Gauta" <?= ($r['status'] ?? '') === 'Gauta' ? 'selected' : '' ?>>Gauta</option>
                          <option value="Patvirtinta" <?= ($r['status'] ?? '') === 'Patvirtinta' ? 'selected' : '' ?>>Patvirtinta</option>
                          <option value="Įvykusi" <?= ($r['status'] ?? '') === 'Įvykusi' ? 'selected' : '' ?>>Įvykusi</option>
                          <option value="Atšaukta" <?= ($r['status'] ?? '') === 'Atšaukta' ? 'selected' : '' ?>>Atšaukta</option>
                        </select>
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

    <!-- 3 TAB: DOVANŲ KUPONAI -->
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
                          <br><span style="font-size: 0.8rem; color: var(--muted);">Gavėjas: <?= htmlspecialchars($c['recipient']) ?></span>
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
                <option value="25">25 € (1 pramoga)</option>
                <option value="40">40 € (KOMBO paketas)</option>
                <option value="50">50 € (Dovana)</option>
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

  </div>
<?php endif; ?>

</body>
</html>
