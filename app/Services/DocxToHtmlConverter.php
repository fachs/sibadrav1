<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class DocxToHtmlConverter
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const WP_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const V_NS = 'urn:schemas-microsoft-com:vml';

    /** @var array<string, string> */
    private array $rels = [];

    /** @var array<string, array<string, mixed>> */
    private array $styles = [];

    private ZipArchive $zip;

    public function convertToHtml(string $docxPath): string
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException("File Word tidak ditemukan: {$docxPath}");
        }

        $this->zip = new ZipArchive();
        if ($this->zip->open($docxPath) !== true) {
            throw new RuntimeException("Gagal membuka arsip DOCX: {$docxPath}");
        }

        try {
            $documentXml = $this->zip->getFromName('word/document.xml');
            if ($documentXml === false) {
                throw new RuntimeException('Struktur word/document.xml tidak ditemukan di dalam DOCX.');
            }

            $this->rels = $this->parseRelationships();
            $this->styles = $this->parseStyles();

            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = true;
            @$dom->loadXML($documentXml);

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', self::W_NS);
            $xpath->registerNamespace('r', self::R_NS);
            $xpath->registerNamespace('a', self::A_NS);
            $xpath->registerNamespace('wp', self::WP_NS);
            $xpath->registerNamespace('v', self::V_NS);

            $pageCss = $this->extractPageCss($xpath);

            $bodyNode = $xpath->query('/w:document/w:body')->item(0);
            $bodyHtml = '';
            if ($bodyNode instanceof DOMElement) {
                foreach ($bodyNode->childNodes as $child) {
                    if (! ($child instanceof DOMElement)) {
                        continue;
                    }
                    if ($child->localName === 'p') {
                        $bodyHtml .= $this->renderParagraph($child, $xpath);
                    } elseif ($child->localName === 'tbl') {
                        $bodyHtml .= $this->renderTable($child, $xpath);
                    }
                }
            }

            return $this->wrapDocumentHtml($bodyHtml, $pageCss);
        } finally {
            $this->zip->close();
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseRelationships(): array
    {
        $relsXml = $this->zip->getFromName('word/_rels/document.xml.rels');
        if ($relsXml === false) {
            return [];
        }

        $dom = new DOMDocument();
        @$dom->loadXML($relsXml);

        $map = [];
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            $id = $rel->getAttribute('Id');
            $target = $rel->getAttribute('Target');
            if ($id !== '' && $target !== '') {
                $map[$id] = ltrim($target, '/');
            }
        }

        return $map;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function parseStyles(): array
    {
        $stylesXml = $this->zip->getFromName('word/styles.xml');
        if ($stylesXml === false) {
            return [];
        }

        $dom = new DOMDocument();
        @$dom->loadXML($stylesXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W_NS);

        $styles = [];
        foreach ($xpath->query('//w:style') as $styleNode) {
            if (! ($styleNode instanceof DOMElement)) {
                continue;
            }
            $id = $styleNode->getAttributeNS(self::W_NS, 'styleId');
            if ($id === '') {
                continue;
            }
            $info = [];
            $jc = $xpath->query('w:pPr/w:jc', $styleNode)->item(0);
            if ($jc instanceof DOMElement) {
                $info['align'] = $jc->getAttributeNS(self::W_NS, 'val');
            }
            $rPr = $xpath->query('w:rPr', $styleNode)->item(0);
            if ($rPr instanceof DOMElement) {
                if ($xpath->query('w:b', $rPr)->length > 0) {
                    $info['bold'] = true;
                }
                $sz = $xpath->query('w:sz', $rPr)->item(0);
                if ($sz instanceof DOMElement && is_numeric($sz->getAttributeNS(self::W_NS, 'val'))) {
                    $info['size_pt'] = ((float) $sz->getAttributeNS(self::W_NS, 'val')) / 2.0;
                }
            }
            $styles[$id] = $info;
        }

        return $styles;
    }

    private function extractPageCss(DOMXPath $xpath): string
    {
        $sectPr = $xpath->query('/w:document/w:body/w:sectPr')->item(0);
        $marginTop = '18mm';
        $marginRight = '16mm';
        $marginBottom = '18mm';
        $marginLeft = '18mm';
        $size = 'A4 portrait';

        if ($sectPr instanceof DOMElement) {
            $pgSz = $xpath->query('w:pgSz', $sectPr)->item(0);
            if ($pgSz instanceof DOMElement) {
                $orient = $pgSz->getAttributeNS(self::W_NS, 'orient');
                $wTwips = (float) $pgSz->getAttributeNS(self::W_NS, 'w');
                $hTwips = (float) $pgSz->getAttributeNS(self::W_NS, 'h');
                if ($wTwips > 0 && $hTwips > 0) {
                    $wMm = round($wTwips * 25.4 / 1440, 1);
                    $hMm = round($hTwips * 25.4 / 1440, 1);
                    $size = "{$wMm}mm {$hMm}mm";
                } elseif ($orient === 'landscape') {
                    $size = 'A4 landscape';
                }
            }

            $pgMar = $xpath->query('w:pgMar', $sectPr)->item(0);
            if ($pgMar instanceof DOMElement) {
                $top = (float) $pgMar->getAttributeNS(self::W_NS, 'top');
                $right = (float) $pgMar->getAttributeNS(self::W_NS, 'right');
                $bottom = (float) $pgMar->getAttributeNS(self::W_NS, 'bottom');
                $left = (float) $pgMar->getAttributeNS(self::W_NS, 'left');
                if ($top > 0) {
                    $marginTop = round(max(10, min(35, $top * 25.4 / 1440)), 1).'mm';
                }
                if ($right > 0) {
                    $marginRight = round(max(10, min(35, $right * 25.4 / 1440)), 1).'mm';
                }
                if ($bottom > 0) {
                    $marginBottom = round(max(10, min(35, $bottom * 25.4 / 1440)), 1).'mm';
                }
                if ($left > 0) {
                    $marginLeft = round(max(10, min(35, $left * 25.4 / 1440)), 1).'mm';
                }
            }
        }

        return "@page { size: {$size}; margin: {$marginTop} {$marginRight} {$marginBottom} {$marginLeft}; }";
    }

    private function renderParagraph(DOMElement $p, DOMXPath $xpath, bool $inTable = false): string
    {
        $pPr = $xpath->query('w:pPr', $p)->item(0);
        $css = [];
        $pageBreakBefore = false;
        $styleBold = false;
        $styleSizePt = null;

        if ($pPr instanceof DOMElement) {
            if ($xpath->query('w:pageBreakBefore', $pPr)->length > 0) {
                $pageBreakBefore = true;
            }

            $pStyle = $xpath->query('w:pStyle', $pPr)->item(0);
            if ($pStyle instanceof DOMElement) {
                $styleId = $pStyle->getAttributeNS(self::W_NS, 'val');
                if (isset($this->styles[$styleId])) {
                    if (! empty($this->styles[$styleId]['align'])) {
                        $css['text-align'] = $this->mapAlignment($this->styles[$styleId]['align']);
                    }
                    if (! empty($this->styles[$styleId]['bold'])) {
                        $styleBold = true;
                    }
                    if (! empty($this->styles[$styleId]['size_pt'])) {
                        $styleSizePt = $this->styles[$styleId]['size_pt'];
                    }
                }
            }

            $jc = $xpath->query('w:jc', $pPr)->item(0);
            if ($jc instanceof DOMElement) {
                $css['text-align'] = $this->mapAlignment($jc->getAttributeNS(self::W_NS, 'val'));
            }

            $spacing = $xpath->query('w:spacing', $pPr)->item(0);
            if ($spacing instanceof DOMElement) {
                $before = $spacing->getAttributeNS(self::W_NS, 'before');
                $after = $spacing->getAttributeNS(self::W_NS, 'after');
                if (is_numeric($before)) {
                    $css['margin-top'] = round(((float) $before) / 20, 1).'pt';
                }
                if (is_numeric($after)) {
                    $css['margin-bottom'] = round(((float) $after) / 20, 1).'pt';
                }
            }

            $ind = $xpath->query('w:ind', $pPr)->item(0);
            if ($ind instanceof DOMElement && ! $inTable) {
                $left = $ind->getAttributeNS(self::W_NS, 'left');
                $firstLine = $ind->getAttributeNS(self::W_NS, 'firstLine');
                if (is_numeric($left) && (float) $left > 0) {
                    $css['margin-left'] = round(min(120, ((float) $left) / 20), 1).'pt';
                }
                if (is_numeric($firstLine) && (float) $firstLine > 0) {
                    $css['text-indent'] = round(min(60, ((float) $firstLine) / 20), 1).'pt';
                }
            }

            if ($xpath->query('w:numPr', $pPr)->length > 0 && ! isset($css['margin-left'])) {
                $ilvlNode = $xpath->query('w:numPr/w:ilvl', $pPr)->item(0);
                $ilvl = $ilvlNode instanceof DOMElement ? (int) $ilvlNode->getAttributeNS(self::W_NS, 'val') : 0;
                $css['margin-left'] = (($ilvl + 1) * 14).'pt';
            }
        }

        $innerHtml = '';
        $hasPageBreakInside = false;

        foreach ($p->childNodes as $child) {
            if (! ($child instanceof DOMElement)) {
                continue;
            }
            if ($child->localName === 'r') {
                $runHtml = $this->renderRun($child, $xpath, $styleBold, $styleSizePt, $hasPageBreakInside);
                $innerHtml .= $runHtml;
            } elseif ($child->localName === 'hyperlink') {
                foreach ($child->childNodes as $hChild) {
                    if ($hChild instanceof DOMElement && $hChild->localName === 'r') {
                        $innerHtml .= $this->renderRun($hChild, $xpath, $styleBold, $styleSizePt, $hasPageBreakInside);
                    }
                }
            }
        }

        $prefix = $pageBreakBefore ? '<div class="page-break"></div>' : '';

        if (trim(strip_tags($innerHtml, '<img>')) === '') {
            if ($hasPageBreakInside) {
                return $prefix.$innerHtml;
            }

            return $prefix.($inTable ? '' : '<p class="empty-p">&nbsp;</p>');
        }

        $styleAttr = $this->buildStyleAttr($css);

        return $prefix."<p{$styleAttr}>{$innerHtml}</p>\n";
    }

    private function renderRun(DOMElement $r, DOMXPath $xpath, bool $inheritBold, ?float $inheritSizePt, bool &$hasPageBreak): string
    {
        $rPr = $xpath->query('w:rPr', $r)->item(0);
        $css = [];
        $bold = $inheritBold;
        $italic = false;
        $underline = false;

        if ($rPr instanceof DOMElement) {
            $bNode = $xpath->query('w:b', $rPr)->item(0);
            if ($bNode instanceof DOMElement) {
                $val = $bNode->getAttributeNS(self::W_NS, 'val');
                $bold = ($val !== '0' && $val !== 'false');
            }
            $iNode = $xpath->query('w:i', $rPr)->item(0);
            if ($iNode instanceof DOMElement) {
                $val = $iNode->getAttributeNS(self::W_NS, 'val');
                $italic = ($val !== '0' && $val !== 'false');
            }
            $uNode = $xpath->query('w:u', $rPr)->item(0);
            if ($uNode instanceof DOMElement) {
                $val = $uNode->getAttributeNS(self::W_NS, 'val');
                $underline = ($val !== 'none' && $val !== '0' && $val !== 'false');
            }
            $szNode = $xpath->query('w:sz', $rPr)->item(0);
            if ($szNode instanceof DOMElement && is_numeric($szNode->getAttributeNS(self::W_NS, 'val'))) {
                $pt = ((float) $szNode->getAttributeNS(self::W_NS, 'val')) / 2.0;
                $css['font-size'] = max(7, min(28, $pt)).'pt';
            } elseif ($inheritSizePt !== null) {
                $css['font-size'] = max(7, min(28, $inheritSizePt)).'pt';
            }
            $colorNode = $xpath->query('w:color', $rPr)->item(0);
            if ($colorNode instanceof DOMElement) {
                $hex = $colorNode->getAttributeNS(self::W_NS, 'val');
                if ($hex !== '' && strtolower($hex) !== 'auto' && preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
                    $css['color'] = '#'.$hex;
                }
            }
        } elseif ($inheritSizePt !== null) {
            $css['font-size'] = max(7, min(28, $inheritSizePt)).'pt';
        }

        if ($bold) {
            $css['font-weight'] = 'bold';
        }
        if ($italic) {
            $css['font-style'] = 'italic';
        }
        if ($underline) {
            $css['text-decoration'] = 'underline';
        }

        $content = '';
        foreach ($r->childNodes as $child) {
            if (! ($child instanceof DOMElement)) {
                continue;
            }
            switch ($child->localName) {
                case 't':
                    $content .= htmlspecialchars($child->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    break;
                case 'tab':
                    $content .= '&nbsp;&nbsp;&nbsp;&nbsp;';
                    break;
                case 'br':
                    if ($child->getAttributeNS(self::W_NS, 'type') === 'page') {
                        $hasPageBreak = true;
                        $content .= '<div class="page-break"></div>';
                    } else {
                        $content .= '<br>';
                    }
                    break;
                case 'drawing':
                case 'pict':
                    $content .= $this->renderImageFromNode($child, $xpath);
                    break;
            }
        }

        if ($content === '') {
            return '';
        }

        if ($css === []) {
            return $content;
        }

        $styleAttr = $this->buildStyleAttr($css);

        return "<span{$styleAttr}>{$content}</span>";
    }

    private function renderImageFromNode(DOMElement $node, DOMXPath $xpath): string
    {
        $rId = '';
        $blip = $xpath->query('.//a:blip', $node)->item(0);
        if ($blip instanceof DOMElement) {
            $rId = $blip->getAttributeNS(self::R_NS, 'embed');
        }
        if ($rId === '') {
            $imagedata = $xpath->query('.//v:imagedata', $node)->item(0);
            if ($imagedata instanceof DOMElement) {
                $rId = $imagedata->getAttributeNS(self::R_NS, 'id');
            }
        }
        if ($rId === '' || ! isset($this->rels[$rId])) {
            return '';
        }

        $target = $this->rels[$rId];
        $zipPath = str_starts_with($target, 'word/') ? $target : 'word/'.ltrim($target, '/');
        $bytes = $this->zip->getFromName($zipPath);
        if ($bytes === false) {
            return '';
        }

        $ext = strtolower(pathinfo($zipPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        $widthStyle = 'max-width: 100%; height: auto;';
        $extent = $xpath->query('.//wp:extent', $node)->item(0);
        if ($extent instanceof DOMElement) {
            $cx = (float) $extent->getAttribute('cx');
            if ($cx > 0) {
                $px = round($cx / 9525);
                if ($px > 10 && $px <= 680) {
                    $widthStyle = "width: {$px}px; max-width: 100%; height: auto;";
                }
            }
        }

        $b64 = base64_encode($bytes);

        return "<img src=\"data:{$mime};base64,{$b64}\" style=\"{$widthStyle}\" alt=\"\">";
    }

    private function renderTable(DOMElement $tbl, DOMXPath $xpath): string
    {
        $hasTableBorders = $this->tableHasBorders($tbl, $xpath);

        // Extract column widths in twips to compute percentages
        $colWidths = [];
        foreach ($xpath->query('w:tblGrid/w:gridCol', $tbl) as $col) {
            if ($col instanceof DOMElement) {
                $w = (float) $col->getAttributeNS(self::W_NS, 'w');
                $colWidths[] = $w > 0 ? $w : 0;
            }
        }
        $totalGridW = array_sum($colWidths);

        // Pass 1: collect rows and cells metadata to resolve vMerge rowspans
        $rowsData = [];
        $trNodes = $xpath->query('w:tr', $tbl);
        foreach ($trNodes as $rIdx => $tr) {
            if (! ($tr instanceof DOMElement)) {
                continue;
            }
            $isHeader = $xpath->query('w:trPr/w:tblHeader', $tr)->length > 0;
            $cells = [];
            $colCursor = 0;

            foreach ($xpath->query('w:tc', $tr) as $tc) {
                if (! ($tc instanceof DOMElement)) {
                    continue;
                }
                $tcPr = $xpath->query('w:tcPr', $tc)->item(0);
                $colspan = 1;
                $vMerge = null; // null | 'restart' | 'continue'
                $bgHex = null;
                $vAlign = null;
                $cellBorderNone = false;

                if ($tcPr instanceof DOMElement) {
                    $gs = $xpath->query('w:gridSpan', $tcPr)->item(0);
                    if ($gs instanceof DOMElement) {
                        $colspan = max(1, (int) $gs->getAttributeNS(self::W_NS, 'val'));
                    }

                    $vm = $xpath->query('w:vMerge', $tcPr)->item(0);
                    if ($vm instanceof DOMElement) {
                        $vmVal = $vm->getAttributeNS(self::W_NS, 'val');
                        $vMerge = ($vmVal === 'restart') ? 'restart' : 'continue';
                    }

                    $shd = $xpath->query('w:shd', $tcPr)->item(0);
                    if ($shd instanceof DOMElement) {
                        $fill = $shd->getAttributeNS(self::W_NS, 'fill');
                        if ($fill !== '' && strtolower($fill) !== 'auto' && preg_match('/^[0-9a-fA-F]{6}$/', $fill)) {
                            $bgHex = '#'.$fill;
                        }
                    }

                    $va = $xpath->query('w:vAlign', $tcPr)->item(0);
                    if ($va instanceof DOMElement) {
                        $vAlign = $va->getAttributeNS(self::W_NS, 'val');
                    }

                    $tcBorders = $xpath->query('w:tcBorders', $tcPr)->item(0);
                    if ($tcBorders instanceof DOMElement) {
                        $allNone = true;
                        $hasAnyBorderTag = false;
                        foreach (['top', 'bottom', 'left', 'right'] as $side) {
                            $sideNode = $xpath->query("w:{$side}", $tcBorders)->item(0);
                            if ($sideNode instanceof DOMElement) {
                                $hasAnyBorderTag = true;
                                $val = $sideNode->getAttributeNS(self::W_NS, 'val');
                                if ($val !== 'none' && $val !== 'nil') {
                                    $allNone = false;
                                }
                            }
                        }
                        if ($hasAnyBorderTag && $allNone) {
                            $cellBorderNone = true;
                        }
                    }
                }

                $widthPct = null;
                if ($totalGridW > 0) {
                    $spanW = 0;
                    for ($c = $colCursor; $c < $colCursor + $colspan; $c++) {
                        $spanW += $colWidths[$c] ?? 0;
                    }
                    if ($spanW > 0) {
                        $widthPct = round(($spanW / $totalGridW) * 100, 2);
                    }
                }

                $cells[] = [
                    'node' => $tc,
                    'colStart' => $colCursor,
                    'colspan' => $colspan,
                    'rowspan' => 1,
                    'vMerge' => $vMerge,
                    'skip' => false,
                    'bg' => $bgHex,
                    'vAlign' => $vAlign,
                    'borderNone' => $cellBorderNone,
                    'widthPct' => $widthPct,
                ];

                $colCursor += $colspan;
            }

            $rowsData[] = [
                'isHeader' => $isHeader,
                'cells' => $cells,
            ];
        }

        // Pass 2: resolve vMerge rowspans
        $rowCount = count($rowsData);
        for ($r = 0; $r < $rowCount; $r++) {
            foreach ($rowsData[$r]['cells'] as $cIdx => &$cell) {
                if ($cell['vMerge'] === 'continue') {
                    $cell['skip'] = true;
                    // Find nearest ancestor row with matching colStart
                    for ($prev = $r - 1; $prev >= 0; $prev--) {
                        $found = false;
                        foreach ($rowsData[$prev]['cells'] as &$prevCell) {
                            if ($prevCell['colStart'] === $cell['colStart'] && ! $prevCell['skip']) {
                                $prevCell['rowspan']++;
                                $found = true;
                                break;
                            }
                        }
                        unset($prevCell);
                        if ($found) {
                            break;
                        }
                    }
                }
            }
            unset($cell);
        }

        $tableClass = $hasTableBorders ? 'docx-table docx-table--bordered' : 'docx-table docx-table--borderless';
        $html = "<table class=\"{$tableClass}\">\n<tbody>\n";

        foreach ($rowsData as $row) {
            $html .= "<tr>\n";
            foreach ($row['cells'] as $cell) {
                if ($cell['skip']) {
                    continue;
                }
                $attrs = '';
                if ($cell['colspan'] > 1) {
                    $attrs .= ' colspan="'.$cell['colspan'].'"';
                }
                if ($cell['rowspan'] > 1) {
                    $attrs .= ' rowspan="'.$cell['rowspan'].'"';
                }

                $cellCss = [];
                if ($cell['widthPct'] !== null) {
                    $cellCss['width'] = $cell['widthPct'].'%';
                }
                if ($cell['bg'] !== null) {
                    $cellCss['background-color'] = $cell['bg'];
                }
                if ($cell['vAlign'] !== null) {
                    $cellCss['vertical-align'] = match ($cell['vAlign']) {
                        'center' => 'middle',
                        'bottom' => 'bottom',
                        default => 'top',
                    };
                }
                if ($cell['borderNone']) {
                    $cellCss['border'] = 'none';
                }

                $styleAttr = $this->buildStyleAttr($cellCss);
                $cellContent = $this->renderCellContent($cell['node'], $xpath);
                $tag = $row['isHeader'] ? 'th' : 'td';
                $html .= "<{$tag}{$attrs}{$styleAttr}>{$cellContent}</{$tag}>\n";
            }
            $html .= "</tr>\n";
        }

        $html .= "</tbody>\n</table>\n";

        return $html;
    }

    private function renderCellContent(DOMElement $tc, DOMXPath $xpath): string
    {
        $out = '';
        foreach ($tc->childNodes as $child) {
            if (! ($child instanceof DOMElement)) {
                continue;
            }
            if ($child->localName === 'p') {
                $out .= $this->renderParagraph($child, $xpath, true);
            } elseif ($child->localName === 'tbl') {
                $out .= $this->renderTable($child, $xpath);
            }
        }

        return $out !== '' ? $out : '&nbsp;';
    }

    private function tableHasBorders(DOMElement $tbl, DOMXPath $xpath): bool
    {
        $tblBorders = $xpath->query('w:tblPr/w:tblBorders', $tbl)->item(0);
        if ($tblBorders instanceof DOMElement) {
            $hasAny = false;
            $hasVisible = false;
            foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
                $node = $xpath->query("w:{$side}", $tblBorders)->item(0);
                if ($node instanceof DOMElement) {
                    $hasAny = true;
                    $val = $node->getAttributeNS(self::W_NS, 'val');
                    if ($val !== '' && $val !== 'none' && $val !== 'nil') {
                        $hasVisible = true;
                    }
                }
            }
            if ($hasAny) {
                return $hasVisible;
            }
        }

        $tblStyle = $xpath->query('w:tblPr/w:tblStyle', $tbl)->item(0);
        if ($tblStyle instanceof DOMElement) {
            $styleVal = strtolower($tblStyle->getAttributeNS(self::W_NS, 'val'));
            if (str_contains($styleVal, 'grid')) {
                return true;
            }
        }

        // Check if any cell defines visible borders
        foreach ($xpath->query('.//w:tcBorders/*', $tbl) as $b) {
            if ($b instanceof DOMElement) {
                $val = $b->getAttributeNS(self::W_NS, 'val');
                if ($val !== '' && $val !== 'none' && $val !== 'nil') {
                    return true;
                }
            }
        }

        return false;
    }

    private function mapAlignment(string $val): string
    {
        return match (strtolower($val)) {
            'center' => 'center',
            'right', 'end' => 'right',
            'both', 'distribute' => 'justify',
            default => 'left',
        };
    }

    /**
     * @param  array<string, string>  $css
     */
    private function buildStyleAttr(array $css): string
    {
        if ($css === []) {
            return '';
        }
        $parts = [];
        foreach ($css as $k => $v) {
            $parts[] = "{$k}: {$v}";
        }

        return ' style="'.implode('; ', $parts).'"';
    }

    private function wrapDocumentHtml(string $bodyHtml, string $pageCss): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
{$pageCss}
body {
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    font-size: 10pt;
    line-height: 1.35;
    color: #111;
    margin: 0;
    padding: 0;
}
p {
    margin: 0 0 4pt 0;
    padding: 0;
}
p.empty-p {
    margin: 0 0 3pt 0;
    line-height: 0.7;
}
.page-break {
    page-break-before: always;
}
table.docx-table {
    width: 100%;
    border-collapse: collapse;
    margin: 6pt 0 8pt 0;
    font-size: 9pt;
}
table.docx-table--bordered th,
table.docx-table--bordered td {
    border: 0.75pt solid #333;
    padding: 3.5pt 5pt;
    vertical-align: top;
    word-wrap: break-word;
}
table.docx-table--bordered th {
    background-color: #f2f4f7;
    font-weight: bold;
}
table.docx-table--borderless th,
table.docx-table--borderless td {
    border: none;
    padding: 2pt 4pt;
    vertical-align: top;
}
table.docx-table p {
    margin: 0 0 2pt 0;
}
table.docx-table p:last-child {
    margin-bottom: 0;
}
img {
    max-width: 100%;
    height: auto;
}
</style>
</head>
<body>
{$bodyHtml}
</body>
</html>
HTML;
    }
}
