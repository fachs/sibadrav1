<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardBludTest extends TestCase
{
    use \Tests\Support\SpdFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-11 12:00:00', 'Asia/Jakarta'));
        config(['database.default' => 'dashboard_testing', 'database.connections.dashboard_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'rba.tahun_anggaran' => 2027]);
        $this->withoutVite();
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id(); $table->string('nama');
        });
        Schema::create('akuns', function (Blueprint $table) {
            $table->id(); $table->string('kode_rek'); $table->string('uraian');
        });
        Schema::create('rencana_pendapatans', function (Blueprint $table) {
            $table->id(); $table->string('kode_rek'); $table->string('uraian'); $table->string('nama_blud');
            $table->unsignedSmallInteger('tahun_anggaran')->default(2027);
            $table->decimal('jumlah', 15, 2); $table->decimal('jumlah_perubahan', 15, 2)->nullable();
            $table->boolean('is_hasil_perubahan')->default(false); $table->string('status_perubahan')->nullable();
        });
        Schema::create('rencana_kebutuhans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('akun_id')->nullable(); $table->string('nama_blud');
            $table->string('subkegiatan')->nullable();
            $table->string('tahun_anggaran')->default('2027'); $table->decimal('jumlah_harga', 15, 2);
            $table->decimal('jumlah_harga_perubahan', 15, 2)->nullable();
            $table->boolean('is_hasil_perubahan')->default(false); $table->string('status_perubahan')->nullable(); $table->softDeletes();
        });
        Schema::create('penerimaans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('unit_blud_id'); $table->string('tahun_anggaran')->default('2027');
            $table->string('kode_rekening')->nullable(); $table->unsignedBigInteger('akun_id')->nullable(); $table->softDeletes();
            $table->date('tanggal_setor')->nullable(); $table->string('nomor_tbp')->nullable();
            $table->string('nomor_terima')->nullable(); $table->text('uraian')->nullable();
        });
        Schema::create('transaksi_penerimaans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('penerimaan_id'); $table->date('tanggal'); $table->decimal('total_biaya', 15, 2);
        });
        (require database_path('migrations/2026_09_28_040049_create_bku_pejabat_keuangans_table.php'))->up();
        (require database_path('migrations/2026_09_28_061218_add_is_saldo_awal_to_bku_pejabat_keuangans_table.php'))->up();
        (require database_path('migrations/2026_09_29_015503_create_bku_bendahara_pengeluarans_table.php'))->up();
        (require database_path('migrations/2026_11_09_000001_add_tax_and_subkegiatan_to_bku_bendahara_pengeluarans.php'))->up();
        (require database_path('migrations/2026_11_09_000002_add_rincian_pajak_to_bku_bendahara_pengeluarans.php'))->up();
        (require database_path('migrations/2026_11_09_000003_add_pajak_transactions_to_bku_bendahara_pengeluarans.php'))->up();
        (require database_path('migrations/2026_11_09_000004_simplify_bku_pajak_child_details.php'))->up();
        Schema::table('bku_bendahara_pengeluarans', fn (Blueprint $table) => $table->unsignedBigInteger('spd_ls_id')->nullable());
        $this->createSpdTables();
        $this->ledgerMigration()->up();
        DB::table('unit_bluds')->insert([['id' => 1, 'nama' => 'Puskesmas A'], ['id' => 2, 'nama' => 'Puskesmas B']]);
        DB::table('akuns')->insert([
            ['id' => 1, 'kode_rek' => '4.1.02.01.01.0001', 'uraian' => 'Pendapatan Rawat Jalan'],
            ['id' => 2, 'kode_rek' => '5.1.02.01.01.0001', 'uraian' => 'Belanja Alat Tulis'],
        ]);
        $this->login();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::purge('dashboard_testing');
        parent::tearDown();
    }

    private function ledgerMigration()
    {
        return require database_path('migrations/2026_10_20_000006_add_unit_blud_to_bku_tables.php');
    }

    private function login(string $role = 'Klaster 2', string $upt = 'Puskesmas A'): void
    {
        $user = new User(['role' => $role, 'upt' => $upt]);
        $user->id = 7;
        $this->actingAs($user);
    }

    public function test_dashboard_uses_global_year_and_discards_stale_week_from_another_year(): void
    {
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2026])->assertSessionHas('tahun_anggaran.user_7', 2026);
        $this->get(route('index', ['tahun' => 2027, 'minggu' => '2027-01-04']))->assertOk()
            ->assertViewHas('year', 2026)->assertSee('data-global-year="2026"', false);
    }

    private function income(array $overrides = []): void
    {
        DB::table('rencana_pendapatans')->insert(array_replace([
            'kode_rek' => '4.1.02.01.01.0001', 'uraian' => 'Pendapatan Rawat Jalan',
            'nama_blud' => 'Puskesmas A', 'jumlah' => 10000,
        ], $overrides));
    }

    private function budget(array $overrides = []): void
    {
        DB::table('rencana_kebutuhans')->insert(array_replace(['akun_id' => 2, 'nama_blud' => 'Puskesmas A', 'jumlah_harga' => 10000], $overrides));
    }

    private function receipt($amount, string $date, array $overrides = []): void
    {
        $parent = DB::table('penerimaans')->insertGetId(array_replace([
            'unit_blud_id' => 1, 'kode_rekening' => '4.1.02.01.01.0001', 'akun_id' => 1,
        ], $overrides));
        DB::table('transaksi_penerimaans')->insert(['penerimaan_id' => $parent, 'tanggal' => $date, 'total_biaya' => $amount]);
    }

    private function payment($amount, string $date, array $overrides = []): void
    {
        $this->spdPayment($amount, $date, $overrides);
    }

    private function cash(array $overrides = []): void
    {
        DB::table('bku_pejabat_keuangans')->insert(array_replace(['unit_blud_id' => 1, 'tanggal' => '2027-01-01'], $overrides));
    }

    public function test_home_renders_reference_layout_with_real_data_and_all_months(): void
    {
        $this->income(); $this->budget();
        $this->receipt(1000, '2027-01-10'); $this->receipt(500, '2027-02-02', ['kode_rekening' => '']);
        $this->payment(200, '2027-01-15'); $this->payment(300, '2027-02-03');
        $this->get('/')->assertOk()->assertSee('Dashboard BLUD')->assertSee('Realisasi Pendapatan')
            ->assertSee('Realisasi Belanja')->assertSee('Surplus / (Defisit) Realisasi')->assertSee('Saldo Kas')
            ->assertSee('Realisasi per Bulan')->assertSee('Pendapatan per Jenis Layanan')->assertSee('Belanja per Kelompok')
            ->assertDontSee('Sales Analytic')->assertDontSee('No. of Properties')
            ->assertViewHas('totals', function ($totals) {
                return $totals['realisasi_pendapatan'] === '1500.00' && $totals['realisasi_belanja'] === '500.00'
                    && $totals['surplus_realisasi'] === '1000.00' && $totals['persentase_pendapatan'] === 15.0
                    && $totals['persentase_belanja'] === 5.0;
            })->assertViewHas('monthly', fn ($rows) => count($rows) === 12 && $rows[0]['pendapatan'] === '1000.00'
                && $rows[1]['belanja'] === '300.00' && $rows[11]['pendapatan'] === '0.00');
        foreach (['/home', '/index'] as $url) $this->get($url)->assertOk()->assertViewHas('year', 2027);
    }

    public function test_lra_and_lpj_use_selected_year_and_user_unit_scope(): void
    {
        $this->income();
        $this->budget();
        $this->receipt(1000, '2027-01-10');
        $this->receipt(9000, '2027-01-10', ['unit_blud_id' => 2]);
        $this->payment(200, '2027-01-15');

        $lraResponse = $this->get(route('lra.index'));
        $lraResponse
            ->assertOk()->assertSee('Laporan Realisasi Anggaran')
            ->assertSee('Kode Rekening')->assertSee('PENDAPATAN DAERAH')->assertSee('1.000')
            ->assertSee('JUMLAH PENDAPATAN')->assertSee('SURPLUS/DEFISIT')
            ->assertDontSee('lra-signature')
            ->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '1000.00'
                && $totals['realisasi_belanja'] === '200.00')
            ->assertViewHas('reportRows', function ($rows) {
                $income = collect($rows)->firstWhere('label', 'JUMLAH PENDAPATAN');
                $expense = collect($rows)->firstWhere('label', 'JUMLAH BELANJA');
                return $income['actual'] === '1000.00' && $expense['actual'] === '200.00';
            });
        $document = new \DOMDocument;
        @$document->loadHTML($lraResponse->getContent());
        $cells = (new \DOMXPath($document))->query(
            "//tr[td[1][normalize-space(.)='4.1.02.01.001']]/td[position() >= 3]"
        );
        $this->assertSame([],
            array_map(fn ($cell) => trim($cell->textContent), iterator_to_array($cells)));
        $this->get(route('lpj.index'))
            ->assertOk()->assertSee('LAPORAN PERTANGGUNGJAWABAN SKPD')
            ->assertSee('Bulan : Januari')->assertSee('SPJ - LS Gaji')->assertSee('SPJ - UP/GU/TU')
            ->assertSee('Belanja Barang dan Jasa')->assertSee('Rp200,00')
            ->assertViewHas('lpj', fn ($report) => $report['month'] === 1 && $report['total']['barang_ini'] === '200.00');
        $this->get(route('lpj.index', ['bulan' => 2]))
            ->assertOk()->assertSee('Bulan : Februari')
            ->assertViewHas('lpj', fn ($report) => $report['total']['barang_lalu'] === '200.00'
                && $report['total']['barang_ini'] === '0.00');

        $this->post(route('tahun-anggaran.update'), ['tahun' => 2026])->assertSessionHas('tahun_anggaran.user_7', 2026);
        $this->get(route('lra.index'))->assertOk()->assertViewHas('year', 2026)
            ->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '0.00');
    }

    public function test_dinas_can_filter_lra_and_lpj_by_unit(): void
    {
        $this->receipt(100, '2027-01-10');
        $this->receipt(200, '2027-01-10', ['unit_blud_id' => 2]);
        $this->login('Klaster 2', 'Dinas Kesehatan');

        foreach (['lra.index', 'lpj.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Semua Unit BLUD')
                ->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '300.00');
            $this->get(route($route, ['unit_blud_id' => 2]))->assertOk()->assertSee('Puskesmas B')
                ->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '200.00');
        }
    }

    public function test_lpj_matches_spj_fungsional_sections_and_downloads_landscape_pdf(): void
    {
        $this->budget();
        $this->payment(200, '2027-01-15');
        DB::table('bku_bendahara_pengeluarans')->insert([
            ['unit_blud_id' => 1, 'tanggal' => '2027-01-10', 'jenis_pajak' => 'Pajak Pertambahan Nilai', 'nominal_pajak' => 150,
                'rincian_pajak' => json_encode([['jenis' => 'Pajak Pertambahan Nilai', 'nominal' => 150]])],
            ['unit_blud_id' => 2, 'tanggal' => '2027-01-10', 'jenis_pajak' => 'Pajak Pertambahan Nilai', 'nominal_pajak' => 900,
                'rincian_pajak' => json_encode([['jenis' => 'Pajak Pertambahan Nilai', 'nominal' => 900]])],
        ]);

        $page = $this->get(route('lpj.index', ['bulan' => 1]));
        $page->assertOk()->assertSee('Unduh LPJ PDF')
            ->assertSee('Total Potongan')->assertSee('Pajak Pertambahan Nilai')
            ->assertSee('JUMLAH PENERIMAAN SP2D BRUTO')->assertSee('Saldo Kas')
            ->assertDontSee('Menyetujui,')->assertDontSee('Disiapkan oleh,')
            ->assertViewHas('lpj', fn ($report) => $report['taxes']['Pajak Pertambahan Nilai']['ini'] === '150.00');

        $response = $this->get(route('lpj.download', ['bulan' => 1]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_lpj_uses_excel_account_reference_for_missing_intermediate_codes(): void
    {
        (require database_path('migrations/2026_11_10_055853_create_referensi_kode_rekenings_table.php'))->up();
        $this->assertSame(479, DB::table('referensi_kode_rekenings')->count());
        $this->assertDatabaseHas('referensi_kode_rekenings', [
            'kode_rek' => '5.1.01.99', 'uraian' => 'Belanja Pegawai BLUD',
        ]);

        DB::table('akuns')->insert(['id' => 3, 'kode_rek' => '5.1.01.99.09999', 'uraian' => 'Belanja Pegawai Khusus']);
        $this->budget(['akun_id' => 3]);
        $report = app(\App\Support\LpjReportData::class)->build(2027, 1, 'Puskesmas A', 1);
        $labels = collect($report['rows'])->pluck('label', 'code');
        $this->assertSame('Belanja Pegawai BLUD', $labels['5.1.01.99']);
        $this->assertSame('Belanja Pegawai Khusus', $labels['5.1.01.99.09999']);

        DB::table('akuns')->insert(['id' => 4, 'kode_rek' => '5.1.01.99', 'uraian' => 'Uraian dari Akuns']);
        $labels = app(\App\Support\KodeRekeningUraian::class)->withPrefix('5.1.01.99');
        $this->assertSame('Uraian dari Akuns', $labels['5.1.01.99']);
    }

    public function test_lra_download_uses_reference_pdf_layout_and_scoped_years(): void
    {
        $this->income();
        $this->budget();
        $this->receipt(1000, '2027-01-10');
        $this->receipt(9000, '2027-01-10', ['unit_blud_id' => 2]);
        $this->receipt(400, '2026-02-10', ['tahun_anggaran' => '2026']);
        $this->payment(200, '2027-01-15');

        $response = $this->get(route('lra.download'));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('lra-puskesmas-a-2027.pdf', $response->headers->get('content-disposition'));
        $this->get(route('lra.index'))->assertOk()->assertViewHas('reportRows', function ($rows) {
            $income = collect($rows)->firstWhere('label', 'JUMLAH PENDAPATAN');
            return $income['actual'] === '1000.00' && $income['previous'] === '400.00';
        });
    }

    public function test_lra_shows_expense_rekening_hierarchy(): void
    {
        DB::table('akuns')->insert(['id' => 3, 'kode_rek' => '5.1.01.02.01.0001', 'uraian' => 'Honorarium']);
        $this->budget(['akun_id' => 3, 'jumlah_harga' => 2500]);
        $this->get(route('lra.index'))->assertOk()
            ->assertViewHas('reportRows', function ($rows) {
                $employee = collect($rows)->firstWhere('code', '5.1.01');
                $detail = collect($rows)->firstWhere('code', '5.1.01.02.01.0001');
                return $employee['budget'] === '2500.00' && $employee['label'] === 'Belanja Pegawai'
                    && $detail['budget'] === '2500.00' && $detail['label'] === 'Honorarium';
            })
            ->assertSee('5.1.01.02.01.0001');
    }

    public function test_lra_shows_only_accounts_planned_in_rba(): void
    {
        $this->income();
        $this->budget();
        $this->receipt(100, '2027-01-10');
        $this->receipt(900, '2027-01-11', ['kode_rekening' => '4.2.01.01.01.0001']);
        $this->receipt(800, '2026-01-11', ['kode_rekening' => '4.2.01.01.01.0001', 'tahun_anggaran' => '2026']);
        $this->payment(50, '2027-01-15');
        $this->payment(700, '2027-01-16', ['kode_rekening' => '5.2.02.01.01.0001']);

        $this->get(route('lra.index'))->assertOk()->assertViewHas('reportRows', function ($rows) {
            $items = collect($rows);
            return ! $items->pluck('code')->contains('4.2.01.01.01.0001')
                && ! $items->pluck('code')->contains('5.2.02.01.01.0001')
                && ! $items->pluck('code')->contains('4.1.02.01.001')
                && $items->firstWhere('label', 'JUMLAH PENDAPATAN')['actual'] === '100.00'
                && $items->firstWhere('label', 'JUMLAH PENDAPATAN')['previous'] === '0.00'
                && $items->firstWhere('label', 'JUMLAH BELANJA')['actual'] === '50.00';
        });
    }

    public function test_lra_includes_scoped_rba_financing_and_its_net_total(): void
    {
        $this->income(['jumlah' => 1000]);
        $this->budget(['jumlah_harga' => 200]);
        Schema::create('rencana_pembiayaans', function (Blueprint $table) {
            $table->id(); $table->string('nama_blud'); $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode_rek'); $table->string('kode_rek_perubahan')->nullable();
            $table->string('uraian'); $table->string('uraian_perubahan')->nullable();
            $table->decimal('jumlah', 15, 2); $table->decimal('jumlah_perubahan', 15, 2)->nullable();
            $table->string('status_perubahan')->nullable();
        });
        DB::table('rencana_pembiayaans')->insert([
            ['nama_blud' => 'Puskesmas A', 'tahun_anggaran' => 2027, 'kode_rek' => '6.1.01.01.01.0001',
                'kode_rek_perubahan' => '6.1.02.01.01.0001', 'uraian' => 'Penerimaan awal',
                'uraian_perubahan' => 'Penerimaan disetujui', 'jumlah' => 500, 'jumlah_perubahan' => 600, 'status_perubahan' => 'verified'],
            ['nama_blud' => 'Puskesmas A', 'tahun_anggaran' => 2027, 'kode_rek' => '6.2.01.01.01.0001',
                'kode_rek_perubahan' => null, 'uraian' => 'Pengeluaran pembiayaan',
                'uraian_perubahan' => null, 'jumlah' => 200, 'jumlah_perubahan' => null, 'status_perubahan' => null],
            ['nama_blud' => 'Puskesmas B', 'tahun_anggaran' => 2027, 'kode_rek' => '6.1.03.02.01.0001',
                'kode_rek_perubahan' => null, 'uraian' => 'Pembiayaan unit lain',
                'uraian_perubahan' => null, 'jumlah' => 900, 'jumlah_perubahan' => null, 'status_perubahan' => null],
        ]);

        $this->get(route('lra.index'))->assertOk()->assertViewHas('reportRows', function ($rows) {
            $items = collect($rows);
            return $items->firstWhere('code', '6.1.02.01.01.0001')['budget'] === '600.00'
                && $items->firstWhere('code', '6.2.01.01.01.0001')['budget'] === '200.00'
                && ! $items->pluck('code')->contains('6.1.01.01.01.0001')
                && ! $items->pluck('code')->contains('6.1.03.02.01.0001')
                && $items->firstWhere('code', '6')['label'] === 'PEMBIAYAAN DAERAH'
                && $items->firstWhere('label', 'SURPLUS/DEFISIT')['budget'] === '800.00'
                && $items->firstWhere('label', 'PEMBIAYAAN NETO')['budget'] === '400.00'
                && $items->firstWhere('label', 'SISA LEBIH PEMBIAYAAN ANGGARAN')['budget'] === '1200.00';
        });
        $this->get(route('lra.download'))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_lra_is_empty_without_rba_even_when_transactions_exist(): void
    {
        $this->receipt(100, '2027-01-10');
        $this->payment(50, '2027-01-15');
        $this->get(route('lra.index'))->assertOk()
            ->assertSee('Belum ada data RBA untuk tahun anggaran dan unit ini.')
            ->assertViewHas('reportRows', fn ($rows) => $rows === []);
        $this->get(route('lra.download'))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_lra_classification_and_date_filters_apply_to_web_and_pdf_link(): void
    {
        $this->income();
        $this->budget();
        $this->receipt(100, '2027-01-10');
        $this->receipt(200, '2027-02-10');
        $this->receipt(300, '2026-01-10', ['tahun_anggaran' => '2026']);
        $this->receipt(400, '2026-02-10', ['tahun_anggaran' => '2026']);
        $this->payment(50, '2027-01-15');
        $this->payment(70, '2027-02-15');

        $filters = ['klasifikasi' => 1, 'tanggal_awal' => '2027-01-01', 'tanggal_akhir' => '2027-01-31'];
        $response = $this->get(route('lra.index', $filters));
        $response->assertOk()->assertSee('Klasifikasi, Kodefikasi dan Nomenklatur Rekening')
            ->assertSee('1. Akun')->assertSee('6. Sub Rincian Objek')
            ->assertSee('01 Januari 2027 Sampai 31 Januari 2027')
            ->assertSee('klasifikasi=1')->assertSee('tanggal_awal=2027-01-01')
            ->assertSee('tanggal_akhir=2027-01-31')
            ->assertViewHas('reportRows', function ($rows) {
                $income = collect($rows)->firstWhere('label', 'JUMLAH PENDAPATAN');
                $expense = collect($rows)->firstWhere('label', 'JUMLAH BELANJA');
                $codes = collect($rows)->pluck('code')->filter()->all();
                return $income['budget'] === '10000.00' && $income['actual'] === '100.00'
                    && $income['previous'] === '300.00' && $expense['actual'] === '50.00'
                    && count($codes) === 2 && in_array('4', $codes, true) && in_array('5', $codes, true);
            });
        $this->get(route('lra.download', $filters))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->income(['kode_rek' => '4.3.01.01.001.00001', 'uraian' => 'Pendapatan Hibah', 'jumlah' => 500]);
        $this->get(route('lra.index', ['klasifikasi' => 2]))->assertOk()
            ->assertViewHas('reportRows', function ($rows) {
                $hibah = collect($rows)->firstWhere('code', '4.3');
                return $hibah['label'] === 'Pendapatan Hibah' && $hibah['budget'] === '500.00'
                    && collect($rows)->where('code', '!=', '')->every(fn ($row) => count(explode('.', $row['code'])) <= 2);
            });
        $this->get(route('lra.index', ['klasifikasi' => 7]))->assertSessionHasErrors('klasifikasi');
        $this->get(route('lra.index', ['tanggal_awal' => '2027-02-01', 'tanggal_akhir' => '2027-01-31']))
            ->assertSessionHasErrors('tanggal_akhir');
    }

    public function test_dashboard_defaults_to_current_year_when_active_rba_is_next_year(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-21 12:00:00', 'Asia/Jakarta'));
        $this->income();
        $this->receipt(1643000, '2026-10-08', ['tahun_anggaran' => '2026']);
        $this->receipt(500, '2027-01-04');
        $this->receipt(900000, '2026-10-08', ['tahun_anggaran' => '2026', 'unit_blud_id' => 2]);

        foreach (['/', '/home', '/index'] as $url) {
            $this->get($url)->assertOk()->assertViewHas('year', 2026)->assertSee('Rp 1.643.000')
                ->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '1643000.00'
                    && $totals['target_pendapatan'] === '0.00')
                ->assertViewHas('monthly', fn ($rows) => $rows[9]['pendapatan'] === '1643000.00');
        }
        $this->get('/?tahun=2027')->assertOk()->assertViewHas('year', 2027)
            ->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '500.00'
                && $totals['target_pendapatan'] === '10000.00');
    }

    public function test_default_dashboard_year_follows_jakarta_at_the_year_boundary(): void
    {
        config(['rba.tahun_anggaran' => 2028]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-31 18:30:00', 'UTC'));
        $this->receipt(100, '2027-01-01');

        $this->get('/')->assertOk()->assertViewHas('year', 2027)->assertViewHas('selectedWeekDate', '2027-01-01')
            ->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '100.00');
    }

    public function test_only_verified_changes_affect_budgets_and_pending_new_accounts_do_not_count(): void
    {
        $this->income(['jumlah_perubahan' => 15000, 'status_perubahan' => 'pending']);
        $this->income(['jumlah' => 5000, 'jumlah_perubahan' => 7000, 'status_perubahan' => 'verified']);
        $this->income(['jumlah' => 0, 'jumlah_perubahan' => 2000, 'is_hasil_perubahan' => true, 'status_perubahan' => 'pending']);
        $this->income(['jumlah' => 0, 'jumlah_perubahan' => 1000, 'is_hasil_perubahan' => true, 'status_perubahan' => 'verified']);
        $this->budget(['jumlah_harga_perubahan' => 15000, 'status_perubahan' => 'pending']);
        $this->budget(['jumlah_harga' => 5000, 'jumlah_harga_perubahan' => 7000, 'status_perubahan' => 'verified']);
        $this->budget(['jumlah_harga' => 0, 'jumlah_harga_perubahan' => 2000, 'is_hasil_perubahan' => true, 'status_perubahan' => 'pending']);
        $this->budget(['jumlah_harga' => 0, 'jumlah_harga_perubahan' => 1000, 'is_hasil_perubahan' => true, 'status_perubahan' => 'verified']);
        $this->budget(['jumlah_harga' => 999999, 'deleted_at' => now()]);
        $this->budget(['jumlah_harga' => 999999, 'akun_id' => null]);
        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['target_pendapatan'] === '18000.00' && $totals['pagu_belanja'] === '18000.00')
            ->assertViewHas('expenseRows', fn ($rows) => count($rows) === 1 && $rows[0]['kode_rek'] === '5.1.02');
    }

    public function test_daily_income_shows_monday_to_saturday_in_selected_week_and_respects_scope(): void
    {
        $this->receipt('100.25', '2027-01-04');
        $this->receipt('200.50', '2027-01-04', ['kode_rekening' => '4.9.99']);
        $this->receipt('300.75', '2027-01-06');
        $this->receipt('400.00', '2027-01-09');
        $this->receipt(999, '2027-01-03');
        $this->receipt(999, '2027-01-10');
        $this->receipt(999, '2027-01-11');
        $this->receipt(999, '2027-01-04', ['unit_blud_id' => 2]);
        $this->receipt(999, '2027-01-04', ['deleted_at' => now()]);
        $this->receipt(999, '2027-01-04', ['tahun_anggaran' => '2026']);

        $this->get('/home?minggu=2027-01-07')->assertOk()->assertSee('Pendapatan Mingguan')
            ->assertSee('dashboard-weekly-chart')->assertSee('dashboard-weekly-data')
            ->assertSee('Pendapatan harian')->assertViewHas('selectedWeekDate', '2027-01-07')
            ->assertViewHas('weekly', function ($days) {
                return array_column($days, 'label') === ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
                    && $days[0]['date'] === '2027-01-04' && $days[5]['date'] === '2027-01-09'
                    && array_column($days, 'pendapatan') === ['300.75', '0.00', '300.75', '0.00', '0.00', '400.00'];
            });
        $this->login('Superuser');
        $this->get('/?minggu=2027-01-07')->assertOk()->assertViewHas('weekly', fn ($days) => $days[0]['pendapatan'] === '1299.75');
        $this->get('/?minggu=2027-01-07&puskesmas=Puskesmas%20B')->assertOk()
            ->assertViewHas('weekly', fn ($days) => $days[0]['pendapatan'] === '999.00' && $days[2]['pendapatan'] === '0.00');
        $this->get('/?minggu=2027-01-10')->assertOk()
            ->assertViewHas('weekly', fn ($days) => $days[0]['date'] === '2027-01-04' && count($days) === 6);
    }

    public function test_daily_income_handles_weeks_crossing_month_and_year_boundaries_in_supported_years(): void
    {
        $this->receipt('25.50', '2026-04-30', ['tahun_anggaran' => '2026']);
        $this->receipt('50.25', '2026-12-31', ['tahun_anggaran' => '2026']);
        $this->receipt(999, '2027-01-01', ['tahun_anggaran' => '2027']);
        $this->get('/?tahun=2026&minggu=2026-04-30')->assertOk()->assertViewHas('weekly', function ($days) {
            return count($days) === 6 && $days[0]['date'] === '2026-04-27'
                && $days[3]['date'] === '2026-04-30' && $days[3]['pendapatan'] === '25.50'
                && $days[5]['date'] === '2026-05-02';
        });
        $this->get('/?tahun=2026&minggu=2026-12-31')->assertOk()
            ->assertViewHas('weekly', fn ($days) => count($days) === 6 && $days[3]['pendapatan'] === '50.25'
                && $days[4]['date'] === '2027-01-01' && $days[4]['pendapatan'] === '0.00');
    }

    public function test_daily_income_defaults_to_current_week_in_jakarta_and_rejects_invalid_dates(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-10 18:30:00', 'UTC'));
        $this->get('/')->assertOk()->assertViewHas('selectedWeekDate', '2027-01-11')
            ->assertViewHas('weekly', fn ($days) => count($days) === 6 && $days[0]['date'] === '2027-01-11'
                && $days[5]['date'] === '2027-01-16' && array_unique(array_column($days, 'pendapatan')) === ['0.00']);
        foreach (['invalid', '2027-02-30', '2026-12-31', '2028-01-01'] as $date) {
            $this->get('/?minggu='.$date)->assertSessionHasErrors('minggu');
        }
        $this->travelBack();
    }

    public function test_regular_roles_cannot_select_another_blud_and_unmapped_ledger_rows_are_excluded(): void
    {
        $this->income(); $this->income(['nama_blud' => 'Puskesmas B', 'jumlah' => 900000]);
        $this->receipt(100, '2027-01-01'); $this->receipt(900000, '2027-01-01', ['unit_blud_id' => 2]);
        $this->payment(25, '2027-01-01'); $this->payment(900000, '2027-01-01', ['unit_blud_id' => 2]);
        $this->payment(800000, '2027-01-01', ['unit_blud_id' => null]);
        $this->cash(['is_saldo_awal' => true, 'saldo' => 1000]);
        $this->cash(['unit_blud_id' => 2, 'is_saldo_awal' => true, 'saldo' => 900000]);
        $this->cash(['unit_blud_id' => null, 'is_saldo_awal' => true, 'saldo' => 800000]);
        $this->get('/?puskesmas=Puskesmas%20B')->assertOk()->assertViewHas('unitLabel', 'Puskesmas A')
            ->assertViewHas('totals', fn ($totals) => $totals['target_pendapatan'] === '10000.00'
                && $totals['realisasi_pendapatan'] === '100.00' && $totals['realisasi_belanja'] === '25.00' && $totals['saldo_kas'] === '975.00');
    }

    public function test_superuser_and_dinas_can_view_all_units_and_filter_one_unit(): void
    {
        $this->income(); $this->income(['nama_blud' => 'Puskesmas B', 'jumlah' => 20000]);
        $this->receipt(100, '2027-01-01'); $this->receipt(200, '2027-01-01', ['unit_blud_id' => 2]);
        $this->payment(50, '2027-01-01'); $this->payment(75, '2027-01-01', ['unit_blud_id' => 2]);
        foreach (['Superuser', 'Dinas Kesehatan', 'Dinas'] as $role) {
            $this->login($role, 'Dinas Kesehatan');
            $this->get('/')->assertOk()->assertViewHas('unitLabel', 'Semua Unit BLUD')
                ->assertViewHas('totals', fn ($totals) => $totals['target_pendapatan'] === '30000.00' && $totals['realisasi_pendapatan'] === '300.00');
            $this->get('/?puskesmas=Puskesmas%20B')->assertOk()
                ->assertViewHas('totals', fn ($totals) => $totals['target_pendapatan'] === '20000.00' && $totals['realisasi_belanja'] === '75.00');
        }
    }

    public function test_year_filter_uses_transaction_date_and_excludes_deleted_or_wrong_year_receipts(): void
    {
        $this->income(); $this->budget();
        $this->budget(['tahun_anggaran' => '2026', 'jumlah_harga' => 2000]);
        $this->receipt(100, '2027-01-01');
        $this->receipt(999, '2027-01-01', ['deleted_at' => now()]);
        $this->receipt(999, '2027-01-01', ['tahun_anggaran' => '2026']);
        $this->receipt(999, '2026-01-01');
        $this->receipt(200, '2026-02-01', ['tahun_anggaran' => '2026']);
        $this->payment(70, '2026-02-01');
        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '100.00');
        $this->get('/?tahun=2026')->assertOk()->assertViewHas('year', 2026)
            ->assertViewHas('totals', fn ($totals) => $totals['target_pendapatan'] === '0.00'
                && $totals['pagu_belanja'] === '2000.00' && $totals['realisasi_pendapatan'] === '200.00' && $totals['realisasi_belanja'] === '70.00');
    }

    public function test_historical_income_and_expense_budgets_are_separated_from_active_rba_year(): void
    {
        $this->income(['jumlah' => 10000]);
        $this->income(['tahun_anggaran' => 2026, 'jumlah' => 50000]);
        $this->budget(['tahun_anggaran' => '2026', 'jumlah_harga' => 20000]);
        $this->get('/?tahun=2026')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['target_pendapatan'] === '50000.00'
            && $totals['pagu_belanja'] === '20000.00');
        $this->get('/?tahun=2027')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['target_pendapatan'] === '10000.00'
            && $totals['pagu_belanja'] === '0.00');
    }

    public function test_expenses_use_final_spd_ls_and_gu_values_without_counting_bendahara_payments_again(): void
    {
        $this->budget();
        $this->cash(['is_saldo_awal' => true, 'saldo' => 5000]);
        $lsId = $this->spdPayment(1000, '2027-01-05', ['jumlah' => 1200, 'potongan' => 200]);
        $this->spdPayment(2000, '2027-02-06', ['jumlah' => 2500, 'potongan' => 500], 'gu');
        DB::table('bku_bendahara_pengeluarans')->insert([
            ['unit_blud_id' => 1, 'tanggal' => '2027-01-05', 'spd_ls_id' => $lsId, 'pengeluaran' => 1000],
            ['unit_blud_id' => 1, 'tanggal' => '2027-01-06', 'spd_ls_id' => null, 'pengeluaran' => 9000],
        ]);
        DB::table('ppd_lss')->insert(['unit_blud_id' => 1, 'jumlah' => 8000]);
        DB::table('opd_gus')->insert(['unit_blud_id' => 1, 'jumlah' => 7000]);

        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['realisasi_belanja'] === '3000.00'
            && $totals['persentase_belanja'] === 30.0 && $totals['saldo_kas'] === '2000.00')
            ->assertViewHas('monthly', fn ($rows) => $rows[0]['belanja'] === '1000.00' && $rows[1]['belanja'] === '2000.00')
            ->assertViewHas('expenseRows', fn ($rows) => collect($rows)->firstWhere('kode_rek', '5.1.02')['realisasi'] === '1000.00'
                && collect($rows)->firstWhere('kode_rek', '')['realisasi'] === '2000.00');
    }

    public function test_spd_expenses_respect_unit_year_soft_deletion_and_fallback_date(): void
    {
        $this->spdPayment(100, '2027-01-05', ['tanggal_dba' => null]);
        $this->spdPayment(900, '2027-01-05', ['unit_blud_id' => 2]);
        $this->spdPayment(800, '2027-01-05', ['deleted_at' => now()]);
        $this->spdPayment(700, '2027-01-05', ['tahun_anggaran' => '2026']);
        $this->spdPayment(600, '2026-12-31', ['tahun_anggaran' => '2027']);
        $this->spdPayment(50, '2026-10-01');
        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['realisasi_belanja'] === '100.00');
        $this->get('/?tahun=2026')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['realisasi_belanja'] === '50.00');
        $this->login('Superuser');
        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['realisasi_belanja'] === '1000.00');
        $this->get('/?puskesmas=Puskesmas%20B')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['realisasi_belanja'] === '900.00');
    }

    public function test_cash_is_calculated_per_unit_and_carries_previous_year_balance_forward(): void
    {
        $this->cash(['tanggal' => '2026-01-01', 'is_saldo_awal' => true, 'saldo' => 1000]);
        $this->cash(['tanggal' => '2026-02-01', 'penerimaan' => 500]);
        $this->cash(['tanggal' => '2027-01-03', 'penerimaan' => 200, 'pengeluaran' => 50]);
        $this->cash(['tanggal' => '2028-01-01', 'penerimaan' => 999999]);
        $this->cash(['unit_blud_id' => 2, 'is_saldo_awal' => true, 'saldo' => 2000]);
        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['saldo_awal'] === '1500.00' && $totals['saldo_kas'] === '1650.00');
        $this->cash(['tanggal' => '2027-01-01', 'is_saldo_awal' => true, 'saldo' => 3000]);
        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['saldo_awal'] === '3000.00' && $totals['saldo_kas'] === '3150.00');
        $this->login('Superuser');
        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['saldo_awal'] === '5000.00' && $totals['saldo_kas'] === '5150.00');
    }

    public function test_cash_includes_income_only_after_deposit_and_uses_deposit_date(): void
    {
        $this->cash(['is_saldo_awal' => true, 'saldo' => 1000]);
        $this->receipt(1643000, '2026-12-31', ['tahun_anggaran' => '2026', 'tanggal_setor' => '2027-01-05']);
        $this->receipt(900, '2027-01-04');
        $this->receipt(800, '2027-01-04', ['tanggal_setor' => '2027-01-05', 'unit_blud_id' => 2]);
        $this->receipt(700, '2027-01-04', ['tanggal_setor' => '2027-01-05', 'deleted_at' => now()]);

        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['saldo_awal'] === '1000.00'
            && $totals['saldo_kas'] === '1644000.00' && $totals['realisasi_pendapatan'] === '900.00');
    }

    public function test_empty_units_zero_budgets_and_percentages_above_one_hundred_are_safe(): void
    {
        $this->get('/')->assertOk()->assertSee('Target belum tersedia')->assertSee('Pagu belum tersedia')
            ->assertViewHas('monthly', fn ($rows) => count($rows) === 12)
            ->assertViewHas('totals', fn ($totals) => $totals['persentase_pendapatan'] === null);
        $this->income(['jumlah' => 100]); $this->receipt(250, '2027-01-01');
        $this->get('/')->assertOk()->assertSee('250,0%')->assertSee('width: 100%', false)
            ->assertViewHas('totals', fn ($totals) => $totals['persentase_pendapatan'] === 250.0);
        $this->login('Klaster 3', 'Unit belum terdaftar');
        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '0.00' && $totals['saldo_kas'] === '0.00');
        $this->login('Klaster 3', '');
        $this->get('/')->assertOk()->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '0.00');
    }

    public function test_actuals_without_corresponding_budget_remain_in_group_totals(): void
    {
        $this->receipt(123, '2027-01-01', ['kode_rekening' => '4.9.99', 'akun_id' => null]);
        $this->payment(45, '2027-01-02', ['kode_rekening' => null]);
        $this->get('/')->assertOk()->assertSee('Pendapatan Belum Diklasifikasikan')->assertSee('Belanja Belum Diklasifikasikan')
            ->assertViewHas('totals', fn ($totals) => $totals['realisasi_pendapatan'] === '123.00' && $totals['realisasi_belanja'] === '45.00');
    }

    public function test_bku_new_entries_are_attributed_to_current_blud_and_ignore_forged_unit(): void
    {
        $this->login('Bendahara Pengeluaran');
        DB::table('rencana_kebutuhans')->insert([
            'nama_blud' => 'Puskesmas A', 'akun_id' => 2, 'tahun_anggaran' => '2027', 'jumlah_harga' => 1000,
            'subkegiatan' => 'Pelayanan BLUD',
        ]);
        $this->post(route('bku-bendahara-pengeluaran.store'), [
            'tanggal' => '2027-01-01', 'uraian' => 'Belanja alat tulis', 'jumlah' => '1.000',
            'kode_rekening' => '5.1.02.01.01.0001', 'subkegiatan' => 'Pelayanan BLUD',
            'pajaks' => [
                ['jenis' => 'Pajak Pertambahan Nilai', 'nominal' => '100'],
                ['jenis' => 'Pajak Daerah', 'nominal' => '50'],
            ], 'unit_blud_id' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', [
            'unit_blud_id' => 1, 'penerimaan' => 0, 'pengeluaran' => 1000, 'giro' => 0,
            'subkegiatan' => 'Pelayanan BLUD', 'jenis_pajak' => 'Pajak Pertambahan Nilai', 'nominal_pajak' => 150,
        ]);
        $parentId = DB::table('bku_bendahara_pengeluarans')->whereNull('parent_bku_id')->value('id');
        $this->assertSame([
            ['jenis' => 'Pajak Pertambahan Nilai', 'nominal' => 100],
            ['jenis' => 'Pajak Daerah', 'nominal' => 50],
        ], json_decode(DB::table('bku_bendahara_pengeluarans')->where('id', $parentId)->value('rincian_pajak'), true));
        $this->assertDatabaseCount('bku_bendahara_pengeluarans', 5);
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', [
            'parent_bku_id' => $parentId, 'pajak_entry_type' => 'pemotongan', 'pengeluaran' => 100,
        ]);
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', [
            'parent_bku_id' => $parentId, 'pajak_entry_type' => 'penyetoran', 'penerimaan' => 50,
        ]);
        $this->login('Pejabat Keuangan');
        $this->post(route('bku-pejabat-keuangan.store'), [
            'tanggal' => '2027-01-01', 'uraian' => 'Penerimaan', 'no_transaksi' => '001', 'kode_rekening' => '4.1',
            'penerimaan' => '2.000', 'pengeluaran' => '100', 'unit_blud_id' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bku_pejabat_keuangans', ['unit_blud_id' => 1, 'penerimaan' => 2000]);
    }

    public function test_bku_new_transaction_cannot_exceed_remaining_rba_rekening_budget(): void
    {
        $this->login('Bendahara Pengeluaran');
        Schema::create('ups', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('unit_blud_id');
            $table->string('tahun_anggaran'); $table->string('no_spd')->nullable();
            $table->bigInteger('jumlah')->default(0); $table->bigInteger('potongan')->default(0);
            $table->timestamps(); $table->softDeletes();
        });
        (require database_path('migrations/2026_11_04_000001_create_bku_bendahara_perubahan_requests_table.php'))->up();
        (require database_path('migrations/2026_11_04_000002_add_spd_ls_to_bku_bendahara_perubahan_requests_table.php'))->up();
        (require database_path('migrations/2026_11_09_000005_add_verifikator_to_bku_bendahara_perubahan_requests.php'))->up();
        $this->budget(['subkegiatan' => 'Pelayanan BLUD', 'jumlah_harga' => 300000]);
        $this->budget(['subkegiatan' => 'Pelayanan BLUD', 'jumlah_harga' => 100000,
            'jumlah_harga_perubahan' => 200000, 'status_perubahan' => 'verified']);
        $this->budget(['nama_blud' => 'Puskesmas B', 'subkegiatan' => 'Pelayanan BLUD', 'jumlah_harga' => 100000]);
        $this->budget(['tahun_anggaran' => '2026', 'subkegiatan' => 'Pelayanan BLUD', 'jumlah_harga' => 100000]);

        $parentId = DB::table('bku_bendahara_pengeluarans')->insertGetId([
            'unit_blud_id' => 1, 'tanggal' => '2027-01-02', 'kode_rekening' => '5.1.02.01.01.0001',
            'subkegiatan' => 'Pelayanan BLUD', 'uraian' => 'Belanja sebelumnya', 'pengeluaran' => 250000,
        ]);
        DB::table('bku_bendahara_pengeluarans')->insert([
            'unit_blud_id' => 1, 'tanggal' => '2027-01-02', 'parent_bku_id' => $parentId,
            'uraian' => 'Pemotongan pajak', 'pengeluaran' => 5000,
        ]);
        DB::table('bku_bendahara_pengeluarans')->insert([
            'unit_blud_id' => 2, 'tanggal' => '2027-01-02', 'kode_rekening' => '5.1.02.01.01.0001',
            'uraian' => 'Belanja unit lain', 'pengeluaran' => 100000,
        ]);
        DB::table('bku_bendahara_pengeluarans')->insert([
            'unit_blud_id' => 1, 'tanggal' => '2026-12-31', 'kode_rekening' => '5.1.02.01.01.0001',
            'uraian' => 'Belanja tahun lalu', 'pengeluaran' => 100000,
        ]);

        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertSee('data-pagu="500000"', false)->assertSee('data-sisa-pagu="250000"', false);
        $payload = ['tanggal' => '2027-01-11', 'subkegiatan' => 'Pelayanan BLUD',
            'kode_rekening' => '5.1.02.01.01.0001', 'uraian' => 'Belanja berikutnya'];
        $this->post(route('bku-bendahara-pengeluaran.store'), $payload + ['jumlah' => '250.001'])
            ->assertSessionHasErrors('jumlah');
        $this->assertDatabaseMissing('bku_bendahara_pengeluarans', ['uraian' => 'Belanja berikutnya']);
        $this->post(route('bku-bendahara-pengeluaran.store'), $payload + ['jumlah' => '250.000'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bku_bendahara_pengeluarans',
            ['uraian' => 'Belanja berikutnya', 'pengeluaran' => 250000]);
        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertSee('data-sisa-pagu="0"', false);
    }

    public function test_bku_manual_rincian_appears_under_rekening_without_separate_child_input(): void
    {
        $this->login('Bendahara Pengeluaran');
        Schema::create('ups', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('unit_blud_id');
            $table->string('tahun_anggaran'); $table->string('no_spd')->nullable();
            $table->bigInteger('jumlah')->default(0); $table->bigInteger('potongan')->default(0);
            $table->timestamps(); $table->softDeletes();
        });
        (require database_path('migrations/2026_11_04_000001_create_bku_bendahara_perubahan_requests_table.php'))->up();
        (require database_path('migrations/2026_11_04_000002_add_spd_ls_to_bku_bendahara_perubahan_requests_table.php'))->up();
        (require database_path('migrations/2026_11_09_000005_add_verifikator_to_bku_bendahara_perubahan_requests.php'))->up();
        DB::table('rencana_kebutuhans')->insert([
            'nama_blud' => 'Puskesmas A', 'akun_id' => 2, 'tahun_anggaran' => '2027',
            'jumlah_harga' => 750000, 'subkegiatan' => 'Pelayanan BLUD',
        ]);
        $this->post(route('bku-bendahara-pengeluaran.store'), [
            'tanggal' => '2027-01-11', 'kode_rekening' => '5.1.02.01.01.0001',
            'subkegiatan' => 'Pelayanan BLUD', 'uraian' => 'Belanja Amlodipine 5mg',
            'jumlah' => '750.000',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $parentId = DB::table('bku_bendahara_pengeluarans')->value('id');
        $html = $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertSee('data-bku-toggle="pajak-'.$parentId.'"', false)->getContent();
        $tableStart = strpos($html, '<table class="table table-striped table-bordered align-middle table-sm">');
        $this->assertGreaterThan(strpos($html, '</table>', $tableStart), strpos($html, 'id="modalEditBku'.$parentId.'"'));
        $parentStart = strpos($html, 'data-bku-toggle="pajak-'.$parentId.'"');
        $parentRow = substr($html, $parentStart, strpos($html, '</tr>', $parentStart) - $parentStart);
        $this->assertStringContainsString('Belanja Alat Tulis', $parentRow);
        $this->assertStringNotContainsString('Belanja Amlodipine 5mg', $parentRow);
        $detailStart = strpos($html, 'data-bku-child="pajak-'.$parentId.'"', $parentStart);
        $detailRow = substr($html, $detailStart, strpos($html, '</tr>', $detailStart) - $detailStart);
        $this->assertStringContainsString('Belanja Amlodipine 5mg', $detailRow);
        $this->assertStringContainsString('Rp 750.000', $detailRow);
        $this->assertStringContainsString('Rp -750.000', $detailRow);
        $this->assertDatabaseCount('bku_bendahara_pengeluarans', 1);

        foreach (['modalEditBku'.$parentId, 'modalTambahBku'] as $modalId) {
            $modalStart = strpos($html, 'id="'.$modalId.'"');
            $formEnd = strpos($html, '</form>', $modalStart);
            $form = substr($html, $modalStart, $formEnd - $modalStart);
            $previous = -1;
            foreach (['subkegiatan', 'tanggal', 'kode_rekening', 'uraian', 'no_transaksi', 'jumlah'] as $field) {
                $position = strpos($form, 'name="'.$field.'"');
                $this->assertNotFalse($position);
                $this->assertGreaterThan($previous, $position);
                $previous = $position;
            }
            $this->assertStringContainsString('Jumlah Diterima Rekanan', $form);
            $this->assertStringNotContainsString('name="penerimaan"', $form);
            $this->assertStringNotContainsString('name="pengeluaran"', $form);
            $this->assertStringNotContainsString('name="giro"', $form);
        }
    }

    public function test_bku_rejects_tax_above_amount_and_rekening_outside_current_blud(): void
    {
        $this->login('Bendahara Pengeluaran');
        DB::table('rencana_kebutuhans')->insert([
            'nama_blud' => 'Puskesmas B', 'akun_id' => 2, 'tahun_anggaran' => '2027', 'jumlah_harga' => 1000,
            'subkegiatan' => 'Pelayanan BLUD',
        ]);
        $data = [
            'tanggal' => '2027-01-01', 'uraian' => 'Belanja alat tulis', 'jumlah' => '1.000',
            'kode_rekening' => '5.1.02.01.01.0001', 'subkegiatan' => 'Pelayanan BLUD',
            'pajaks' => [['jenis' => 'Pajak Pertambahan Nilai', 'nominal' => '100']],
        ];
        $this->postJson(route('bku-bendahara-pengeluaran.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('kode_rekening');
        DB::table('rencana_kebutuhans')->insert([
            'nama_blud' => 'Puskesmas A', 'akun_id' => 2, 'tahun_anggaran' => '2027', 'jumlah_harga' => 1000,
            'subkegiatan' => 'Pelayanan BLUD',
        ]);
        $this->postJson(route('bku-bendahara-pengeluaran.store'), array_replace($data, ['pajaks' => [
            ['jenis' => 'Pajak Pertambahan Nilai', 'nominal' => '600'], ['jenis' => 'PPH 21', 'nominal' => '500'],
        ]]))
            ->assertUnprocessable()->assertJsonValidationErrors('jumlah');
        $this->postJson(route('bku-bendahara-pengeluaran.store'), array_replace($data, ['pajaks' => [
            ['jenis' => 'PPN', 'nominal' => '100'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('pajaks.0.jenis');
        $this->assertDatabaseCount('bku_bendahara_pengeluarans', 0);
    }

    public function test_bku_edit_can_replace_and_remove_multiple_taxes(): void
    {
        $this->login('Bendahara Pengeluaran');
        DB::table('rencana_kebutuhans')->insert([
            'nama_blud' => 'Puskesmas A', 'akun_id' => 2, 'tahun_anggaran' => '2027',
            'jumlah_harga' => 1000, 'subkegiatan' => 'Pelayanan BLUD',
        ]);
        $id = DB::table('bku_bendahara_pengeluarans')->insertGetId([
            'unit_blud_id' => 1, 'tanggal' => '2027-01-11', 'uraian' => 'Belanja alat tulis',
            'kode_rekening' => '5.1.02.01.01.0001', 'subkegiatan' => 'Pelayanan BLUD',
            'pengeluaran' => 1000, 'giro' => 50, 'nominal_pajak' => 100, 'jenis_pajak' => 'PPN',
        ]);
        $data = ['tanggal' => '2027-01-11', 'uraian' => 'Belanja alat tulis',
            'subkegiatan' => 'Pelayanan BLUD', 'kode_rekening' => '5.1.02.01.01.0001',
            'jumlah' => '1.000'];
        $this->put(route('bku-bendahara-pengeluaran.update', $id), $data + ['pajaks' => [
            ['jenis' => 'Pajak Pertambahan Nilai', 'nominal' => '100'], ['jenis' => 'PPH 21', 'nominal' => '50'],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', ['id' => $id, 'nominal_pajak' => 150, 'giro' => 50]);
        $this->assertSame(4, DB::table('bku_bendahara_pengeluarans')->where('parent_bku_id', $id)->count());
        $this->put(route('bku-bendahara-pengeluaran.update', $id), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', [
            'id' => $id, 'nominal_pajak' => 0, 'rincian_pajak' => '[]', 'giro' => 50,
        ]);
        $this->assertSame(0, DB::table('bku_bendahara_pengeluarans')->where('parent_bku_id', $id)->count());
    }

    public function test_bku_tax_deduction_and_deposit_cancel_each_other_in_running_balance(): void
    {
        $this->login('Bendahara Pengeluaran');
        Schema::create('ups', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('unit_blud_id');
            $table->string('tahun_anggaran'); $table->string('no_spd')->nullable();
            $table->bigInteger('jumlah')->default(0); $table->bigInteger('potongan')->default(0);
            $table->timestamps(); $table->softDeletes();
        });
        (require database_path('migrations/2026_11_04_000001_create_bku_bendahara_perubahan_requests_table.php'))->up();
        (require database_path('migrations/2026_11_04_000002_add_spd_ls_to_bku_bendahara_perubahan_requests_table.php'))->up();
        (require database_path('migrations/2026_11_09_000005_add_verifikator_to_bku_bendahara_perubahan_requests.php'))->up();
        DB::table('rencana_kebutuhans')->insert([
            'nama_blud' => 'Puskesmas A', 'akun_id' => 2, 'tahun_anggaran' => '2027',
            'jumlah_harga' => 100000, 'subkegiatan' => 'Pelayanan BLUD',
        ]);
        $this->post(route('bku-bendahara-pengeluaran.store'), [
            'tanggal' => '2027-01-11', 'uraian' => 'Belanja obat', 'jumlah' => '100.000',
            'kode_rekening' => '5.1.02.01.01.0001', 'subkegiatan' => 'Pelayanan BLUD',
            'pajaks' => [['jenis' => 'Pajak Daerah', 'nominal' => '5.000']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bku_bendahara_pengeluarans', 3);
        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertViewHas('totalPenerimaan', 5000)
            ->assertViewHas('totalPengeluaran', 105000)
            ->assertViewHas('saldoAkhir', -100000)
            ->assertViewHas('bkuList', function ($rows) {
                return $rows->count() === 3
                    && $rows->pluck('uraian')->all() === [
                        'Belanja obat',
                        'Pemotongan Pajak Daerah',
                        'Penyetoran Pajak Daerah',
                    ]
                    && $rows->pluck('saldo')->all() === [-100000, -105000, -100000];
            });

        $parentId = DB::table('bku_bendahara_pengeluarans')->whereNull('parent_bku_id')->value('id');
        $response = $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertSee('data-bku-toggle="pajak-'.$parentId.'"', false)
            ->assertSee('data-bku-child="pajak-'.$parentId.'"', false);
        $html = $response->getContent();
        $parentStart = strpos($html, 'data-bku-toggle="pajak-'.$parentId.'"');
        $parentRow = substr($html, $parentStart, strpos($html, '</tr>', $parentStart) - $parentStart);
        $this->assertStringContainsString('Belanja Alat Tulis', $parentRow);
        $this->assertStringNotContainsString('Belanja obat', $parentRow);
        $detailStart = strpos($html, 'data-bku-child="pajak-'.$parentId.'"', $parentStart);
        $detailRow = substr($html, $detailStart, strpos($html, '</tr>', $detailStart) - $detailStart);
        $this->assertStringContainsString('Belanja obat', $detailRow);
        $this->assertStringContainsString('Rp 100.000', $detailRow);
        $this->assertStringContainsString('Rp -100.000', $detailRow);
        $childId = DB::table('bku_bendahara_pengeluarans')->where('parent_bku_id', $parentId)->value('id');
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', [
            'id' => $childId, 'tanggal' => '2027-01-11', 'kode_rekening' => null,
            'uraian' => 'Pemotongan Pajak Daerah',
        ]);
        DB::table('bku_bendahara_pengeluarans')->where('id', $childId)->update([
            'kode_rekening' => '5.1.02.01.01.0001',
            'uraian' => 'Pemotongan Pajak Daerah - Belanja obat',
        ]);
        (require database_path('migrations/2026_11_09_000004_simplify_bku_pajak_child_details.php'))->up();
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', [
            'id' => $childId, 'kode_rekening' => null, 'uraian' => 'Pemotongan Pajak Daerah',
        ]);
        $this->put(route('bku-bendahara-pengeluaran.update', $childId), [
            'tanggal' => '2027-01-11', 'uraian' => 'Tidak boleh diubah',
        ])->assertNotFound();
        $this->delete(route('bku-bendahara-pengeluaran.destroy', $childId))->assertNotFound();

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) {
            $this->assertSame('pdf.bku-bendahara-pengeluaran', $view);
            $this->assertCount(3, $data['bkuList']);
            $this->assertSame(-100000, $data['saldoAkhir']);
            return true;
        })->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('pdf'));
        $this->get(route('bku-bendahara-pengeluaran.pdf'))->assertOk();

        $this->delete(route('bku-bendahara-pengeluaran.destroy', $parentId))->assertRedirect();
        $this->assertDatabaseCount('bku_bendahara_pengeluarans', 0);
    }

    public function test_setting_opening_balance_does_not_overwrite_other_blud_or_unassigned_openings(): void
    {
        $this->cash(['unit_blud_id' => 2, 'is_saldo_awal' => true, 'saldo' => 2000]);
        $this->cash(['unit_blud_id' => null, 'is_saldo_awal' => true, 'saldo' => 3000]);
        $this->login('Pejabat Keuangan');
        $this->post(route('bku-pejabat-keuangan.set-saldo-awal'), ['tanggal' => '2027-01-01', 'saldo_awal' => '1.000'])->assertRedirect();
        $this->assertDatabaseHas('bku_pejabat_keuangans', ['unit_blud_id' => 1, 'saldo' => 1000, 'is_saldo_awal' => true]);
        $this->assertDatabaseHas('bku_pejabat_keuangans', ['unit_blud_id' => 2, 'saldo' => 2000]);
        $this->assertDatabaseHas('bku_pejabat_keuangans', ['unit_blud_id' => null, 'saldo' => 3000]);
    }

    public function test_migration_only_backfills_ledger_rows_with_a_known_ls_relationship(): void
    {
        $this->ledgerMigration()->down();
        DB::table('spd_lss')->insert(['id' => 1, 'unit_blud_id' => 2]);
        DB::table('bku_bendahara_pengeluarans')->insert([['id' => 1, 'spd_ls_id' => 1], ['id' => 2, 'spd_ls_id' => null]]);
        $this->ledgerMigration()->up();
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', ['id' => 1, 'unit_blud_id' => 2]);
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', ['id' => 2, 'unit_blud_id' => null]);
    }

    public function test_guests_and_invalid_filters_are_rejected(): void
    {
        $this->get('/?tahun=invalid')->assertSessionHasErrors('tahun');
        $this->get('/?tahun=9999')->assertSessionHasErrors('tahun');
        $this->login('Superuser');
        $this->get('/?puskesmas=Tidak%20Ada')->assertSessionHasErrors('puskesmas');
        auth()->forgetGuards();
        foreach (['/', '/home', '/index'] as $url) $this->get($url)->assertRedirect(route('login'));
    }
}
