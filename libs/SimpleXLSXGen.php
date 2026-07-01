<?php
class SimpleXLSXGen {
    public $curSheet = 0;
    protected $sheets = [];
    protected $SI = [];
    protected $SI_KEYS = [];

    public function __construct() {
        $this->sheets = [['name' => 'Sheet1', 'rows' => []]];
        $this->curSheet = 0;
    }

    public function addSheet(array $rows, $sheetName = null) {
        $this->sheets[$this->curSheet]['name'] = $this->sanitizeSheetName($sheetName ?: ('Sheet' . ($this->curSheet + 1)));
        $this->sheets[$this->curSheet]['rows'] = $rows;
        return $this;
    }

    public function writeSheet(array $rows, $sheetName = null) {
        $this->curSheet = count($this->sheets);
        $this->sheets[] = [
            'name' => $this->sanitizeSheetName($sheetName ?: ('Sheet' . ($this->curSheet + 1))),
            'rows' => $rows,
        ];
        return $this;
    }

    public function downloadAs($filename) {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmp === false) {
            return false;
        }
        $ok = $this->saveAs($tmp);
        if (!$ok) {
            @unlink($tmp);
            return false;
        }

        if (function_exists('ob_get_level') && ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $this->sanitizeFilename($filename) . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        header('Content-Length: ' . (string)filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        return true;
    }

    public function saveAs($filename) {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $this->SI = [];
        $this->SI_KEYS = [];
        $this->buildSharedStrings();

        $zip = new ZipArchive();
        if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addEmptyDir('_rels');
        $zip->addEmptyDir('docProps');
        $zip->addEmptyDir('xl');
        $zip->addEmptyDir('xl/_rels');
        $zip->addEmptyDir('xl/worksheets');

        $zip->addFromString('[Content_Types].xml', $this->_getContentTypes());
        $zip->addFromString('_rels/.rels', $this->_getRels());
        $zip->addFromString('docProps/core.xml', $this->_getCore());
        $zip->addFromString('docProps/app.xml', $this->_getApp());
        $zip->addFromString('xl/workbook.xml', $this->_getWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->_getWorkbookRels());
        $zip->addFromString('xl/styles.xml', $this->_getStyles());
        $zip->addFromString('xl/sharedStrings.xml', $this->_getSharedStrings());

        foreach ($this->sheets as $idx => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($idx + 1) . '.xml', $this->_getWorksheet($idx));
        }

        $zip->close();
        return true;
    }

    protected function buildSharedStrings() {
        foreach ($this->sheets as $sheet) {
            foreach ($sheet['rows'] as $row) {
                if (!is_array($row)) continue;
                foreach ($row as $v) {
                    if (is_string($v) || is_bool($v) || $v === null) {
                        $this->_str2sst((string)$v);
                    }
                }
            }
        }
    }

    protected function _getWorksheet($sheetIndex) {
        $rows = $this->sheets[$sheetIndex]['rows'];
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        foreach ($rows as $r => $row) {
            if (!is_array($row)) $row = [$row];
            $rowNum = $r + 1;
            $xml .= '<row r="' . $rowNum . '">';
            $colIndex = 0;
            foreach ($row as $v) {
                $cell = $this->num2name($colIndex) . $rowNum;
                if (is_int($v) || is_float($v) || (is_string($v) && $v !== '' && is_numeric($v) && preg_match('/^-?\d+(\.\d+)?$/', $v))) {
                    $xml .= '<c r="' . $cell . '"><v>' . $this->escNum($v) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $cell . '" t="s"><v>' . $this->_str2sst((string)$v) . '</v></c>';
                }
                $colIndex++;
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    protected function _getSharedStrings() {
        $count = 0;
        foreach ($this->SI as $s) $count += (int)($s['count'] ?? 1);
        $unique = count($this->SI);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $unique . '">';

        foreach ($this->SI as $s) {
            $xml .= '<si><t xml:space="preserve">' . $this->escXml($s['v']) . '</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }

    protected function _getWorkbook() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>';

        foreach ($this->sheets as $i => $sheet) {
            $name = $this->sanitizeSheetName($sheet['name'] ?: ('Sheet' . ($i + 1)));
            $xml .= '<sheet name="' . $this->escXml($name) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }

        $xml .= '</sheets></workbook>';
        return $xml;
    }

    protected function _getWorkbookRels() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }

        $rid = count($this->sheets) + 1;
        $xml .= '<Relationship Id="rId' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $rid++;
        $xml .= '<Relationship Id="rId' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    protected function _getRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    protected function _getContentTypes() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';

        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        $xml .= '</Types>';
        return $xml;
    }

    protected function _getStyles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    protected function _getCore() {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>minven</dc:creator>'
            . '<cp:lastModifiedBy>minven</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    protected function _getApp() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>minven</Application>'
            . '</Properties>';
    }

    protected function _str2sst($v) {
        $key = $v;
        if (!isset($this->SI_KEYS[$key])) {
            $this->SI_KEYS[$key] = count($this->SI);
            $this->SI[] = ['v' => $v, 'count' => 1];
            return $this->SI_KEYS[$key];
        }
        $idx = $this->SI_KEYS[$key];
        $this->SI[$idx]['count']++;
        return $idx;
    }

    protected function sanitizeSheetName($name) {
        $name = (string)$name;
        $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name);
        $name = trim($name);
        if ($name === '') $name = 'Sheet';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($name, 'UTF-8') > 31) $name = mb_substr($name, 0, 31, 'UTF-8');
        } else {
            if (strlen($name) > 31) $name = substr($name, 0, 31);
        }
        return $name;
    }

    protected function sanitizeFilename($name) {
        $name = (string)$name;
        $name = str_replace(["\r", "\n"], ' ', $name);
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name);
        $name = trim($name);
        if ($name === '') $name = 'export.xlsx';
        if (!preg_match('/\\.xlsx$/i', $name)) $name .= '.xlsx';
        return $name;
    }

    protected function escXml($v) {
        return htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected function escNum($v) {
        if (is_bool($v)) return $v ? '1' : '0';
        if ($v === null) return '0';
        return (string)$v;
    }

    public function num2name($num) {
        $num = (int)$num;
        $name = '';
        while ($num >= 0) {
            $name = chr($num % 26 + 65) . $name;
            $num = intdiv($num, 26) - 1;
        }
        return $name;
    }
}
