<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RbaDocumentFieldUpdater;
use App\Services\RbaDocumentPdfConverter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class DokumenRbaTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->mock(RbaDocumentFieldUpdater::class, function ($mock) {
            $mock->shouldReceive('update')->andReturnNull();
        });
        $this->cachePath = storage_path('framework/testing/dokumen-rba-'.Str::uuid());
        config(['dokumen-rba.cache_path' => $this->cachePath]);
        $this->mock(RbaDocumentPdfConverter::class, function ($mock) {
            $mock->shouldReceive('convert')->andReturnUsing(function ($word, $pdf) {
                // Native Word conversion is checked separately; feature tests exercise context and caching.
                file_put_contents($pdf, "%PDF-1.4\n".$this->documentText($word));
            });
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cachePath);
        parent::tearDown();
    }

    private function documentText(string $path): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function login(string $role = 'Pejabat Keuangan', string $upt = 'Puskesmas A'): void
    {
        $user = new User(['name' => 'Pengguna Uji', 'role' => $role, 'roles' => [$role], 'upt' => $upt]);
        $user->id = 7;
        $this->actingAs($user);
    }

    public function test_pages_preview_and_downloads_require_login(): void
    {
        foreach (['murni', 'perubahan'] as $jenis) {
            foreach ([route('dokumen-rba.show', $jenis), route('dokumen-rba.preview', $jenis),
                route('dokumen-rba.download', [$jenis, 'word']), route('dokumen-rba.download', [$jenis, 'pdf'])] as $url) {
                $this->get($url)->assertRedirect(route('login'));
            }
        }
    }

    public function test_both_submenus_display_the_correct_preview_and_download_links(): void
    {
        $this->login();
        foreach (['murni' => 'RBA Murni', 'perubahan' => 'RBA Perubahan'] as $jenis => $label) {
            $response = $this->get(route('dokumen-rba.show', $jenis))->assertOk()
                ->assertSee('Dokumen '.$label)->assertSee('Preview Dokumen')
                ->assertSee('Unduh Word')->assertSee('Unduh PDF')
                ->assertSee(route('dokumen-rba.preview', $jenis).'#view=FitH', false)
                ->assertSee(route('dokumen-rba.download', [$jenis, 'word']), false)
                ->assertSee(route('dokumen-rba.download', [$jenis, 'pdf']), false)
                ->assertViewHas('wordAvailable', true)->assertViewHas('pdfAvailable', true);
            foreach (['murni', 'perubahan'] as $submenu) {
                $response->assertSee(route('dokumen-rba.show', $submenu), false);
            }
            $response->assertSee('id="sidebarDokumenRba"', false);
        }
        $this->get(route('dokumen-rba.index'))->assertRedirect('/dokumen-rba/murni');
    }

    public function test_word_download_fills_the_supplied_template_for_both_types_and_preserves_other_parts(): void
    {
        $this->login();
        $this->withSession(['tahun_anggaran.user_7' => 2026]);
        $source = resource_path('documents/rba/template-rba.docx');
        $sourceHash = hash_file('sha256', $source);
        foreach (['murni', 'perubahan'] as $jenis) {
            $response = $this->get(route('dokumen-rba.download', [$jenis, 'word']))->assertOk()
                ->assertDownload('rba-'.$jenis.'.docx')
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $path = $response->baseResponse->getFile()->getPathname();
            $text = $this->documentText($path);
            $this->assertStringContainsString('Puskesmas A', $text);
            $this->assertStringContainsString('Tahun Anggaran 2026', $text);
            $this->assertStringContainsString('KATA PENGANTAR', $text);
            $this->assertStringContainsString('RINGKASAN EKSEKUTIF', $text);
            $this->assertStringContainsString('DAFTAR ISI', $text);
            $this->assertStringContainsString('Total pendapatan: Rp ', $text);
            $this->assertStringNotContainsString('[[Ringkasan Total Pendapatan]]', $text);
            $this->assertStringNotContainsString('[[Ringkasan Total Belanja]]', $text);
            $this->assertStringNotContainsString('[[Ringkasan Total Pembiayaan]]', $text);
            $this->assertStringNotContainsString('[[Ringkasan Surplus Defisit]]', $text);
            $this->assertStringContainsString('Susunan Pengelola BLUD UPTD Puskesmas A Tahun 2026', $text);
            $this->assertStringContainsString('Ringkasan Pendapatan, Belanja dan Pembiayaan Puskesmas A Tahun 2026', $text);
            $this->assertStringContainsString('Ambang batas Puskesmas A Tahun 2026', $text);
            $this->assertStringContainsString('ambang batas RBA tahun anggaran 2026', $text);
            $this->assertStringNotContainsString('dr. Kepala Sosialisasi15', $text);
            $this->assertStringNotContainsString('19800101 200501 1 015', $text);
            $this->assertStringNotContainsString('[[Nama Puskesmas]]', $text);
            $this->assertStringNotContainsString('[[Tahun Anggaran]]', $text);
            $this->assertStringNotContainsString('{HALAMAN HASIL UNDUH RBA', $text);
            foreach (['PENDAPATAN', 'BELANJA', 'PEMBIAYAAN'] as $section) {
                $this->assertStringContainsString('ANGGARAN '.$section, $text);
            }
            $template = new ZipArchive;
            $output = new ZipArchive;
            $template->open($source);
            $output->open($path);
            $this->assertSame($template->numFiles + 1, $output->numFiles);
            $this->assertNotFalse($output->getFromName('word/media/rba-logo-pemkot.png'));
            $this->assertStringContainsString('rIdRbaLogo', $output->getFromName('word/_rels/document.xml.rels'));
            preg_match_all('~<w:tbl(?:\s[^>]*)?>.*?</w:tbl>~s', $output->getFromName('word/document.xml'), $tables);
            foreach ([2, 3, 4, 5, 6, 7, 8, 9, 11, 12, 14, 16, 18, 19] as $tableIndex) {
                preg_match('~<w:tr(?:\s[^>]*)?>.*?</w:tr>~s', $tables[0][$tableIndex], $headerRow);
                $this->assertStringContainsString('<w:tblHeader/>', $headerRow[0], 'Header tabel '.$tableIndex.' harus diulang.');
            }
            $this->assertStringNotContainsString('<w:tblHeader/>', $tables[0][15], 'Tabel informasi Belanja tidak perlu diulang.');
            for ($i = 0; $i < $template->numFiles; $i++) {
                $name = $template->getNameIndex($i);
                if (! in_array($name, ['word/document.xml', 'word/_rels/document.xml.rels'], true)) {
                    $this->assertSame($template->getFromName($name), $output->getFromName($name), $name);
                }
            }
            $template->close();
            $output->close();
        }
        $this->assertSame($sourceHash, hash_file('sha256', $source));
    }

    public function test_pdf_preview_and_download_serve_the_exact_same_pdf(): void
    {
        $this->login();
        foreach (['murni', 'perubahan'] as $jenis) {
            $preview = $this->get(route('dokumen-rba.preview', $jenis))->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'inline; filename="rba-'.$jenis.'.pdf"')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $download = $this->get(route('dokumen-rba.download', [$jenis, 'pdf']))->assertOk()
                ->assertDownload('rba-'.$jenis.'.pdf')->assertHeader('Content-Type', 'application/pdf');
            $path = $download->baseResponse->getFile()->getPathname();
            $this->assertSame(hash_file('sha256', $path), hash_file('sha256', $preview->baseResponse->getFile()->getPathname()));
            $this->assertSame('%PDF-', file_get_contents($path, false, null, 0, 5));
            $this->assertStringContainsString('private', $preview->headers->get('Cache-Control'));
        }
    }

    public function test_documents_are_separate_for_each_upt_and_topbar_year(): void
    {
        $hashes = [];
        foreach (['Puskesmas A', 'Puskesmas B'] as $upt) {
            $this->login('Kepala Puskesmas', $upt);
            foreach ([2026, 2027] as $year) {
                $this->post(route('tahun-anggaran.update'), ['tahun' => $year])->assertRedirect('/');
                foreach (['murni', 'perubahan'] as $jenis) {
                    foreach (['word', 'pdf'] as $format) {
                        $response = $this->get(route('dokumen-rba.download', [$jenis, $format, 'tahun' => 2026]))->assertOk();
                        $path = $response->baseResponse->getFile()->getPathname();
                        $text = $format === 'word' ? $this->documentText($path) : file_get_contents($path);
                        $this->assertStringContainsString($upt, $text);
                        $this->assertStringContainsString('Tahun Anggaran '.$year, $text);
                        $hashes[$jenis][$format][] = hash_file('sha256', $path);
                    }
                }
            }
        }
        foreach (['word', 'pdf'] as $format) {
            $this->assertCount(4, array_unique($hashes['murni'][$format]));
            $this->assertSame($hashes['murni'][$format], $hashes['perubahan'][$format]);
        }
    }

    public function test_unknown_document_types_and_formats_are_not_available(): void
    {
        $this->login();
        foreach (['/dokumen-rba/rahasia', '/dokumen-rba/rahasia/preview',
            '/dokumen-rba/murni/unduh/env', '/dokumen-rba/murni/unduh/docx', '/dokumen-rba/rahasia/unduh/pdf'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_unavailable_files_show_a_clear_state_and_never_download_another_file(): void
    {
        $this->login();
        config([
            'dokumen-rba.templates.murni.word' => resource_path('documents/rba/not-present.docx'),
        ]);
        $this->get(route('dokumen-rba.show', 'murni'))->assertOk()
            ->assertViewHas('wordAvailable', false)->assertViewHas('pdfAvailable', false)
            ->assertSee('Template PDF belum tersedia.')->assertDontSee('<iframe', false);
        $this->get(route('dokumen-rba.preview', 'murni'))->assertNotFound();
        $this->get(route('dokumen-rba.download', ['murni', 'word']))->assertNotFound();
        $this->get(route('dokumen-rba.download', ['murni', 'pdf']))->assertNotFound();
        $this->get(route('dokumen-rba.show', 'perubahan'))->assertOk()->assertViewHas('pdfAvailable', true);
    }

    public function test_word_handles_xml_characters_in_upt(): void
    {
        $this->login('Pejabat Keuangan', 'Puskesmas A & B <Utama>');
        $response = $this->get(route('dokumen-rba.download', ['murni', 'word']))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertStringContainsString('Puskesmas A & B <Utama>', $this->documentText($path));
        $zip = new ZipArchive;
        $zip->open($path);
        $this->assertNotFalse(simplexml_load_string($zip->getFromName('word/document.xml')));
        $zip->close();
    }

    public function test_placeholders_split_across_runs_are_filled_in_body_header_footer_and_text_boxes(): void
    {
        $this->login('Pejabat Keuangan', 'Puskesmas A & B');
        File::ensureDirectoryExists($this->cachePath);
        $template = $this->cachePath.'/split.docx';
        $xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:p><w:r><w:rPr><w:b/></w:rPr><w:t>[[Nama </w:t></w:r><w:r><w:t>Puskesmas]]</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>[[Tahun</w:t></w:r><w:r><w:t> Anggaran]]</w:t></w:r></w:p>'
            .'<w:txbxContent><w:p><w:r><w:t>[[Nama Puskesmas]] [[Tahun Anggaran]]</w:t></w:r></w:p></w:txbxContent>'
            .'</w:document>';
        $zip = new ZipArchive;
        $zip->open($template, ZipArchive::CREATE);
        foreach (['word/document.xml', 'word/header1.xml', 'word/footer1.xml'] as $part) {
            $zip->addFromString($part, $xml);
        }
        $zip->close();
        config(['dokumen-rba.templates.murni.word' => $template]);
        $this->withSession(['tahun_anggaran.user_7' => 2026]);
        $response = $this->get(route('dokumen-rba.download', ['murni', 'word']))->assertOk();
        $zip->open($response->baseResponse->getFile()->getPathname());
        foreach (['word/document.xml', 'word/header1.xml', 'word/footer1.xml'] as $part) {
            $output = $zip->getFromName($part);
            $this->assertStringNotContainsString('[[', $output);
            $this->assertSame(2, substr_count(str_replace("\u{00A0}", ' ', $output), 'Puskesmas A &amp; B'));
            $this->assertSame(2, substr_count($output, '2026'));
            $this->assertStringContainsString('<w:rPr><w:b/></w:rPr>', $output);
            $this->assertNotFalse(simplexml_load_string($output));
        }
        $zip->close();
    }

    public function test_missing_upt_cannot_download_a_document(): void
    {
        $this->login('Pejabat Keuangan', '');
        $this->get(route('dokumen-rba.download', ['murni', 'word']))->assertStatus(422);
    }

    public function test_failed_pdf_conversion_never_serves_the_static_pdf_and_can_be_retried(): void
    {
        $this->login();
        $this->mock(RbaDocumentPdfConverter::class, function ($mock) {
            $mock->shouldReceive('convert')->once()->andThrow(new \RuntimeException('Conversion failed'));
        });
        $this->get(route('dokumen-rba.preview', 'murni'))->assertStatus(503);
        $this->get(route('dokumen-rba.download', ['murni', 'word']))->assertOk();
        $this->mock(RbaDocumentPdfConverter::class, function ($mock) {
            $mock->shouldReceive('convert')->once()->andReturnUsing(fn ($word, $pdf) => file_put_contents($pdf, '%PDF-1.4 retry'));
        });
        $this->get(route('dokumen-rba.preview', 'murni'))->assertOk();
    }
}
