<?php
/**
 * @param array $data
 * @param string $fileName
 */
function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $fp = fopen('php://output', 'w');
    foreach ($data as $row) {
        fputcsv($fp, $row, ',', '"', "\\");
        echo "\r\n";
    }
    fclose($fp);
    exit;
}
