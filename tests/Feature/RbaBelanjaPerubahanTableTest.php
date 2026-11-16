<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\RencanaKebutuhan;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class RbaBelanjaPerubahanTableTest extends TestCase
{
    public function test_comparison_shows_signed_differences_and_preserves_proposal_statuses(): void
    {
        $this->withoutVite();
        $user = new User(['upt' => 'Puskesmas A', 'role' => 'Kepala Puskesmas']);
        $user->id = 7;
        $this->actingAs($user);

        $cases = [
            [null, null, null, '0', 'Tidak Ada Perubahan', null],
            [150000, 'pending', null, '+50.000', 'Menunggu Verifikasi (Kepala Puskesmas)', 'rba-delta-positive'],
            [50000, 'verified', null, '-50.000', 'Terverifikasi', 'rba-delta-negative'],
            [0, 'pending', null, '-100.000', 'Menunggu Verifikasi (Kepala Puskesmas)', 'rba-delta-negative'],
            [100000, 'pending', 'Keterangan diperbarui', '0', 'Menunggu Verifikasi (Kepala Puskesmas)', null],
            [75000, 'rejected', null, '-25.000', 'Ditolak', 'rba-delta-negative'],
        ];
        $items = collect($cases)->map(function ($case, $index) {
            $item = new RencanaKebutuhan([
                'akun_id' => 1, 'uraian' => 'Rincian '.$index, 'jumlah' => 2, 'satuan' => 'Paket',
                'harga_satuan' => 50000, 'jumlah_harga' => 100000, 'keterangan' => 'Keterangan awal',
                'jumlah_harga_perubahan' => $case[0], 'status_perubahan' => $case[1],
                'keterangan_perubahan' => $case[2],
            ]);
            $item->id = $index + 1;
            $item->setRelation('akuns', new Akun(['kode_rek' => '5.1.02.01.01.0001', 'uraian' => 'Belanja Barang']));

            return $item;
        });
        $grouped = collect([1 => $items]);
        $total = $items->sum('proposed_jumlah_harga');
        $html = (string) $this->view('rba-belanja-perubahan', [
            'errors' => new \Illuminate\Support\ViewErrorBag,
            'grouped' => $grouped, 'groupedByKlasifikasi' => ['barang_jasa' => $grouped],
            'klasifikasi' => ['barang_jasa' => ['prefix' => '5.1.02', 'label' => 'Belanja Barang dan Jasa', 'color' => 'table-info']],
            'total_5' => $total, 'total_5_1' => $total, 'total_5_2' => 0, 'grandTotalJumlah' => $total,
            'tahun' => 2027, 'tahunRba' => 2027, 'kinerjaKegiatan' => null, 'puskesmasList' => collect(),
        ]);

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($document);
        $table = '//table[contains(@class, "rba-detail-table--comparison")]';
        $this->assertSame('Selisih (Rp)', trim($xpath->query($table.'/thead/tr[1]/th[4]')->item(0)->textContent));
        $rows = $xpath->query($table.'/tbody/tr');
        $this->assertCount(count($cases), $rows);

        foreach ($cases as $index => [, , , $difference, $status, $color]) {
            $cells = $xpath->query('./td', $rows->item($index));
            $this->assertCount(15, $cells);
            $this->assertSame($difference, trim($cells->item(13)->textContent));
            $this->assertStringContainsString($status, $cells->item(14)->textContent);
            if ($color) {
                $this->assertStringContainsString($color, $cells->item(13)->getAttribute('class'));
            }
        }
    }
}
