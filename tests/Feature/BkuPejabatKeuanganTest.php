<?php

namespace Tests\Feature;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BkuPejabatKeuanganTest extends TestCase
{
    use \Tests\Support\SpdFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'bku_testing', 'database.connections.bku_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->withoutVite();
        $this->createSpdTables();
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id(); $table->string('nama');
        });
        Schema::create('penerimaans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('unit_blud_id')->nullable();
            $table->date('tanggal_setor')->nullable(); $table->string('kode_rekening')->nullable();
            $table->string('nomor_tbp')->nullable(); $table->string('nomor_terima')->nullable();
            $table->text('uraian')->nullable(); $table->softDeletes();
        });
        Schema::create('transaksi_penerimaans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('penerimaan_id');
            $table->date('tanggal'); $table->bigInteger('total_biaya');
        });
        (require database_path('migrations/2026_09_28_040049_create_bku_pejabat_keuangans_table.php'))->up();
        (require database_path('migrations/2026_09_28_061218_add_is_saldo_awal_to_bku_pejabat_keuangans_table.php'))->up();
        Schema::table('bku_pejabat_keuangans', fn (Blueprint $table) => $table->unsignedBigInteger('unit_blud_id')->nullable());
        DB::table('unit_bluds')->insert([['id' => 1, 'nama' => 'Puskesmas A'], ['id' => 2, 'nama' => 'Puskesmas B']]);
        $this->login();
    }

    protected function tearDown(): void
    {
        DB::purge('bku_testing');
        parent::tearDown();
    }

    private function login(string $upt = 'Puskesmas A'): void
    {
        $user = new User(['role' => 'Pejabat Keuangan', 'upt' => $upt]);
        $user->id = 7;
        $this->actingAs($user);
    }

    private function deposit(int $amount = 1643000, array $overrides = []): int
    {
        $id = DB::table('penerimaans')->insertGetId(array_replace([
            'unit_blud_id' => 1, 'tanggal_setor' => '2026-10-21', 'kode_rekening' => '4.1.04.16.02.0001',
            'nomor_tbp' => 'TBP-001', 'nomor_terima' => 'TERIMA-001', 'uraian' => 'Pendapatan jasa layanan',
        ], $overrides));
        DB::table('transaksi_penerimaans')->insert(['penerimaan_id' => $id, 'tanggal' => '2026-09-30', 'total_biaya' => $amount]);

        return $id;
    }

    private function manual(array $overrides = []): int
    {
        return DB::table('bku_pejabat_keuangans')->insertGetId(array_replace([
            'unit_blud_id' => 1, 'tanggal' => '2026-10-22', 'uraian' => 'Transaksi manual',
        ], $overrides));
    }

    public function test_opening_balance_and_manual_rows_do_not_show_edit_or_delete_actions(): void
    {
        $openingId = $this->manual(['tanggal' => '2026-09-01', 'is_saldo_awal' => true, 'saldo' => 5000]);
        $manualId = $this->manual(['penerimaan' => 1000]);

        $this->get(route('bku-pejabat-keuangan.index'))->assertOk()
            ->assertDontSee('data-bs-target="#modalEditBku'.$manualId.'"', false)
            ->assertDontSee('action="'.route('bku-pejabat-keuangan.destroy', $openingId).'"', false)
            ->assertDontSee('action="'.route('bku-pejabat-keuangan.destroy', $manualId).'"', false)
            ->assertDontSee('Edit saldo awal')->assertDontSee('Hapus saldo awal')
            ->assertDontSee('Edit transaksi BKU')->assertDontSee('Hapus transaksi BKU');

        $this->delete(route('bku-pejabat-keuangan.destroy', $openingId))->assertRedirect();
        $this->assertDatabaseMissing('bku_pejabat_keuangans', ['id' => $openingId]);
        $this->assertDatabaseHas('bku_pejabat_keuangans', ['id' => $manualId, 'saldo' => 1000]);
    }

    public function test_deposits_appear_once_in_card_table_and_balance_for_current_unit(): void
    {
        $id = $this->deposit(1000000);
        DB::table('transaksi_penerimaans')->insert(['penerimaan_id' => $id, 'tanggal' => '2026-10-08', 'total_biaya' => 643000]);
        $this->deposit(900000, ['tanggal_setor' => null]);
        $this->deposit(800000, ['unit_blud_id' => 2]);
        $this->deposit(700000, ['deleted_at' => now()]);
        $this->manual(['unit_blud_id' => 2, 'penerimaan' => 600000]);
        $this->manual(['unit_blud_id' => null, 'penerimaan' => 500000]);

        for ($i = 0; $i < 2; $i++) {
            $this->get(route('bku-pejabat-keuangan.index'))->assertOk()->assertSee('Rp 1.643.000')
                ->assertSee('Setoran Penerimaan')->assertSee('TBP-001')->assertDontSee('modalEditBku'.$id)
                ->assertViewHas('totalPenerimaan', 1643000)->assertViewHas('saldoAkhir', '1643000')
                ->assertViewHas('bkuList', fn ($rows) => $rows->count() === 1 && $rows[0]->tanggal === '2026-10-21');
        }
        $this->assertDatabaseCount('bku_pejabat_keuangans', 2);
    }

    public function test_month_filter_uses_deposit_date_and_preserves_running_balance(): void
    {
        $this->manual(['tanggal' => '2026-09-01', 'is_saldo_awal' => true, 'saldo' => 1000]);
        $this->deposit(100, ['tanggal_setor' => '2026-09-30']);
        $this->deposit();
        $manualId = $this->manual(['penerimaan' => 2000, 'pengeluaran' => 500, 'saldo' => 0]);

        $this->get(route('bku-pejabat-keuangan.index', ['bulan' => 'Oktober']))->assertOk()
            ->assertViewHas('totalPenerimaan', 1645000)->assertViewHas('totalPengeluaran', 500)
            ->assertViewHas('saldoAkhir', '1645600')->assertViewHas('jumlahSdTanggalLalu', 100)
            ->assertViewHas('bkuList', fn ($rows) => $rows->count() === 2 && $rows[0]->saldo === '1644100'
                && $rows[1]->saldo === '1645600')->assertDontSee('modalEditBku'.$manualId);
        $this->get(route('bku-pejabat-keuangan.index', ['bulan' => 'September']))->assertOk()
            ->assertViewHas('totalPenerimaan', 100)->assertViewHas('saldoAkhir', '1100');
    }

    public function test_report_reflects_changed_transactions_deposit_date_and_deleted_receipts(): void
    {
        $id = $this->deposit();
        DB::table('transaksi_penerimaans')->where('penerimaan_id', $id)->update(['total_biaya' => 2000000]);
        $this->get(route('bku-pejabat-keuangan.index'))->assertOk()->assertViewHas('totalPenerimaan', 2000000);
        DB::table('penerimaans')->where('id', $id)->update(['tanggal_setor' => '2026-11-01']);
        $this->get(route('bku-pejabat-keuangan.index', ['bulan' => 'Oktober']))->assertOk()->assertViewHas('totalPenerimaan', 0);
        $this->get(route('bku-pejabat-keuangan.index', ['bulan' => 'November']))->assertOk()->assertViewHas('totalPenerimaan', 2000000);
        DB::table('penerimaans')->where('id', $id)->update(['deleted_at' => now()]);
        $this->get(route('bku-pejabat-keuangan.index'))->assertOk()->assertViewHas('totalPenerimaan', 0);
        $this->assertDatabaseCount('bku_pejabat_keuangans', 0);
    }

    public function test_pdf_receives_the_same_deposits_and_totals_as_the_page(): void
    {
        $this->deposit(100, ['tanggal_setor' => '2026-09-30']);
        $this->deposit();
        $this->deposit(900000, ['unit_blud_id' => 2]);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) {
            $this->assertSame('pdf.bku-pejabat-keuangan', $view);
            $this->assertEquals(1643000, $data['totalPenerimaan']);
            $this->assertEquals(1643000, $data['jumlahSaatIni']);
            $this->assertEquals(100, $data['jumlahSdTanggalLalu']);
            $this->assertSame('1643100', $data['saldoAkhir']);
            $this->assertCount(1, $data['bkuList']);
            $this->assertStringContainsString('1.643.000', view($view, $data)->render());

            return true;
        })->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('pdf'));
        $this->get(route('bku-pejabat-keuangan.pdf', ['bulan' => 'Oktober']))->assertOk();
    }

    public function test_manual_changes_recalculate_with_deposits_and_do_not_change_other_units(): void
    {
        $this->deposit();
        $otherId = $this->manual(['unit_blud_id' => 2, 'saldo' => 900000]);
        $this->post(route('bku-pejabat-keuangan.store'), [
            'tanggal' => '2026-10-22', 'no_transaksi' => 'MANUAL-001', 'kode_rekening' => '4.1',
            'uraian' => 'Transaksi manual', 'penerimaan' => '2.000', 'pengeluaran' => '500',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bku_pejabat_keuangans', ['unit_blud_id' => 1, 'saldo' => 1644500]);
        $this->delete(route('bku-pejabat-keuangan.destroy', $otherId))->assertNotFound();
        $this->put(route('bku-pejabat-keuangan.update', $otherId), [])->assertNotFound();
        $this->assertDatabaseHas('bku_pejabat_keuangans', ['id' => $otherId, 'saldo' => 900000]);
        $this->login('Unit tidak terdaftar');
        $this->get(route('bku-pejabat-keuangan.index'))->assertOk()->assertViewHas('totalPenerimaan', 0)
            ->assertViewHas('bkuList', fn ($rows) => $rows->isEmpty());
    }

    public function test_spd_ls_and_gu_are_automatic_debits_in_card_table_and_cash_balance(): void
    {
        $this->manual(['tanggal' => '2026-09-01', 'is_saldo_awal' => true, 'saldo' => 20000000]);
        $this->deposit();
        $this->spdPayment(1000000, '2026-09-29', ['jumlah' => 1200000, 'potongan' => 200000]);
        $this->spdPayment(10000000, '2026-09-28', [], 'gu');
        $this->spdPayment(9000000, '2026-09-28', ['unit_blud_id' => 2]);
        $this->spdPayment(8000000, '2026-09-28', ['deleted_at' => now()]);
        DB::table('opd_gus')->insert(['unit_blud_id' => 1, 'jumlah' => 7000000]);
        for ($i = 0; $i < 2; $i++) {
            $this->get(route('bku-pejabat-keuangan.index'))->assertOk()->assertSee('SPD LS')->assertSee('SPD GU')
                ->assertSee('Rp 11.000.000')->assertViewHas('totalPengeluaran', 11000000)
                ->assertViewHas('saldoAkhir', '10643000')->assertViewHas('bkuList', fn ($rows) => $rows->count() === 4
                    && $rows->where('is_spd', true)->count() === 2)->assertDontSee('modalEditBkuspd_ls_1');
        }
        $this->assertDatabaseCount('bku_pejabat_keuangans', 1);
        $this->get(route('bku-pejabat-keuangan.index', ['bulan' => 'September']))->assertOk()
            ->assertViewHas('totalPengeluaran', 11000000)->assertViewHas('saldoAkhir', '9000000');
        $this->get(route('bku-pejabat-keuangan.index', ['bulan' => 'Oktober']))->assertOk()
            ->assertViewHas('totalPengeluaran', 0)->assertViewHas('saldoAkhir', '10643000');
    }

    public function test_spd_updates_and_deletions_are_reflected_without_copying_ledger_records(): void
    {
        $id = $this->spdPayment(1000, '2026-10-21');
        DB::table('spd_lss')->where('id', $id)->update(['jumlah_akhir' => 2000, 'tanggal_dba' => '2026-11-01']);
        $this->get(route('bku-pejabat-keuangan.index', ['bulan' => 'Oktober']))->assertOk()->assertViewHas('totalPengeluaran', 0);
        $this->get(route('bku-pejabat-keuangan.index', ['bulan' => 'November']))->assertOk()->assertViewHas('totalPengeluaran', 2000);
        DB::table('spd_lss')->where('id', $id)->update(['deleted_at' => now()]);
        $this->get(route('bku-pejabat-keuangan.index'))->assertOk()->assertViewHas('totalPengeluaran', 0);
        $this->assertDatabaseCount('bku_pejabat_keuangans', 0);
    }

    public function test_pdf_and_manual_balance_recalculation_include_spd_debits(): void
    {
        $this->deposit();
        $this->spdPayment(1000000, '2026-10-21');
        $this->post(route('bku-pejabat-keuangan.store'), ['tanggal' => '2026-10-22', 'uraian' => 'Penerimaan manual', 'penerimaan' => '2.000'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bku_pejabat_keuangans', ['unit_blud_id' => 1, 'uraian' => 'Penerimaan manual', 'saldo' => 645000]);
        $this->assertDatabaseCount('bku_pejabat_keuangans', 1);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) {
            $this->assertEquals(1000000, $data['totalPengeluaran']);
            $this->assertSame('645000', $data['saldoAkhir']);
            $this->assertCount(3, $data['bkuList']);
            $html = view($view, $data)->render();
            $this->assertStringContainsString('Pencairan SPD LS', $html);
            $this->assertStringContainsString('1.000.000', $html);

            return true;
        })->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('pdf'));
        $this->get(route('bku-pejabat-keuangan.pdf', ['bulan' => 'Oktober']))->assertOk();
    }
}
