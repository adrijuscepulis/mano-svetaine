<?php
// test_mail.php - El. pašto siuntimo ir SMTP diagnostikos įrankis „ThrowHouse Šiauliai“
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/email_helper.php';

$cfg = loadEmailConfig();
$resultMsg = '';
$resultType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_cfg') {
        $cfg['smtp_enabled'] = !empty($_POST['smtp_enabled']);
        $cfg['smtp_host'] = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
        $cfg['smtp_port'] = intval($_POST['smtp_port'] ?? 465);
        $cfg['smtp_user'] = trim($_POST['smtp_user'] ?? 'throwhousee@gmail.com');
        if (isset($_POST['smtp_pass']) && trim($_POST['smtp_pass']) !== '') {
            $cfg['smtp_pass'] = trim($_POST['smtp_pass']);
        }
        $cfg['admin_email'] = trim($_POST['admin_email'] ?? 'throwhousee@gmail.com');
        saveEmailConfig($cfg);
        $resultMsg = "Nustatymai sėkmingai išsaugoti!";
        $resultType = 'success';
    } elseif ($_POST['action'] === 'send_test') {
        $testTo = trim($_POST['test_to'] ?? $cfg['admin_email'] ?? 'throwhousee@gmail.com');
        $subject = "Bandomasis laiškas iš ThrowHouse Šiauliai (" . date('H:i:s') . ")";
        
        $testHtml = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif; background:#0b0f19; padding:20px; color:#fff;">
          <div style="max-width:550px; margin:0 auto; background:#18102a; border:2px solid #ff6a00; border-radius:12px; padding:25px; text-align:center;">
            <h1 style="color:#ff6a00; margin:0 0 10px 0;">THROW HOUSE ŠIAULIAI</h1>
            <p style="font-size:16px; color:#4ade80; font-weight:bold;">✓ El. pašto sistema veikia sėkmingai!</p>
            <p style="color:#e2e8f0; font-size:14px; line-height:1.5;">
              Šis laiškas patvirtina, kad Jūsų serverio el. pašto siuntimas (SMTP / PHP mail) yra pilnai sukonfigūruotas ir laiškai sėkmingai pasiekia gavėjus.
            </p>
            <div style="background:rgba(255,255,255,0.05); padding:12px; border-radius:8px; margin-top:15px; font-size:13px; color:#94a3b8;">
              Išsiuntimo laikas: ' . date('Y-m-d H:i:s') . '<br>
              Gavėjas: ' . htmlspecialchars($testTo) . '
            </div>
          </div>
        </body></html>';

        if (!empty($cfg['smtp_enabled']) && !empty($cfg['smtp_pass'])) {
            $res = sendSmtpMail(
                $cfg['smtp_host'],
                $cfg['smtp_port'],
                $cfg['smtp_user'],
                $cfg['smtp_pass'],
                $testTo,
                $subject,
                $testHtml,
                'ThrowHouse Šiauliai',
                $cfg['admin_email']
            );
            if ($res['success']) {
                $resultMsg = "✓ SĖKMĖ: Bandomasis laiškas sėkmingai išsiųstas per SMTP į $testTo! Patikrinkite savo pašto dėžutę.";
                $resultType = 'success';
            } else {
                $resultMsg = "⚠️ SMTP KLAIDA: " . htmlspecialchars($res['error'] ?? 'Nežinoma klaida');
                $resultType = 'error';
            }
        } else {
            $sent = sendThrowHouseMail($testTo, $subject, $testHtml);
            if ($sent) {
                $resultMsg = "Bandomasis laiškas išsiųstas per standartinį PHP mail() į $testTo. (Rekomenduojama įvesti SMTP slaptažodį 100% garantijai į Inbox).";
                $resultType = 'success';
            } else {
                $resultMsg = "⚠️ PHP mail() nepavyko išsiųsti. Įveskite SMTP slaptažodį žemiau.";
                $resultType = 'error';
            }
        }
    }
}

// Skaitome žurnalą
$logContent = file_exists(__DIR__ . '/mail_log.txt') ? file_get_contents(__DIR__ . '/mail_log.txt') : 'Žurnalas kol kas tuščias.';
$logLines = array_slice(array_reverse(explode("\n", trim($logContent))), 0, 25);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <title>El. pašto diagnostika – ThrowHouse Šiauliai</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { background: #08050e; color: #e5e7eb; font-family: 'Plus Jakarta Sans', sans-serif; padding: 2rem 1rem; margin: 0; }
    .box { max-width: 750px; margin: 0 auto; background: #130d20; border: 1px solid rgba(255,106,0,0.3); border-radius: 14px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.6); }
    h1 { font-family: 'Outfit', sans-serif; font-size: 1.6rem; color: #ff6a00; margin-top: 0; }
    .form-group { margin-bottom: 1.1rem; }
    label { display: block; font-size: 0.85rem; font-weight: 700; color: #a1a1aa; margin-bottom: 0.35rem; }
    input[type="text"], input[type="password"], input[type="number"], input[type="email"] { width: 100%; box-sizing: border-box; background: #08050e; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; color: #fff; padding: 0.75rem 1rem; font-size: 0.95rem; }
    input:focus { border-color: #ff6a00; outline: none; }
    .btn { background: #ff6a00; color: #000; font-weight: 800; border: none; padding: 0.85rem 1.5rem; border-radius: 8px; cursor: pointer; font-size: 0.95rem; transition: all 0.2s; }
    .btn:hover { background: #ff8533; transform: translateY(-1px); }
    .btn-secondary { background: #27272a; color: #fff; }
    .btn-secondary:hover { background: #3f3f46; }
    .alert { padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.92rem; line-height: 1.5; }
    .alert-success { background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; color: #86efac; }
    .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; }
    .guide-box { background: rgba(255,106,0,0.06); border-left: 4px solid #ff6a00; padding: 1rem 1.25rem; border-radius: 0 8px 8px 0; margin: 1.5rem 0; font-size: 0.88rem; line-height: 1.6; }
    .log-box { background: #050308; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 1rem; font-family: monospace; font-size: 0.8rem; color: #a1a1aa; max-height: 250px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
  </style>
</head>
<body>
  <div class="box">
    <h1>📧 „ThrowHouse Šiauliai“ El. Pašto Diagnostika</h1>
    <p style="color: #94a3b8; font-size: 0.9rem; margin-top: -0.5rem; margin-bottom: 1.5rem;">
      Šis įrankis leidžia tiesiogiai patikrinti ir sukonfigūruoti el. laiškų siuntimą (Gmail / Hostinger SMTP), kad klientai ir administratorius garantuotai gautų reprezentatyvius laiškus.
    </p>

    <?php if ($resultMsg): ?>
      <div class="alert alert-<?= $resultType ?>">
        <?= $resultMsg ?>
      </div>
    <?php endif; ?>

    <!-- TESTAVIMO MYGTUKAS -->
    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem;">
      <h3 style="margin-top:0; font-size:1.1rem; color:#fff;">✉️ Išbandyti siuntimą tiesiogiai:</h3>
      <form method="POST">
        <input type="hidden" name="action" value="send_test">
        <div class="form-group">
          <label>Gavėjo el. paštas (kur siųsti bandomąjį laišką):</label>
          <input type="email" name="test_to" value="<?= htmlspecialchars($cfg['admin_email']) ?>" required>
        </div>
        <button type="submit" class="btn">🚀 Siųsti bandomąjį laišką dabar ➔</button>
      </form>
    </div>

    <!-- NUSTATYMAI -->
    <form method="POST">
      <input type="hidden" name="action" value="save_cfg">
      <h3 style="font-size:1.1rem; color:#fff; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.5rem;">⚙️ SMTP nustatymai (Rekomenduojama Google Gmail):</h3>
      
      <div class="form-group" style="display:flex; align-items:center; gap:0.6rem; margin:1rem 0;">
        <input type="checkbox" id="smtp_enabled" name="smtp_enabled" value="1" <?= !empty($cfg['smtp_enabled']) ? 'checked' : '' ?> style="width:18px; height:18px; accent-color:#ff6a00;">
        <label for="smtp_enabled" style="margin:0; font-size:0.95rem; color:#fff; cursor:pointer;">Aktyvuoti tiesioginį SMTP siuntimą</label>
      </div>

      <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>SMTP Serveris:</label>
          <input type="text" name="smtp_host" value="<?= htmlspecialchars($cfg['smtp_host']) ?>" placeholder="smtp.gmail.com" required>
        </div>
        <div class="form-group">
          <label>Portas (465 SSL / 587 TLS):</label>
          <input type="number" name="smtp_port" value="<?= htmlspecialchars($cfg['smtp_port']) ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>SMTP Vartotojas (El. paštas):</label>
        <input type="text" name="smtp_user" value="<?= htmlspecialchars($cfg['smtp_user']) ?>" required>
      </div>

      <div class="form-group">
        <label>SMTP Slaptažodis (Gmail programos 16-os ženklų slaptažodis):</label>
        <input type="password" name="smtp_pass" value="<?= htmlspecialchars($cfg['smtp_pass']) ?>" placeholder="<?= empty($cfg['smtp_pass']) ? 'Įveskite Gmail programos slaptažodį' : '••••••••••••••••' ?>">
        <small style="color:#71717a; font-size:0.75rem; display:block; margin-top:0.25rem;">
          Jei naudojate Gmail (throwhousee@gmail.com), čia įveskite 16 ženklų „Programos slaptažodį“ (App Password).
        </small>
      </div>

      <div class="form-group">
        <label>Administratoriaus el. paštas (pranešimams apie naujas rezervacijas):</label>
        <input type="email" name="admin_email" value="<?= htmlspecialchars($cfg['admin_email']) ?>" required>
      </div>

      <button type="submit" class="btn btn-secondary" style="width:100%;">💾 Išsaugoti nustatymus</button>
    </form>

    <!-- GIDAS: KAIP GAUTI GMAIL PROGRAMOS SLAPTAŽODĮ -->
    <div class="guide-box">
      <strong style="color:#ffaa00; font-size:0.95rem; display:block; margin-bottom:0.4rem;">🔑 Kaip per 1 minutę gauti Gmail „Programos slaptažodį“ (App Password)?</strong>
      Kad Gmail leistų serveriui siųsti laiškus ir jie <strong>100% pasiektų klientus tiesiai į pagrindinę pašto dėžutę (Inbox)</strong>:
      <ol style="margin: 0.5rem 0 0.5rem 1.2rem; padding: 0;">
        <li>Prisijunkite prie savo <strong>throwhousee@gmail.com</strong> Google paskyros: <a href="https://myaccount.google.com/security" target="_blank" style="color:#ff6a00;">myaccount.google.com/security</a>.</li>
        <li>Skiltyje <em>„Kaip prisijungiate prie Google“</em> įjunkite <strong>„Patvirtinimas dviem veiksmais“ (2-Step Verification)</strong> (jei dar neįjungta).</li>
        <li>Paieškos laukelyje viršuje įveskite <strong>„Programų slaptažodžiai“</strong> (angl. <em>App passwords</em>) arba eikite tiesiai į: <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:#ff6a00;">myaccount.google.com/apppasswords</a>.</li>
        <li>Programos pavadinimui įrašykite pvz. <strong>„ThrowHouse Svetaine“</strong> ir paspauskite <em>„Sukurti“</em>.</li>
        <li>Google sugeneruos <strong>16 geltonų raidžių kodą</strong> (pvz. <code>abcd efgh ijkl mnop</code>). Nukopijuokite jį, įklijuokite į laukelį aukščiau ir paspauskite <em>„Išsaugoti nustatymus“</em>!</li>
      </ol>
      Nuo tos akimirkos visi rezervacijų patvirtinimai, atšaukimai ir kuponai bus siunčiami tiesiai per oficialius Google serverius su 100% garantija!
    </div>

    <!-- ŽURNALAS -->
    <h3 style="font-size:1.1rem; color:#fff; margin-top:2rem;">📋 Paskutiniai siuntimo įrašai (mail_log.txt):</h3>
    <div class="log-box">
      <?php foreach ($logLines as $line): ?>
        <?= htmlspecialchars($line) ?><br>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center; margin-top:1.5rem;">
      <a href="admin.php" style="color:#ff6a00; font-size:0.9rem; text-decoration:none;">➔ Grįžti į valdymo pultą (admin.php)</a>
    </div>
  </div>
</body>
</html>
