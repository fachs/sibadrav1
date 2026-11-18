<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class RbaDocumentFieldUpdater
{
    /**
     * Update Word document fields purely in PHP.
     * Injects <w:updateFields w:val="true"/> into word/settings.xml so that
     * Microsoft Word or LibreOffice automatically updates all dynamic fields
     * (Table of Contents, page numbers) when the document is opened.
     */
    public function update(string $word): void
    {
        $zip = new ZipArchive;
        if ($zip->open($word) !== true) {
            throw new RuntimeException('Berkas Word RBA tidak dapat dibuka untuk pembaruan field.');
        }

        try {
            $settings = $zip->getFromName('word/settings.xml');
            if ($settings !== false) {
                if (! str_contains($settings, '<w:updateFields')) {
                    $settings = preg_replace(
                        '/<w:settings([^>]*)>/i',
                        '<w:settings$1><w:updateFields w:val="true"/>',
                        $settings,
                        1
                    );
                    $zip->addFromString('word/settings.xml', $settings);
                }
            }
        } finally {
            $zip->close();
        }
    }
}

