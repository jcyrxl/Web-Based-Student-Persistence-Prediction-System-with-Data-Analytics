<?php
// Reads the text of a Word (.docx) class list so it can be checked and added (Add students). Faculty only; nothing is stored.
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/roles.php';
header('Content-Type: application/json; charset=utf-8');
function fail(string $m): void { echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE); exit; }
if (!isFaculty()) { http_response_code(403); fail('Only a professor can add students.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) { fail('No file was received.'); }
$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) { fail('The file could not be uploaded (' . $f['error'] . ').'); }
if ($f['size'] > 6 * 1024 * 1024) { fail('The file is too big (the limit is 6 MB).'); }
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if ($ext === 'doc') { fail('Old Word files (.doc) cannot be read. Open it in Word and use Save As → Word Document (.docx), then upload that.'); }
if ($ext !== 'docx') { fail('Please upload a .docx file.'); }
if (!class_exists('ZipArchive')) { fail('The server cannot open Word files: turn on extension=zip in php.ini (the same one the PEF import needs), then restart Apache.'); }
$zip = new ZipArchive();
if ($zip->open($f['tmp_name']) !== true) { fail('That does not look like a Word document.'); }
$xml = $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === false) { fail('That Word file has no readable text.'); }
$dom = new DOMDocument();
if (!@$dom->loadXML($xml, LIBXML_NONET)) { fail('That Word file could not be read.'); }
$xp = new DOMXPath($dom);
$xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
$textOf = function (DOMNode $n) use ($xp): string {
    $out = '';
    foreach ($xp->query('.//w:t|.//w:tab|.//w:br', $n) as $t) {
        if ($t->localName === 't') { $out .= $t->textContent; } else { $out .= ($t->localName === 'tab' ? "\t" : ' '); }
    }
    return trim($out);
};
$lines = [];
foreach ($xp->query('//w:body/*') as $node) {
    if ($node->localName === 'p') { $t = $textOf($node); if ($t !== '') { $lines[] = $t; } }
    elseif ($node->localName === 'tbl') {
        foreach ($xp->query('.//w:tr', $node) as $tr) {
            $cells = [];
            foreach ($xp->query('./w:tc', $tr) as $tc) { $cells[] = $textOf($tc); }
            if (array_filter($cells, 'strlen')) { $lines[] = implode("\t", $cells); }
        }
    }
}
echo json_encode(['ok' => true, 'text' => implode("\n", $lines)], JSON_UNESCAPED_UNICODE);
