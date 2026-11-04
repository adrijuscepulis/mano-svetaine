<?php
// =========================================================================
// ThrowHouse Šiauliai - Baro & Užkandžių sistemos pagalbinis modulis
// =========================================================================

date_default_timezone_set('Europe/Vilnius');

define('BARAS_PRODUKTAI_FILE', __DIR__ . '/baras_produktai.json');
define('BARAS_PRODUKTAI_DB_FILE', __DIR__ . '/baras_produktai_db.json');
define('BARAS_UZSAKYMAI_FILE', __DIR__ . '/baras_uzsakymai.json');
define('BARAS_UZSAKYMAI_DB_FILE', __DIR__ . '/baras_uzsakymai_db.json');

/**
 * Numatytoji pradinė prekių bazė
 */
function getDefaultBarProducts() {
    return [
        [
            'id' => 'p_coca_cola',
            'name' => 'Coca-Cola 0.5L',
            'category' => 'Gėrimai',
            'price' => 2.00,
            'icon' => '🥤',
            'active' => true
        ],
        [
            'id' => 'p_fanta',
            'name' => 'Fanta 0.5L',
            'category' => 'Gėrimai',
            'price' => 2.00,
            'icon' => '🥤',
            'active' => true
        ],
        [
            'id' => 'p_sprite',
            'name' => 'Sprite 0.5L',
            'category' => 'Gėrimai',
            'price' => 2.00,
            'icon' => '🥤',
            'active' => true
        ],
        [
            'id' => 'p_redbull',
            'name' => 'Red Bull Energinis 0.25L',
            'category' => 'Gėrimai',
            'price' => 2.50,
            'icon' => '⚡',
            'active' => true
        ],
        [
            'id' => 'p_monster',
            'name' => 'Monster Energy 0.5L',
            'category' => 'Gėrimai',
            'price' => 3.00,
            'icon' => '⚡',
            'active' => true
        ],
        [
            'id' => 'p_water_still',
            'name' => 'Negazuotas vanduo Neptūnas 0.5L',
            'category' => 'Gėrimai',
            'price' => 1.50,
            'icon' => '💧',
            'active' => true
        ],
        [
            'id' => 'p_water_sparkling',
            'name' => 'Gazuotas vanduo Neptūnas 0.5L',
            'category' => 'Gėrimai',
            'price' => 1.50,
            'icon' => '💧',
            'active' => true
        ],
        [
            'id' => 'p_cido_juice',
            'name' => 'Cido sultys (įvairių skonių) 0.3L',
            'category' => 'Gėrimai',
            'price' => 1.80,
            'icon' => '🧃',
            'active' => true
        ],
        [
            'id' => 'p_pringles_orig',
            'name' => 'Pringles Original 165g',
            'category' => 'Užkandžiai',
            'price' => 3.50,
            'icon' => '🥔',
            'active' => true
        ],
        [
            'id' => 'p_pringles_onion',
            'name' => 'Pringles Sour Cream & Onion 165g',
            'category' => 'Užkandžiai',
            'price' => 3.50,
            'icon' => '🥔',
            'active' => true
        ],
        [
            'id' => 'p_lays',
            'name' => 'Lay\'s traškučiai 130g',
            'category' => 'Užkandžiai',
            'price' => 2.50,
            'icon' => '🍿',
            'active' => true
        ],
        [
            'id' => 'p_peanuts',
            'name' => 'Sūdyti žemės riešutai Estrella 100g',
            'category' => 'Užkandžiai',
            'price' => 2.00,
            'icon' => '🥜',
            'active' => true
        ],
        [
            'id' => 'p_snickers',
            'name' => 'Batonėlis Snickers 50g',
            'category' => 'Užkandžiai',
            'price' => 1.50,
            'icon' => '🍫',
            'active' => true
        ],
        [
            'id' => 'p_twix',
            'name' => 'Batonėlis Twix 50g',
            'category' => 'Užkandžiai',
            'price' => 1.50,
            'icon' => '🍫',
            'active' => true
        ]
    ];
}

/**
 * Užkrauna baro prekes (atsparus Git diegimui)
 */
function loadBarProducts() {
    $db = file_exists(BARAS_PRODUKTAI_DB_FILE) ? json_decode(@file_get_contents(BARAS_PRODUKTAI_DB_FILE), true) : null;
    $main = file_exists(BARAS_PRODUKTAI_FILE) ? json_decode(@file_get_contents(BARAS_PRODUKTAI_FILE), true) : null;

    if (!empty($db) && is_array($db)) {
        if (empty($main)) {
            @file_put_contents(BARAS_PRODUKTAI_FILE, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        return $db;
    }

    if (!empty($main) && is_array($main)) {
        @file_put_contents(BARAS_PRODUKTAI_DB_FILE, json_encode($main, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $main;
    }

    $defaults = getDefaultBarProducts();
    saveBarProducts($defaults);
    return $defaults;
}

/**
 * Išsaugo prekių sąrašą į abu failus
 */
function saveBarProducts(array $products) {
    $json = json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $r1 = @file_put_contents(BARAS_PRODUKTAI_FILE, $json, LOCK_EX);
    $r2 = @file_put_contents(BARAS_PRODUKTAI_DB_FILE, $json, LOCK_EX);
    return ($r1 !== false || $r2 !== false);
}

/**
 * Užkrauna visus baro užsakymus
 */
function loadBarOrders() {
    $db = file_exists(BARAS_UZSAKYMAI_DB_FILE) ? json_decode(@file_get_contents(BARAS_UZSAKYMAI_DB_FILE), true) : null;
    $main = file_exists(BARAS_UZSAKYMAI_FILE) ? json_decode(@file_get_contents(BARAS_UZSAKYMAI_FILE), true) : null;

    if (is_array($db) && is_array($main)) {
        $orders = (count($db) >= count($main)) ? $db : $main;
    } elseif (is_array($db)) {
        $orders = $db;
    } elseif (is_array($main)) {
        $orders = $main;
    } else {
        $orders = [];
    }

    // Automatiškai atšaukiame neapmokėtus ilgiau nei 15 min
    $now = time();
    $modified = false;
    foreach ($orders as &$ord) {
        if (($ord['status'] ?? '') === 'Laukiama apmokėjimo') {
            $createdTs = strtotime($ord['created_at'] ?? 'now');
            if ($now - $createdTs > 900) { // 15 min
                $ord['status'] = 'Atšaukta (Neapmokėta per 15 min)';
                $modified = true;
            }
        }
    }
    unset($ord);

    if ($modified) {
        saveBarOrders($orders);
    }

    return $orders;
}

/**
 * Išsaugo baro užsakymus į abu failus
 */
function saveBarOrders(array $orders) {
    $json = json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $r1 = @file_put_contents(BARAS_UZSAKYMAI_FILE, $json, LOCK_EX);
    $r2 = @file_put_contents(BARAS_UZSAKYMAI_DB_FILE, $json, LOCK_EX);
    return ($r1 !== false || $r2 !== false);
}

/**
 * Generuoja unikalų užsakymo numerį: TH-BAR-XXXX
 */
function generateBarOrderNumber() {
    return 'TH-BAR-' . date('d') . strtoupper(substr(uniqid(), -4));
}

/**
 * Apdoroja įkeltą prekės nuotrauką
 * @param array $file $_FILES['prod_image']
 * @return string|null Grąžina santykinį kelią arba null
 */
function handleBarProductImageUpload($file) {
    if (empty($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Maksimalus dydis 8MB
    if ($file['size'] > 8 * 1024 * 1024) {
        return null;
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        return null;
    }

    // Tikriname ar failas tikrai paveikslėlis
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false) {
        return null;
    }

    $uploadDir = __DIR__ . '/uploads/baras/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $fileName = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
        @chmod($targetPath, 0666);
        return 'uploads/baras/' . $fileName;
    }

    return null;
}
