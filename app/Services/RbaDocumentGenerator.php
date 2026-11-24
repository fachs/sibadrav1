<?php

namespace App\Services;

use App\Models\AmbangBatas;
use App\Models\RencanaKebutuhan;
use App\Models\RencanaPembiayaan;
use App\Models\RencanaPendapatan;
use App\Models\UnitBlud;
use App\Models\User;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class RbaDocumentGenerator
{
    public function generate(string $template, string $upt, int $tahun, string $format, array $spm = []): string
    {
        // The template and context are part of the key so another UPT/year can never reuse this document.
        $spmValues = array_map(fn ($item) => [
            'nomor' => $item->nomor,
            'indikator' => $item->indikator,
            'target_sasaran' => $item->target_sasaran,
            'target_capaian' => $item->target_capaian,
        ], $spm);
        $ambang = AmbangBatas::forDocumentYear($upt, $tahun)->get()
            ->map(fn ($item) => [$item->tahun, $item->anggaran, $item->realisasi, $item->selisih, $item->persentase])->all();
        $totalPendapatan = RencanaPendapatan::where('nama_blud', $upt)->where('tahun_anggaran', $tahun)
            ->where(fn ($query) => $query->where('is_hasil_perubahan', false)->orWhereNull('is_hasil_perubahan'))
            ->sum('jumlah');
        $totalBelanja = RencanaKebutuhan::where('nama_blud', $upt)->where('tahun_anggaran', (string) $tahun)
            ->whereNotNull('akun_id')
            ->where(fn ($query) => $query->where('is_hasil_perubahan', false)->orWhereNull('is_hasil_perubahan'))
            ->sum('jumlah_harga');
        $totalPembiayaan = RencanaPembiayaan::where('nama_blud', $upt)->where('tahun_anggaran', $tahun)
            ->sum('jumlah');
        $prakiraan = app(PrakiraanMajuData::class)->rows($upt, $tahun);
        $values = $this->placeholderValues($upt, $tahun);
        $values += [
            '[[Ringkasan Total Pendapatan]]' => number_format((float) $totalPendapatan, 0, ',', '.'),
            '[[Ringkasan Total Belanja]]' => number_format((float) $totalBelanja, 0, ',', '.'),
            '[[Ringkasan Total Pembiayaan]]' => number_format((float) $totalPembiayaan, 0, ',', '.'),
            '[[Ringkasan Surplus Defisit]]' => number_format((float) $totalPendapatan - (float) $totalBelanja, 0, ',', '.'),
        ];
        $appendixContext = $this->appendixContext($upt, $tahun);
        $sourceFingerprint = $this->rbaSourceFingerprint($upt, $tahun);
        $key = hash('sha256', json_encode([hash_file('sha256', $template), $upt, $tahun, $spmValues, $ambang, $totalPendapatan, $totalBelanja, $totalPembiayaan, $prakiraan, $values, $appendixContext, $sourceFingerprint, hash_file('sha256', public_path('images/logo-pemkot.png')), 'v27'], JSON_THROW_ON_ERROR));
        $directory = config('dokumen-rba.cache_path').DIRECTORY_SEPARATOR.$key;
        File::ensureDirectoryExists($directory);
        $lock = fopen($directory.'/generate.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Tidak dapat mengunci pembuatan dokumen RBA.');
        }

        try {
            $word = $directory.'/rba.docx';
            if (! is_file($word)) {
                $temporary = $directory.'/working.docx';
                try {
                    File::copy($template, $temporary);
                    // Keep the UPT name together in the cover text box.
                    $coverUpt = preg_replace('/\s+/u', "\u{00A0}", trim($upt));
                    $values['[[Nama Puskesmas]]'] = $coverUpt;
                    $this->fill($temporary, $values, $spm, $upt, $tahun, $ambang, $prakiraan, $appendixContext);
                    app(RbaDocumentFieldUpdater::class)->update($temporary);
                    File::move($temporary, $word);
                } finally {
                    File::delete($temporary);
                }
            }
            if ($format === 'word') {
                return $word;
            }

            $pdf = $directory.'/rba.pdf';
            if (! is_file($pdf)) {
                $temporary = $directory.'/working.pdf';
                try {
                    app(RbaDocumentPdfConverter::class)->convert($word, $temporary);
                    if (! is_file($temporary) || file_get_contents($temporary, false, null, 0, 5) !== '%PDF-') {
                        throw new RuntimeException('Konversi dokumen RBA tidak menghasilkan PDF yang valid.');
                    }
                    File::move($temporary, $pdf);
                } finally {
                    File::delete($temporary);
                }
            }

            return $pdf;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function rbaSourceFingerprint(string $upt, int $tahun): string
    {
        $pendapatan = RencanaPendapatan::where('nama_blud', $upt)->where('tahun_anggaran', $tahun)
            ->where(fn ($query) => $query->where('is_hasil_perubahan', false)->orWhereNull('is_hasil_perubahan'))
            ->orderBy('id')->get(['kode_rek', 'uraian', 'jumlah'])->toArray();
        $belanja = RencanaKebutuhan::with('akuns:id,kode_rek,uraian')
            ->where('nama_blud', $upt)->where('tahun_anggaran', (string) $tahun)->whereNotNull('akun_id')
            ->where(fn ($query) => $query->where('is_hasil_perubahan', false)->orWhereNull('is_hasil_perubahan'))
            ->orderBy('id')->get(['akun_id', 'uraian', 'spesifikasi', 'jumlah_harga'])->toArray();
        $pembiayaan = RencanaPembiayaan::where('nama_blud', $upt)->where('tahun_anggaran', $tahun)
            ->orderBy('id')->get(['kode_rek', 'uraian', 'jumlah'])->toArray();

        return hash('sha256', json_encode([$pendapatan, $belanja, $pembiayaan], JSON_THROW_ON_ERROR));
    }

    private function fill(string $path, array $values, array $spm = [], string $upt = '', int $tahun = 0, array $ambang = [], array $prakiraan = [], array $appendixContext = []): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Template Word RBA tidak dapat dibuka.');
        }
        try {
            $hasAppendices = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $part = $zip->getNameIndex($i);
                if (! preg_match('~^word/.*\.xml$~', $part)) {
                    continue;
                }
                $xml = $zip->getFromIndex($i);
                $templateXml = $part === 'word/document.xml' ? $this->replaceTemplateBudgetYear($xml, $tahun) : $xml;
                $filled = $this->replaceText($templateXml, array_merge($values, $this->spmPlaceholderValues($spm)));
                if ($part === 'word/document.xml') {
                    $filled = $this->replaceConclusionSignature($filled, $values);
                    $filled = $this->widenCoverNameBox($filled);
                    $filled = $this->replaceSpmRows($filled, $spm);
                    $filled = $this->replaceRbaTables($filled, $upt, $tahun, $ambang, $prakiraan);
                    $hasAppendices = str_contains($filled, '{HALAMAN HASIL UNDUH RBA PENDAPATAN}');
                    if ($hasAppendices) {
                        $filled = $this->appendRbaPdfTables($filled, $upt, $tahun, $values, $appendixContext);
                    }
                }
                if ($filled !== $xml && ! $zip->addFromString($part, $filled)) {
                    throw new RuntimeException('Isian template Word RBA gagal disimpan.');
                }
            }
            if ($hasAppendices) {
                $this->addAppendixLogo($zip);
            }
        } finally {
            $zip->close();
        }
    }

    private function appendixContext(string $upt, int $tahun): array
    {
        $unit = UnitBlud::where('nama', $upt)->first();
        $kinerja = $unit?->kinerjaKegiatans()->where('tahun', $tahun)->first();

        return [
            'alamat' => (string) ($unit?->alamat ?? ''),
            'kinerja' => $kinerja?->only([
                'capaian_program_tolak_ukur', 'capaian_program_target',
                'masukan_tolak_ukur', 'masukan_target',
                'keluaran_tolak_ukur', 'keluaran_target',
            ]) ?? [],
        ];
    }

    private function appendRbaPdfTables(string $xml, string $upt, int $tahun, array $values, array $context): string
    {
        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $tables);
        if (! isset($tables[0][2], $tables[0][3], $tables[0][4])) {
            return $xml;
        }

        $appendices = [
            '{HALAMAN HASIL UNDUH RBA PENDAPATAN}' => ['PENDAPATAN', $tables[0][2]],
            '{HALAMAN HASIL UNDUH RBA BELANJA}' => ['BELANJA', $tables[0][3]],
            '{HALAMAN HASIL UNDUH RBA PEMBIAYAAN}' => ['PEMBIAYAAN', $tables[0][4]],
        ];
        $logoNumber = 9001;

        return preg_replace_callback('~<w:p(?:\s[^>]*)?>.*?</w:p>~s', function ($match) use ($appendices, $upt, $tahun, $values, $context, &$logoNumber) {
            preg_match_all('~<w:t(?:\s[^>]*)?>(.*?)</w:t>~s', $match[0], $texts);
            $text = html_entity_decode(implode('', $texts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $appendix = $appendices[trim($text)] ?? null;
            if ($appendix === null) {
                return $match[0];
            }

            [$type, $table] = $appendix;
            $pageBreak = '<w:p><w:pPr><w:pageBreakBefore/><w:spacing w:after="0"/></w:pPr></w:p>';
            $kop = $this->appendixKop($type, $upt, $tahun, $context['alamat'] ?? '', $logoNumber++);
            $kinerja = $type === 'BELANJA' ? $this->appendixKinerja($context['kinerja'] ?? []) : '';
            $signature = $this->appendixSignature($upt, $values);

            return $pageBreak.$kop.$kinerja.$table.$signature;
        }, $xml);
    }

    private function appendixKop(string $type, string $upt, int $tahun, string $alamat, int $logoNumber): string
    {
        $government = $this->appendixParagraph('PEMERINTAH KOTA SUKABUMI', true, 23)
            .$this->appendixParagraph('DINAS KESEHATAN', true, 23)
            .$this->appendixParagraph('BLUD UPTD '.mb_strtoupper($upt), true, 25)
            .$this->appendixParagraph($alamat, false, 18);
        $first = $this->wordTableRow([
            $this->appendixCell($this->appendixLogo($logoNumber), 1500),
            $this->appendixCell($government, 7000),
            $this->appendixCell($this->appendixParagraph(''), 1500),
        ], true);
        $title = $this->appendixParagraph('RINCIAN BISNIS DAN ANGGARAN', false, 22)
            .$this->appendixParagraph('ANGGARAN '.$type, false, 22)
            .$this->appendixParagraph('TAHUN ANGGARAN '.$tahun, false, 22);
        $second = $this->wordTableRow([$this->appendixCell($title, 10000, 3)], true);

        return '<w:tbl><w:tblPr><w:tblW w:w="10000" w:type="dxa"/></w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="1500"/><w:gridCol w:w="7000"/><w:gridCol w:w="1500"/></w:tblGrid>'
            .$first.$second.'</w:tbl>';
    }

    private function appendixKinerja(array $kinerja): string
    {
        $rows = [
            ['Program', '1.02.01 - PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA'],
            ['Kegiatan', '1.02.01.2.10 - Peningkatan Pelayanan BLUD'],
            ['Sub Kegiatan', '1.02.01.2.10.0001 - Pelayanan dan Penunjang Pelayanan BLUD'],
            ['Sumber Dana', 'BLUD'],
        ];
        $tableRows = [];
        foreach ($rows as [$label, $value]) {
            $tableRows[] = $this->wordTableRow([
                $this->wordTableCell($label, 2500, ['bold' => true]),
                $this->wordTableCell($value, 7500, ['gridSpan' => 2]),
            ]);
        }
        $tableRows[] = $this->wordTableRow([
            $this->wordTableCell('Indikator & Tolak Ukur Kinerja Kegiatan BLUD', 10000,
                ['gridSpan' => 3, 'bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
        ]);
        $tableRows[] = $this->wordTableRow([
            $this->wordTableCell('Indikator', 2500, ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Tolak Ukur Kinerja', 5000, ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Target Kinerja', 2500, ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
        ]);
        foreach ([
            ['Capaian Program', 'capaian_program_tolak_ukur', 'capaian_program_target'],
            ['Masukan', 'masukan_tolak_ukur', 'masukan_target'],
            ['Keluaran', 'keluaran_tolak_ukur', 'keluaran_target'],
        ] as [$label, $measure, $target]) {
            $tableRows[] = $this->wordTableRow([
                $this->wordTableCell($label, 2500, ['bold' => true]),
                $this->wordTableCell((string) ($kinerja[$measure] ?? ''), 5000),
                $this->wordTableCell((string) ($kinerja[$target] ?? ''), 2500),
            ]);
        }
        $tableRows[] = $this->wordTableRow([
            $this->wordTableCell('Kelompok Sasaran Kegiatan', 2500, ['bold' => true]),
            $this->wordTableCell(':', 7500, ['gridSpan' => 2]),
        ]);

        return '<w:tbl><w:tblPr><w:tblW w:w="10000" w:type="dxa"/></w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="2500"/><w:gridCol w:w="5000"/><w:gridCol w:w="2500"/></w:tblGrid>'
            .implode('', $tableRows).'</w:tbl>';
    }

    private function appendixSignature(string $upt, array $values): string
    {
        $date = (string) ($values['{Tanggal}'] ?? '');
        $name = (string) ($values['{Nama Kepala Puskesmas}'] ?? '');
        $nip = (string) ($values['{NIP Kepala Puskesmas}'] ?? '');
        $right = $this->appendixParagraph('Sukabumi, '.$date, false, 19, 'left')
            .$this->appendixParagraph('Kepala '.$upt, false, 19, 'left')
            .'<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="1200" w:lineRule="exact"/></w:pPr>'
            .'<w:r><w:t xml:space="preserve"> </w:t></w:r></w:p>'
            .$this->appendixParagraph($name, true, 19, 'left')
            .$this->appendixParagraph('NIP. '.$nip, false, 19, 'left');

        return '<w:p><w:pPr><w:spacing w:after="160"/></w:pPr></w:p>'
            .'<w:tbl><w:tblPr><w:tblW w:w="10000" w:type="dxa"/><w:tblBorders><w:top w:val="nil"/>'
            .'<w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="6000"/><w:gridCol w:w="4000"/></w:tblGrid>'
            .'<w:tr><w:trPr><w:cantSplit/></w:trPr>'
            .'<w:tc><w:tcPr><w:tcW w:w="6000" w:type="dxa"/></w:tcPr><w:p/></w:tc>'
            .'<w:tc><w:tcPr><w:tcW w:w="4000" w:type="dxa"/></w:tcPr>'.$right.'</w:tc>'
            .'</w:tr></w:tbl>';
    }

    private function appendixCell(string $paragraphs, int $width, int $span = 1): string
    {
        $spanXml = $span > 1 ? '<w:gridSpan w:val="'.$span.'"/>' : '';

        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'.$spanXml
            .'<w:tcBorders><w:top w:val="single"/><w:left w:val="single"/>'
            .'<w:bottom w:val="single"/><w:right w:val="single"/></w:tcBorders>'
            .'<w:vAlign w:val="center"/></w:tcPr>'.$paragraphs.'</w:tc>';
    }

    private function appendixParagraph(string $value, bool $bold = false, int $size = 18, string $align = 'center'): string
    {
        $encoded = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<w:p><w:pPr><w:jc w:val="'.$align.'"/><w:spacing w:after="0"/></w:pPr>'
            .'<w:r><w:rPr>'.($bold ? '<w:b/>' : '').'<w:sz w:val="'.$size.'"/></w:rPr>'
            .'<w:t xml:space="preserve">'.$encoded.'</w:t></w:r></w:p>';
    }

    private function appendixLogo(int $number): string
    {
        return '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
            .'<wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="760000" cy="760000"/>'
            .'<wp:docPr id="'.$number.'" name="Logo Kota Sukabumi"/>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="'.$number.'" name="Logo Kota Sukabumi"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="rIdRbaLogo"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="760000" cy="760000"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            .'</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    private function addAppendixLogo(ZipArchive $zip): void
    {
        $logo = file_get_contents(public_path('images/logo-pemkot.png'));
        $relationships = $zip->getFromName('word/_rels/document.xml.rels');
        if ($logo === false || $relationships === false) {
            throw new RuntimeException('Logo atau relasi dokumen RBA tidak tersedia.');
        }
        $relationship = '<Relationship Id="rIdRbaLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/rba-logo-pemkot.png"/>';
        $relationships = str_replace('</Relationships>', $relationship.'</Relationships>', $relationships);
        if (! $zip->addFromString('word/_rels/document.xml.rels', $relationships)
            || ! $zip->addFromString('word/media/rba-logo-pemkot.png', $logo)) {
            throw new RuntimeException('Logo kop lampiran RBA gagal disimpan.');
        }
    }

    private function replaceTemplateBudgetYear(string $xml, int $tahun): string
    {
        if ($tahun === 2027) {
            return $xml;
        }

        do {
            $previous = $xml;
            $xml = $this->replaceFirstTextPattern($xml, '/(?<!\d)2027(?!\d)/u', (string) $tahun);
        } while ($xml !== $previous);

        return $xml;
    }

    private function replaceConclusionSignature(string $xml, array $values): string
    {
        $xml = $this->replaceFirstTextPattern($xml, '/dr\. Kepala Sosialisasi15/u',
            (string) ($values['{Nama Kepala Puskesmas}'] ?? ''));

        return $this->replaceFirstTextPattern($xml, '/NIP\.\s*19800101\s*200501\s*1\s*015/u',
            'NIP. '.($values['{NIP Kepala Puskesmas}'] ?? ''));
    }

    private function replaceText(string $xml, array $values): string
    {
        // Word can split a placeholder over several runs, including inside text boxes.
        // Patch only the text contents, preserving every run and all other package parts.
        preg_match_all('~<w:t(?:\s[^>]*)?>(.*?)</w:t>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        $nodes = [];
        $text = '';
        foreach ($matches[1] as [$encoded, $offset]) {
            $decoded = html_entity_decode($encoded, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $nodes[] = ['offset' => $offset, 'encoded' => $encoded, 'text' => $decoded,
                'start' => mb_strlen($text), 'length' => mb_strlen($decoded)];
            $text .= $decoded;
        }
        preg_match_all('~\[\[[^\[\]]+\]\]|\{[^{}]+\}~u', $text, $slots, PREG_OFFSET_CAPTURE);
        $replacements = [];
        foreach ($slots[0] as [$slot, $byteOffset]) {
            $value = $this->placeholderValue($slot, $values);
            if ($value !== null) {
                $replacements[] = [$slot, $byteOffset, $value];
            }
        }
        foreach (array_reverse($replacements) as [$slot, $byteOffset, $replacement]) {
            $start = mb_strlen(substr($text, 0, $byteOffset));
            $end = $start + mb_strlen($slot);
            foreach ($nodes as &$node) {
                $from = max($start, $node['start']);
                $to = min($end, $node['start'] + $node['length']);
                if ($from < $to) {
                    $local = $from - $node['start'];
                    $node['text'] = mb_substr($node['text'], 0, $local)
                        .($from === $start ? $replacement : '')
                        .mb_substr($node['text'], $to - $node['start']);
                }
            }
            unset($node);
        }
        foreach (array_reverse($nodes) as $node) {
            if (html_entity_decode($node['encoded'], ENT_QUOTES | ENT_XML1, 'UTF-8') !== $node['text']) {
                $xml = substr_replace($xml, htmlspecialchars($node['text'], ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    $node['offset'], strlen($node['encoded']));
            }
        }

        return $xml;
    }

    private function widenCoverNameBox(string $xml): string
    {
        // The template's 219 pt box wraps longer UPT names into the budget year.
        $xml = preg_replace_callback('~<wp:anchor\b.*?<wp:docPr id="217"[^>]*/>.*?</wp:anchor>~s',
            function ($match) {
                $box = str_replace('<wp:posOffset>3467100</wp:posOffset>', '<wp:posOffset>2825750</wp:posOffset>', $match[0]);
                $box = str_replace('cx="2781300" cy="1404620"', 'cx="4064000" cy="1404620"', $box);
                return $box;
            }, $xml, 1);

        return preg_replace_callback('~<v:shape\b[^>]*id="_x0000_s1027"[^>]*>~s',
            fn ($match) => str_replace(['margin-left:273pt', 'width:219pt'],
                ['margin-left:222.5pt', 'width:320pt'], $match[0]), $xml, 1);
    }

    private function placeholderValue(string $slot, array $values): ?string
    {
        $normalizedSlot = $this->normalizePlaceholder($slot);
        foreach ($values as $placeholder => $value) {
            if ($this->normalizePlaceholder((string) $placeholder) === $normalizedSlot) {
                return (string) $value;
            }
        }

        return null;
    }

    private function normalizePlaceholder(string $placeholder): string
    {
        $placeholder = trim($placeholder);
        if (str_starts_with($placeholder, '{') && str_ends_with($placeholder, '}')) {
            $inner = trim(substr($placeholder, 1, -1));
            $inner = preg_replace('/\s+/u', ' ', $inner);
            $inner = preg_replace('/(?<=\d)\s+(?=\d)/u', '', $inner);

            return '{'.strtolower($inner).'}';
        }

        return strtolower(preg_replace('/\s+/u', ' ', $placeholder));
    }

    private function spmPlaceholderValues(array $spm): array
    {
        $saved = collect($spm)->keyBy(fn ($item) => (int) (is_array($item) ? $item['nomor'] : $item->nomor));
        $values = [];
        foreach (range(1, count(config('spm.indicators', []))) as $nomor) {
            $target = $saved->get($nomor);
            $sasaran = $target === null ? 0 : (is_array($target) ? $target['target_sasaran'] : $target->target_sasaran);
            $capaian = $target === null ? 0 : (is_array($target) ? $target['target_capaian'] : $target->target_capaian);
            $values['{Target Sasaran '.$nomor.'}'] = $this->formatNumber($sasaran);
            $values['{Target Capaian '.$nomor.'}'] = $this->formatNumber($capaian).'%';
        }

        return $values;
    }

    private function replaceSpmRows(string $xml, array $spm): string
    {
        $saved = collect($spm)->keyBy(fn ($item) => (int) (is_array($item) ? $item['nomor'] : $item->nomor));
        foreach (config('spm.indicators', []) as $index => $indicatorText) {
            $target = $saved->get($index + 1);
            $sasaran = $target === null ? 0 : (is_array($target) ? $target['target_sasaran'] : $target->target_sasaran);
            $capaian = $target === null ? 0 : (is_array($target) ? $target['target_capaian'] : $target->target_capaian);
            $indicator = htmlspecialchars((string) $indicatorText, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $position = strpos($xml, $indicator);
            if ($position === false) continue;
            $beforeIndicator = substr($xml, 0, $position);
            preg_match_all('~<w:tr(?:\s[^>]*)?>~', $beforeIndicator, $rowStarts, PREG_OFFSET_CAPTURE);
            $rowStart = empty($rowStarts[0]) ? false : end($rowStarts[0])[1];
            $rowEnd = strpos($xml, '</w:tr>', $position);
            if ($rowStart === false || $rowEnd === false) continue;
            $rowEnd += strlen('</w:tr>');
            $row = substr($xml, $rowStart, $rowEnd - $rowStart);
            preg_match_all('~<w:tc(?:\s[^>]*)?>.*?</w:tc>~s', $row, $cells);
            if (count($cells[0]) < 4) continue;
            $cells[0][2] = $this->replaceFirstTextNode($cells[0][2], $this->formatNumber($sasaran));
            $cells[0][3] = $this->replaceFirstTextNode($cells[0][3], $this->formatNumber($capaian).'%');
            $row = substr($xml, $rowStart, $rowEnd - $rowStart);
            $openingEnd = strpos($row, '>') + 1;
            $closingStart = strrpos($row, '</w:tr>');
            $updatedRow = substr($row, 0, $openingEnd).implode('', $cells[0]).substr($row, $closingStart);
            $xml = substr_replace($xml, $updatedRow, $rowStart, $rowEnd - $rowStart);
        }

        return $xml;
    }

    private function replaceRbaTables(string $xml, string $upt, int $tahun, array $ambang, array $prakiraan): string
    {
        $pendapatan = RencanaPendapatan::with('jenisPelayanans')->where('nama_blud', $upt)->where('tahun_anggaran', $tahun)
            ->where(fn ($query) => $query->where('is_hasil_perubahan', false)->orWhereNull('is_hasil_perubahan'))
            ->get()->groupBy('kode_rek')->map(function ($items) {
                return [
                    'kode' => (string) $items->first()->kode_rek,
                    'uraian' => (string) $items->first()->uraian,
                    'jumlah' => (float) $items->sum('jumlah'),
                    'details' => $items->flatMap(fn ($item) => $item->jenisPelayanans)->map(fn ($detail) => [
                        'uraian' => (string) $detail->uraian,
                        'volume' => (float) $detail->volume_1,
                        'satuan' => (string) $detail->satuan_1,
                        'harga_satuan' => (float) $detail->harga_satuan,
                        'jumlah' => (float) $detail->jumlah,
                    ])->values()->all(),
                ];
            })->values()->all();

        $belanja = RencanaKebutuhan::with('akuns')->where('nama_blud', $upt)->where('tahun_anggaran', (string) $tahun)
            ->whereNotNull('akun_id')->where(fn ($query) => $query->where('is_hasil_perubahan', false)->orWhereNull('is_hasil_perubahan'))
            ->get()->groupBy(fn ($item) => optional($item->akuns)->kode_rek ?: 'Lainnya')
            ->map(fn ($items, $kode) => [
                'kode' => (string) $kode,
                'uraian' => (string) (optional($items->first()->akuns)->uraian ?: $items->first()->uraian),
                'jumlah' => (float) $items->sum('jumlah_harga'),
                'details' => $items->map(fn ($item) => [
                    'uraian' => (string) $item->uraian,
                    'spesifikasi' => (string) ($item->spesifikasi ?? ''),
                    'volume' => (float) $item->jumlah,
                    'satuan' => (string) $item->satuan,
                    'harga_satuan' => (float) $item->harga_satuan,
                    'jumlah' => (float) $item->jumlah_harga,
                ])->values()->all(),
            ])->values()->all();

        $pembiayaan = RencanaPembiayaan::where('nama_blud', $upt)->where('tahun_anggaran', $tahun)
            ->get()->groupBy('kode_rek')->map(fn ($items) => [
                'kode' => (string) $items->first()->kode_rek,
                'uraian' => (string) $items->first()->uraian,
                'jumlah' => (float) $items->sum('jumlah'),
            ])->values()->all();

        $totals = [
            'pendapatan' => array_sum(array_column($pendapatan, 'jumlah')),
            'belanja' => array_sum(array_column($belanja, 'jumlah')),
            'pembiayaan' => array_sum(array_column($pembiayaan, 'jumlah')),
        ];
        $xml = $this->replacePendapatanTable($xml, $pendapatan);
        $xml = $this->replaceBelanjaTable($xml, $belanja);
        $xml = $this->replacePembiayaanTable($xml, $pembiayaan);
        $xml = $this->replaceRingkasanTable($xml, $pendapatan, $belanja, $pembiayaan);
        $xml = $this->replaceRincianTable($xml, $pendapatan, $belanja, $pembiayaan);
        $xml = $this->replaceAmbangTable($xml, $ambang);
        $xml = $this->replaceAmbangNarrative($xml, $totals['pendapatan'], $ambang);
        $xml = $this->replaceKesimpulanNarrative($xml, $tahun, $totals['pendapatan'], $ambang);
        $xml = $this->replaceBelanjaNarrativeAmount($xml, $totals['belanja']);
        $xml = $this->replacePrakiraanNarrativeYears($xml, $tahun);

        return $this->replaceTablesByIndex($xml, [
            8 => [array_map(fn ($row, $i) => [$i + 1, $row['kode_rek'], $row['uraian'], $this->formatDisplayNumber($row['nilai_baseline']), $this->formatDisplayNumber($row['nilai_tahun_1']), $this->formatDisplayNumber($row['nilai_tahun_2'])], $prakiraan, array_keys($prakiraan)),
                ['TOTAL PRAKIRAAN MAJU', $this->formatDisplayNumber(array_sum(array_column($prakiraan, 'nilai_baseline'))), $this->formatDisplayNumber(array_sum(array_column($prakiraan, 'nilai_tahun_1'))), $this->formatDisplayNumber(array_sum(array_column($prakiraan, 'nilai_tahun_2')))],
                ['No', 'Kode', 'Nama Jenis Belanja', 'Tahun '.$tahun.' (Rp)', 'Tahun '.($tahun + 1).' (Rp)', 'Tahun '.($tahun + 2).' (Rp)']],
        ]);
    }

    private function replacePrakiraanNarrativeYears(string $xml, int $tahun): string
    {
        $xml = $this->replaceFirstTextPattern($xml, '/untuk tahun \d{4} dan \d{4}/iu',
            'untuk tahun '.($tahun + 1).' dan '.($tahun + 2));
        $xml = $this->replaceFirstTextPattern($xml, '/dengan mempertimbangkan laju pertumbuhan sebesar 1-3%/iu',
            'berdasarkan rencana belanja dan proyeksi yang ditetapkan');
        $xml = $this->replaceFirstTextPattern($xml,
            '/Proyeksi tahun \d{4} dan \d{4} menggunakan laju pertumbuhan 1-3% dari tahun sebelumnya/iu',
            'Proyeksi tahun '.($tahun + 1).' dan '.($tahun + 2).' mengikuti nilai Prakiraan Maju yang ditetapkan');

        return $this->replaceFirstTextPattern($xml,
            '/Perhitungan berdasarkan data anggaran sub kegiatan yang bersumber dari pendapatan BLUD/iu',
            'Nilai tahun dasar diambil dari total RBA Belanja pada tahun anggaran terpilih');
    }

    private function replaceBelanjaNarrativeAmount(string $xml, float $totalBelanja): string
    {
        return $this->replaceFirstTextPattern(
            $xml,
            '/Total belanja sebesar Rp\.\s*[\d.,]+/iu',
            'Total belanja sebesar Rp. '.$this->formatDisplayNumber($totalBelanja)
        );
    }

    private function replaceAmbangNarrative(string $xml, float $totalPendapatan, array $ambang): string
    {
        $jumlahPersentase = array_sum(array_column($ambang, 4));
        $jumlahData = count($ambang);
        $persentase = $this->ambangPersentase($ambang);
        $nilaiAmbang = round($totalPendapatan * $persentase / 100);
        $batasBelanja = $totalPendapatan + $nilaiAmbang;
        $pendapatan = $this->formatPendapatanNumber($totalPendapatan);
        $ambangFormatted = $this->formatPendapatanNumber($nilaiAmbang);
        $batasFormatted = $this->formatPendapatanNumber($batasBelanja);
        $persentaseFormatted = number_format($persentase, 2, ',', '.').'%';
        $jumlahPersentaseFormatted = number_format($jumlahPersentase, 2, ',', '.');

        $xml = $this->replaceFirstTextPattern($xml,
            '/\([\d,.]+\s*:\s*\d+\)\s*=\s*[\d,.]+%/u',
            '('.$jumlahPersentaseFormatted.' : '.$jumlahData.') = '.$persentaseFormatted);
        $xml = $this->replaceFirstTextPattern($xml,
            '/misal diperkirakan Rp\.\s*[\d.]+,-/iu',
            'misal diperkirakan Rp.'.$pendapatan.',-');
        $xml = $this->replaceFirstTextPattern($xml,
            '/besaran ambang batas\s+[\d,.]+%\s*X\s*Rp\.[\d.]+,-\s*=\s*Rp\.[\d.]+,-/iu',
            'besaran ambang batas '.$persentaseFormatted.' X Rp.'.$pendapatan.',- = Rp.'.$ambangFormatted.',-');
        $xml = $this->replaceFirstTextPattern($xml,
            '/apabila realisasi belanja BLUD masih dibawah\s*\(Rp\.[\d.]+,-\s*\+\s*Rp\.[\d.]+,-\)\s*=\s*Rp\.[\d.]+,-/iu',
            'apabila realisasi belanja BLUD masih dibawah (Rp.'.$pendapatan.',- + Rp.'.$ambangFormatted.',-) = Rp.'.$batasFormatted.',-');

        return $this->replaceFirstTextPattern($xml,
            '/Sedangkan apabila pendapatan melebihi Rp\.[\d.]+,-/iu',
            'Sedangkan apabila pendapatan melebihi Rp.'.$batasFormatted.',-');
    }

    private function replaceKesimpulanNarrative(string $xml, int $tahun, float $totalPendapatan, array $ambang): string
    {
        $pendapatan = $this->formatPendapatanNumber($totalPendapatan);
        $persentase = number_format($this->ambangPersentase($ambang), 2, ',', '.').'%';

        $xml = $this->replaceFirstTextPattern($xml,
            '/Tahun \d{4} diajukan sebesar Rp\.[\d.]+,-/iu',
            'Tahun '.$tahun.' diajukan sebesar Rp.'.$pendapatan.',-');

        return $this->replaceFirstTextPattern($xml,
            '/ambang batas RBA yang kami tetapkan sebesar [\d,.]+%/iu',
            'ambang batas RBA yang kami tetapkan sebesar '.$persentase);
    }

    private function ambangPersentase(array $ambang): float
    {
        return count($ambang) > 0 ? array_sum(array_column($ambang, 4)) / count($ambang) : 0;
    }

    private function replaceRingkasanTable(string $xml, array $pendapatan, array $belanja, array $pembiayaan): string
    {
        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        if (! isset($matches[0][5])) {
            return $xml;
        }

        $original = $matches[0][5][0];
        preg_match('~<w:tblPr(?:\s[^>]*)?>.*?</w:tblPr>~s', $original, $properties);
        $tableProperties = $properties[0] ?? '<w:tblPr><w:tblW w:w="10000" w:type="dxa"/></w:tblPr>';
        $widths = [500, 500, 500, 5500, 3000];
        $grid = '<w:tblGrid>'.implode('', array_map(fn ($width) => '<w:gridCol w:w="'.$width.'"/>', $widths)).'</w:tblGrid>';
        $headerFill = 'F8F9FA';
        $rows = [];

        $rows[] = $this->wordTableRow([
            $this->wordTableCell('Kode', 1500, ['gridSpan' => 3, 'bold' => true, 'align' => 'center', 'fill' => $headerFill]),
            $this->wordTableCell('Uraian', 5500, ['bold' => true, 'align' => 'center', 'fill' => $headerFill]),
            $this->wordTableCell('Jumlah (Rp)', 3000, ['bold' => true, 'align' => 'center', 'fill' => $headerFill]),
        ], true);
        $rows[] = $this->wordTableRow([
            $this->wordTableCell('1', 1500, ['gridSpan' => 3, 'bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']),
            $this->wordTableCell('2', 5500, ['bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']),
            $this->wordTableCell('3', 3000, ['bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']),
        ], true);

        $pendapatanGroups = $this->buildRingkasanGroups($pendapatan);
        $belanjaGroups = $this->buildRingkasanGroups($belanja);
        $pembiayaanGroups = $this->buildRingkasanGroups($pembiayaan);
        $totalPendapatan = array_sum(array_column($pendapatan, 'jumlah'));
        $totalBelanja = array_sum(array_column($belanja, 'jumlah'));
        $totalPembiayaan = array_sum(array_column($pembiayaan, 'jumlah'));
        $surplusDefisit = $totalPendapatan - $totalBelanja;
        $penerimaanPembiayaan = $this->ringkasanGroupTotal($pembiayaanGroups, '6.1');
        $pengeluaranPembiayaan = $this->ringkasanGroupTotal($pembiayaanGroups, '6.2');
        $pembiayaanNetto = $penerimaanPembiayaan - $pengeluaranPembiayaan;
        $silpa = $surplusDefisit + $pembiayaanNetto;

        $this->appendRingkasanSection($rows, '4', 'PENDAPATAN BLUD', $totalPendapatan, $pendapatanGroups);
        $this->appendRingkasanSection($rows, '5', 'BELANJA DAERAH', $totalBelanja, $belanjaGroups);
        $rows[] = $this->ringkasanSummaryRow(['', '', '', 'Total Belanja', $this->formatRingkasanNumber($totalBelanja)]);
        $rows[] = $this->ringkasanSummaryRow(['', '', '', 'SURPLUS/ DEFISIT', $this->formatRingkasanNumber($surplusDefisit)]);
        $this->appendRingkasanSection($rows, '6', 'PEMBIAYAAN', $totalPembiayaan, $pembiayaanGroups);
        $rows[] = $this->ringkasanSummaryRow(['', '', '', 'Pembiayaan Neto', $this->formatRingkasanNumber($pembiayaanNetto)]);
        $rows[] = $this->ringkasanSummaryRow(['', '', '', 'Sisa Lebih Pembiayaan Anggaran Tahun Berkenaan (SiLPA)', $this->formatRingkasanNumber($silpa)]);

        $table = '<w:tbl>'.$tableProperties.$grid.implode('', $rows).'</w:tbl>';

        return substr_replace($xml, $table, $matches[0][5][1], strlen($original));
    }

    private function replaceRincianTable(string $xml, array $pendapatan, array $belanja, array $pembiayaan): string
    {
        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        if (! isset($matches[0][6])) {
            return $xml;
        }

        $original = $matches[0][6][0];
        preg_match('~<w:tblPr(?:\s[^>]*)?>.*?</w:tblPr>~s', $original, $properties);
        $tableProperties = $properties[0] ?? '<w:tblPr><w:tblW w:w="10000" w:type="dxa"/></w:tblPr>';
        $widths = [2700, 4800, 2500];
        $grid = '<w:tblGrid>'.implode('', array_map(fn ($width) => '<w:gridCol w:w="'.$width.'"/>', $widths)).'</w:tblGrid>';
        $rows = [];
        $rows[] = $this->wordTableRow([
            $this->wordTableCell('Kode Rekening', $widths[0], ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Uraian', $widths[1], ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Jumlah (Rp)', $widths[2], ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
        ], true);

        $pendapatanGroups = $this->buildRingkasanGroups($pendapatan);
        $belanjaGroups = $this->buildRingkasanGroups($belanja);
        $pembiayaanGroups = $this->buildRingkasanGroups($pembiayaan);
        $totalPendapatan = array_sum(array_column($pendapatan, 'jumlah'));
        $totalBelanja = array_sum(array_column($belanja, 'jumlah'));
        $totalPembiayaan = array_sum(array_column($pembiayaan, 'jumlah'));
        $pembiayaanNetto = $this->ringkasanGroupTotal($pembiayaanGroups, '6.1')
            - $this->ringkasanGroupTotal($pembiayaanGroups, '6.2');

        $this->appendRincianSection($rows, '4', 'PENDAPATAN BLUD', $totalPendapatan, $pendapatanGroups, $pendapatan);
        $this->appendRincianSection($rows, '5', 'BELANJA DAERAH', $totalBelanja, $belanjaGroups, $belanja);
        $rows[] = $this->rincianRow('', 'Total Belanja', $totalBelanja, true);
        $rows[] = $this->rincianRow('', 'SURPLUS/ DEFISIT', $totalPendapatan - $totalBelanja, true);
        $this->appendRincianSection($rows, '6', 'PEMBIAYAAN', $totalPembiayaan, $pembiayaanGroups, $pembiayaan);
        $rows[] = $this->rincianRow('', 'Pembiayaan Neto', $pembiayaanNetto, true);
        $rows[] = $this->rincianRow('', 'Sisa Lebih Pembiayaan Anggaran Tahun Berkenaan (SiLPA)',
            $totalPendapatan - $totalBelanja + $pembiayaanNetto, true);

        $table = '<w:tbl>'.$tableProperties.$grid.implode('', $rows).'</w:tbl>';

        return substr_replace($xml, $table, $matches[0][6][1], strlen($original));
    }

    private function appendRincianSection(array &$rows, string $code, string $label, float $total, array $groups, array $accounts): void
    {
        $rows[] = $this->rincianRow($code, $label, $total, true);
        $details = [];
        foreach ($accounts as $account) {
            $accountCode = trim((string) ($account['kode'] ?? ''));
            if ($accountCode === '') {
                continue;
            }
            $prefix = implode('.', array_slice(explode('.', $accountCode), 0, 3));
            $details[$prefix][] = $account;
        }
        foreach ($details as &$items) {
            usort($items, fn ($a, $b) => strnatcmp((string) $a['kode'], (string) $b['kode']));
        }
        unset($items);

        foreach ($groups as $group) {
            $rows[] = $this->rincianRow($group['kode_rek'], $group['uraian'], $group['jumlah']);
            foreach ($group['children'] as $child) {
                $rows[] = $this->rincianRow($child['kode_rek'], $child['uraian'], $child['jumlah']);
                foreach ($details[$child['kode_rek']] ?? [] as $account) {
                    if ($account['kode'] !== $child['kode_rek']) {
                        $rows[] = $this->rincianRow($account['kode'], $account['uraian'], $account['jumlah']);
                    }
                }
            }
            $rows[] = $this->rincianRow('', 'Jumlah', $group['jumlah'], true);
        }
    }

    private function rincianRow(string $code, string $label, float $amount, bool $bold = false): string
    {
        $fill = $bold ? 'F8F9FA' : 'FFFFFF';

        return $this->wordTableRow([
            $this->wordTableCell($code, 2700, ['bold' => $bold, 'fill' => $fill, 'leftPadding' => 120]),
            $this->wordTableCell($label, 4800, ['bold' => $bold, 'fill' => $fill]),
            $this->wordTableCell($this->formatRingkasanNumber($amount), 2500, ['bold' => $bold, 'align' => 'right', 'fill' => $fill]),
        ]);
    }

    private function replaceAmbangTable(string $xml, array $ambang): string
    {
        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        if (! isset($matches[0][7])) {
            return $xml;
        }

        $original = $matches[0][7][0];
        preg_match('~<w:tblPr(?:\s[^>]*)?>.*?</w:tblPr>~s', $original, $properties);
        $tableProperties = $properties[0] ?? '<w:tblPr><w:tblW w:w="10000" w:type="dxa"/></w:tblPr>';
        $widths = [650, 1500, 2200, 2200, 2200, 1250];
        $grid = '<w:tblGrid>'.implode('', array_map(fn ($width) => '<w:gridCol w:w="'.$width.'"/>', $widths)).'</w:tblGrid>';
        $headerFill = 'F8F9FA';
        $rows = [];

        $headers = ['No', 'Tahun', 'Anggaran (Rp)', 'Realisasi (Rp)', 'Selisih (Rp)', 'Persentase (%)'];
        $rows[] = $this->wordTableRow(array_map(
            fn ($header, $index) => $this->wordTableCell($header, $widths[$index], ['bold' => true, 'align' => 'center', 'fill' => $headerFill]),
            $headers,
            array_keys($headers)
        ), true);

        foreach ($ambang as $index => $row) {
            $rows[] = $this->wordTableRow([
                $this->wordTableCell((string) ($index + 1), $widths[0], ['align' => 'center']),
                $this->wordTableCell((string) ($row[0] ?? ''), $widths[1], ['align' => 'center']),
                $this->wordTableCell($this->formatAmbangAmount($row[1] ?? 0), $widths[2], ['align' => 'right']),
                $this->wordTableCell($this->formatAmbangAmount($row[2] ?? 0), $widths[3], ['align' => 'right']),
                $this->wordTableCell($this->formatAmbangAmount($row[3] ?? 0), $widths[4], ['align' => 'right']),
                $this->wordTableCell($this->formatAmbangPercent($row[4] ?? null), $widths[5], ['align' => 'right']),
            ]);
        }

        $rows[] = $this->wordTableRow([
            $this->wordTableCell('JUMLAH PERSENTASE', 8750, ['gridSpan' => 5, 'bold' => true, 'align' => 'right', 'fill' => $headerFill]),
            $this->wordTableCell($this->formatAmbangPercent(array_sum(array_column($ambang, 4))), $widths[5], ['bold' => true, 'align' => 'right', 'fill' => $headerFill]),
        ]);
        $table = '<w:tbl>'.$tableProperties.$grid.implode('', $rows).'</w:tbl>';

        return substr_replace($xml, $table, $matches[0][7][1], strlen($original));
    }

    private function formatAmbangAmount(mixed $value): string
    {
        $number = (float) $value;

        return number_format($number, fmod($number, 1) == 0.0 ? 0 : 2, ',', '.');
    }

    private function formatAmbangPercent(mixed $value): string
    {
        return $value === null || $value === '' ? '-' : number_format((float) $value, 2, ',', '.').'%';
    }

    private function appendRingkasanSection(array &$rows, string $code, string $label, float $total, array $groups): void
    {
        $rows[] = $this->ringkasanSummaryRow([$code, '', '', $label, $this->formatRingkasanNumber($total)], true);
        foreach ($groups as $group) {
            $rows[] = $this->ringkasanSummaryRow(array_merge($this->splitRingkasanCode($group['kode_rek'], 3), [$group['uraian'], $this->formatRingkasanNumber($group['jumlah'])]));
            foreach ($group['children'] as $child) {
                $rows[] = $this->ringkasanSummaryRow(array_merge($this->splitRingkasanCode($child['kode_rek'], 3), [$child['uraian'], $this->formatRingkasanNumber($child['jumlah'])]));
            }
            $rows[] = $this->ringkasanSummaryRow(['', '', '', 'Jumlah', $this->formatRingkasanNumber($group['jumlah'])], true);
        }
    }

    private function buildRingkasanGroups(array $rows): array
    {
        $aggregated = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['kode'] ?? ''));
            if ($code === '') {
                continue;
            }
            $parts = explode('.', $code);
            $prefix2 = implode('.', array_slice($parts, 0, 2));
            $prefix3 = implode('.', array_slice($parts, 0, 3));
            if (! isset($aggregated[$prefix2])) {
                $aggregated[$prefix2] = [
                    'kode_rek' => $prefix2,
                    'uraian' => $this->ringkasanGroupLabel($prefix2),
                    'jumlah' => 0,
                    'children' => [],
                ];
            }
            if (! isset($aggregated[$prefix2]['children'][$prefix3])) {
                $aggregated[$prefix2]['children'][$prefix3] = [
                    'kode_rek' => $prefix3,
                    'uraian' => $this->ringkasanChildLabel($prefix2, $prefix3),
                    'jumlah' => 0,
                ];
            }
            $amount = (float) ($row['jumlah'] ?? 0);
            $aggregated[$prefix2]['jumlah'] += $amount;
            $aggregated[$prefix2]['children'][$prefix3]['jumlah'] += $amount;
        }

        ksort($aggregated);
        foreach ($aggregated as &$group) {
            ksort($group['children']);
            $group['children'] = array_values($group['children']);
        }
        unset($group);

        return array_values($aggregated);
    }

    private function ringkasanGroupLabel(string $code): string
    {
        return [
            '4.1' => 'JASA LAYANAN',
            '4.2' => 'HIBAH',
            '4.3' => 'HASIL KERJASAMA',
            '4.4' => 'APBD',
            '4.5' => 'Lain-lain Pendapatan BLUD yang Sah',
            '5.1' => 'BELANJA OPERASI',
            '5.2' => 'BELANJA MODAL',
            '5.3' => 'BELANJA TAK TERDUGA',
            '5.4' => 'BELANJA TRANSFER',
            '6.1' => 'PENERIMAAN PEMBIAYAAN',
            '6.2' => 'PENGELUARAN PEMBIAYAAN',
        ][$code] ?? 'AKUMULASI '.$code;
    }

    private function ringkasanChildLabel(string $groupCode, string $childCode): string
    {
        $labels = [
            '5.1.01' => 'Belanja Pegawai BLUD',
            '5.1.02' => 'Belanja Barang dan Jasa BLUD',
            '5.2.02' => 'Belanja Modal Peralatan dan Mesin BLUD',
            '5.2.03' => 'Belanja Modal Gedung dan Bangunan BLUD',
            '5.2.04' => 'Belanja Modal Jalan, Jaringan dan Irigasi BLUD',
            '4.1' => 'Jasa Layanan BLUD',
            '4.2' => 'Hibah BLUD',
            '4.3' => 'Hasil Kerjasama BLUD',
            '4.4' => 'APBD BLUD',
            '4.5' => 'Lain-lain Pendapatan BLUD yang Sah',
            '6.1' => 'Penerimaan Pembiayaan BLUD',
            '6.2' => 'Pengeluaran Pembiayaan BLUD',
        ];
        if (str_starts_with($childCode, '4.')) {
            return $labels[$groupCode] ?? 'Akumulasi '.$childCode;
        }
        if (str_starts_with($childCode, '6.')) {
            return $labels[$groupCode] ?? 'Akumulasi '.$childCode;
        }

        return $labels[$childCode] ?? 'Akumulasi '.$childCode;
    }

    private function ringkasanGroupTotal(array $groups, string $code): float
    {
        foreach ($groups as $group) {
            if ($group['kode_rek'] === $code) {
                return (float) $group['jumlah'];
            }
        }

        return 0;
    }

    private function ringkasanSummaryRow(array $values, bool $bold = false): string
    {
        $codes = array_pad(array_slice($values, 0, 3), 3, '');
        $cells = [];
        foreach ($codes as $code) {
            $cells[] = $this->wordTableCell((string) $code, 500, ['bold' => $bold, 'align' => 'center', 'fill' => $bold ? 'F8F9FA' : 'FFFFFF']);
        }
        $cells[] = $this->wordTableCell((string) ($values[3] ?? ''), 5500, ['bold' => $bold, 'fill' => $bold ? 'F8F9FA' : 'FFFFFF']);
        $cells[] = $this->wordTableCell((string) ($values[4] ?? ''), 3000, ['bold' => $bold, 'align' => 'right', 'fill' => $bold ? 'F8F9FA' : 'FFFFFF']);

        return $this->wordTableRow($cells);
    }

    private function splitRingkasanCode(string $code, int $parts): array
    {
        return array_pad(array_slice(explode('.', $code), 0, $parts), $parts, '');
    }

    private function formatRingkasanNumber(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function replacePendapatanTable(string $xml, array $pendapatan): string
    {
        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        if (! isset($matches[0][2])) {
            return $xml;
        }

        $original = $matches[0][2][0];
        preg_match('~<w:tblPr(?:\s[^>]*)?>.*?</w:tblPr>~s', $original, $properties);
        $tableProperties = $properties[0] ?? '<w:tblPr><w:tblW w:w="10000" w:type="dxa"/></w:tblPr>';
        $widths = [375, 375, 375, 375, 5500, 3000];
        $grid = '<w:tblGrid>'.implode('', array_map(fn ($width) => '<w:gridCol w:w="'.$width.'"/>', $widths)).'</w:tblGrid>';
        $headerFill = 'F8F9FA';
        $rows = [];

        $rows[] = $this->wordTableRow([
            $this->wordTableCell('Kode', 1500, ['gridSpan' => 4, 'bold' => true, 'align' => 'center', 'fill' => $headerFill]),
            $this->wordTableCell('Uraian', 5500, ['bold' => true, 'align' => 'center', 'fill' => $headerFill]),
            $this->wordTableCell('Jumlah (Rp)', 3000, ['bold' => true, 'align' => 'center', 'fill' => $headerFill]),
        ], true);
        $rows[] = $this->wordTableRow([
            $this->wordTableCell('1', 1500, ['gridSpan' => 4, 'bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']),
            $this->wordTableCell('2', 5500, ['bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']),
            $this->wordTableCell('3', 3000, ['bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']),
        ], true);

        $total = array_sum(array_column($pendapatan, 'jumlah'));
        $rows[] = $this->pendapatanSummaryRow(['4', '', '', '', 'PENDAPATAN BLUD', $this->formatPendapatanNumber($total)]);

        $classifications = [
            ['prefix' => '4.1', 'label' => 'JASA LAYANAN'],
            ['prefix' => '4.2', 'label' => 'HIBAH'],
            ['prefix' => '4.3', 'label' => 'HASIL KERJASAMA'],
            ['prefix' => '4.4', 'label' => 'APBD'],
            ['prefix' => '4.5', 'label' => 'LAIN-LAIN PENDAPATAN BLUD YANG SAH'],
        ];
        $classified = [];
        foreach ($classifications as $classification) {
            $items = array_values(array_filter($pendapatan, fn ($row) => str_starts_with($row['kode'], $classification['prefix'])));
            if ($items === []) {
                continue;
            }

            $classified = array_merge($classified, array_column($items, 'kode'));
            $subtotal = array_sum(array_column($items, 'jumlah'));
            $parts = explode('.', $classification['prefix']);
            $rows[] = $this->pendapatanSummaryRow([
                $parts[0] ?? '', $parts[1] ?? '', '', '',
                $classification['label'], $this->formatPendapatanNumber($subtotal),
            ]);
            foreach ($items as $item) {
                $rows[] = $this->pendapatanAccountRow($item);
            }
        }

        foreach (array_values(array_filter($pendapatan, fn ($row) => ! in_array($row['kode'], $classified, true))) as $item) {
            $rows[] = $this->pendapatanAccountRow($item);
        }

        $rows[] = $this->pendapatanSummaryRow(['', '', '', '', 'JUMLAH', $this->formatPendapatanNumber($total)]);
        $table = '<w:tbl>'.$tableProperties.$grid.implode('', $rows).'</w:tbl>';

        return substr_replace($xml, $table, $matches[0][2][1], strlen($original));
    }

    private function pendapatanSummaryRow(array $values): string
    {
        $codes = array_pad(array_slice($values, 0, 4), 4, '');
        $cells = [];
        foreach ($codes as $code) {
            $cells[] = $this->wordTableCell((string) $code, 375, ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']);
        }
        $cells[] = $this->wordTableCell((string) ($values[4] ?? ''), 5500, ['bold' => true, 'fill' => 'F8F9FA']);
        $cells[] = $this->wordTableCell((string) ($values[5] ?? ''), 3000, ['bold' => true, 'align' => 'right', 'fill' => 'F8F9FA']);

        return $this->wordTableRow($cells);
    }

    private function pendapatanAccountRow(array $item): string
    {
        $cells = [];
        foreach ($this->splitPendapatanCode($item['kode']) as $code) {
            $cells[] = $this->wordTableCell($code, 375, ['align' => 'center']);
        }
        $cells[] = $this->wordTableCell($item['uraian'], 5500);
        $cells[] = $this->wordTableCell($this->formatPendapatanNumber($item['jumlah']), 3000, ['align' => 'right']);

        return $this->wordTableRow($cells);
    }

    private function splitPendapatanCode(string $code): array
    {
        return array_pad(array_slice(explode('.', $code), 0, 4), 4, '');
    }

    private function formatPendapatanNumber(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function replacePembiayaanTable(string $xml, array $pembiayaan): string
    {
        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        if (! isset($matches[0][4])) {
            return $xml;
        }

        $original = $matches[0][4][0];
        preg_match('~<w:tblPr(?:\s[^>]*)?>.*?</w:tblPr>~s', $original, $properties);
        $tableProperties = $properties[0] ?? '<w:tblPr><w:tblW w:w="10000" w:type="dxa"/></w:tblPr>';
        $widths = [375, 375, 375, 375, 5500, 3000];
        $grid = '<w:tblGrid>'.implode('', array_map(fn ($width) => '<w:gridCol w:w="'.$width.'"/>', $widths)).'</w:tblGrid>';
        $headerFill = 'F8F9FA';
        $rows = [];

        $rows[] = $this->wordTableRow([
            $this->wordTableCell('Kode', 1500, ['gridSpan' => 4, 'bold' => true, 'align' => 'center', 'fill' => $headerFill]),
            $this->wordTableCell('Uraian', 5500, ['bold' => true, 'align' => 'center', 'fill' => $headerFill]),
            $this->wordTableCell('Jumlah (Rp)', 3000, ['bold' => true, 'align' => 'center', 'fill' => $headerFill]),
        ], true);
        $rows[] = $this->wordTableRow([
            $this->wordTableCell('1', 1500, ['gridSpan' => 4, 'bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']),
            $this->wordTableCell('2', 5500, ['bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']),
            $this->wordTableCell('3', 3000, ['bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']),
        ], true);

        $total = array_sum(array_column($pembiayaan, 'jumlah'));
        $rows[] = $this->pembiayaanSummaryRow(['6', '', '', '', 'PEMBIAYAAN BLUD', $this->formatPembiayaanNumber($total)]);

        $classifications = [
            ['prefix' => '6.1', 'label' => 'PENERIMAAN PEMBIAYAAN'],
            ['prefix' => '6.2', 'label' => 'PENGELUARAN PEMBIAYAAN'],
        ];
        $classified = [];
        foreach ($classifications as $classification) {
            $items = array_values(array_filter($pembiayaan, fn ($row) => str_starts_with($row['kode'], $classification['prefix'])));
            if ($items === []) {
                continue;
            }

            $classified = array_merge($classified, array_column($items, 'kode'));
            $subtotal = array_sum(array_column($items, 'jumlah'));
            $parts = explode('.', $classification['prefix']);
            $rows[] = $this->pembiayaanSummaryRow([
                $parts[0] ?? '', $parts[1] ?? '', '', '',
                $classification['label'], $this->formatPembiayaanNumber($subtotal),
            ]);
            foreach ($items as $item) {
                $rows[] = $this->pembiayaanAccountRow($item);
            }
        }

        foreach (array_values(array_filter($pembiayaan, fn ($row) => ! in_array($row['kode'], $classified, true))) as $item) {
            $rows[] = $this->pembiayaanAccountRow($item);
        }

        $rows[] = $this->pembiayaanSummaryRow(['', '', '', '', 'JUMLAH', $this->formatPembiayaanNumber($total)]);
        $table = '<w:tbl>'.$tableProperties.$grid.implode('', $rows).'</w:tbl>';

        return substr_replace($xml, $table, $matches[0][4][1], strlen($original));
    }

    private function pembiayaanSummaryRow(array $values): string
    {
        $codes = array_pad(array_slice($values, 0, 4), 4, '');
        $cells = [];
        foreach ($codes as $code) {
            $cells[] = $this->wordTableCell((string) $code, 375, ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']);
        }
        $cells[] = $this->wordTableCell((string) ($values[4] ?? ''), 5500, ['bold' => true, 'fill' => 'F8F9FA']);
        $cells[] = $this->wordTableCell((string) ($values[5] ?? ''), 3000, ['bold' => true, 'align' => 'right', 'fill' => 'F8F9FA']);

        return $this->wordTableRow($cells);
    }

    private function pembiayaanAccountRow(array $item): string
    {
        $cells = [];
        foreach ($this->splitPembiayaanCode($item['kode']) as $code) {
            $cells[] = $this->wordTableCell($code, 375, ['align' => 'center']);
        }
        $cells[] = $this->wordTableCell($item['uraian'], 5500);
        $cells[] = $this->wordTableCell($this->formatPembiayaanNumber($item['jumlah']), 3000, ['align' => 'right']);

        return $this->wordTableRow($cells);
    }

    private function splitPembiayaanCode(string $code): array
    {
        return array_pad(array_slice(explode('.', $code), 0, 4), 4, '');
    }

    private function formatPembiayaanNumber(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function replaceFirstTextPattern(string $xml, string $pattern, string $replacement): string
    {
        preg_match_all('~<w:t(?:\s[^>]*)?>(.*?)</w:t>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        $nodes = [];
        $text = '';
        foreach ($matches[1] as [$encoded, $offset]) {
            $decoded = html_entity_decode($encoded, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $nodes[] = [
                'offset' => $offset,
                'encoded' => $encoded,
                'text' => $decoded,
                'start' => mb_strlen($text),
                'length' => mb_strlen($decoded),
            ];
            $text .= $decoded;
        }

        if (! preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
            return $xml;
        }

        $matched = $match[0][0];
        $start = mb_strlen(substr($text, 0, $match[0][1]));
        $end = $start + mb_strlen($matched);
        foreach ($nodes as &$node) {
            $from = max($start, $node['start']);
            $to = min($end, $node['start'] + $node['length']);
            if ($from < $to) {
                $local = $from - $node['start'];
                $node['text'] = mb_substr($node['text'], 0, $local)
                    .($from === $start ? $replacement : '')
                    .mb_substr($node['text'], $to - $node['start']);
            }
        }
        unset($node);

        foreach (array_reverse($nodes) as $node) {
            if (html_entity_decode($node['encoded'], ENT_QUOTES | ENT_XML1, 'UTF-8') !== $node['text']) {
                $xml = substr_replace(
                    $xml,
                    htmlspecialchars($node['text'], ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    $node['offset'],
                    strlen($node['encoded'])
                );
            }
        }

        return $xml;
    }

    private function replaceBelanjaTable(string $xml, array $belanja): string
    {
        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        if (! isset($matches[0][3])) {
            return $xml;
        }

        $original = $matches[0][3][0];
        preg_match('~<w:tblPr(?:\s[^>]*)?>.*?</w:tblPr>~s', $original, $properties);
        $tableProperties = $properties[0] ?? '<w:tblPr><w:tblW w:w="10000" w:type="dxa"/></w:tblPr>';
        $widths = [350, 350, 350, 350, 350, 350, 3300, 800, 800, 1300, 1700];
        $grid = '<w:tblGrid>'.implode('', array_map(fn ($width) => '<w:gridCol w:w="'.$width.'"/>', $widths)).'</w:tblGrid>';
        $rows = [];
        $rows[] = $this->wordTableRow([$this->wordTableCell('RINCIAN BISNIS DAN ANGGARAN PER SUB KEGIATAN', 10000, ['gridSpan' => 11, 'bold' => true, 'align' => 'center', 'fill' => 'F8F9FA'])], true);
        $rows[] = $this->wordTableRow([
            $this->wordTableCell('Kode', 2100, ['gridSpan' => 6, 'vMerge' => 'restart', 'bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Uraian', 3300, ['vMerge' => 'restart', 'bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Rincian Perhitungan', 4600, ['gridSpan' => 4, 'bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
        ], true);
        $rows[] = $this->wordTableRow([
            $this->wordTableCell('', 2100, ['gridSpan' => 6, 'vMerge' => 'continue', 'fill' => 'F8F9FA']),
            $this->wordTableCell('', 3300, ['vMerge' => 'continue', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Volume', 800, ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Satuan', 800, ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Harga Satuan (Rp)', 1300, ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
            $this->wordTableCell('Jumlah (Rp)', 1700, ['bold' => true, 'align' => 'center', 'fill' => 'F8F9FA']),
        ], true);
        $numberHeaders = ['1', '', '', '', '', '', '2', '3', '4', '5', '6'];
        $rows[] = $this->wordTableRow(array_map(fn ($value, $index) => $this->wordTableCell($value, $widths[$index], ['bold' => true, 'align' => 'center', 'fill' => 'FFFFFF']), $numberHeaders, array_keys($numberHeaders)), true);

        $totalBelanja = array_sum(array_column($belanja, 'jumlah'));
        if ($totalBelanja > 0) {
            $rows[] = $this->belanjaSummaryRow(['5', '', '', '', '', '', 'BELANJA', $this->formatDisplayNumber($totalBelanja)], true);
        }

        $classifications = [
            ['prefix' => '5.1.01', 'label' => 'Belanja Pegawai BLUD'],
            ['prefix' => '5.1.02', 'label' => 'Belanja Barang dan Jasa BLUD'],
            ['prefix' => '5.2.02', 'label' => 'Belanja Modal Peralatan dan Mesin BLUD'],
            ['prefix' => '5.2.03', 'label' => 'Belanja Modal Gedung dan Bangunan BLUD'],
            ['prefix' => '5.2.04', 'label' => 'Belanja Modal Jalan, Jaringan dan Irigasi BLUD'],
        ];
        $classified = [];
        foreach ($classifications as $classification) {
            $items = array_values(array_filter($belanja, fn ($row) => str_starts_with($row['kode'], $classification['prefix'])));
            if ($items === []) continue;
            $classified = array_merge($classified, array_column($items, 'kode'));
            $subtotal = array_sum(array_column($items, 'jumlah'));
            $rows[] = $this->belanjaSummaryRow(array_merge($this->splitBudgetCode($classification['prefix']), [$classification['label'], $this->formatDisplayNumber($subtotal)]), true);
            foreach ($items as $item) {
                $rows[] = $this->belanjaAccountRow($item);
            }
        }

        $remaining = array_values(array_filter($belanja, fn ($row) => ! in_array($row['kode'], $classified, true)));
        foreach ($remaining as $item) {
            $rows[] = $this->belanjaAccountRow($item);
        }
        $rows[] = $this->belanjaSummaryRow(['', '', '', '', '', '', 'JUMLAH', $this->formatDisplayNumber($totalBelanja)], true);

        $table = '<w:tbl>'.$tableProperties.$grid.implode('', $rows).'</w:tbl>';

        return substr_replace($xml, $table, $matches[0][3][1], strlen($original));
    }

    private function belanjaSummaryRow(array $values, bool $bold = false): string
    {
        $codes = array_pad(array_slice($values, 0, 6), 6, '');
        $label = (string) ($values[6] ?? '');
        $amount = (string) ($values[7] ?? '');
        $cells = [];
        foreach ($codes as $code) $cells[] = $this->wordTableCell($code, 350, ['bold' => $bold, 'align' => 'center', 'fill' => 'F8F9FA']);
        $cells[] = $this->wordTableCell($label, 3300, ['bold' => $bold, 'fill' => 'F8F9FA']);
        foreach (['', '', ''] as $blank) $cells[] = $this->wordTableCell($blank, 800, ['fill' => 'F8F9FA']);
        $cells[] = $this->wordTableCell($amount, 1700, ['bold' => $bold, 'align' => 'right', 'fill' => 'F8F9FA']);

        return $this->wordTableRow($cells);
    }

    private function belanjaAccountRow(array $item): string
    {
        $cells = [];
        foreach ($this->splitBudgetCode($item['kode']) as $code) $cells[] = $this->wordTableCell($code, 350, ['bold' => true, 'align' => 'center']);
        $cells[] = $this->wordTableCell($item['uraian'], 3300, ['bold' => true]);
        foreach ($item['details'] as $detail) {
            // Item rows are appended to the account row below.
        }
        foreach (['', '', ''] as $blank) $cells[] = $this->wordTableCell($blank, 800);
        $cells[] = $this->wordTableCell($this->formatDisplayNumber($item['jumlah']), 1700, ['bold' => true, 'align' => 'right']);
        $rows = [$this->wordTableRow($cells)];
        foreach ($item['details'] as $detail) {
            $detailCells = array_fill(0, 6, $this->wordTableCell('', 350));
            $description = '• '.$detail['uraian'];
            if ($detail['spesifikasi'] !== '') {
                $description .= ' (Spesifikasi: '.$detail['spesifikasi'].')';
            }
            $detailCells[] = $this->wordTableCell($description, 3300);
            $detailCells[] = $this->wordTableCell($this->formatDisplayNumber($detail['volume']), 800, ['align' => 'center']);
            $detailCells[] = $this->wordTableCell($detail['satuan'], 800, ['align' => 'center']);
            $detailCells[] = $this->wordTableCell($this->formatDisplayNumber($detail['harga_satuan']), 1300, ['align' => 'right']);
            $detailCells[] = $this->wordTableCell($this->formatDisplayNumber($detail['jumlah']), 1700, ['align' => 'right']);
            $rows[] = $this->wordTableRow($detailCells);
        }

        return implode('', $rows);
    }

    private function splitBudgetCode(string $code): array
    {
        return array_pad(array_slice(explode('.', $code), 0, 6), 6, '');
    }

    private function wordTableRow(array $cells, bool $repeatHeader = false): string
    {
        $properties = $repeatHeader ? '<w:trPr><w:tblHeader/></w:trPr>' : '';

        return '<w:tr>'.$properties.implode('', $cells).'</w:tr>';
    }

    private function wordTableCell(string $value, int $width, array $options = []): string
    {
        $encoded = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $tcPr = '<w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>';
        if (($options['gridSpan'] ?? 1) > 1) $tcPr .= '<w:gridSpan w:val="'.$options['gridSpan'].'"/>';
        if (isset($options['vMerge'])) $tcPr .= '<w:vMerge w:val="'.$options['vMerge'].'"/>';
        if (isset($options['fill'])) $tcPr .= '<w:shd w:fill="'.$options['fill'].'"/>';
        if (isset($options['leftPadding'])) $tcPr .= '<w:tcMar><w:left w:w="'.$options['leftPadding'].'" w:type="dxa"/></w:tcMar>';
        $tcPr .= '<w:tcBorders><w:top w:val="single" w:sz="4" w:color="808080"/><w:left w:val="single" w:sz="4" w:color="808080"/><w:bottom w:val="single" w:sz="4" w:color="808080"/><w:right w:val="single" w:sz="4" w:color="808080"/></w:tcBorders><w:vAlign w:val="center"/></w:tcPr>';
        $align = ($options['align'] ?? 'left') === 'right' ? 'right' : (($options['align'] ?? 'left') === 'center' ? 'center' : 'left');
        $rPr = ($options['bold'] ?? false) ? '<w:b/><w:bCs/>' : '';

        return '<w:tc>'.$tcPr.'<w:p><w:pPr><w:jc w:val="'.$align.'"/></w:pPr><w:r><w:rPr>'.$rPr.'<w:sz w:val="17"/><w:szCs w:val="17"/></w:rPr><w:t xml:space="preserve">'.$encoded.'</w:t></w:r></w:p></w:tc>';
    }

    private function replaceTablesByIndex(string $xml, array $tables): string
    {
        preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        foreach ($tables as $index => [$dataRows, $footer, $headerValues]) {
            if (!isset($matches[0][$index])) continue;
            $table = $matches[0][$index][0];
            preg_match_all('~<w:tr(?:\s[^>]*)?>.*?</w:tr>~s', $table, $rows);
            if (count($rows[0]) < 3) continue;
            $header = $headerValues === null ? $rows[0][0] : $this->setRowCells($rows[0][0], $headerValues);
            $prototype = $rows[0][1];
            $footerTemplate = $rows[0][count($rows[0]) - 1];
            $newRows = [$header];
            foreach ($dataRows as $row) {
                $plain = ($index === 2 && ($row[0] ?? null) === '' && ($row[1] ?? null) === '');
                $newRows[] = $this->setRowCells($prototype, $row, $plain);
            }
            $newRows[] = $this->setRowCells($footerTemplate, $footer);
            $updated = substr($table, 0, strpos($table, $rows[0][0])).implode('', $newRows).substr($table, strrpos($table, $rows[0][count($rows[0]) - 1]) + strlen($rows[0][count($rows[0]) - 1]));
            $xml = substr_replace($xml, $updated, $matches[0][$index][1], strlen($table));
            preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $xml, $matches, PREG_OFFSET_CAPTURE);
        }
        return $xml;
    }

    private function setRowCells(string $row, array $values, bool $plain = false): string
    {
        preg_match_all('~<w:tc(?:\s[^>]*)?>.*?</w:tc>~s', $row, $cellMatches);
        $cellValues = array_values($values);
        if (count($cellMatches[0]) < count($cellValues) && count($cellMatches[0]) > 1) {
            // Total rows in the template use merged cells. Keep the label in the
            // first cell and place the rightmost total in the final numeric cell.
            $cellValues = array_merge(
                [$cellValues[0]],
                array_slice($cellValues, -(count($cellMatches[0]) - 1))
            );
        }
        $runProperties = '';
        if (preg_match('~<w:rPr(?:\s[^>]*)?>.*?</w:rPr>~s', $row, $runMatch)) {
            $runProperties = $runMatch[0];
        }
        if ($plain) {
            $runProperties = preg_replace('~<w:b(?:Cs)?(?:\s[^>]*)?/>~', '', $runProperties);
        }
        $cell = 0;
        return preg_replace_callback('~<w:tc(?:\s[^>]*)?>.*?</w:tc>~s', function ($match) use (&$cell, $cellValues, $plain, $runProperties) {
            $value = $cellValues[$cell] ?? '';
            $cell++;
            return $this->setCellText($match[0], (string) $value, $plain, $runProperties);
        }, $row);
    }

    private function setCellText(string $cell, string $value, bool $plain = false, string $runProperties = ''): string
    {
        $encoded = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $first = true;
        $updated = preg_replace_callback('~(<w:t(?:\s[^>]*)?>).*?(</w:t>)~s', function ($match) use (&$first, $encoded) {
            $text = $first ? $encoded : '';
            $first = false;
            return $match[1].$text.$match[2];
        }, $cell);
        if ($plain) {
            $updated = preg_replace('~<w:b(?:Cs)?(?:\s[^>]*)?/>~', '', $updated);
        }

        if ($encoded !== '' && ! preg_match('~<w:t(?:\s|>)~', $cell)) {
            $run = '<w:r>'.$runProperties.'<w:t xml:space="preserve">'.$encoded.'</w:t></w:r>';
            $paragraphEnd = strpos($updated, '</w:p>');
            if ($paragraphEnd !== false) {
                $updated = substr_replace($updated, $run, $paragraphEnd, 0);
            }
        }

        return $updated;
    }

    private function placeholderValues(string $upt, int $tahun): array
    {
        $user = function_exists('auth') ? auth()->user() : null;
        $unit = UnitBlud::where('nama', $upt)->first();
        $pejabatKeuangan = User::withRole('Pejabat Keuangan')->where('upt', $upt)->first();
        $bendaharaPenerimaan = User::withRole('Bendahara Penerimaan')->where('upt', $upt)->first();
        $bendaharaPengeluaran = User::withRole('Bendahara Pengeluaran')->where('upt', $upt)->first();
        $tanggal = $this->formatTanggalIndonesia();

        return [
            '[[Nama Puskesmas]]' => $upt,
            '[[Tahun Anggaran]]' => (string) $tahun,
            '{NAMA PUSKESMAS}' => $upt,
            '{Nama Puskesmas}' => $upt,
            '{TAHUN ANGGARAN}' => (string) $tahun,
            '{Nama Kepala Puskesmas}' => (string) ($unit?->kepala ?? ''),
            '{NIP Kepala Puskesmas}' => $this->formatNip($unit?->nip_kapus),
            '{Nama Pejabat Keuangan}' => (string) ($pejabatKeuangan?->name ?? ''),
            '{Nama Bendahara Penerimaan}' => (string) ($bendaharaPenerimaan?->name ?? ''),
            '{Nama Bendahara Pengeluaran}' => (string) ($bendaharaPengeluaran?->name ?? ''),
            '{Tanggal}' => $tanggal,
            '{Tanggal Saat Ini}' => $tanggal,
        ];
    }

    private function formatNip(?string $nip): string
    {
        $digits = preg_replace('/\D+/', '', (string) $nip);
        if (strlen($digits) === 18) {
            return substr($digits, 0, 8).' '.substr($digits, 8, 6).' '.substr($digits, 14, 1).' '.substr($digits, 15, 3);
        }

        return trim((string) $nip);
    }

    private function formatTanggalIndonesia(): string
    {
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $tanggal = now('Asia/Jakarta');

        return $tanggal->format('d').' '.$bulan[(int) $tanggal->format('n')].' '.$tanggal->format('Y');
    }

    private function replaceFirstTextNode(string $cell, string $value): string
    {
        $encoded = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return preg_replace_callback('~(<w:t(?:\s[^>]*)?>).*?(</w:t>)~s',
            fn ($match) => $match[1].$encoded.$match[2], $cell, 1);
    }

    private function formatNumber(mixed $value): string
    {
        $number = (float) $value;
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function formatDisplayNumber(mixed $value): string
    {
        $number = (float) $value;
        $formatted = number_format($number, 4, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
