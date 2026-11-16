<?php

namespace Tests\Feature;

use App\Services\RbaDocumentPdfConverter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DokumenRbaNativePdfTest extends TestCase
{
    public function test_native_pdf_export_works_when_web_superglobals_omit_windows_environment(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || getenv('RBA_TEST_NATIVE_PDF') !== '1') {
            $this->markTestSkipped('Opt in with RBA_TEST_NATIVE_PDF=1 on Windows with Microsoft Word installed.');
        }

        $directory = storage_path('framework/testing/rba-native-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        $source = resource_path('documents/rba/template-rba.docx');
        $sourceHash = hash_file('sha256', $source);
        $environment = $_ENV;
        $server = $_SERVER;

        try {
            // Reproduce cli-server: PHP getenv() contains Windows variables, but $_SERVER does not.
            // A nonempty intersection leaves Symfony's default child environment without SystemRoot/APPDATA.
            $_ENV = ['APP_ENV' => 'testing'];
            $_SERVER = ['APP_ENV' => 'testing', 'REQUEST_METHOD' => 'GET'];
            app(RbaDocumentPdfConverter::class)->convert($source, $directory.'/rba.pdf');

            $this->assertFileExists($directory.'/rba.pdf');
            $this->assertSame('%PDF-', file_get_contents($directory.'/rba.pdf', false, null, 0, 5));
            $this->assertSame($sourceHash, hash_file('sha256', $source));
        } finally {
            $_ENV = $environment;
            $_SERVER = $server;
            File::deleteDirectory($directory);
        }
    }
}
