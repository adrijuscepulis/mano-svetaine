<?php
// pdf_helper.php - Gryno PHP A4 formato dovanų kupono PDF generatorius „ThrowHouse Šiauliai“
// Naudoja absoliučias PDF teksto matricas (Tm), užtikrinančias nepriekaištingą išlygiavimą be jokių persidengimų.

function generateThrowHouseCouponPdf($code, $amount, $expiresAt) {
    // A4 Portrait: 210mm x 297mm (72 pt/colis)
    $w = 595.28;
    $h = 841.89;

    $safeCode = preg_replace('/[^A-Za-z0-9\-]/', '', $code ?: 'TH-KUP-0000');
    $safeAmount = intval($amount ?: 50);
    $safeExpires = preg_replace('/[^0-9\-]/', '', $expiresAt ?: date('Y-m-d', strtotime('+6 months')));

    // Helper: išveda tekstą ABSOLIUČIOMIS koordinatėmis (1 0 0 1 x y Tm)
    $put = function($font, $size, $r, $g, $b, $x, $y, $text) {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return "BT\n"
            . "/$font $size Tf\n"
            . "$r $g $b rg\n"
            . "1 0 0 1 $x $y Tm\n"
            . "($escaped) Tj\n"
            . "ET\n";
    };

    // Helper: išveda centruotą tekstą
    $putCenter = function($font, $size, $r, $g, $b, $y, $text) use ($w, $put) {
        $charW = ($font === 'F2') ? ($size * 0.58) : ($size * 0.50);
        $textW = strlen($text) * $charW;
        $x = round(($w - $textW) / 2, 2);
        if ($x < 25) $x = 25;
        return $put($font, $size, $r, $g, $b, $x, $y, $text);
    };

    $stream = "";

    // 1. Baltas fonas
    $stream .= "1.0 1.0 1.0 rg 0 0 $w $h re f\n";

    // 2. Rėmeliai
    // Išorinis tamsus rėmelis
    $stream .= "0.12 0.16 0.23 RG 2.5 w 28 28 539.28 785.89 re S\n";
    // Vidinis ThrowHouse oranžinis akcento rėmelis
    $stream .= "1.0 0.416 0.0 RG 1.5 w 34 34 527.28 773.89 re S\n";

    // Kampiniai L akcentai (3.0 w)
    $stream .= "1.0 0.416 0.0 RG 3.0 w\n";
    $stream .= "34 780 m 34 807.89 l 61.89 807.89 l S\n";
    $stream .= "561.28 780 m 561.28 807.89 l 533.39 807.89 l S\n";
    $stream .= "34 61.89 m 34 34 l 61.89 34 l S\n";
    $stream .= "561.28 61.89 m 561.28 34 l 533.39 34 l S\n";

    // 3. Viršūnė: Prekės ženklas (centruotas)
    $stream .= $putCenter('F2', 26, 1.0, 0.416, 0.0, 762, 'THROW HOUSE');
    $stream .= $putCenter('F2', 10, 0.35, 0.4, 0.5, 744, 'SIAULIAI - ADRENALINO IR PRAMOGU ERDVE');

    // Skiriamoji linija viršuje
    $stream .= "0.85 0.88 0.92 RG 1.0 w 60 728 m 535 728 l S\n";

    // Sertifikato ženklelis (Pill box)
    $stream .= "0.99 0.95 0.90 rg 1.0 0.416 0.0 RG 1.0 w 185 696 225 22 re B\n";
    $stream .= $putCenter('F2', 9, 0.85, 0.3, 0.0, 703, 'OFICIALUS DOVANU SERTIFIKATAS');

    // 4. Centrinė dalis: DOVANŲ KUPONAS (Didelis, aiškus, centruotas)
    $stream .= $putCenter('F2', 34, 0.08, 0.12, 0.20, 650, 'DOVANU KUPONAS');

    // 5. Centrinė dalis: SUMA (Labai didelė, aiški dėžutė)
    $stream .= "0.995 0.99 0.985 rg 1.0 0.416 0.0 RG 2.5 w 140 500 315 125 re B\n";
    $stream .= $putCenter('F1', 11, 0.45, 0.5, 0.58, 598, 'KUPONO VERTE');
    
    $amountStr = $safeAmount . " EUR";
    $stream .= $putCenter('F2', 50, 1.0, 0.416, 0.0, 532, $amountStr);

    // 6. Unikalus registracijos kodas
    $stream .= "0.96 0.97 0.99 rg 0.7 0.75 0.82 RG 1.5 w 175 438 245 44 re B\n";
    $stream .= $putCenter('F1', 8.5, 0.45, 0.5, 0.58, 465, 'UNIKALUS KUPONO KODAS');
    $stream .= $putCenter('F2', 17, 0.08, 0.12, 0.20, 447, $safeCode);

    // 7. Pramogų blokas
    $stream .= "0.97 0.98 0.99 rg 0.85 0.88 0.92 RG 1.0 w 55 315 485 105 re B\n";
    $stream .= $put('F2', 10.5, 0.12, 0.16, 0.23, 72, 396, 'SIS KUPONAS GALIOJA PASIRINKTAI PRAMOGAI:');

    // 3 kortelės viduje
    // Kortelė 1: Pykčio kambarys
    $stream .= "1.0 1.0 1.0 rg 0.82 0.85 0.9 RG 1.0 w 68 328 146 54 re B\n";
    $stream .= $put('F2', 10, 1.0, 0.416, 0.0, 78, 364, 'PYKCIO KAMBARYS');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.45, 78, 348, 'Streso iskrovos sesija');
    $stream .= $put('F1', 8, 0.5, 0.55, 0.6, 78, 336, '(1 val. dalyviui)');

    // Kortelė 2: Kirvių mėtymas
    $stream .= "1.0 1.0 1.0 rg 0.82 0.85 0.9 RG 1.0 w 224 328 146 54 re B\n";
    $stream .= $put('F2', 10, 1.0, 0.416, 0.0, 234, 364, 'KIRVIU METYMAS');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.45, 234, 348, 'Taiklumo rungtys');
    $stream .= $put('F1', 8, 0.5, 0.55, 0.6, 234, 336, '(1 val. takelyje)');

    // Kortelė 3: KOMBO paketas
    $stream .= "1.0 1.0 1.0 rg 0.82 0.85 0.9 RG 1.0 w 380 328 146 54 re B\n";
    $stream .= $put('F2', 10, 1.0, 0.416, 0.0, 390, 364, 'KOMBO PAKETAS');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.45, 390, 348, 'Abi pramogos kartu!');
    $stream .= $put('F1', 8, 0.5, 0.55, 0.6, 390, 336, '(pilna 2 val. sesija)');

    // 8. Rezervacija TIK asmeniškai susisiekus
    $stream .= "0.995 0.965 0.93 rg 1.0 0.6 0.3 RG 1.0 w 55 210 485 88 re B\n";
    // Kairioji stora oranžinė linija
    $stream .= "1.0 0.416 0.0 rg 55 210 5 88 re f\n";

    $stream .= $put('F2', 10.5, 0.85, 0.25, 0.0, 72, 276, 'DEMESIO - REZERVACIJA TIK ASMENISKAI SUSISIEKUS:');
    $stream .= $put('F1', 9.2, 0.2, 0.25, 0.3, 72, 258, 'Su dovanu kuponu apsilankymo laikas derinamas TIK is anksto asmeniskai.');
    $stream .= $put('F1', 9.2, 0.2, 0.25, 0.3, 72, 242, 'Paskambinkite arba parasykite ir nurodykite kupono koda ' . $safeCode . ':');
    $stream .= $put('F2', 10.5, 0.08, 0.12, 0.2, 72, 224, 'Tel.: +370 619 88009     |     El. pastas: throwhousee@gmail.com');

    // 9. Apatiniai rekvizitai
    $stream .= "0.88 0.90 0.94 RG 1.0 w 55 192 m 540 192 l S\n";

    // Stulpelis 1: Adresas
    $stream .= $put('F2', 9, 0.12, 0.16, 0.23, 65, 175, 'ADRESAS:');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 65, 160, 'Pramones g. 15, Siauliai (2-as a.)');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 65, 147, 'Iejimas pro duris Floristika');

    // Stulpelis 2: Kontaktai
    $stream .= $put('F2', 9, 0.12, 0.16, 0.23, 240, 175, 'KONTAKTAI:');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 240, 160, '+370 619 88009');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 240, 147, 'throwhousee@gmail.com');

    // Stulpelis 3: Galiojimas
    $stream .= $put('F2', 9, 0.12, 0.16, 0.23, 415, 175, 'GALIOJIMAS:');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 415, 160, 'Galioja 6 menesius');
    $stream .= $put('F2', 8.5, 0.12, 0.16, 0.23, 415, 147, 'iki: ' . $safeExpires);

    // 10. Teisinė ir saugumo informacija
    $stream .= "0.88 0.90 0.94 RG 1.0 w 80 115 m 515 115 l S\n";
    $stream .= $putCenter('F1', 8, 0.45, 0.5, 0.55, 95, 'Saugumui uztikrinti visiems dalyviams BUTINA patogi, visiskai uzdara avalyne (sportbaciai).');
    $stream .= $putCenter('F1', 8, 0.5, 0.55, 0.6, 80, 'Kuponas i pinigus nekeiciamas. ThrowHouse Siauliai (c) ' . date('Y'));

    // PDF 1.4 struktūra
    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w $h] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>";
    $streamLen = strlen($stream);
    $objects[4] = "<< /Length $streamLen >>\r\nstream\r\n" . $stream . "\r\nendstream";
    $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

    $out = "%PDF-1.4\r\n";
    $offsets = [];
    for ($i = 1; $i <= 6; $i++) {
        $offsets[$i] = strlen($out);
        $out .= "$i 0 obj\r\n" . $objects[$i] . "\r\nendobj\r\n";
    }

    $xrefOffset = strlen($out);
    $out .= "xref\r\n0 7\r\n";
    $out .= "0000000000 65535 f \r\n";
    for ($i = 1; $i <= 6; $i++) {
        $out .= sprintf("%010d 00000 n \r\n", $offsets[$i]);
    }
    $out .= "trailer\r\n<< /Size 7 /Root 1 0 R >>\r\n";
    $out .= "startxref\r\n$xrefOffset\r\n%%EOF";

    return $out;
}
