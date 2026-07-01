<?php
class PhpExcelReader {
    public $sheets = array();
    
    public function read($file) {
        $data = array();
        
        if(($handle = fopen($file, "r")) !== FALSE) {
            $row = 1;
            while (($rowData = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $data[$row] = $rowData;
                $row++;
            }
            fclose($handle);
        }
        
        $this->sheets[0]['cells'] = $data;
    }
}