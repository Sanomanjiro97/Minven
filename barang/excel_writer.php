<?php
class ExcelWriter {
    private $fp = null;
    private $filename;
    
    public function __construct($filename) {
        $this->filename = $filename;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $this->fp = fopen('php://output', 'w');
    }
    
    public function writeRow($row) {
        fputcsv($this->fp, $row);
    }
    
    public function close() {
        fclose($this->fp);
    }
}