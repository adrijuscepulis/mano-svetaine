<?php
// pdf_helper.php - Gryno PHP A4 formato dovanų kupono PDF generatorius „ThrowHouse Šiauliai“
// Veikia be jokių išorinių bibliotekų ar papildomų serverio modulių.

function generateThrowHouseCouponPdf($code, $amount, $expiresAt) {
    // A4 Portrait išmatavimai taškais (points, 72 pt = 1 colis):
    // Plotis: 210mm = 595.28 pt
    // Aukštis: 297mm = 841.89 pt
    $w = 595.28;
    $h = 841.89;

    $safeCode = preg_replace('/[^A-Za-z0-9\-]/', '', $code ?: 'TH-KUP-0000');
    $safeAmount = intval($amount ?: 50);
    $safeExpires = preg_replace('/[^0-9\-]/', '', $expiresAt ?: date('Y-m-d', strtotime('+6 months')));

    // Grafinių instrukcijų srautas (PDF Stream)
    $stream = "";

    // 1. Baltas fonas
    $stream .= "1.0 1.0 1.0 rg 0 0 $w $h re f\n";

    // 2. Šiuolaikiniai elegantiški rėmeliai
    // Išorinis tamsus rėmelis
    $stream .= "0.12 0.16 0.23 RG 2.5 w 28 28 539.28 785.89 re S\n";
    // Vidinis ThrowHouse oranžinis akcento rėmelis
    $stream .= "1.0 0.416 0.0 RG 1.0 w 34 34 527.28 773.89 re S\n";

    // Kampiniai akcentai
    $stream .= "1.0 0.416 0.0 RG 3.0 w\n";
    $stream .= "34 780 m 34 807.89 l 61.89 807.89 l S\n";
    $stream .= "561.28 780 m 561.28 807.89 l 533.39 807.89 l S\n";
    $stream .= "34 61.89 m 34 34 l 61.89 34 l S\n";
    $stream .= "561.28 61.89 m 561.28 34 l 533.39 34 l S\n";

    // 3. Viršūnė: Prekės ženklas
    $stream .= "BT\n";
    $stream .= "/F2 26 Tf\n";
    $stream .= "1.0 0.416 0.0 rg\n";
    $stream .= "195 765 Td (THROW HOUSE) Tj\n";
    $stream .= "/F2 12 Tf\n";
    $stream .= "0.12 0.16 0.23 rg\n";
    $stream .= "-40 -20 Td (SIAULIAI - ADRENALINO IR PRAMOGU ERDVE) Tj\n";
    $stream .= "ET\n";

    // Skiriamoji linija
    $stream .= "0.85 0.88 0.92 RG 1.0 w 60 725 m 535 725 l S\n";

    // Sertifikato ženklelis
    $stream .= "0.98 0.94 0.90 rg 1.0 0.416 0.0 RG 1.0 w 180 685 235 24 re B\n";
    $stream .= "BT\n";
    $stream .= "/F2 10 Tf\n";
    $stream .= "0.9 0.35 0.0 rg\n";
    $stream .= "208 693 Td (OFICIALUS DOVANU SERTIFIKATAS) Tj\n";
    $stream .= "ET\n";

    // 4. RYŠKIAI PER VIDURĮ: DOVANŲ KUPONAS
    $stream .= "BT\n";
    $stream .= "/F2 36 Tf\n";
    $stream .= "0.08 0.11 0.17 rg\n";
    $stream .= "135 635 Td (DOVANU KUPONAS) Tj\n";
    $stream .= "ET\n";

    // 5. RYŠKI SUMA PER VIDURĮ
    $stream .= "0.98 0.98 0.99 rg 1.0 0.416 0.0 RG 2.5 w 150 490 295 125 re B\n";
    $stream .= "BT\n";
    $stream .= "/F1 11 Tf\n";
    $stream .= "0.4 0.45 0.5 rg\n";
    $stream .= "248 592 Td (KUPONO VERTE) Tj\n";
    $stream .= "/F2 56 Tf\n";
    $stream .= "1.0 0.416 0.0 rg\n";
    $amountStr = $safeAmount . " EUR";
    $amountX = (strlen($amountStr) > 6) ? 190 : 215;
    $stream .= "$amountX 530 Td ($amountStr) Tj\n";
    $stream .= "ET\n";

    // 6. Registracijos kodas
    $stream .= "0.94 0.96 0.98 rg 0.7 0.75 0.82 RG 1.5 w 180 435 235 38 re B\n";
    $stream .= "BT\n";
    $stream .= "/F1 9 Tf\n";
    $stream .= "0.45 0.5 0.58 rg\n";
    $stream .= "225 458 Td (UNIKALUS KUPONO KODAS) Tj\n";
    $stream .= "/F2 16 Tf\n";
    $stream .= "0.08 0.11 0.17 rg\n";
    $codeX = 297.64 - (strlen($safeCode) * 5.2);
    $stream .= "$codeX 442 Td ($safeCode) Tj\n";
    $stream .= "ET\n";

    // 7. Pramogų blokas
    $stream .= "0.96 0.97 0.99 rg 0.85 0.88 0.92 RG 1.0 w 55 315 485 100 re B\n";
    $stream .= "BT\n";
    $stream .= "/F2 11 Tf\n";
    $stream .= "0.12 0.16 0.23 rg\n";
    $stream .= "70 395 Td (SIS KUPONAS GALIOJA PASIRINKTAI PRAMOGAI:) Tj\n";
    $stream .= "/F2 10 Tf\n";
    $stream .= "0.9 0.35 0.0 rg\n";
    $stream .= "70 370 Td ([*] PYKCIO KAMBARYS) Tj\n";
    $stream .= "235 370 Td ([*] KIRVIU METYMAS) Tj\n";
    $stream .= "395 370 Td ([*] KOMBO PAKETAS) Tj\n";
    $stream .= "/F1 9 Tf\n";
    $stream .= "0.3 0.35 0.4 rg\n";
    $stream .= "70 355 Td (1 val. streso iskrovos sesija) Tj\n";
    $stream .= "235 355 Td (1 val. taiklumo rungtys) Tj\n";
    $stream .= "395 355 Td (Abi pramogos kartu!) Tj\n";
    $stream .= "ET\n";

    // 8. Rezervacija TIK asmeniškai susisiekus
    $stream .= "0.99 0.95 0.92 rg 1.0 0.416 0.0 RG 1.5 w 55 210 485 85 re B\n";
    $stream .= "BT\n";
    $stream .= "/F2 11 Tf\n";
    $stream .= "0.85 0.25 0.0 rg\n";
    $stream .= "70 272 Td (DEMESIO - REZERVACIJA TIK ASMENISKAI SUSISIEKUS:) Tj\n";
    $stream .= "/F1 10 Tf\n";
    $stream .= "0.2 0.25 0.3 rg\n";
    $stream .= "70 252 Td (Su dovanu kuponu apsilankymo laikas derinamas TIK asmeniskai is anksto.) Tj\n";
    $stream .= "70 236 Td (Susisiekite ir nurodykite kupono koda $safeCode:) Tj\n";
    $stream .= "/F2 10 Tf\n";
    $stream .= "0.08 0.11 0.17 rg\n";
    $stream .= "70 220 Td (Tel.: +370 619 88009     El. pastas: throwhousee@gmail.com) Tj\n";
    $stream .= "ET\n";

    // 9. Apatiniai rekvizitai
    $stream .= "0.88 0.90 0.94 RG 1.0 w 55 185 m 540 185 l S\n";
    $stream .= "BT\n";
    $stream .= "/F2 9 Tf\n";
    $stream .= "0.12 0.16 0.23 rg\n";
    $stream .= "60 165 Td (ADRESAS:) Tj\n";
    $stream .= "240 165 Td (KONTAKTAI:) Tj\n";
    $stream .= "410 165 Td (GALIOJIMAS:) Tj\n";
    $stream .= "/F1 8.5 Tf\n";
    $stream .= "0.35 0.4 0.48 rg\n";
    $stream .= "60 150 Td (Pramones g. 15, Siauliai (2-as a.)) Tj\n";
    $stream .= "60 138 Td (Iejimas pro duris Floristika) Tj\n";
    $stream .= "240 150 Td (+370 619 88009) Tj\n";
    $stream .= "240 138 Td (throwhousee@gmail.com) Tj\n";
    $stream .= "410 150 Td (Galioja 6 menesius) Tj\n";
    $stream .= "410 138 Td (iki: $safeExpires) Tj\n";
    $stream .= "ET\n";

    // 10. Teisinė ir saugumo informacija
    $stream .= "BT\n";
    $stream .= "/F1 8 Tf\n";
    $stream .= "0.5 0.55 0.6 rg\n";
    $stream .= "80 80 Td (Saugumui uztikrinti visiems dalyviams BUTINA patogi, visiskai uzdara avalyne.) Tj\n";
    $stream .= "140 68 Td (Kuponas i pinigus nekeiciamas. ThrowHouse Siauliai (c) " . date('Y') . ") Tj\n";
    $stream .= "ET\n";

    // PDF 1.4 struktūros surinkimas
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
