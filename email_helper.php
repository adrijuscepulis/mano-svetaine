<?php
// email_helper.php - Vieninga el. laiškų siuntimo ir šablonų sistema „ThrowHouse Šiauliai“

function getThrowHouseBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'https://';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'violet-dove-675017.hostingersite.com';
    return $protocol . $host;
}

/**
 * Užkrauna el. pašto (SMTP) konfigūraciją
 */
function loadEmailConfig() {
    $configFile = __DIR__ . '/email_config.json';
    $configDbFile = __DIR__ . '/email_config_db.json';

    $default = [
        'smtp_enabled' => true,
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 465,
        'smtp_user' => 'throwhousee@gmail.com',
        'smtp_pass' => '',
        'admin_email' => 'throwhousee@gmail.com'
    ];

    $data = [];
    if (file_exists($configFile)) {
        $data = json_decode(@file_get_contents($configFile), true) ?: [];
    }
    if (empty($data) && file_exists($configDbFile)) {
        $data = json_decode(@file_get_contents($configDbFile), true) ?: [];
    }

    return array_merge($default, $data);
}

/**
 * Išsaugo el. pašto (SMTP) konfigūraciją
 */
function saveEmailConfig($data) {
    $configFile = __DIR__ . '/email_config.json';
    $configDbFile = __DIR__ . '/email_config_db.json';

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    @file_put_contents($configFile, $json, LOCK_EX);
    @file_put_contents($configDbFile, $json, LOCK_EX);
    @chmod($configFile, 0666);
    @chmod($configDbFile, 0666);
    return true;
}

/**
 * Tiesioginis SMTP siuntimas per PHP sockets (be jokių išorinių bibliotekų).
 * Palaiko SSL (portas 465) ir STARTTLS (portas 587).
 */
function sendSmtpMail($host, $port, $user, $pass, $to, $subject, $htmlBody, $fromName = 'ThrowHouse Šiauliai', $replyTo = null) {
    $timeout = 12;
    $isSsl = ($port == 465);
    $transport = $isSsl ? 'ssl://' . $host : 'tcp://' . $host;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['success' => false, 'error' => "Nepavyko prisijungti prie $host:$port ($errstr)"];
    }

    stream_set_timeout($socket, $timeout);

    $readResponse = function($sock) {
        $data = "";
        while ($line = fgets($sock, 515)) {
            $data .= $line;
            if (strlen($line) >= 4 && substr($line, 3, 1) === " ") break;
        }
        return $data;
    };

    $sendCommand = function($sock, $cmd, $expectCode) use ($readResponse) {
        fputs($sock, $cmd . "\r\n");
        $resp = $readResponse($sock);
        $code = substr($resp, 0, 3);
        if ($code != $expectCode) {
            return ['ok' => false, 'code' => $code, 'resp' => trim($resp)];
        }
        return ['ok' => true, 'code' => $code, 'resp' => trim($resp)];
    };

    $greeting = $readResponse($socket);
    if (substr($greeting, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => "Serveris neatsakė 220: " . trim($greeting)];
    }

    $c = $sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
    if (!$c['ok']) {
        $sendCommand($socket, "HELO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
    }

    if ($port == 587) {
        $tls = $sendCommand($socket, "STARTTLS", '220');
        if ($tls['ok']) {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
        }
    }

    $auth = $sendCommand($socket, "AUTH LOGIN", '334');
    if (!$auth['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "AUTH LOGIN nepalaikomas: {$auth['resp']}"];
    }

    $uRes = $sendCommand($socket, base64_encode($user), '334');
    if (!$uRes['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "Vartotojo vardas atmestas: {$uRes['resp']}"];
    }

    $pRes = $sendCommand($socket, base64_encode($pass), '235');
    if (!$pRes['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "Neteisingas slaptažodis arba atmesta autentifikacija: {$pRes['resp']}"];
    }

    $mf = $sendCommand($socket, "MAIL FROM:<$user>", '250');
    if (!$mf['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM klaida: {$mf['resp']}"];
    }

    $rcpt = $sendCommand($socket, "RCPT TO:<$to>", '250');
    if (!$rcpt['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "Gavėjas atmestas ($to): {$rcpt['resp']}"];
    }

    $dRes = $sendCommand($socket, "DATA", '354');
    if (!$dRes['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "DATA klaida: {$dRes['resp']}"];
    }

    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $encodedFromName = "=?UTF-8?B?" . base64_encode($fromName) . "?=";
    $reply = $replyTo ?: $user;

    $headers = "MIME-Version: 1.0\r\n"
        . "From: $encodedFromName <$user>\r\n"
        . "To: <$to>\r\n"
        . "Reply-To: $reply\r\n"
        . "Date: " . date('r') . "\r\n"
        . "Subject: $encodedSubject\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n";

    $payload = $headers . "\r\n" . $htmlBody . "\r\n.";
    fputs($socket, $payload . "\r\n");
    $dataSent = $readResponse($socket);

    $sendCommand($socket, "QUIT", '221');
    fclose($socket);

    if (substr($dataSent, 0, 3) === '250') {
        return ['success' => true];
    }

    return ['success' => false, 'error' => "Klaida siunčiant laišką: " . trim($dataSent)];
}

/**
 * Pagrindinė funkcija: siunčia el. laišką per SMTP (arba per PHP mail)
 */
function sendThrowHouseMail($to, $subject, $htmlBody, $replyTo = 'throwhousee@gmail.com', $fromName = 'ThrowHouse Šiauliai') {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $cfg = loadEmailConfig();

    // 1. Jei įjungtas SMTP ir nurodytas slaptažodis – siunčiame per SMTP (100% patikimumas)
    if (!empty($cfg['smtp_enabled']) && !empty($cfg['smtp_pass'])) {
        $host = $cfg['smtp_host'] ?: 'smtp.gmail.com';
        $port = intval($cfg['smtp_port'] ?: 465);
        $user = $cfg['smtp_user'] ?: 'throwhousee@gmail.com';
        $pass = trim($cfg['smtp_pass']);

        $res = sendSmtpMail($host, $port, $user, $pass, $to, $subject, $htmlBody, $fromName, $replyTo);
        $log = date('Y-m-d H:i:s') . " | SMTP | TO: $to | SUB: $subject | RES: " . ($res['success'] ? 'OK' : 'FAIL: ' . ($res['error'] ?? '')) . "\n";
        @file_put_contents(__DIR__ . '/mail_log.txt', $log, FILE_APPEND);

        if ($res['success']) {
            return true;
        }
    }

    // 2. Atsarginis variantas: standartinis PHP mail() su RFC suderinamomis antraštėmis
    $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'throwhouse.lt';
    $serverHost = preg_replace('/:[0-9]+$/', '', $serverHost);
    $serverHost = preg_replace('/^www\./i', '', $serverHost);

    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $encodedFromName = "=?UTF-8?B?" . base64_encode($fromName) . "?=";
    $fromEmail = "noreply@" . $serverHost;

    $headers = "MIME-Version: 1.0\r\n"
        . "From: $encodedFromName <$fromEmail>\r\n"
        . "Reply-To: $replyTo\r\n"
        . "Date: " . date('r') . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n";

    $sent = @mail($to, $encodedSubject, $htmlBody, $headers);
    if (!$sent) {
        $sent = @mail($to, $encodedSubject, $htmlBody, $headers, "-f" . $fromEmail);
    }

    $logMsg = date('Y-m-d H:i:s') . " | PHP_MAIL | TO: $to | SUB: $subject | RES: " . ($sent ? 'OK' : 'FAIL') . "\n";
    @file_put_contents(__DIR__ . '/mail_log.txt', $logMsg, FILE_APPEND);

    return $sent;
}

// =========================================================================
// ŠABLONAI: REZERVACIJOS
// =========================================================================

/**
 * 1. Naujos rezervacijos patvirtinimas klientui
 */
function renderNewReservationClientHtml($res) {
    $id = htmlspecialchars($res['id'] ?? '');
    $name = htmlspecialchars($res['name'] ?? '');
    $activity = htmlspecialchars($res['activity'] ?? '');
    $lane = htmlspecialchars($res['lane'] ?? '');
    $count = htmlspecialchars($res['count'] ?? 1);
    $date = htmlspecialchars($res['date'] ?? '');
    $time = htmlspecialchars($res['time'] ?? '');
    $payMethod = htmlspecialchars($res['payment'] ?? 'Elektroninė bankininkystė');
    $price = htmlspecialchars($res['price'] ?? '');
    $isKombo = (stripos($activity, 'KOMBO') !== false);
    $durationLabel = $isKombo ? "2 val. sesija (Kirviai + Pykčio kambarys)" : "1 val. sesija";

    return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 12px 35px rgba(0,0,0,0.25);">
    
    <!-- HEADER -->
    <div style="background-color: #0f172a; color: #ffffff; padding: 26px 20px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 22px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Rezervacijos patvirtinimas</p>
      <div style="display:inline-block; background-color: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.4); padding: 5px 14px; border-radius: 20px; font-weight: bold; font-size: 13px; margin-top: 10px;">
        Užsakymo Nr. ' . $id . '
      </div>
    </div>

    <div style="padding: 24px; line-height: 1.6; font-size: 15px; color: #334155;">
      <p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $name . '</strong>!</p>
      <p>Nuoširdžiai dėkojame už Jūsų rezervaciją! Užsakymas sėkmingai priimtas ir patvirtintas.</p>
      
      <!-- DETALĖS -->
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin: 18px 0;">
        <span style="font-weight: 800; color: #0f172a; margin-bottom: 10px; font-size: 15px; display: block;">📋 REZERVACIJOS DUOMENYS:</span>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
          <tr><td style="padding: 6px 0; color: #64748b; width: 40%;">Užsakymo numeris:</td><td style="font-weight: 700; color: #d97706;">' . $id . '</td></tr>
          <tr><td style="padding: 6px 0; color: #64748b;">Pramoga:</td><td style="font-weight: 700; color: #0f172a;">' . $activity . ($lane ? " ($lane)" : "") . '</td></tr>
          <tr><td style="padding: 6px 0; color: #64748b;">Trukmė:</td><td style="font-weight: 700; color: #d97706;">' . $durationLabel . '</td></tr>
          <tr><td style="padding: 6px 0; color: #64748b;">Dalyvių skaičius:</td><td style="font-weight: 700; color: #0f172a;">' . $count . ' asm.</td></tr>
          <tr><td style="padding: 6px 0; color: #64748b;">Data ir laikas:</td><td style="font-weight: 800; color: #0f172a; font-size: 15px;">🕒 ' . $date . ' d., ' . $time . ' val.</td></tr>
          <tr><td style="padding: 6px 0; color: #64748b;">Apmokėjimo būdas:</td><td style="font-weight: 700; color: #0f172a;">' . $payMethod . '</td></tr>
          <tr><td style="padding: 6px 0; color: #64748b;">Suma:</td><td style="font-weight: 800; font-size: 16px; color: #059669;">' . $price . ' <span style="font-size: 12px; font-weight: normal; color: #10b981;">(Apmokėta internetu)</span></td></tr>
        </table>
      </div>

      <!-- LOKACIJA -->
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin: 18px 0;">
        <span style="font-weight: 800; color: #0f172a; margin-bottom: 8px; font-size: 15px; display: block;">📍 KUR MUS RASTI:</span>
        <p style="margin: 0 0 14px 0; font-size: 14px; line-height: 1.5;">
          Adresas: <a href="https://maps.google.com/?q=55.910730,23.315700" target="_blank" rel="noopener noreferrer" style="color: #0f172a; font-weight: bold; text-decoration: underline;">Pramonės g. 15, Šiauliai (2-as aukštas)</a><br>
          Koordinatės: <strong>55.910730, 23.315700</strong>
        </p>
        
        <!-- NAVIGACIJOS MYGTUKAI (BULLETPROOF EMAIL BUTTONS) -->
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-top: 8px;">
          <tr>
            <td align="center" style="padding: 6px; width: 50%;">
              <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td align="center" bgcolor="#2563eb" style="border-radius: 8px;">
                    <a href="https://maps.google.com/?q=55.910730,23.315700" target="_blank" rel="noopener noreferrer" style="display: block; width: 100%; box-sizing: border-box; background-color: #2563eb; color: #ffffff !important; font-size: 14px; font-weight: bold; text-decoration: none; padding: 12px 14px; border-radius: 8px; text-align: center; cursor: pointer; border: 1px solid #2563eb;">
                      🗺️ Google Maps žemėlapis
                    </a>
                  </td>
                </tr>
              </table>
            </td>
            <td align="center" style="padding: 6px; width: 50%;">
              <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td align="center" bgcolor="#0284c7" style="border-radius: 8px;">
                    <a href="https://www.waze.com/ul?ll=55.910730,23.315700&navigate=yes" target="_blank" rel="noopener noreferrer" style="display: block; width: 100%; box-sizing: border-box; background-color: #0284c7; color: #ffffff !important; font-size: 14px; font-weight: bold; text-decoration: none; padding: 12px 14px; border-radius: 8px; text-align: center; cursor: pointer; border: 1px solid #0284c7;">
                      🚗 Waze navigacija
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <p style="margin: 12px 0 0 0; text-align: center; font-size: 13px; color: #64748b;">
          Tiesioginė nuoroda: <a href="https://maps.google.com/?q=55.910730,23.315700" target="_blank" rel="noopener noreferrer" style="color: #2563eb; font-weight: bold; text-decoration: underline;">Atidaryti Pramonės g. 15 per Google Maps ➔</a>
        </p>
      </div>

      <!-- INSTRUKCIJA: ĮĖJIMAS -->
      <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #92400e; display: block; margin-bottom: 4px;">🚪 PATEKIMAS Į PATALPAS:</strong>
        <p style="margin: 0; color: #78350f; font-size: 14px;">
          Atvykus prie pastato (Pramonės g. 15), maloniai prašome užeiti pro duris, pažymėtas iškaba <strong>„Floristika“</strong>. Patekus į pastato vidų, eikite pro pirmąsias duris kairėje pusėje ir laiptais pakilkite į antrąjį aukštą – čia Jūsų lauks „ThrowHouse“ erdvė.
        </p>
      </div>

      <!-- INSTRUKCIJA: AVALYNĖ -->
      <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #065f46; display: block; margin-bottom: 4px;">👟 SVARBU – AVALYNĖ IR APRANGA:</strong>
        <p style="margin: 0; color: #047857; font-size: 14px;">
          Saugumui užtikrinti visiems dalyviams <strong>būtina visiškai uždara avalynė</strong> (sportbačiai ar auliniai batai). Su atvira avalyne (šlepetėmis, basutėmis) dalyvauti griežtai draudžiama. Visą apsauginę aprangą (kombinezonus, apsauginius šalmus su skydeliais, pirštines) suteikiame vietoje nemokamai!
        </p>
      </div>

      <!-- 24H TAISYKLĖ -->
      <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #991b1b; display: block; margin-bottom: 4px;">⏱️ REZERVACIJOS ATŠAUKIMAS (LIKUS 24 VAL.):</strong>
        <p style="margin: 0; color: #b91c1c; font-size: 14px;">
          Jei pasikeistų Jūsų planai, maloniai prašome apie rezervacijos atšaukimą arba laiko pakeitimą informuoti <strong>likus ne mažiau kaip 24 valandoms</strong> iki sesijos pradžios.
        </p>
      </div>

      <p style="margin-top: 16px; font-size: 14px; color: #475569;">
        Prašome atvykti likus <strong>5–10 min.</strong> iki rezervuoto laiko trumpam saugumo instruktažui.
      </p>

      <div style="margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 16px; font-size: 14px;">
        <strong>Kilus bet kokiems klausimams:</strong><br>
        📞 Tel.: <a href="tel:+37061988009" style="color: #d97706; font-weight: 700; text-decoration:none;">+370 619 88009</a><br>
        ✉️ El. paštas: <a href="mailto:throwhousee@gmail.com" style="color: #d97706; text-decoration:none;">throwhousee@gmail.com</a>
      </div>
    </div>

    <!-- FOOTER -->
    <div style="background-color: #f1f5f9; padding: 16px 20px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0;">
      Iki malonaus susitikimo!<br>
      <strong>„ThrowHouse Šiauliai“ komanda</strong> · Pramonės g. 15, Šiauliai (2-as a.)
    </div>
  </div>
</body>
</html>';
}

/**
 * 2. Pranešimas administratoriui apie naują rezervaciją
 */
function renderNewReservationAdminHtml($res) {
    $id = htmlspecialchars($res['id'] ?? '');
    $name = htmlspecialchars($res['name'] ?? '');
    $phone = htmlspecialchars($res['phone'] ?? '');
    $email = htmlspecialchars($res['email'] ?? '');
    $activity = htmlspecialchars($res['activity'] ?? '');
    $lane = htmlspecialchars($res['lane'] ?? '');
    $count = htmlspecialchars($res['count'] ?? 1);
    $date = htmlspecialchars($res['date'] ?? '');
    $time = htmlspecialchars($res['time'] ?? '');
    $payMethod = htmlspecialchars($res['payment'] ?? 'Elektroninė bankininkystė');
    $price = htmlspecialchars($res['price'] ?? '');
    $baseUrl = getThrowHouseBaseUrl();

    return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 12px 35px rgba(0,0,0,0.25);">
    
    <div style="background-color: #0f172a; color: #ffffff; padding: 24px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8;">Gauta nauja rezervacija sistemoje</p>
      <div style="display:inline-block; background-color:rgba(245, 158, 11, 0.15); color:#b45309; border:1px solid rgba(245, 158, 11, 0.4); padding:4px 12px; border-radius:20px; font-weight:bold; font-size:13px; margin-top:8px;">
        Užsakymo Nr. ' . $id . '
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
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-weight: bold;">' . $count . ' asm.</td>
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
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;">Apmokėjimas:</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-weight: bold;">' . $payMethod . '</td>
          </tr>
          <tr>
            <td style="padding: 8px 0; color: #64748b;">Suma:</td>
            <td style="padding: 8px 0; color: #059669; font-weight: bold; font-size: 16px;">' . $price . ' <span style="font-size:12px; font-weight:normal; color:#10b981;">(Apmokėta internetu)</span></td>
          </tr>
        </table>
      </div>

      <div style="text-align: center; margin: 26px 0 12px 0;">
        <a href="' . $baseUrl . '/admin.php?tab=kalendorius" style="display:inline-block; background-color:#0f172a; color:#ffffff; font-weight:bold; text-decoration:none; padding:12px 24px; border-radius:8px; border:1px solid #334155; font-size:14px;" target="_blank">
          ⚙️ Atidaryti valdymo pultą (admin.php) ➔
        </a>
      </div>

    </div>
    <div style="background-color: #f8fafc; padding: 14px 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
      ThrowHouse Šiauliai · Automatinis pranešimas administratoriams · ' . date('Y-m-d H:i') . '
    </div>
  </div>
</body>
</html>';
}

/**
 * 3. Pranešimas klientui apie ATŠAUKTĄ rezervaciją
 */
function renderCancelledReservationClientHtml($res) {
    $id = htmlspecialchars($res['id'] ?? '');
    $name = htmlspecialchars($res['name'] ?? '');
    $activity = htmlspecialchars($res['activity'] ?? '');
    $date = htmlspecialchars($res['date'] ?? '');
    $time = htmlspecialchars($res['time'] ?? '');
    $baseUrl = getThrowHouseBaseUrl();

    return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 12px 35px rgba(0,0,0,0.25);">
    
    <!-- HEADER -->
    <div style="background-color: #0f172a; color: #ffffff; padding: 26px 20px; text-align: center; border-bottom: 3px solid #ef4444;">
      <h1 style="margin: 0 0 6px 0; font-size: 22px; color: #f87171; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Rezervacijos atšaukimas</p>
      <div style="display:inline-block; background-color: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 5px 14px; border-radius: 20px; font-weight: bold; font-size: 13px; margin-top: 10px;">
        ✕ Rezervacija atšaukta
      </div>
    </div>

    <div style="padding: 24px; line-height: 1.6; font-size: 15px; color: #334155;">
      <p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $name . '</strong>!</p>
      <p>Informuojame, kad Jūsų rezervacija pramogų erdvėje <strong>„ThrowHouse Šiauliai“</strong> buvo <strong>atšaukta</strong>.</p>
      
      <!-- DETALĖS -->
      <div style="background-color: #fef2f2; border: 1px solid #fee2e2; border-left: 4px solid #ef4444; border-radius: 0 10px 10px 0; padding: 16px; margin: 18px 0;">
        <span style="font-weight: 800; color: #991b1b; margin-bottom: 8px; font-size: 15px; display: block;">📋 ATŠAUKTOS REZERVACIJOS DUOMENYS:</span>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
          <tr><td style="padding: 5px 0; color: #7f1d1d; width: 40%;">Užsakymo Nr.:</td><td style="font-weight: 700; color: #0f172a;">' . $id . '</td></tr>
          <tr><td style="padding: 5px 0; color: #7f1d1d;">Pramoga:</td><td style="font-weight: 700; color: #0f172a;">' . $activity . '</td></tr>
          <tr><td style="padding: 5px 0; color: #7f1d1d;">Buvęs laikas:</td><td style="font-weight: 700; color: #0f172a;">' . $date . ' d., ' . $time . ' val.</td></tr>
          <tr><td style="padding: 5px 0; color: #7f1d1d;">Būsena:</td><td style="font-weight: 800; color: #ef4444;">Atšaukta</td></tr>
        </table>
      </div>

      <p style="font-size: 14px; color: #475569;">
        Jeigu rezervaciją atšaukėte patys arba norėtumėte pasirinkti kitą apsilankymo datą bei laiką, maloniai kviečiame apsilankyti mūsų svetainėje ir išsirinkti naują patogų laiką:
      </p>

      <div style="text-align: center; margin: 24px 0 16px 0;">
        <a href="' . $baseUrl . '/#rezervacija" style="display:inline-block; background-color: #f59e0b; color: #08050e; font-weight: 800; text-decoration: none; padding: 13px 26px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);" target="_blank">
          📅 Rezervuoti naują laiką svetainėje ➔
        </a>
      </div>

      <div style="margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 16px; font-size: 14px;">
        <strong>Turite klausimų ar norite pasikonsultuoti?</strong><br>
        📞 Tel.: <a href="tel:+37061988009" style="color: #d97706; font-weight: 700; text-decoration:none;">+370 619 88009</a><br>
        ✉️ El. paštas: <a href="mailto:throwhousee@gmail.com" style="color: #d97706; text-decoration:none;">throwhousee@gmail.com</a>
      </div>
    </div>

    <!-- FOOTER -->
    <div style="background-color: #f1f5f9; padding: 16px 20px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0;">
      Pagarbiai,<br>
      <strong>„ThrowHouse Šiauliai“ komanda</strong> · Pramonės g. 15, Šiauliai
    </div>
  </div>
</body>
</html>';
}

/**
 * 4. Pranešimas klientui apie PAKEISTĄ rezervacijos laiką
 */
function renderRescheduledReservationClientHtml($res, $oldDate = '', $oldTime = '') {
    $id = htmlspecialchars($res['id'] ?? '');
    $name = htmlspecialchars($res['name'] ?? '');
    $activity = htmlspecialchars($res['activity'] ?? '');
    $lane = htmlspecialchars($res['lane'] ?? '');
    $count = htmlspecialchars($res['count'] ?? 1);
    $newDate = htmlspecialchars($res['date'] ?? '');
    $newTime = htmlspecialchars($res['time'] ?? '');
    $isKombo = (stripos($activity, 'KOMBO') !== false);
    $durationLabel = $isKombo ? "2 val. sesija (Kirviai + Pykčio kambarys)" : "1 val. sesija";

    return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 12px 35px rgba(0,0,0,0.25);">
    
    <!-- HEADER -->
    <div style="background-color: #0f172a; color: #ffffff; padding: 26px 20px; text-align: center; border-bottom: 3px solid #3b82f6;">
      <h1 style="margin: 0 0 6px 0; font-size: 22px; color: #60a5fa; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Rezervacijos laiko pakeitimas</p>
      <div style="display:inline-block; background-color: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); padding: 5px 14px; border-radius: 20px; font-weight: bold; font-size: 13px; margin-top: 10px;">
        🕒 Laikas sėkmingai atnaujintas
      </div>
    </div>

    <div style="padding: 24px; line-height: 1.6; font-size: 15px; color: #334155;">
      <p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $name . '</strong>!</p>
      <p>Informuojame, kad Jūsų rezervacijos laikas „ThrowHouse Šiauliai“ pramogų erdvėje buvo <strong>sėkmingai atnaujintas</strong>.</p>
      
      <!-- NAUJAS LAIKAS (RYŠKUS AKCENTAS) -->
      <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #ffffff; border-radius: 12px; padding: 20px; margin: 18px 0; border: 1px solid #334155; text-align: center;">
        <span style="font-size: 12px; color: #f59e0b; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; display: block; margin-bottom: 6px;">NAUJAS REZERVACIJOS LAIKAS:</span>
        <div style="font-size: 24px; font-weight: 900; color: #fbbf24; margin-bottom: 4px;">
          📅 ' . $newDate . ' d.
        </div>
        <div style="font-size: 22px; font-weight: 800; color: #ffffff;">
          🕒 ' . $newTime . ' val.
        </div>
        ' . ($oldDate || $oldTime ? '
        <div style="font-size: 12px; color: #94a3b8; margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 8px;">
          (Buvęs laikas: ' . htmlspecialchars($oldDate) . ' d., ' . htmlspecialchars($oldTime) . ' val.)
        </div>' : '') . '
      </div>

      <!-- DETALĖS -->
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin: 18px 0;">
        <span style="font-weight: 800; color: #0f172a; margin-bottom: 10px; font-size: 15px; display: block;">📋 REZERVACIJOS DUOMENYS:</span>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
          <tr><td style="padding: 6px 0; color: #64748b; width: 40%;">Užsakymo Nr.:</td><td style="font-weight: 700; color: #d97706;">' . $id . '</td></tr>
          <tr><td style="padding: 6px 0; color: #64748b;">Pramoga:</td><td style="font-weight: 700; color: #0f172a;">' . $activity . ($lane ? " ($lane)" : "") . '</td></tr>
          <tr><td style="padding: 6px 0; color: #64748b;">Trukmė:</td><td style="font-weight: 700; color: #d97706;">' . $durationLabel . '</td></tr>
          <tr><td style="padding: 6px 0; color: #64748b;">Dalyvių skaičius:</td><td style="font-weight: 700; color: #0f172a;">' . $count . ' asm.</td></tr>
        </table>
      </div>

      <!-- INSTRUKCIJA: ĮĖJIMAS IR AVALYNĖ -->
      <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0;">
        <strong style="color: #92400e; display: block; margin-bottom: 4px;">🚪 PATEKIMAS IR SVARBI INFORMACIJA:</strong>
        <p style="margin: 0; color: #78350f; font-size: 14px;">
          • <strong>Adresas:</strong> Pramonės g. 15, Šiauliai (2-as aukštas).<br>
          • <strong>Įėjimas:</strong> Pro duris „Floristika“, pirmos durys kairėje laiptais į antrąjį aukštą.<br>
          • <strong>Avalynė:</strong> Visiems dalyviams privaloma patogi, uždara avalynė (sportbačiai).<br>
          • Prašome atvykti likus <strong>5–10 min.</strong> iki laiko pradžios saugumo instruktažui.
        </p>
      </div>

      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin: 18px 0;">
        <span style="font-weight: 800; color: #0f172a; margin-bottom: 8px; font-size: 15px; display: block;">📍 KUR MUS RASTI:</span>
        <p style="margin: 0 0 14px 0; font-size: 14px; line-height: 1.5;">
          Adresas: <a href="https://maps.google.com/?q=55.910730,23.315700" target="_blank" rel="noopener noreferrer" style="color: #0f172a; font-weight: bold; text-decoration: underline;">Pramonės g. 15, Šiauliai (2-as aukštas)</a><br>
          Koordinatės: <strong>55.910730, 23.315700</strong>
        </p>
        
        <!-- NAVIGACIJOS MYGTUKAI (BULLETPROOF EMAIL BUTTONS) -->
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-top: 8px;">
          <tr>
            <td align="center" style="padding: 6px; width: 50%;">
              <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td align="center" bgcolor="#2563eb" style="border-radius: 8px;">
                    <a href="https://maps.google.com/?q=55.910730,23.315700" target="_blank" rel="noopener noreferrer" style="display: block; width: 100%; box-sizing: border-box; background-color: #2563eb; color: #ffffff !important; font-size: 14px; font-weight: bold; text-decoration: none; padding: 12px 14px; border-radius: 8px; text-align: center; cursor: pointer; border: 1px solid #2563eb;">
                      🗺️ Google Maps žemėlapis
                    </a>
                  </td>
                </tr>
              </table>
            </td>
            <td align="center" style="padding: 6px; width: 50%;">
              <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td align="center" bgcolor="#0284c7" style="border-radius: 8px;">
                    <a href="https://www.waze.com/ul?ll=55.910730,23.315700&navigate=yes" target="_blank" rel="noopener noreferrer" style="display: block; width: 100%; box-sizing: border-box; background-color: #0284c7; color: #ffffff !important; font-size: 14px; font-weight: bold; text-decoration: none; padding: 12px 14px; border-radius: 8px; text-align: center; cursor: pointer; border: 1px solid #0284c7;">
                      🚗 Waze navigacija
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <p style="margin: 12px 0 0 0; text-align: center; font-size: 13px; color: #64748b;">
          Tiesioginė nuoroda: <a href="https://maps.google.com/?q=55.910730,23.315700" target="_blank" rel="noopener noreferrer" style="color: #2563eb; font-weight: bold; text-decoration: underline;">Atidaryti Pramonės g. 15 per Google Maps ➔</a>
        </p>
      </div>

      <div style="margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 16px; font-size: 14px;">
        <strong>Kilus klausimams dėl laiko ar pakeitimų:</strong><br>
        📞 Tel.: <a href="tel:+37061988009" style="color: #d97706; font-weight: 700; text-decoration:none;">+370 619 88009</a><br>
        ✉️ El. paštas: <a href="mailto:throwhousee@gmail.com" style="color: #d97706; text-decoration:none;">throwhousee@gmail.com</a>
      </div>
    </div>

    <!-- FOOTER -->
    <div style="background-color: #f1f5f9; padding: 16px 20px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0;">
      Laukiame Jūsų atvykstant naujuoju laiku!<br>
      <strong>„ThrowHouse Šiauliai“ komanda</strong> · Pramonės g. 15, Šiauliai
    </div>
  </div>
</body>
</html>';
}

// =========================================================================
// ŠABLONAI: DOVANŲ KUPONAI
// =========================================================================

/**
 * 5. Dovanų kupono HTML gavėjui (draugui arba pirkėjui, jei pirko sau)
 */
function renderVoucherHtml($coupon) {
    $code = htmlspecialchars($coupon['code'] ?? '');
    $amount = htmlspecialchars($coupon['amount'] ?? 30);
    $expiresAt = htmlspecialchars($coupon['expires_at'] ?? '');

    $introGreeting = '<p style="font-size: 16px; margin-top: 0;">Sveiki!</p>
    <p style="font-size: 15px; color: #334155;">Nuoširdžiai dėkojame, kad įsigijote pramogų erdvės <strong>„ThrowHouse Šiauliai“</strong> dovanų kuponą! Žemiau pateikiamas Jūsų kuponas:</p>';

    return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
    
    <!-- HEADER -->
    <div style="background-color: #0f172a; color: #ffffff; padding: 26px 20px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 22px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Oficialus Dovanų Kuponas</p>
    </div>

    <div style="padding: 24px; line-height: 1.6; font-size: 15px; color: #334155;">
      ' . $introGreeting . '

      <!-- KUPONO KORTELĖ -->
      <div style="background: linear-gradient(135deg, #111827 0%, #1f2937 100%); border: 2px solid #f59e0b; border-radius: 14px; padding: 24px; color: #ffffff; text-align: center; margin: 20px 0; box-shadow: 0 6px 20px rgba(245, 158, 11, 0.2);">
        <span style="font-size: 12px; color: #f59e0b; text-transform: uppercase; letter-spacing: 2px; font-weight: 800; display: block; margin-bottom: 8px;">OFICIALUS DOVANŲ KUPONAS</span>
        <div style="font-size: 42px; font-weight: 900; color: #ff6a00; line-height: 1; margin: 8px 0 14px 0;">
          ' . $amount . ' €
        </div>
        <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; margin-bottom: 4px;">UNIKALUS KUPONO KODAS</div>
        <div style="font-size: 24px; font-weight: 900; letter-spacing: 3px; font-family: monospace; color: #ffffff; background: rgba(0,0,0,0.4); padding: 8px 16px; border-radius: 8px; display: inline-block; border: 1px dashed rgba(245, 158, 11, 0.5); margin-bottom: 12px;">
          ' . $code . '
        </div>
        <div style="font-size: 13px; color: #94a3b8;">
          Galioja 6 mėnesius iki: <strong style="color: #ffffff;">' . $expiresAt . '</strong>
        </div>
      </div>

      <!-- ATSISIŲSTI / SPAUSDINTI PDF MYGTUKAS -->
      <div style="text-align: center; margin: 18px 0 24px 0;">
        <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
          <tr>
            <td align="center" bgcolor="#f59e0b" style="border-radius: 8px;">
              <a href="' . getThrowHouseBaseUrl() . '/kuponas_print.php?code=' . urlencode($code) . '&print=1" target="_blank" rel="noopener noreferrer" style="display: inline-block; background-color: #f59e0b; color: #000000 !important; font-size: 15px; font-weight: 800; text-decoration: none; padding: 13px 26px; border-radius: 8px; text-align: center; cursor: pointer; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);">
                🖨️ Atsisiųsti PDF / Spausdinti kuponą (A4) ➔
              </a>
            </td>
          </tr>
        </table>
        <div style="font-size: 12px; color: #64748b; margin-top: 8px;">
          Galite atsispausdinti A4 formatu ant popieriaus arba išsisaugoti kaip PDF failą
        </div>
      </div>

      <!-- INSTRUKCIJA KAIP PANAUDOTI -->
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin: 18px 0;">
        <span style="font-weight: 800; color: #0f172a; margin-bottom: 8px; font-size: 15px; display: block;">📌 REZERVACIJA TIK ASMENIŠKAI SUSISIEKUS:</span>
        <p style="margin: 0; font-size: 14px; color: #475569; line-height: 1.5;">
          Su dovanų kuponu apsilankymo laikas derinamas <strong>TIK asmeniškai susisiekus</strong> iš anksto.<br>
          Paskambinkite arba parašykite ir nurodykite savo kupono kodą <strong>' . $code . '</strong>:<br>
          📞 Tel.: <a href="tel:+37061988009" style="color: #d97706; font-weight: bold; text-decoration: none;">+370 619 88009</a><br>
          ✉️ El. paštu: <a href="mailto:throwhousee@gmail.com" style="color: #d97706; font-weight: bold; text-decoration: none;">throwhousee@gmail.com</a>
        </p>
      </div>

      <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; margin: 18px 0; border-radius: 0 8px 8px 0; font-size: 14px; color: #78350f;">
        <strong>📍 Mūsų adresas:</strong> Pramonės g. 15, Šiauliai (2-as aukštas, įėjimas pro duris „Floristika“).
      </div>
    </div>

    <!-- FOOTER -->
    <div style="background-color: #f1f5f9; padding: 16px 20px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0;">
      Šilčiausi linkėjimai,<br>
      <strong>„ThrowHouse Šiauliai“ komanda</strong> · Pramonės g. 15, Šiauliai
    </div>
  </div>
</body>
</html>';
}

/**
 * 6. Kupono pirkimo patvirtinimas pirkėjui (jei siuntė draugui)
 */
function renderVoucherBuyerReceiptHtml($coupon) {
    $code = htmlspecialchars($coupon['code'] ?? '');
    $amount = htmlspecialchars($coupon['amount'] ?? 30);
    $buyerName = htmlspecialchars(explode(' (', $coupon['buyer'] ?? '')[0] ?: ($coupon['buyer'] ?? ''));
    $targetName = htmlspecialchars($coupon['recipient'] ?? '');
    $targetEmail = htmlspecialchars($coupon['recipient_email'] ?? '');
    $expiresAt = htmlspecialchars($coupon['expires_at'] ?? '');

    return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0;">
    <div style="background-color: #0f172a; color: #ffffff; padding: 24px; text-align: center; border-bottom: 3px solid #10b981;">
      <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #10b981; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8;">Dovanų kupono pirkimo patvirtinimas</p>
    </div>
    <div style="padding: 24px; font-size: 15px; line-height: 1.6; color: #334155;">
      <p style="font-size: 16px; margin-top: 0;">Sveiki, <strong>' . $buyerName . '</strong>!</p>
      <p>Nuoširdžiai dėkojame! Jūsų nupirktas <strong>' . $amount . ' €</strong> vertės dovanų kuponas buvo sėkmingai sugeneruotas ir išsiųstas Jūsų nurodytam gavėjui <strong>' . $targetName . '</strong> (' . $targetEmail . ').</p>
      
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin: 18px 0; font-size: 14px;">
        <span style="font-weight: 800; color: #0f172a; margin-bottom: 8px; display: block;">📋 PIRKIMO INFORMACIJA:</span>
        • Kupono kodas: <strong>' . $code . '</strong><br>
        • Kupono vertė: <strong>' . $amount . ' €</strong><br>
        • Gavėjas: <strong>' . $targetName . '</strong> (' . $targetEmail . ')<br>
        • Galioja 6 mėn. iki: <strong>' . $expiresAt . '</strong>
      </div>

      <p style="font-size: 14px; color: #64748b;">
        Kilus bet kokiems klausimams, susisiekite su mumis: tel. +370 619 88009 arba el. p. throwhousee@gmail.com.
      </p>
    </div>
    <div style="background-color: #f1f5f9; padding: 14px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
      Ačiū, kad renkatės „ThrowHouse Šiauliai“!
    </div>
  </div>
</body>
</html>';
}

/**
 * 7. Pranešimas administratoriui apie nupirktą dovanų kuponą
 */
function renderVoucherAdminHtml($coupon) {
    $code = htmlspecialchars($coupon['code'] ?? '');
    $amount = htmlspecialchars($coupon['amount'] ?? 30);
    $buyer = htmlspecialchars($coupon['buyer'] ?? '');
    $buyerEmail = htmlspecialchars($coupon['buyer_email'] ?? '');
    $targetName = htmlspecialchars($coupon['recipient'] ?? '');
    $targetEmail = htmlspecialchars($coupon['recipient_email'] ?? '');
    $greeting = htmlspecialchars($coupon['greeting'] ?? '');
    $targetType = htmlspecialchars($coupon['target_type'] ?? '');
    $expiresAt = htmlspecialchars($coupon['expires_at'] ?? '');
    $createdDate = htmlspecialchars($coupon['created_at'] ?? date('Y-m-d H:i'));
    $baseUrl = getThrowHouseBaseUrl();

    return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 20px;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 12px 35px rgba(0,0,0,0.25);">
    
    <div style="background-color: #0f172a; color: #ffffff; padding: 24px; text-align: center; border-bottom: 3px solid #f59e0b;">
      <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #f59e0b; letter-spacing: 1px;">THROW HOUSE ŠIAULIAI</h1>
      <p style="margin: 0; font-size: 13px; color: #94a3b8;">Gautas naujas dovanų kupono pirkimas</p>
      <div style="display:inline-block; background-color:rgba(245, 158, 11, 0.15); color:#f59e0b; border:1px solid rgba(245, 158, 11, 0.4); padding:4px 14px; border-radius:20px; font-weight:bold; font-size:12px; margin-top:8px;">
        ' . $targetType . '
      </div>
    </div>

    <div style="padding: 24px; font-size: 14px; line-height: 1.6; color: #334155;">
      
      <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 12px; padding: 16px 20px; color: #ffffff; margin-bottom: 18px;">
        <span style="font-size: 11px; color: #f59e0b; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; display: block;">Kupono kodas</span>
        <span style="font-size: 24px; font-weight: 800; font-family: monospace; letter-spacing: 2px;">' . $code . '</span>
        <div style="margin-top: 6px; font-size: 18px; font-weight: 900; color: #34d399;">Vertė: ' . $amount . ' €</div>
      </div>

      <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 6px; display: block;">👤 Pirkėjo kontaktai:</span>
      <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
        <strong>' . $buyer . '</strong><br>
        ✉️ El. paštas: ' . $buyerEmail . '
      </div>

      <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 6px; display: block;">🎁 Gavėjo duomenys:</span>
      <div style="background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
        <strong>' . $targetName . '</strong> (' . $targetEmail . ')
        ' . (!empty($greeting) ? '<br><em style="color:#78350f; display:block; margin-top:4px;">„' . nl2br($greeting) . '“</em>' : '') . '
      </div>

      <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; margin-bottom: 18px; font-size: 13px;">
        Užsakymo data: <strong>' . $createdDate . '</strong><br>
        Galioja iki: <strong style="color: #059669;">' . $expiresAt . ' (6 mėn.)</strong>
      </div>

      <div style="text-align: center; margin: 20px 0 10px 0;">
        <a href="' . $baseUrl . '/admin.php?tab=kuponai" style="display:inline-block; background-color:#0f172a; color:#ffffff; font-weight:bold; text-decoration:none; padding:12px 24px; border-radius:8px; border:1px solid #334155; font-size:14px;" target="_blank">
          ⚙️ Peržiūrėti kuponus valdymo pulte (admin.php) ➔
        </a>
      </div>

    </div>
    <div style="background-color: #f8fafc; padding: 14px 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
      ThrowHouse Šiauliai · Automatinis pranešimas administratoriams · ' . date('Y-m-d H:i') . '
    </div>
  </div>
</body>
</html>';
}
