<?php

namespace Tests\Feature;

use App\Models\Angkas;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialUnitScopeTest extends TestCase
{
    use \Tests\Support\SpdFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'financial_unit_testing', 'database.connections.financial_unit_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->withoutVite();
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id(); $table->string('nama');
        });
        DB::table('unit_bluds')->insert([['id' => 1, 'nama' => 'Puskesmas A'], ['id' => 2, 'nama' => 'Puskesmas B']]);
        Schema::create('akuns', function (Blueprint $table) {
            $table->id(); $table->string('kode_rek'); $table->string('uraian');
        });
        DB::table('akuns')->insert(['id' => 1, 'kode_rek' => '5.1.02.01.01.0001', 'uraian' => 'Belanja Barang']);
        foreach (['2026_06_08_080303_create_angkases_table.php', '2026_09_23_041142_add_alokasi_anggaran_to_angkases_table.php',
            '2026_10_21_000001_add_unit_blud_to_angkases_table.php', '2026_09_28_040049_create_bku_pejabat_keuangans_table.php',
            '2026_09_28_061218_add_is_saldo_awal_to_bku_pejabat_keuangans_table.php',
            '2026_09_29_015503_create_bku_bendahara_pengeluarans_table.php',
            '2026_09_29_023905_add_spd_ls_id_to_bku_bendahara_pengeluarans_table.php',
            '2026_11_09_000001_add_tax_and_subkegiatan_to_bku_bendahara_pengeluarans.php',
            '2026_11_09_000002_add_rincian_pajak_to_bku_bendahara_pengeluarans.php',
            '2026_11_09_000003_add_pajak_transactions_to_bku_bendahara_pengeluarans.php',
            '2026_11_09_000004_simplify_bku_pajak_child_details.php',
            '2026_11_04_000001_create_bku_bendahara_perubahan_requests_table.php',
            '2026_11_04_000002_add_spd_ls_to_bku_bendahara_perubahan_requests_table.php',
            '2026_11_09_000005_add_verifikator_to_bku_bendahara_perubahan_requests.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        foreach (['bku_pejabat_keuangans', 'bku_bendahara_pengeluarans'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('unit_blud_id')->nullable());
        }
        $this->createSpdTables();
        Schema::create('ups', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('unit_blud_id');
            $table->string('tahun_anggaran'); $table->string('no_spd')->nullable();
            $table->bigInteger('jumlah'); $table->bigInteger('potongan')->default(0);
            $table->timestamps(); $table->softDeletes();
        });
        (require database_path('migrations/2026_10_21_000007_add_budget_year_to_angkas_and_payment_requests.php'))->up();
        Schema::create('rencana_kebutuhans', function (Blueprint $table) {
            $table->id(); $table->string('nama_blud'); $table->string('jenis_belanja')->nullable();
            $table->string('tahun_anggaran')->default('2026');
            $table->string('subkegiatan')->nullable();
            $table->unsignedBigInteger('akun_id')->nullable();
            $table->decimal('jumlah_harga', 15, 2)->nullable();
            $table->string('status_perubahan')->nullable();
            $table->decimal('jumlah_harga_perubahan', 15, 2)->nullable();
            $table->softDeletes();
        });
        Schema::create('penerimaans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('unit_blud_id')->nullable();
            $table->date('tanggal_setor')->nullable(); $table->string('kode_rekening')->nullable();
            $table->string('nomor_tbp')->nullable(); $table->string('nomor_terima')->nullable();
            $table->text('uraian')->nullable(); $table->softDeletes();
        });
        Schema::create('transaksi_penerimaans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('penerimaan_id'); $table->bigInteger('total_biaya');
        });
        $this->login();
    }

    protected function tearDown(): void
    {
        DB::purge('financial_unit_testing');
        parent::tearDown();
    }

    private function login(string $role = 'Pejabat Keuangan', string $upt = 'Puskesmas A'): void
    {
        $user = new User(['role' => $role, 'upt' => $upt]);
        $user->id = 7;
        $this->actingAs($user);
    }

    private function angkas(?int $unitId, string $jenis = 'Belanja Pegawai', int $jumlah = 1000, int $alokasi = 10000): Angkas
    {
        return Angkas::create(['unit_blud_id' => $unitId, 'jenis_belanja' => $jenis,
            'bulan' => 'Januari', 'jumlah' => $jumlah, 'alokasi_anggaran' => $alokasi]);
    }

    private function bku(string $table, ?int $unitId, array $attributes = []): int
    {
        return DB::table($table)->insertGetId(array_replace([
            'unit_blud_id' => $unitId, 'tanggal' => '2026-10-21', 'uraian' => 'Transaksi unit '.$unitId,
        ], $attributes));
    }

    public function test_angkas_matrix_cards_and_budget_choices_only_include_own_unit(): void
    {
        $this->angkas(1);
        $this->angkas(2, 'Belanja Pegawai', 900000, 9000000);
        $this->angkas(2, 'Jenis unit lain');
        $this->angkas(null, 'Jenis tanpa unit');
        DB::table('rencana_kebutuhans')->insert([
            ['nama_blud' => 'Puskesmas A', 'jenis_belanja' => 'Jenis dari RBA sendiri'],
            ['nama_blud' => 'Puskesmas B', 'jenis_belanja' => 'Jenis dari RBA lain'],
        ]);
        $this->get('/angkas?unit_blud_id=2')->assertOk()->assertViewHas('totalAngkas', 1000)
            ->assertViewHas('totalAlokasiAnggaran', 10000)->assertViewHas('sisaAnggaran', 9000)
            ->assertSee('Jenis dari RBA sendiri')->assertDontSee('Jenis dari RBA lain')
            ->assertDontSee('Jenis unit lain')->assertDontSee('Jenis tanpa unit');
    }

    public function test_angkas_creation_inline_allocation_and_modal_are_independent_between_units(): void
    {
        $other = $this->angkas(2, 'Belanja Pegawai', 200, 200);
        $this->post(route('angkas.store'), ['jenis_belanja' => 'Belanja Pegawai', 'alokasi_anggaran' => '10.000', 'unit_blud_id' => 2])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(12, Angkas::where('unit_blud_id', 1)->count());
        $this->patchJson(route('angkas.update-inline'), ['jenis_belanja' => 'Belanja Pegawai', 'bulan' => 'Januari', 'value' => 8000, 'unit_blud_id' => 2])
            ->assertOk()->assertJsonPath('jumlah', 8000);
        $this->patchJson(route('angkas.update-alokasi-inline'), ['jenis_belanja' => 'Belanja Pegawai', 'value' => 9000, 'unit_blud_id' => 2])->assertOk();
        $this->patchJson(route('angkas.update-inline'), ['jenis_belanja' => 'Belanja Pegawai', 'bulan' => 'Februari', 'value' => 2000])
            ->assertUnprocessable()->assertJsonValidationErrors('jumlah');
        $this->postJson(route('angkas.update-modal'), ['jenis_belanja' => 'Belanja Pegawai', 'alokasi_anggaran' => 12000,
            'bulan' => ['Januari' => 5000, 'Februari' => 4000], 'unit_blud_id' => 2])->assertOk();
        $this->assertDatabaseHas('angkases', ['unit_blud_id' => 1, 'bulan' => 'Februari', 'jumlah' => 4000, 'alokasi_anggaran' => 12000]);
        $this->assertDatabaseHas('angkases', ['id' => $other->id, 'unit_blud_id' => 2, 'jumlah' => 200, 'alokasi_anggaran' => 200]);
    }

    public function test_angkas_inline_and_modal_creation_set_unit_even_for_new_months(): void
    {
        $this->angkas(1);
        $this->angkas(2, 'Belanja Pegawai', 200, 200);
        $this->patchJson(route('angkas.update-inline'), ['jenis_belanja' => 'Belanja Pegawai', 'bulan' => 'Februari', 'value' => 1000])->assertOk();
        $this->assertDatabaseHas('angkases', ['unit_blud_id' => 1, 'bulan' => 'Februari', 'jumlah' => 1000]);
        $this->postJson(route('angkas.update-modal'), ['jenis_belanja' => 'Belanja baru', 'alokasi_anggaran' => 5000,
            'bulan' => ['Januari' => 2000]])->assertOk();
        $this->assertSame(12, Angkas::where('unit_blud_id', 1)->where('jenis_belanja', 'Belanja baru')->count());
        $this->assertDatabaseHas('angkases', ['unit_blud_id' => 1, 'jenis_belanja' => 'Belanja baru', 'alokasi_anggaran' => 5000]);
    }

    public function test_angkas_cannot_update_or_delete_records_of_other_unit_or_without_unit(): void
    {
        foreach ([$this->angkas(2), $this->angkas(null)] as $row) {
            $this->put(route('angkas.update', $row->id), ['jenis_belanja' => 'Belanja Pegawai', 'bulan' => 'Januari', 'jumlah' => 0])->assertNotFound();
            $this->delete(route('angkas.destroy', $row->id))->assertNotFound();
            $this->assertDatabaseHas('angkases', ['id' => $row->id, 'jumlah' => 1000, 'deleted_at' => null]);
        }
    }

    public function test_bku_pejabat_opening_balance_and_manual_entries_use_logged_in_unit(): void
    {
        foreach (['bku-pejabat-keuangan' => 'Pejabat Keuangan'] as $route => $role) {
            $this->login($role, ' puskesmas a ');
            $table = str_replace('-', '_', $route).'s';
            $otherId = $this->bku($table, 2, ['is_saldo_awal' => true, 'saldo' => 900000]);
            $nullId = $this->bku($table, null, ['is_saldo_awal' => true, 'saldo' => 800000]);
            $this->post(route($route.'.set-saldo-awal'), ['tanggal' => '2026-01-01', 'saldo_awal' => '10.000', 'unit_blud_id' => 2])->assertRedirect()->assertSessionHasNoErrors();
            $this->post(route($route.'.store'), ['tanggal' => '2026-10-21', 'uraian' => 'Transaksi baru', 'penerimaan' => '1.000', 'unit_blud_id' => 2])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas($table, ['unit_blud_id' => 1, 'is_saldo_awal' => true, 'saldo' => 10000]);
            $this->assertDatabaseHas($table, ['unit_blud_id' => 1, 'uraian' => 'Transaksi baru', 'penerimaan' => 1000]);
            $this->assertDatabaseHas($table, ['id' => $otherId, 'unit_blud_id' => 2, 'saldo' => 900000]);
            $this->assertDatabaseHas($table, ['id' => $nullId, 'unit_blud_id' => null, 'saldo' => 800000]);
            $this->get(route($route.'.index'))->assertOk()->assertViewHas('totalPenerimaan', 1000)
                ->assertViewHas('saldoAkhir', fn ($saldo) => (int) $saldo === 11000)
                ->assertViewHas('bkuSaldoAwal', fn ($row) => $row->unit_blud_id === 1);
        }
    }

    public function test_bendahara_opening_balance_follows_current_unit_and_year_spd_up(): void
    {
        $this->login('Bendahara Pengeluaran');
        DB::table('ups')->insert([
            ['unit_blud_id' => 1, 'tahun_anggaran' => '2026', 'no_spd' => 'UP-001', 'jumlah' => 10000, 'potongan' => 1000],
            ['unit_blud_id' => 1, 'tahun_anggaran' => '2026', 'no_spd' => 'UP-002', 'jumlah' => 5000, 'potongan' => 0],
            ['unit_blud_id' => 2, 'tahun_anggaran' => '2026', 'no_spd' => 'UP-OTHER', 'jumlah' => 900000, 'potongan' => 0],
            ['unit_blud_id' => 1, 'tahun_anggaran' => '2027', 'no_spd' => 'UP-2027', 'jumlah' => 30000, 'potongan' => 0],
            ['unit_blud_id' => 1, 'tahun_anggaran' => '2026', 'no_spd' => null, 'jumlah' => 7000, 'potongan' => 0],
        ]);
        $legacy = $this->bku('bku_bendahara_pengeluarans', 1, ['is_saldo_awal' => true, 'saldo' => 999999]);
        $this->bku('bku_bendahara_pengeluarans', 1, ['penerimaan' => 1000]);

        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertDontSee('Set Saldo Awal')->assertSee('Saldo Awal (SPD UP)')
            ->assertViewHas('saldoAkhir', 15000)
            ->assertViewHas('bkuList', fn ($rows) => $rows->count() === 2 && $rows[0]->saldo === 14000
                && $rows[0]->no_transaksi === 'UP-001, UP-002' && $rows[1]->saldo === 15000);
        $this->post('/bku-bendahara-pengeluaran/set-saldo-awal', ['tanggal' => '2026-01-01', 'saldo_awal' => '500.000'])
            ->assertStatus(405);
        $this->put(route('bku-bendahara-pengeluaran.update', $legacy), ['tanggal' => '2026-10-21', 'uraian' => 'Ubah'])
            ->assertNotFound();
        $this->delete(route('bku-bendahara-pengeluaran.destroy', $legacy))->assertNotFound();
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', ['id' => $legacy, 'saldo' => 999999]);

        $this->post(route('tahun-anggaran.update'), ['tahun' => 2027]);
        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertViewHas('saldoAkhir', 30000)
            ->assertViewHas('bkuList', fn ($rows) => $rows->count() === 1 && $rows[0]->no_transaksi === 'UP-2027');
    }

    public function test_bendahara_combined_rows_and_pdf_only_use_own_manual_gu_and_ls(): void
    {
        $this->login('Bendahara Pengeluaran');
        foreach ([1 => 1000, 2 => 900000] as $unit => $amount) {
            $this->bku('bku_bendahara_pengeluarans', $unit, ['penerimaan' => $amount]);
            foreach (['gu', 'ls'] as $kind) {
                DB::table('spd_'.$kind.'s')->insert(['unit_blud_id' => $unit, 'nomor' => strtoupper($kind).'-'.$unit,
                    'tanggal_dba' => '2026-10-20', 'tahun_anggaran' => '2026', 'jumlah_akhir' => $amount]);
            }
        }
        $this->bku('bku_bendahara_pengeluarans', null, ['penerimaan' => 700000]);
        $this->get(route('bku-bendahara-pengeluaran.index', ['bulan' => 'Oktober', 'unit_blud_id' => 2]))->assertOk()
            ->assertViewHas('totalPenerimaan', 3000)->assertViewHas('saldoAkhir', 3000)
            ->assertViewHas('bkuList', fn ($rows) => $rows->count() === 3 && $rows->every(fn ($row) => $row->unit_blud_id === 1))
            ->assertDontSee('GU-2')->assertDontSee('LS-2');
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) {
            $this->assertSame('pdf.bku-bendahara-pengeluaran', $view);
            $this->assertEquals(3000, $data['totalPenerimaan']);
            $this->assertCount(3, $data['bkuList']);
            $this->assertTrue($data['bkuList']->every(fn ($row) => $row->unit_blud_id === 1));

            return true;
        })->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('pdf'));
        $this->get(route('bku-bendahara-pengeluaran.pdf', ['bulan' => 'Oktober']))->assertOk();
    }

    public function test_dinas_selects_puskesmas_to_read_both_bku_and_pdf_without_change_access(): void
    {
        $this->bku('bku_pejabat_keuangans', 1, ['penerimaan' => 1000, 'uraian' => 'BKU pejabat A']);
        $this->bku('bku_pejabat_keuangans', 2, ['penerimaan' => 9000, 'uraian' => 'BKU pejabat B']);
        $this->bku('bku_bendahara_pengeluarans', 1, ['penerimaan' => 2000, 'uraian' => 'BKU bendahara A']);
        $this->bku('bku_bendahara_pengeluarans', 2, ['penerimaan' => 8000, 'uraian' => 'BKU bendahara B']);
        $this->login('Dinas', 'Dinas Kesehatan');

        foreach (['bku-pejabat-keuangan', 'bku-bendahara-pengeluaran'] as $route) {
            $this->get(route($route.'.index'))->assertOk()
                ->assertSee('Pilih Puskesmas')->assertDontSee('BKU pejabat A')
                ->assertDontSee('BKU bendahara A')->assertDontSee('Unduh PDF');
            $this->get(route($route.'.index', ['unit_blud_id' => 999]))->assertNotFound();
            $this->get(route($route.'.pdf'))->assertStatus(422);

            $response = $this->get(route($route.'.index', ['unit_blud_id' => 2]));
            $response->assertOk()->assertSee('Puskesmas B')
                ->assertViewHas('totalPenerimaan', $route === 'bku-pejabat-keuangan' ? 9000 : 8000)
                ->assertDontSee('BKU pejabat A')->assertDontSee('BKU bendahara A')
                ->assertDontSee('Set Saldo Awal')->assertDontSee('Tambah Data BKU')
                ->assertDontSee('Ajukan Edit')->assertDontSee('Ajukan Tambah Rincian');

            $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($route) {
                $this->assertSame('pdf.'.$route, $view);
                $this->assertSame('Puskesmas B', $data['namaPuskesmas']);
                $this->assertCount(1, $data['bkuList']);
                $this->assertSame(2, (int) $data['bkuList']->first()->unit_blud_id);

                return true;
            })->andReturn($pdf);
            $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
            $pdf->shouldReceive('download')->once()->andReturn(response('pdf'));
            $this->get(route($route.'.pdf', ['unit_blud_id' => 2]))->assertOk();

            $this->post(route($route.'.store'), ['tanggal' => '2026-10-21', 'uraian' => 'Tidak diizinkan', 'unit_blud_id' => 2])
                ->assertForbidden();
        }
        $this->assertDatabaseCount('bku_pejabat_keuangans', 2);
        $this->assertDatabaseCount('bku_bendahara_pengeluarans', 2);
    }

    public function test_dinas_upt_account_with_another_role_sees_bku_menu_and_can_read_reports(): void
    {
        $this->login('Superuser', 'Dinas Kesehatan');

        foreach (['bku-pejabat-keuangan', 'bku-bendahara-pengeluaran'] as $route) {
            $this->get(route($route.'.index'))
                ->assertOk()
                ->assertSee('Buku Kas Umum')
                ->assertSee('BKU Pejabat Keuangan')
                ->assertSee('BKU Bendahara Pengeluaran')
                ->assertSee('Pilih Puskesmas');

            $this->get(route($route.'.index', ['unit_blud_id' => 1]))
                ->assertOk()->assertSee('Puskesmas A');

            $this->post(route($route.'.store'), [
                'tanggal' => '2026-10-21', 'uraian' => 'Tidak diizinkan', 'unit_blud_id' => 1,
            ])->assertForbidden();
        }

        $this->get(route('bku-bendahara-pengajuan.index'))->assertOk();
    }

    public function test_kepala_puskesmas_reads_both_own_bku_and_sees_pending_change_requests(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 12:00:00', 'Asia/Jakarta'));
        $this->bku('bku_pejabat_keuangans', 1, ['tanggal' => '2026-11-08', 'uraian' => 'BKU pejabat A']);
        $this->bku('bku_pejabat_keuangans', 2, ['tanggal' => '2026-11-08', 'uraian' => 'BKU pejabat B']);
        $ownBku = $this->bku('bku_bendahara_pengeluarans', 1, ['tanggal' => '2026-11-08', 'uraian' => 'BKU bendahara A']);
        $this->bku('bku_bendahara_pengeluarans', 2, ['tanggal' => '2026-11-08', 'uraian' => 'BKU bendahara B']);
        $this->login('Bendahara Pengeluaran');
        $this->post(route('bku-bendahara-pengajuan.store', $ownBku),
            ['aksi' => 'edit', 'alasan' => 'Koreksi Kepala Puskesmas'])->assertRedirect()->assertSessionHasNoErrors();

        $this->login('Kepala Puskesmas', 'Puskesmas A');
        foreach (['bku-pejabat-keuangan', 'bku-bendahara-pengeluaran'] as $route) {
            $this->get(route($route.'.index', ['unit_blud_id' => 2]))->assertOk()
                ->assertSee('BKU Pejabat Keuangan')->assertSee('BKU Bendahara Pengeluaran')
                ->assertSee('Pengajuan Perubahan BKU')->assertSee('<span class="badge bg-danger">1</span>', false)
                ->assertSee($route === 'bku-pejabat-keuangan' ? 'BKU pejabat A' : 'BKU bendahara A')
                ->assertDontSee($route === 'bku-pejabat-keuangan' ? 'BKU pejabat B' : 'BKU bendahara B')
                ->assertDontSee('Pilih Puskesmas')->assertDontSee('Tambah Transaksi')
                ->assertDontSee('Set Saldo Awal')->assertDontSee('Ajukan Edit');
            $this->post(route($route.'.store'), ['tanggal' => '2026-11-09', 'uraian' => 'Tidak diizinkan'])
                ->assertForbidden();
        }
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        Pdf::shouldReceive('loadView')->twice()->withArgs(function ($view, $data) {
            $this->assertContains($view, ['pdf.bku-pejabat-keuangan', 'pdf.bku-bendahara-pengeluaran']);
            $this->assertSame('Puskesmas A', $data['namaPuskesmas']);
            $this->assertTrue($data['bkuList']->every(fn ($row) => (int) $row->unit_blud_id === 1));
            return true;
        })->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->twice()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->twice()->andReturn(response('pdf'));
        foreach (['bku-pejabat-keuangan', 'bku-bendahara-pengeluaran'] as $route) {
            $this->get(route($route.'.pdf', ['unit_blud_id' => 2]))->assertOk();
        }
        $this->get(route('bku-bendahara-pengajuan.index'))->assertOk()->assertSee('Koreksi Kepala Puskesmas');
        $this->put(route('bku-bendahara-pengeluaran.update', $ownBku),
            ['tanggal' => '2026-11-08', 'uraian' => 'Tidak diizinkan'])->assertForbidden();
        $this->delete(route('bku-bendahara-pengeluaran.destroy', $ownBku))->assertForbidden();

        $this->login('Kepala Puskesmas', 'Puskesmas B');
        $this->get(route('bku-bendahara-pengajuan.index'))->assertOk()->assertDontSee('Koreksi Kepala Puskesmas');
        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertSee('BKU bendahara B')->assertDontSee('BKU bendahara A');
        $this->travelBack();
    }

    public function test_old_bendahara_transaction_requires_dinas_approval_for_each_edit_and_delete(): void
    {
        $this->login('Bendahara Pengeluaran');
        $id = $this->bku('bku_bendahara_pengeluarans', 1);
        $data = ['tanggal' => '2026-10-21', 'uraian' => 'Perubahan disetujui', 'penerimaan' => '1.000'];

        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertSee('title="Ajukan Edit"', false)->assertSee('title="Ajukan Hapus"', false)
            ->assertDontSee('>Ajukan Edit</button>', false)->assertDontSee('>Ajukan Hapus</button>', false);
        $this->put(route('bku-bendahara-pengeluaran.update', $id), $data)->assertForbidden();
        $this->delete(route('bku-bendahara-pengeluaran.destroy', $id))->assertForbidden();

        $this->post(route('bku-bendahara-pengajuan.store', $id), ['aksi' => 'edit', 'alasan' => 'Koreksi nominal transaksi'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $requestId = DB::table('bku_bendahara_perubahan_requests')->value('id');
        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()->assertSee('Edit menunggu verifikasi');
        $this->patch(route('bku-bendahara-pengajuan.review', $requestId), ['keputusan' => 'approved'])->assertForbidden();

        $this->login('Dinas', 'Dinas Kesehatan');
        $this->get(route('bku-bendahara-pengajuan.index'))->assertOk()->assertSee('Koreksi nominal transaksi');
        $this->patch(route('bku-bendahara-pengajuan.review', $requestId), ['keputusan' => 'approved'])
            ->assertRedirect();
        $this->patch(route('bku-bendahara-pengajuan.review', $requestId), ['keputusan' => 'approved'])
            ->assertStatus(409);

        $this->login('Bendahara Pengeluaran');
        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()->assertSee('modalEditBku'.$id);
        $this->put(route('bku-bendahara-pengeluaran.update', $id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', ['id' => $id, 'uraian' => 'Perubahan disetujui']);
        $this->assertDatabaseHas('bku_bendahara_perubahan_requests', ['id' => $requestId, 'status' => 'used']);
        $this->put(route('bku-bendahara-pengeluaran.update', $id), $data)->assertForbidden();
        $this->delete(route('bku-bendahara-pengeluaran.destroy', $id))->assertForbidden();

        $this->post(route('bku-bendahara-pengajuan.store', $id), ['aksi' => 'hapus', 'alasan' => 'Transaksi ganda'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $deleteRequestId = DB::table('bku_bendahara_perubahan_requests')->max('id');
        $this->login('Dinas Kesehatan', 'Dinas Kesehatan');
        $this->patch(route('bku-bendahara-pengajuan.review', $deleteRequestId), ['keputusan' => 'approved'])
            ->assertRedirect();
        $this->login('Bendahara Pengeluaran');
        $this->delete(route('bku-bendahara-pengeluaran.destroy', $id))->assertRedirect();
        $this->assertDatabaseMissing('bku_bendahara_pengeluarans', ['id' => $id]);
        $this->assertDatabaseHas('bku_bendahara_perubahan_requests', ['id' => $deleteRequestId, 'status' => 'used']);
    }

    public function test_same_month_bku_change_requires_own_kepala_puskesmas_approval(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 12:00:00', 'Asia/Jakarta'));
        $this->login('Bendahara Pengeluaran');
        $id = $this->bku('bku_bendahara_pengeluarans', 1, ['tanggal' => '2026-11-08']);
        $data = ['tanggal' => '2026-11-08', 'uraian' => 'Perubahan bulan berjalan'];

        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()->assertSee('Kepala Puskesmas');
        $this->put(route('bku-bendahara-pengeluaran.update', $id), $data)->assertForbidden();
        $this->post(route('bku-bendahara-pengajuan.store', $id),
            ['aksi' => 'edit', 'alasan' => 'Koreksi bulan berjalan'])->assertRedirect()->assertSessionHasNoErrors();
        $requestId = DB::table('bku_bendahara_perubahan_requests')->value('id');
        $this->assertDatabaseHas('bku_bendahara_perubahan_requests',
            ['id' => $requestId, 'verifikator' => 'kepala_puskesmas']);

        $this->login('Dinas', 'Dinas Kesehatan');
        $this->get(route('bku-bendahara-pengajuan.index'))->assertOk()->assertDontSee('Koreksi bulan berjalan');
        $this->patch(route('bku-bendahara-pengajuan.review', $requestId), ['keputusan' => 'approved'])->assertForbidden();

        $this->login('Kepala Puskesmas', 'Puskesmas B');
        $this->get(route('bku-bendahara-pengajuan.index'))->assertOk()->assertDontSee('Koreksi bulan berjalan');
        $this->patch(route('bku-bendahara-pengajuan.review', $requestId), ['keputusan' => 'approved'])->assertForbidden();

        $this->login('Kepala Puskesmas', 'Puskesmas A');
        $this->get(route('bku-bendahara-pengajuan.index'))->assertOk()->assertSee('Koreksi bulan berjalan');
        $this->patch(route('bku-bendahara-pengajuan.review', $requestId), ['keputusan' => 'approved'])->assertRedirect();

        $this->login('Bendahara Pengeluaran');
        $this->put(route('bku-bendahara-pengeluaran.update', $id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', ['id' => $id, 'uraian' => 'Perubahan bulan berjalan']);
        $this->travelBack();
    }

    public function test_pending_bku_request_moves_to_dinas_when_month_changes(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-30 12:00:00', 'Asia/Jakarta'));
        $this->login('Bendahara Pengeluaran');
        $id = $this->bku('bku_bendahara_pengeluarans', 1, ['tanggal' => '2026-11-29']);
        $this->post(route('bku-bendahara-pengajuan.store', $id),
            ['aksi' => 'edit', 'alasan' => 'Koreksi saat akhir bulan'])->assertRedirect()->assertSessionHasNoErrors();
        $requestId = DB::table('bku_bendahara_perubahan_requests')->value('id');

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-01 12:00:00', 'Asia/Jakarta'));
        $this->login('Kepala Puskesmas', 'Puskesmas A');
        $this->get(route('bku-bendahara-pengajuan.index'))->assertOk()->assertDontSee('Koreksi saat akhir bulan');
        $this->patch(route('bku-bendahara-pengajuan.review', $requestId), ['keputusan' => 'approved'])->assertForbidden();

        $this->login('Dinas', 'Dinas Kesehatan');
        $this->get(route('bku-bendahara-pengajuan.index'))->assertOk()->assertSee('Koreksi saat akhir bulan');
        $this->patch(route('bku-bendahara-pengajuan.review', $requestId), ['keputusan' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('bku_bendahara_perubahan_requests',
            ['id' => $requestId, 'verifikator' => 'dinas_kesehatan', 'status' => 'approved']);

        $this->login('Bendahara Pengeluaran');
        $this->put(route('bku-bendahara-pengeluaran.update', $id),
            ['tanggal' => '2026-11-29', 'uraian' => 'Koreksi disetujui Dinas'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->travelBack();
    }

    public function test_kepala_approval_expires_when_month_changes_and_dinas_request_can_follow(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-30 12:00:00', 'Asia/Jakarta'));
        $this->login('Bendahara Pengeluaran');
        $id = $this->bku('bku_bendahara_pengeluarans', 1, ['tanggal' => '2026-11-29']);
        $this->post(route('bku-bendahara-pengajuan.store', $id),
            ['aksi' => 'edit', 'alasan' => 'Koreksi sebelum tutup bulan'])->assertRedirect();
        $kepalaRequestId = DB::table('bku_bendahara_perubahan_requests')->value('id');
        $this->login('Kepala Puskesmas', 'Puskesmas A');
        $this->patch(route('bku-bendahara-pengajuan.review', $kepalaRequestId),
            ['keputusan' => 'approved'])->assertRedirect();

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-01 12:00:00', 'Asia/Jakarta'));
        $this->login('Bendahara Pengeluaran');
        $this->put(route('bku-bendahara-pengeluaran.update', $id),
            ['tanggal' => '2026-11-29', 'uraian' => 'Belum disetujui Dinas'])->assertForbidden();
        $this->post(route('bku-bendahara-pengajuan.store', $id),
            ['aksi' => 'edit', 'alasan' => 'Koreksi setelah tutup bulan'])->assertRedirect()->assertSessionHasNoErrors();
        $dinasRequestId = DB::table('bku_bendahara_perubahan_requests')->max('id');
        $this->assertNotEquals($kepalaRequestId, $dinasRequestId);
        $this->assertDatabaseHas('bku_bendahara_perubahan_requests',
            ['id' => $dinasRequestId, 'verifikator' => 'dinas_kesehatan']);
        $this->travelBack();
    }

    public function test_same_day_bendahara_transaction_can_be_changed_without_approval(): void
    {
        $this->login('Bendahara Pengeluaran');
        $today = now('Asia/Jakarta')->toDateString();
        $id = $this->bku('bku_bendahara_pengeluarans', 1, ['tanggal' => $today]);
        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertSee('data-bs-target="#modalEditBku'.$id.'"', false)
            ->assertDontSee('Ajukan Edit');
        $this->post(route('bku-bendahara-pengajuan.store', $id), ['aksi' => 'edit', 'alasan' => 'Tidak diperlukan'])
            ->assertStatus(422);
        $this->put(route('bku-bendahara-pengeluaran.update', $id), ['tanggal' => $today, 'uraian' => 'Diperbarui'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->delete(route('bku-bendahara-pengeluaran.destroy', $id))->assertRedirect();
        $this->assertDatabaseMissing('bku_bendahara_pengeluarans', ['id' => $id]);
        $this->assertDatabaseCount('bku_bendahara_perubahan_requests', 0);
    }

    public function test_rejected_request_can_be_resubmitted_and_other_unit_cannot_request_it(): void
    {
        $this->login('Bendahara Pengeluaran');
        $ownId = $this->bku('bku_bendahara_pengeluarans', 1);
        $otherId = $this->bku('bku_bendahara_pengeluarans', 2);
        $payload = ['aksi' => 'edit', 'alasan' => 'Koreksi uraian transaksi'];

        $this->post(route('bku-bendahara-pengajuan.store', $otherId), $payload)->assertNotFound();
        $this->post(route('bku-bendahara-pengajuan.store', $ownId), $payload)->assertRedirect();
        $this->post(route('bku-bendahara-pengajuan.store', $ownId), $payload)
            ->assertSessionHasErrors('aksi');
        $requestId = DB::table('bku_bendahara_perubahan_requests')->value('id');

        $this->login('Pejabat Keuangan');
        $this->get(route('bku-bendahara-pengajuan.index'))->assertForbidden();
        $this->post(route('bku-bendahara-pengajuan.store', $ownId), $payload)->assertForbidden();

        $this->login('Dinas', 'Dinas Kesehatan');
        $this->patch(route('bku-bendahara-pengajuan.review', $requestId), ['keputusan' => 'rejected'])
            ->assertRedirect();
        $this->login('Bendahara Pengeluaran');
        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()->assertSee('Ajukan Edit');
        $this->put(route('bku-bendahara-pengeluaran.update', $ownId), ['tanggal' => '2026-10-21', 'uraian' => 'Dilarang'])
            ->assertForbidden();
        $this->post(route('bku-bendahara-pengajuan.store', $ownId), $payload)->assertRedirect();
        $this->assertDatabaseCount('bku_bendahara_perubahan_requests', 2);
    }

    public function test_bendahara_cannot_add_ls_child_directly(): void
    {
        $this->login('Bendahara Pengeluaran');
        $ownId = DB::table('spd_lss')->insertGetId(['unit_blud_id' => 1, 'tahun_anggaran' => '2026',
            'tanggal_dba' => now('Asia/Jakarta')->toDateString()]);
        $otherId = DB::table('spd_lss')->insertGetId(['unit_blud_id' => 2]);
        $deletedId = DB::table('spd_lss')->insertGetId(['unit_blud_id' => 1, 'deleted_at' => now()]);
        $data = ['tanggal' => '2026-10-21', 'uraian' => 'Pembayaran LS', 'pengeluaran' => '1.000'];
        foreach ([$ownId, $otherId, $deletedId] as $id) {
            $this->postJson(route('bku-bendahara-pengeluaran.store'), $data + ['spd_ls_id' => $id])
                ->assertUnprocessable()->assertJsonValidationErrors('spd_ls_id');
        }
        $this->assertDatabaseCount('bku_bendahara_pengeluarans', 0);
    }

    public function test_ls_child_creation_flow_is_unavailable(): void
    {
        $this->login('Bendahara Pengeluaran');
        $id = $this->spdPayment(5000, '2026-10-21');
        $data = ['tanggal' => '2026-10-21', 'uraian' => 'Rincian LS tambahan',
            'pengeluaran' => '1.000', 'spd_ls_id' => $id];

        $this->get(route('bku-bendahara-pengeluaran.index'))->assertOk()
            ->assertDontSee('modalTambahRincian'.$id)
            ->assertDontSee('modalPengajuanRincian'.$id);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('bku-bendahara-pengajuan.rincian'));
        $this->postJson(route('bku-bendahara-pengeluaran.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('spd_ls_id');
        $this->assertDatabaseCount('bku_bendahara_pengeluarans', 0);
    }

    public function test_both_bku_pages_reject_cross_unit_update_and_delete(): void
    {
        foreach (['bku-pejabat-keuangan' => 'Pejabat Keuangan', 'bku-bendahara-pengeluaran' => 'Bendahara Pengeluaran'] as $route => $role) {
            $this->login($role);
            $table = str_replace('-', '_', $route).'s';
            foreach ([2, null] as $unitId) {
                $id = $this->bku($table, $unitId, ['penerimaan' => 1000]);
                $this->put(route($route.'.update', $id), ['tanggal' => '2026-10-21', 'uraian' => 'Ubah dari unit lain'])->assertNotFound();
                $this->delete(route($route.'.destroy', $id))->assertNotFound();
                $this->assertDatabaseHas($table, ['id' => $id, 'penerimaan' => 1000]);
            }
        }
    }

    public function test_unmapped_users_cannot_save_financial_data_even_with_forged_unit(): void
    {
        foreach (['bku-pejabat-keuangan' => 'Pejabat Keuangan', 'bku-bendahara-pengeluaran' => 'Bendahara Pengeluaran'] as $route => $role) {
            $this->login($role, 'Unit tidak terdaftar');
            $this->postJson(route($route.'.store'), ['tanggal' => '2026-10-21', 'uraian' => 'Tidak boleh tersimpan', 'unit_blud_id' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('unit_blud_id');
            if ($route === 'bku-pejabat-keuangan') {
                $this->postJson(route($route.'.set-saldo-awal'), ['tanggal' => '2026-01-01', 'saldo_awal' => '1.000', 'unit_blud_id' => 1])
                    ->assertUnprocessable()->assertJsonValidationErrors('unit_blud_id');
            }
            $this->assertDatabaseCount(str_replace('-', '_', $route).'s', 0);
        }
        $data = ['jenis_belanja' => 'Belanja Pegawai', 'bulan' => 'Januari', 'value' => 1000, 'unit_blud_id' => 1];
        $this->postJson(route('angkas.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('unit_blud_id');
        $this->patchJson(route('angkas.update-inline'), $data)->assertUnprocessable()->assertJsonValidationErrors('unit_blud_id');
        $this->patchJson(route('angkas.update-alokasi-inline'), $data)->assertUnprocessable()->assertJsonValidationErrors('unit_blud_id');
        $this->postJson(route('angkas.update-modal'), $data)->assertUnprocessable()->assertJsonValidationErrors('unit_blud_id');
        $this->assertDatabaseCount('angkases', 0);
    }

    public function test_global_year_keeps_angkas_allocations_separate_for_the_same_unit_and_month(): void
    {
        $this->angkas(1, 'Belanja Pegawai', 1000, 10000);
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2027]);
        $this->get(route('angkas.index'))->assertOk()->assertViewHas('totalAngkas', 0);
        $this->post(route('angkas.store'), ['jenis_belanja' => 'Belanja Pegawai', 'alokasi_anggaran' => 20000])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->patchJson(route('angkas.update-inline'), ['jenis_belanja' => 'Belanja Pegawai', 'bulan' => 'Januari', 'value' => 3000])
            ->assertOk();
        $this->get(route('angkas.index'))->assertOk()->assertViewHas('totalAngkas', 3000)->assertViewHas('totalAlokasiAnggaran', 20000);
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2026]);
        $this->get(route('angkas.index'))->assertOk()->assertViewHas('totalAngkas', 1000)->assertViewHas('totalAlokasiAnggaran', 10000);
        $this->assertDatabaseHas('angkases', ['unit_blud_id' => 1, 'tahun_anggaran' => 2026, 'bulan' => 'Januari', 'jumlah' => 1000]);
        $this->assertDatabaseHas('angkases', ['unit_blud_id' => 1, 'tahun_anggaran' => 2027, 'bulan' => 'Januari', 'jumlah' => 3000]);
    }

    public function test_global_year_filters_both_bku_reports_and_rejects_dates_outside_active_year(): void
    {
        foreach (['bku-pejabat-keuangan' => 'Pejabat Keuangan', 'bku-bendahara-pengeluaran' => 'Bendahara Pengeluaran'] as $route => $role) {
            $table = str_replace('-', '_', $route).'s';
            $this->bku($table, 1, ['tanggal' => '2026-10-21', 'penerimaan' => 1000]);
            $this->bku($table, 1, ['tanggal' => '2027-01-02', 'penerimaan' => 2000]);
            $this->bku($table, 2, ['tanggal' => '2027-01-02', 'penerimaan' => 900000]);
            $this->login($role);
            $this->post(route('tahun-anggaran.update'), ['tahun' => 2027]);
            $this->get(route($route.'.index'))->assertOk()->assertViewHas('totalPenerimaan', 2000)
                ->assertViewHas('bkuList', fn ($rows) => $rows->count() === 1);
            $this->postJson(route($route.'.store'), ['tanggal' => '2026-10-21', 'uraian' => 'Tanggal tahun lain', 'penerimaan' => '3000'])
                ->assertUnprocessable()->assertJsonValidationErrors('tanggal');
            $this->post(route('tahun-anggaran.update'), ['tahun' => 2026]);
            $this->get(route($route.'.index'))->assertOk()->assertViewHas('totalPenerimaan', 1000);
        }
    }

    public function test_legacy_assignment_only_maps_confirmed_ids_without_overwriting_existing_units(): void
    {
        DB::table('unit_bluds')->insert(['id' => 16, 'nama' => 'Puskesmas Limusnunggal']);
        $this->bku('bku_pejabat_keuangans', null, ['id' => 1, 'saldo' => 60000000]);
        $this->bku('bku_bendahara_pengeluarans', null, ['id' => 1, 'pengeluaran' => 5000000]);
        $this->bku('bku_bendahara_pengeluarans', 2, ['id' => 2]);
        DB::table('angkases')->insert([
            ['id' => 73, 'unit_blud_id' => null, 'jenis_belanja' => 'Belanja Pegawai', 'bulan' => 'Januari', 'jumlah' => 10000000],
            ['id' => 74, 'unit_blud_id' => 2, 'jenis_belanja' => 'Belanja Pegawai', 'bulan' => 'Februari', 'jumlah' => 10000000],
            ['id' => 85, 'unit_blud_id' => null, 'jenis_belanja' => 'Belum dikonfirmasi', 'bulan' => 'Januari', 'jumlah' => 0],
        ]);
        $migration = require database_path('migrations/2026_10_21_000002_assign_legacy_financial_records_to_limusnunggal.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('bku_pejabat_keuangans', ['id' => 1, 'unit_blud_id' => 16, 'saldo' => 60000000]);
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', ['id' => 1, 'unit_blud_id' => 16, 'pengeluaran' => 5000000]);
        $this->assertDatabaseHas('bku_bendahara_pengeluarans', ['id' => 2, 'unit_blud_id' => 2]);
        $this->assertDatabaseHas('angkases', ['id' => 73, 'unit_blud_id' => 16, 'jumlah' => 10000000]);
        $this->assertDatabaseHas('angkases', ['id' => 74, 'unit_blud_id' => 2]);
        $this->assertDatabaseHas('angkases', ['id' => 85, 'unit_blud_id' => null]);
    }

    public function test_four_combined_roles_allow_both_bku_pages_and_preserve_unit_scope(): void
    {
        DB::table('akuns')->insert(['id' => 2, 'kode_rek' => '5.1.01', 'uraian' => 'Belanja']);
        DB::table('rencana_kebutuhans')->insert([
            'nama_blud' => 'Puskesmas A', 'tahun_anggaran' => '2026', 'akun_id' => 2,
            'subkegiatan' => 'Pelayanan BLUD', 'jumlah_harga' => 1000,
        ]);
        $user = new User(['role' => 'PPTK', 'roles' => ['PPTK', 'Pejabat Keuangan', 'Bendahara Pengeluaran', 'Kepala Puskesmas'], 'upt' => 'Puskesmas A']);
        $user->id = 7;
        $this->actingAs($user);
        foreach (['bku-pejabat-keuangan', 'bku-bendahara-pengeluaran'] as $route) {
            $table = str_replace('-', '_', $route).'s';
            $this->bku($table, 2, ['penerimaan' => 900000]);
            $payload = ['tanggal' => '2026-10-21', 'uraian' => 'Input beberapa role', 'unit_blud_id' => 2];
            $payload += $route === 'bku-bendahara-pengeluaran'
                ? ['jumlah' => '1.000', 'kode_rekening' => '5.1.01', 'subkegiatan' => 'Pelayanan BLUD']
                : ['penerimaan' => '1.000'];
            $this->post(route($route.'.store'), $payload)
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->get(route($route.'.index'))->assertOk()->assertViewHas('totalPenerimaan', $route === 'bku-bendahara-pengeluaran' ? 0 : 1000)
                ->assertSee('BKU Pejabat Keuangan')->assertSee('BKU Bendahara Pengeluaran');
            $this->assertDatabaseHas($table, ['unit_blud_id' => 1, 'uraian' => 'Input beberapa role']);
        }
    }
}
