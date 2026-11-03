<?php
// pdf_helper.php - Oficialus ThrowHouse dovanų kuponų PDF generatorius
// Pagrindinis režimas: naudoja vartotojo Canva 2-jų puslapių dovanų kupono šabloną (dovanu_kuponas_sablonas.pdf)
// su automatiniu duomenų užpildymu (Vertė, Kupono nr., Įsigijimo data, Dovanoja, Kam skirta).
// Atsarginis režimas (fallback): sugeneruoja nepriklausomą švarų A4 dovanų kuponą, jei šablono failas nerandamas.

function transliterateLtToAscii($str) {
    if (empty($str)) return '';
    $map = [
        'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        'Ą' => 'A', 'Č' => 'C', 'Ę' => 'E', 'Ė' => 'E', 'Į' => 'I', 'Š' => 'S', 'Ų' => 'U', 'Ū' => 'U', 'Ž' => 'Z',
    ];
    $clean = strtr($str, $map);
    return preg_replace('/[^\x20-\x7E]/', '', $clean);
}

function escapePdfText($text) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function generateThrowHouseCouponPdf($code, $amount, $expiresAt, $buyer = '', $recipient = '') {
    $templatePath = __DIR__ . '/dovanu_kuponas_sablonas.pdf';
    if (file_exists($templatePath)) {
        try {
            return generateCanvaCouponPdf($templatePath, $code, $amount, $expiresAt, $buyer, $recipient);
        } catch (\Throwable $e) {
            @file_put_contents(__DIR__ . '/pdf_error.log', date('Y-m-d H:i:s') . " | Canva PDF klaida: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
    return generateFallbackCouponPdf($code, $amount, $expiresAt);
}

function generateCanvaCouponPdf($templatePath, $code, $amount, $expiresAt, $buyer = '', $recipient = '') {
    $origBytes = @file_get_contents($templatePath);
    if ($origBytes === false || strlen($origBytes) < 1000) {
        throw new \Exception("Nepavyko nuskaityti dovanu_kuponas_sablonas.pdf");
    }

    // Rasti paskutinę startxref žymą
    if (!preg_match('/startxref\s+(\d+)\s+%%EOF/', $origBytes, $m)) {
        throw new \Exception("PDF faile nerasta startxref žyma");
    }
    $prevXref = intval($m[1]);

    // Duomenų paruošimas
    $cleanCode = preg_replace('/[^A-Za-z0-9\-]/', '', $code ?: 'TH-KUP-0000');
    $cleanAmount = intval($amount ?: 50);
    $createdDate = date('Y-m-d');

    // Valome pirkėją ir gavėją
    $cleanBuyer = trim(preg_replace('/\s*\(.*?\)/', '', $buyer ?: ''));
    $cleanRecipient = trim(preg_replace('/\s*\(.*?\)/', '', $recipient ?: ''));

    // Transliteracija į saugius ASCII (Type 1 Helvetica)
    $cleanBuyer = transliterateLtToAscii($cleanBuyer);
    $cleanRecipient = transliterateLtToAscii($cleanRecipient);

    if ($cleanBuyer === '') $cleanBuyer = '-';
    if ($cleanRecipient === '') $cleanRecipient = '-';

    // PDF teksto pabėgimas
    $escCode = escapePdfText($cleanCode);
    $escAmount = $cleanAmount . " \\200"; // \200 yra € simbolis WinAnsiEncoding
    $escDate = escapePdfText($createdDate);
    $escBuyer = escapePdfText(mb_substr($cleanBuyer, 0, 32));
    $escRecipient = escapePdfText(mb_substr($cleanRecipient, 0, 36));

    // Teksto koordinatės ant Canva šablono linijų (MediaBox [0 7.5 595.5 288.0]):
    // Vertė: linija X=107..324, Y=138. Reikšmė X=185, Y=142
    // Kupono nr.: linija X=350..566, Y=137. Reikšmė X=435, Y=142
    // Įsigijimo data: linija X=107..324, Y=100. Reikšmė X=205, Y=104
    // Dovanoja: linija X=350..566, Y=100. Reikšmė X=425, Y=104
    // Kam skirta: linija X=213..487, Y=67. Reikšmė X=300, Y=71

    $stream = "/gsNormal gs\n";
    // Vertė
    $stream .= "BT\n/F2 14 Tf\n0.08 0.12 0.14 rg\n1 0 0 1 185 142 Tm\n($escAmount) Tj\nET\n";
    // Kupono nr.
    $stream .= "BT\n/F2 13 Tf\n0.08 0.12 0.14 rg\n1 0 0 1 435 142 Tm\n($escCode) Tj\nET\n";
    // Įsigijimo data
    $stream .= "BT\n/F2 11.5 Tf\n0.12 0.16 0.18 rg\n1 0 0 1 205 104 Tm\n($escDate) Tj\nET\n";
    // Dovanoja
    $stream .= "BT\n/F2 11.5 Tf\n0.12 0.16 0.18 rg\n1 0 0 1 425 104 Tm\n($escBuyer) Tj\nET\n";
    // Kam skirta
    $stream .= "BT\n/F2 12 Tf\n0.08 0.12 0.14 rg\n1 0 0 1 300 71 Tm\n($escRecipient) Tj\nET\n";

    $streamLen = strlen($stream);

    // Nauji PDF objektai
    $newObj104 = "104 0 obj\r\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\r\nendobj\r\n";
    $newObj105 = "105 0 obj\r\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\r\nendobj\r\n";
    $newObj106 = "106 0 obj\r\n<< /Length $streamLen >>\r\nstream\r\n" . $stream . "\r\nendstream\r\nendobj\r\n";
    $newObj107 = "107 0 obj\r\n<< /Type /ExtGState /CA 1.0 /ca 1.0 >>\r\nendobj\r\n";

    $newObj10 = "10 0 obj\r\n<<\r\n/Type /Page\r\n/Resources <<\r\n/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]\r\n/ExtGState <<\r\n/G3 45 0 R\r\n/gs2 46 0 R\r\n/gsNormal 107 0 R\r\n>>\r\n/XObject <<\r\n/X9 18 0 R\r\n>>\r\n/Font <<\r\n/F1 104 0 R\r\n/F2 105 0 R\r\n>>\r\n>>\r\n/MediaBox [0.0 7.5000086 595.5 288.0]\r\n/Contents [19 0 R 106 0 R]\r\n/StructParents 0\r\n/Tabs /S\r\n/Parent 2 0 R\r\n/BleedBox [0.0 7.5000086 595.5 288.0]\r\n>>\r\nendobj\r\n";

    $out = $origBytes;

    $offset10 = strlen($out);
    $out .= $newObj10;

    $offset104 = strlen($out);
    $out .= $newObj104;

    $offset105 = strlen($out);
    $out .= $newObj105;

    $offset106 = strlen($out);
    $out .= $newObj106;

    $offset107 = strlen($out);
    $out .= $newObj107;

    $newXrefOffset = strlen($out);

    $xref = "xref\r\n";
    $xref .= "10 1\r\n";
    $xref .= sprintf("%010d 00000 n \r\n", $offset10);
    $xref .= "104 4\r\n";
    $xref .= sprintf("%010d 00000 n \r\n", $offset104);
    $xref .= sprintf("%010d 00000 n \r\n", $offset105);
    $xref .= sprintf("%010d 00000 n \r\n", $offset106);
    $xref .= sprintf("%010d 00000 n \r\n", $offset107);
    $xref .= "trailer\r\n<<\r\n/Size 108\r\n/Root 1 0 R\r\n/Prev $prevXref\r\n>>\r\n";
    $xref .= "startxref\r\n$newXrefOffset\r\n%%EOF";

    $out .= $xref;
    return $out;
}

function generateFallbackCouponPdf($code, $amount, $expiresAt) {
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
    $stream .= "0.12 0.16 0.23 RG 2.5 w 28 28 539.28 785.89 re S\n";
    $stream .= "0.0 0.961 0.608 RG 1.5 w 34 34 527.28 773.89 re S\n";

    // Kampiniai L akcentai
    $stream .= "0.0 0.961 0.608 RG 3.0 w\n";
    $stream .= "34 780 m 34 807.89 l 61.89 807.89 l S\n";
    $stream .= "561.28 780 m 561.28 807.89 l 533.39 807.89 l S\n";
    $stream .= "34 61.89 m 34 34 l 61.89 34 l S\n";
    $stream .= "561.28 61.89 m 561.28 34 l 533.39 34 l S\n";

    // 3. Prekės ženklas
    $stream .= $putCenter('F2', 26, 0.02, 0.588, 0.412, 762, 'THROW HOUSE');
    $stream .= $putCenter('F2', 10, 0.35, 0.4, 0.5, 744, 'SIAULIAI - ADRENALINO IR PRAMOGU ERDVE');
    $stream .= "0.85 0.88 0.92 RG 1.0 w 60 728 m 535 728 l S\n";

    // Sertifikato ženklelis
    $stream .= "0.94 0.99 0.96 rg 0.0 0.961 0.608 RG 1.0 w 185 696 225 22 re B\n";
    $stream .= $putCenter('F2', 9, 0.02, 0.588, 0.412, 703, 'OFICIALUS DOVANU SERTIFIKATAS');

    // 4. Centrinė dalis
    $stream .= $putCenter('F2', 34, 0.08, 0.12, 0.20, 650, 'DOVANU KUPONAS');

    // 5. Suma
    $stream .= "0.95 0.99 0.97 rg 0.0 0.961 0.608 RG 2.5 w 140 500 315 125 re B\n";
    $stream .= $putCenter('F1', 11, 0.45, 0.5, 0.58, 598, 'KUPONO VERTE');
    $amountStr = $safeAmount . " EUR";
    $stream .= $putCenter('F2', 50, 0.02, 0.588, 0.412, 532, $amountStr);

    // 6. Unikalus kodas
    $stream .= "0.96 0.97 0.99 rg 0.7 0.75 0.82 RG 1.5 w 175 438 245 44 re B\n";
    $stream .= $putCenter('F1', 8.5, 0.45, 0.5, 0.58, 465, 'UNIKALUS KUPONO KODAS');
    $stream .= $putCenter('F2', 17, 0.08, 0.12, 0.20, 447, $safeCode);

    // 7. Pramogos
    $stream .= "0.97 0.98 0.99 rg 0.85 0.88 0.92 RG 1.0 w 55 315 485 105 re B\n";
    $stream .= $put('F2', 10.5, 0.12, 0.16, 0.23, 72, 396, 'SIS KUPONAS GALIOJA PASIRINKTAI PRAMOGAI:');

    $stream .= "1.0 1.0 1.0 rg 0.82 0.85 0.9 RG 1.0 w 68 328 146 54 re B\n";
    $stream .= $put('F2', 10, 0.02, 0.588, 0.412, 78, 364, 'PYKCIO KAMBARYS');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.45, 78, 348, 'Streso iskrovos sesija');
    $stream .= $put('F1', 8, 0.5, 0.55, 0.6, 78, 336, '(1 val. dalyviui)');

    $stream .= "1.0 1.0 1.0 rg 0.82 0.85 0.9 RG 1.0 w 224 328 146 54 re B\n";
    $stream .= $put('F2', 10, 0.02, 0.588, 0.412, 234, 364, 'KIRVIU METYMAS');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.45, 234, 348, 'Taiklumo rungtys');
    $stream .= $put('F1', 8, 0.5, 0.55, 0.6, 234, 336, '(1 val. takelyje)');

    $stream .= "1.0 1.0 1.0 rg 0.82 0.85 0.9 RG 1.0 w 380 328 146 54 re B\n";
    $stream .= $put('F2', 10, 0.02, 0.588, 0.412, 390, 364, 'KOMBO PAKETAS');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.45, 390, 348, 'Abi pramogos kartu!');
    $stream .= $put('F1', 8, 0.5, 0.55, 0.6, 390, 336, '(pilna 2 val. sesija)');

    // 8. Rezervacija
    $stream .= "0.94 0.99 0.96 rg 0.0 0.85 0.52 RG 1.0 w 55 210 485 88 re B\n";
    $stream .= "0.0 0.961 0.608 rg 55 210 5 88 re f\n";
    $stream .= $put('F2', 10.5, 0.02, 0.588, 0.412, 72, 276, 'DEMESIO - REZERVACIJA TIK ASMENISKAI SUSISIEKUS:');
    $stream .= $put('F1', 9.2, 0.2, 0.25, 0.3, 72, 258, 'Su dovanu kuponu apsilankymo laikas derinamas TIK is anksto asmeniskai.');
    $stream .= $put('F1', 9.2, 0.2, 0.25, 0.3, 72, 242, 'Paskambinkite arba parasykite ir nurodykite kupono koda ' . $safeCode . ':');
    $stream .= $put('F2', 10.5, 0.08, 0.12, 0.2, 72, 224, 'Tel.: +370 619 88009     |     El. pastas: info@throwhouse.lt');

    // 9. Rekvizitai
    $stream .= "0.88 0.90 0.94 RG 1.0 w 55 192 m 540 192 l S\n";
    $stream .= $put('F2', 9, 0.12, 0.16, 0.23, 65, 175, 'ADRESAS:');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 65, 160, 'Pramones g. 15, Siauliai (2-as a.)');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 65, 147, 'Iejimas pro duris Floristika');

    $stream .= $put('F2', 9, 0.12, 0.16, 0.23, 240, 175, 'KONTAKTAI:');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 240, 160, '+370 619 88009');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 240, 147, 'info@throwhouse.lt');

    $stream .= $put('F2', 9, 0.12, 0.16, 0.23, 415, 175, 'GALIOJIMAS:');
    $stream .= $put('F1', 8.5, 0.35, 0.4, 0.48, 415, 160, 'Galioja 6 menesius');
    $stream .= $put('F2', 8.5, 0.12, 0.16, 0.23, 415, 147, 'iki: ' . $safeExpires);

    // 10. Teisinė informacija
    $stream .= "0.88 0.90 0.94 RG 1.0 w 80 115 m 515 115 l S\n";
    $stream .= $putCenter('F1', 8, 0.45, 0.5, 0.55, 95, 'Saugumui uztikrinti visiems dalyviams BUTINA patogi, visiskai uzdara avalyne (sportbaciai).');
    $stream .= $putCenter('F1', 8, 0.5, 0.55, 0.6, 80, 'Kuponas i pinigus nekeiciamas. ThrowHouse Siauliai (c) ' . date('Y'));

    // PDF 1.4
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
