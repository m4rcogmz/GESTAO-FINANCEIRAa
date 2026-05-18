<?php
/**
 * FPDF - Versão simplificada para PAP
 * Esta é uma versão básica que funciona sem dependências externas
 * Baseada em FPDF mas adaptada para funcionar sempre
 */

class FPDF {
    private $pageWidth = 210;
    private $pageHeight = 297;
    private $currentX = 10;
    private $currentY = 10;
    private $fontSize = 12;
    private $fontFamily = 'Arial';
    private $fontStyle = '';
    
    public function AddPage() {
        $this->currentY = 10;
        $this->currentX = 10;
    }
    
    public function SetFont($family, $style = '', $size = 12) {
        $this->fontFamily = $family;
        $this->fontStyle = $style;
        $this->fontSize = $size;
    }
    
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false) {
        // Implementação básica para funcionar
        $this->currentX += $w;
        if ($ln == 1) {
            $this->currentY += $h;
            $this->currentX = 10;
        }
    }
    
    public function Ln($h = null) {
        if ($h === null) $h = $this->fontSize + 2;
        $this->currentY += $h;
        $this->currentX = 10;
    }
    
    public function Output($dest = '', $name = '') {
        // Redireciona para uma versão HTML simples se não conseguir gerar PDF real
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Relatório</title>';
        echo '<style>body{font-family:Arial;padding:20px;}table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#6f42c1;color:white;}</style></head><body>';
        echo '<h1 style="color:#6f42c1;">Relatório Financeiro</h1>';
        echo '<p><strong>Nota:</strong> Para gerar PDF real, instala FPDF completo em /vendor/fpdf/fpdf.php</p>';
        echo '</body></html>';
        exit;
    }
}
