<?php

namespace Tests\Feature;

use App\Models\RencanaPendapatan;
use App\Models\User;
use App\Support\ReferensiPendapatan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RencanaPendapatanPelayananTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'pelayanan_testing', 'database.connections.pelayanan_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        $this->withoutVite();
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
        });
        DB::table('users')->insert(['id' => 7]);
        DB::table('unit_bluds')->insert([['nama' => 'Puskesmas A'], ['nama' => 'Puskesmas B']]);
        (require database_path('migrations/2026_09_02_033636_create_rencana_pendapatans_table.php'))->up();
        (require database_path('migrations/2026_09_21_042641_add_perubahan_columns_to_rencana_pendapatans_table.php'))->up();
        (require database_path('migrations/2026_09_02_065239_create_rencana_pembiayaans_table.php'))->up();
        (require database_path('migrations/2026_09_21_063401_add_perubahan_columns_to_rencana_pembiayaans_table.php'))->up();
        (require database_path('migrations/2026_10_21_000006_add_tahun_anggaran_to_rencana_pembiayaans.php'))->up();
        Schema::create('rencana_kebutuhans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('akun_id')->nullable();
            $table->string('nama_blud');
            $table->string('tahun_anggaran')->default('2027');
            $table->boolean('is_hasil_perubahan')->default(false);
            $table->softDeletes();
        });
        $this->migration()->up();
        (require database_path('migrations/2026_10_20_000003_add_cagr_to_jenis_pelayanan_pendapatans.php'))->up();
        (require database_path('migrations/2026_10_20_000004_add_pelayanan_perubahan_to_rencana_pendapatans.php'))->up();
        (require database_path('migrations/2026_10_20_000005_add_alur_pergeseran_to_rencana_pendapatans.php'))->up();
        (require database_path('migrations/2026_10_21_000003_add_tahun_anggaran_to_rencana_pendapatans.php'))->up();
        // Referensi kecil untuk menguji aritmetika tanpa bergantung pada tarif produksi.
        $this->app->instance(ReferensiPendapatan::class, new ReferensiPendapatan([[
            'kode_rek' => '4.1.01', 'uraian' => 'Jasa layanan', 'pelayanan' => [
                ['uraian' => 'Pemeriksaan umum', 'harga_satuan' => '6000.50'],
                ['uraian' => 'Pemeriksaan gigi', 'harga_satuan' => '10000.00'],
                ['uraian' => 'Layanan pembulatan', 'harga_satuan' => '0.05'],
                ['uraian' => 'Layanan harga batas', 'harga_satuan' => '9999999999999.99'],
                ['uraian' => 'Proyeksi layanan', 'harga_satuan' => '6000.50'],
                ['uraian' => 'Proyeksi volume', 'harga_satuan' => '5.00'],
            ],
        ]]));
        $this->login();
    }

    private function migration()
    {
        return require database_path('migrations/2026_10_20_000002_create_jenis_pelayanan_pendapatans_table.php');
    }

    protected function tearDown(): void
    {
        DB::purge('pelayanan_testing');
        parent::tearDown();
    }

    private function login(string $role = 'Klaster 2'): void
    {
        $user = new User(['upt' => 'Puskesmas A', 'role' => $role]);
        $user->id = 7;
        $this->actingAs($user);
    }

    private function layanan(array $overrides = []): array
    {
        return array_replace([
            'unit_pelayanan' => 'Klaster 1', 'uraian' => 'Pemeriksaan umum',
            'volume_1' => 10, 'satuan_1' => 'Pasien',
            'volume_2' => 2, 'satuan_2' => 'Kunjungan',
            'volume_3' => 12, 'satuan_3' => 'Bulan',
            'volume_4' => 1, 'satuan_4' => 'Tahun', 'harga_satuan' => '6000.50',
        ], $overrides);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'kode_rek' => '4.1.01', 'uraian' => 'Jasa layanan',
            'jenis_pelayanan' => [$this->layanan(), $this->layanan(['unit_pelayanan' => 'Klaster 2', 'uraian' => 'Pemeriksaan gigi', 'harga_satuan' => 10000])],
        ], $overrides);
    }

    private function createPlan(): RencanaPendapatan
    {
        $this->post(route('rba.pendapatan.store'), $this->payload())->assertSessionHasNoErrors();

        return RencanaPendapatan::firstOrFail();
    }

    public function test_multiple_services_accumulate_into_one_account_and_ignore_submitted_totals(): void
    {
        $this->post(route('rba.pendapatan.store'), $this->payload([
            'jumlah' => 1, 'nama_blud' => 'Puskesmas B',
            'jenis_pelayanan' => [$this->layanan(['jumlah' => 1]), $this->layanan(['uraian' => 'Pemeriksaan gigi', 'harga_satuan' => 10000, 'jumlah' => 1])],
        ]))->assertRedirect(route('rba.pendapatan'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rencana_pendapatans', 1);
        $this->assertDatabaseCount('jenis_pelayanan_pendapatans', 2);
        $this->assertDatabaseHas('rencana_pendapatans', ['jumlah' => 3840120, 'nama_blud' => 'Puskesmas A']);
        $this->assertDatabaseHas('jenis_pelayanan_pendapatans', ['jumlah' => 1440120, 'harga_satuan' => 6000.50]);
        $plan = RencanaPendapatan::firstOrFail();
        $this->assertEquals($plan->jumlah, $plan->jenisPelayanans->sum('jumlah'));
        $this->get(route('rba.pendapatan'))->assertOk()->assertSee('Klaster 1')->assertSee('3.840.120,00');
    }

    public function test_edit_can_add_change_and_remove_services_and_recalculate_parent(): void
    {
        $plan = $this->createPlan();
        $this->get(route('rba.pendapatan.edit', $plan->id))->assertRedirect(route('rba.pendapatan', ['edit' => $plan->id]));
        $this->get(route('rba.pendapatan', ['edit' => $plan->id]))->assertOk()
            ->assertSee('Klaster 2')->assertSee('Volume 4')->assertSee('Satuan 4');
        $rows = [$this->layanan(['volume_1' => 1]), $this->layanan(['unit_pelayanan' => 'Klaster 3']), $this->layanan(['unit_pelayanan' => 'Lintas Klaster'])];
        $this->put(route('rba.pendapatan.update', $plan->id), $this->payload(['jenis_pelayanan' => $rows]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(3024252, $plan->fresh()->jumlah);
        $this->assertCount(3, $plan->fresh()->jenisPelayanans);
        $this->put(route('rba.pendapatan.update', $plan->id), $this->payload(['jenis_pelayanan' => [$rows[0]]]))
            ->assertSessionHasNoErrors();
        $this->assertEquals(144012, $plan->fresh()->jumlah);
        $this->assertDatabaseCount('jenis_pelayanan_pendapatans', 1);
    }

    public function test_fractional_volumes_round_each_line_before_aggregation(): void
    {
        $row = $this->layanan(['uraian' => 'Layanan pembulatan', 'harga_satuan' => '0.05', 'volume_1' => '0.1', 'volume_2' => 1, 'volume_3' => 1]);
        $this->post(route('rba.pendapatan.store'), $this->payload(['jenis_pelayanan' => [$row, $row]]))
            ->assertSessionHasNoErrors();
        $this->assertEquals(0.02, RencanaPendapatan::firstOrFail()->jumlah);
    }

    public function test_optional_dimensions_default_to_one_and_zero_volume_is_preserved(): void
    {
        $row = $this->layanan(['volume_1' => 0, 'volume_2' => null, 'volume_3' => null, 'volume_4' => null, 'satuan_2' => null]);
        $this->post(route('rba.pendapatan.store'), $this->payload(['jenis_pelayanan' => [$row]]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jenis_pelayanan_pendapatans', ['volume_1' => 0, 'volume_2' => 1, 'jumlah' => 0]);
        $this->assertEquals(0, RencanaPendapatan::firstOrFail()->jumlah);
    }

    public function test_invalid_service_is_rejected_without_partial_data(): void
    {
        foreach ([
            ['unit_pelayanan' => ''], ['uraian' => ''], ['satuan_1' => ''],
            ['volume_1' => -1], ['volume_2' => '1.00001'], ['volume_4' => 'abc'],
            ['uraian' => 'Layanan harga batas', 'harga_satuan' => '9999999999999.99'], ['uraian' => 'Tidak ada di referensi'],
            ['harga_satuan' => null], ['harga_satuan' => -1], ['harga_satuan' => '1.001'], ['harga_satuan' => 'abc'],
        ] as $invalid) {
            $this->post(route('rba.pendapatan.store'), $this->payload(['jenis_pelayanan' => [$this->layanan($invalid)]]))
                ->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('rencana_pendapatans', 0);
        $this->assertDatabaseCount('jenis_pelayanan_pendapatans', 0);
    }

    public function test_invalid_edit_keeps_existing_children_and_total(): void
    {
        $plan = $this->createPlan();
        $ids = $plan->jenisPelayanans->pluck('id')->all();
        $this->put(route('rba.pendapatan.update', $plan->id), $this->payload(['jenis_pelayanan' => [$this->layanan(['volume_1' => -1])]]))
            ->assertSessionHasErrors('jenis_pelayanan.0.volume_1');
        $this->assertEquals(3840120, $plan->fresh()->jumlah);
        $this->assertSame($ids, $plan->fresh()->jenisPelayanans->pluck('id')->all());
    }

    public function test_manual_total_or_cagr_without_services_is_rejected(): void
    {
        foreach ([[], ['jumlah' => 100000], ['gunakan_cagr' => 1, 'realisasi_tahun_3' => 100000, 'realisasi_jan_jun' => 60000]] as $extra) {
            $this->post(route('rba.pendapatan.store'), ['kode_rek' => '4.1.01', 'uraian' => 'Pendapatan'] + $extra)
                ->assertSessionHasErrors('jenis_pelayanan');
        }
        $this->assertDatabaseCount('rencana_pendapatans', 0);
    }

    public function test_same_account_cannot_be_duplicated_in_one_blud(): void
    {
        $plan = $this->createPlan();
        $this->post(route('rba.pendapatan.store'), $this->payload())->assertSessionHasErrors('kode_rek');
        $this->put(route('rba.pendapatan.update', $plan->id), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rencana_pendapatans', 1);
        $this->login('Superuser');
        $this->post(route('rba.pendapatan.store'), $this->payload(['nama_blud' => 'Puskesmas B']))
            ->assertRedirect(route('rba.pendapatan', ['puskesmas' => 'Puskesmas B']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rencana_pendapatans', 2);
    }

    public function test_foreign_blud_cannot_be_read_updated_or_deleted(): void
    {
        $plan = $this->createPlan();
        $plan->update(['nama_blud' => 'Puskesmas B']);
        $this->get(route('rba.pendapatan', ['puskesmas' => 'Puskesmas B']))->assertOk()
            ->assertViewHas('grouped', fn ($grouped) => $grouped->isEmpty());
        $this->get(route('rba.pendapatan.edit', $plan->id))->assertNotFound();
        $this->put(route('rba.pendapatan.update', $plan->id), $this->payload())->assertNotFound();
        $this->delete(route('rba.pendapatan.destroy', $plan->id))->assertNotFound();
        $this->assertDatabaseCount('jenis_pelayanan_pendapatans', 2);
    }

    public function test_delete_removes_children_and_parent(): void
    {
        $plan = $this->createPlan();
        $this->get(route('rba.pendapatan'))->assertOk()
            ->assertSee('Hapus Rencana Pendapatan')
            ->assertSee('action="'.route('rba.pendapatan.destroy', $plan->id).'"', false)
            ->assertSee('Hapus rencana pendapatan beserta seluruh rincian pelayanannya?');
        $this->delete(route('rba.pendapatan.destroy', $plan->id))->assertRedirect();
        $this->assertDatabaseCount('rencana_pendapatans', 0);
        $this->assertDatabaseCount('jenis_pelayanan_pendapatans', 0);
    }

    public function test_form_preserves_multiple_rows_after_validation_error(): void
    {
        $url = route('rba.pendapatan', ['tambah' => 1]);
        $this->get(route('rba.pendapatan.create'))->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Tambah Jenis Pelayanan')->assertSee('rba-pendapatan-pelayanan.js')
            ->assertSee('id="modalPendapatan"', false)->assertSee('Hitung Proyeksi CAGR');
        $this->from($url)->post(route('rba.pendapatan.store'), $this->payload(['kode_rek' => '5.1']))
            ->assertRedirect($url)->assertSessionHasErrors('kode_rek');
        $this->get($url)->assertOk()->assertSee('value="Klaster 1" selected', false)->assertSee('value="Klaster 2" selected', false)
            ->assertSee('jenis_pelayanan[1][harga_satuan]', false);
    }

    public function test_pdf_and_summary_use_accumulated_amount(): void
    {
        $this->createPlan();
        $this->get(route('rba.pendapatan.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('rba.ringkasan'))->assertOk()->assertSee('3.840.120');
    }

    public function test_same_account_can_be_budgeted_in_different_years_and_year_filter_is_preserved(): void
    {
        $active = $this->createPlan();
        $historical = $this->payload(['tahun' => 2026, 'jenis_pelayanan' => [$this->layanan(['volume_1' => 1])]]);
        $this->post(route('rba.pendapatan.store'), $historical)->assertSessionHasNoErrors()
            ->assertRedirect(route('rba.pendapatan', ['tahun' => 2026]));
        $this->assertSame(2, RencanaPendapatan::count());
        $this->assertSame(2027, $active->fresh()->tahun_anggaran);
        $this->get(route('rba.pendapatan', ['tahun' => 2026]))->assertOk()->assertViewHas('tahunRba', 2026)
            ->assertViewHas('grandTotalJumlah', 144012)->assertSee('name="tahun" value="2026"', false);
        $this->get(route('rba.pendapatan'))->assertOk()->assertViewHas('grandTotalJumlah', 3840120);
        $this->post(route('rba.pendapatan.store'), $historical)->assertSessionHasErrors('kode_rek');
        $historicalPlan = RencanaPendapatan::where('tahun_anggaran', 2026)->firstOrFail();
        $this->put(route('rba.pendapatan.update', $historicalPlan->id), $historical)->assertSessionHasNoErrors();
        $this->assertEquals(3840120, $active->fresh()->jumlah);
        $this->get(route('rba.pendapatan', ['tahun' => 'invalid']))->assertSessionHasErrors('tahun');
    }

    public function test_migration_preserves_existing_totals_and_change_states(): void
    {
        $yearMigration = require database_path('migrations/2026_10_21_000003_add_tahun_anggaran_to_rencana_pendapatans.php');
        $yearMigration->down();
        $this->migration()->down();
        $plan = RencanaPendapatan::create([
            'kode_rek' => '4.1.01', 'uraian' => 'Data sebelumnya', 'jumlah' => 12345.67,
            'nama_blud' => 'Puskesmas A', 'user_id' => 7, 'is_hasil_perubahan' => false,
            'jumlah_perubahan' => 15000, 'status_perubahan' => 'pending',
        ]);
        $this->migration()->up();
        $yearMigration->up();
        $plan->refresh();
        $this->assertEquals(12345.67, $plan->jumlah);
        $this->assertEquals(12345.67, $plan->jenisPelayanans->sum('jumlah'));
        $this->assertTrue($plan->jenisPelayanans->first()->is_data_lama);
        $this->assertSame('pending', $plan->status_perubahan);
        $this->assertEquals(15000, $plan->jumlah_perubahan);
    }

    public function test_guests_cannot_access_forms_or_save(): void
    {
        auth()->forgetGuards();
        $this->get(route('rba.pendapatan.create'))->assertRedirect(route('login'));
        $this->post(route('rba.pendapatan.store'), $this->payload())->assertRedirect(route('login'));
    }

    private function cagr(array $overrides = []): array
    {
        return array_replace($this->layanan(), [
            'unit_pelayanan' => 'Klaster 1', 'uraian' => 'Proyeksi layanan', 'gunakan_cagr' => 1,
            'realisasi_tahun_3' => 1000000, 'realisasi_tahun_2' => 1100000, 'realisasi_jan_jun' => 605000,
        ], $overrides);
    }

    public function test_cagr_and_manual_services_accumulate_and_history_is_persisted(): void
    {
        $this->post(route('rba.pendapatan.store'), $this->payload([
            'jenis_pelayanan' => [$this->layanan(), $this->cagr(['jumlah' => 999])],
        ]))->assertSessionHasNoErrors();
        $plan = RencanaPendapatan::firstOrFail();
        $this->assertEquals(2880240, $plan->jumlah);
        $cagr = $plan->jenisPelayanans->last();
        $this->assertTrue($cagr->gunakan_cagr);
        $this->assertEquals(1440120, $cagr->jumlah);
        $this->assertEquals(6000.50, $cagr->harga_satuan);
        $this->assertEquals(10, $cagr->volume_1);
        $this->assertEquals(2, $cagr->volume_2);
        $this->assertEquals(12, $cagr->volume_3);
        $this->assertEquals(1, $cagr->volume_4);
        $this->assertSame('Pasien', $cagr->satuan_1);
        $this->assertEquals(1000000, $cagr->realisasi_tahun_3);
        $this->assertEquals(1100000, $cagr->realisasi_tahun_2);
        $this->assertEquals(605000, $cagr->realisasi_jan_jun);
        $this->get(route('rba.pendapatan'))->assertOk()->assertSee('Proyeksi CAGR tersedia')->assertSee('2.880.240,00')->assertSee('Gunakan sebagai Vol 1');
    }

    public function test_cagr_middle_year_is_optional_and_declining_revenue_is_supported(): void
    {
        $this->post(route('rba.pendapatan.store'), $this->payload([
            'jenis_pelayanan' => [$this->cagr(['realisasi_tahun_2' => null, 'realisasi_jan_jun' => 320000])],
        ]))->assertSessionHasNoErrors();
        $plan = RencanaPendapatan::firstOrFail();
        $this->assertEquals(1440120, $plan->jumlah);
        $this->assertSame('512000.00', \App\Models\JenisPelayananPendapatan::hitungProyeksiCagr($plan->jenisPelayanans->first()->toArray()));
    }

    public function test_invalid_cagr_history_or_projection_does_not_save_partial_services(): void
    {
        foreach ([
            ['realisasi_tahun_3' => 0], ['realisasi_tahun_3' => -1],
            ['realisasi_tahun_2' => -1], ['realisasi_jan_jun' => null],
            ['realisasi_jan_jun' => 0], ['realisasi_jan_jun' => 'abc'],
            ['realisasi_tahun_3' => '0.01', 'realisasi_jan_jun' => '9999999999999.99'],
            ['realisasi_tahun_3' => 1000000, 'realisasi_jan_jun' => '0.01'],
        ] as $invalid) {
            $this->post(route('rba.pendapatan.store'), $this->payload([
                'jenis_pelayanan' => [$this->layanan(), $this->cagr($invalid)],
            ]))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('rencana_pendapatans', 0);
        $this->assertDatabaseCount('jenis_pelayanan_pendapatans', 0);
    }

    public function test_switching_methods_recalculates_total_and_clears_inactive_history(): void
    {
        $plan = $this->createPlan();
        $this->put(route('rba.pendapatan.update', $plan->id), $this->payload(['jenis_pelayanan' => [$this->cagr()]]))
            ->assertSessionHasNoErrors();
        $this->assertEquals(1440120, $plan->fresh()->jumlah);
        $this->put(route('rba.pendapatan.update', $plan->id), $this->payload([
            'jenis_pelayanan' => [$this->layanan(['gunakan_cagr' => 0, 'realisasi_tahun_3' => 'abc'])],
        ]))->assertSessionHasNoErrors();
        $row = $plan->fresh()->jenisPelayanans->first();
        $this->assertFalse($row->gunakan_cagr);
        $this->assertNull($row->realisasi_tahun_3);
        $this->assertEquals(1440120, $plan->fresh()->jumlah);
    }

    public function test_edit_validation_reopens_modal_with_saved_input_and_correct_update_action(): void
    {
        $plan = $this->createPlan();
        $url = route('rba.pendapatan');
        $this->from($url)->put(route('rba.pendapatan.update', $plan->id), $this->payload([
            'pendapatan_id' => $plan->id, 'jenis_pelayanan' => [$this->cagr(['realisasi_jan_jun' => 0])],
        ]))->assertRedirect($url)->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee('data-open-on-load="1"', false)
            ->assertSee('action="'.route('rba.pendapatan.update', $plan->id).'"', false)
            ->assertSee('value="1000000"', false)->assertSee('Proyeksi layanan');
    }

    public function test_copied_cagr_volume_is_multiplied_by_manual_price_and_other_volumes(): void
    {
        $row = $this->cagr([
            'uraian' => 'Proyeksi volume',
            'volume_1' => 1331000, 'volume_2' => 2, 'volume_3' => 3, 'volume_4' => 4,
            'harga_satuan' => 5, 'jumlah' => 1,
        ]);
        $this->post(route('rba.pendapatan.store'), $this->payload(['jenis_pelayanan' => [$row]]))
            ->assertSessionHasNoErrors();
        $plan = RencanaPendapatan::firstOrFail();
        $this->assertEquals(159720000, $plan->jumlah);
        $service = $plan->jenisPelayanans->first();
        $this->assertEquals(1331000, $service->volume_1);
        $this->assertEquals(5, $service->harga_satuan);
        $this->assertSame('Pasien', $service->satuan_1);
        $this->assertSame('Kunjungan', $service->satuan_2);
    }

    public function test_manual_inputs_remain_required_when_cagr_is_active(): void
    {
        foreach ([['uraian' => ''], ['volume_1' => 'abc'], ['satuan_1' => '']] as $invalid) {
            $this->post(route('rba.pendapatan.store'), $this->payload(['jenis_pelayanan' => [$this->cagr($invalid)]]))
                ->assertSessionHasErrors('jenis_pelayanan.0.'.array_key_first($invalid));
        }
        $this->assertDatabaseCount('rencana_pendapatans', 0);
    }

    private function useWorkbookReference(): ReferensiPendapatan
    {
        $referensi = new ReferensiPendapatan();
        $this->app->instance(ReferensiPendapatan::class, $referensi);

        return $referensi;
    }

    public function test_workbook_reference_is_complete_and_deduplicates_identical_service(): void
    {
        $referensi = $this->useWorkbookReference();
        $this->assertCount(10, $referensi->all());
        $workbookAccounts = array_filter($referensi->all(), fn ($account) => isset($account['baris_sumber']));
        $this->assertCount(4, $workbookAccounts);
        $this->assertEquals(120, array_sum(array_map(fn ($account) => count($account['pelayanan']), $workbookAccounts)));
        foreach ($referensi->all() as $account) {
            $labels = array_column($account['pelayanan'], 'uraian');
            $this->assertCount(count(array_unique($labels)), $labels);
        }
        $this->assertSame([17, 77], $referensi->pelayanan('4.1.02.01.04.0001', 'Buka Jahitan')['baris_sumber']);
    }

    public function test_account_description_comes_from_reference_and_manual_tariff_is_saved(): void
    {
        $this->useWorkbookReference();
        $this->post(route('rba.pendapatan.store'), $this->payload([
            'kode_rek' => '4.1.02.01.01.0001', 'uraian' => 'Uraian palsu',
            'jenis_pelayanan' => [$this->layanan(['uraian' => 'Pelayanan Rawat Jalan', 'harga_satuan' => '20000.25'])],
        ]))->assertSessionHasNoErrors();
        $plan = RencanaPendapatan::firstOrFail();
        $this->assertSame('Pendapatan Pelayanan Rawat Jalan', $plan->uraian);
        $this->assertEquals(4800060, $plan->jumlah);
        $this->assertEquals(20000.25, $plan->jenisPelayanans->first()->harga_satuan);
        $this->get(route('rba.pendapatan'))->assertOk()
            ->assertSee('id="kode-rek" name="kode_rek" class="form-select"', false)
            ->assertSee('pelayanan-select')->assertSee('harga-view" required placeholder="Masukkan harga satuan"', false);
        $data = $this->payload([
            'kode_rek' => $plan->kode_rek,
            'jenis_pelayanan' => [$this->layanan(['uraian' => 'Pelayanan Rawat Jalan', 'harga_satuan' => '25000.75'])],
        ]);
        $this->put(route('rba.pendapatan.update', $plan->id), $data)->assertSessionHasNoErrors();
        $this->assertEquals(6000180, $plan->fresh()->jumlah);
        $this->assertEquals(25000.75, $plan->fresh()->jenisPelayanans->first()->harga_satuan);
    }

    public function test_services_from_other_account_and_unknown_accounts_are_rejected(): void
    {
        $this->useWorkbookReference();
        $this->post(route('rba.pendapatan.store'), $this->payload([
            'kode_rek' => '4.1.02.01.01.0001',
            'jenis_pelayanan' => [$this->layanan(['uraian' => 'Hemoglobin (Fotometri)'])],
        ]))->assertSessionHasErrors('jenis_pelayanan.0.uraian');
        $this->post(route('rba.pendapatan.store'), $this->payload())->assertSessionHasErrors('kode_rek');
        $this->assertDatabaseCount('rencana_pendapatans', 0);
    }

    public function test_zero_tariff_from_workbook_is_preserved(): void
    {
        $this->useWorkbookReference();
        $this->post(route('rba.pendapatan.store'), $this->payload([
            'kode_rek' => '4.1.02.01.04.0001',
            'jenis_pelayanan' => [$this->layanan(['uraian' => 'Injeksi Obat', 'harga_satuan' => 0])],
        ]))->assertSessionHasNoErrors();
        $plan = RencanaPendapatan::firstOrFail();
        $this->assertEquals(0, $plan->jumlah);
        $this->assertEquals(0, $plan->jenisPelayanans->first()->harga_satuan);
    }

    public function test_edit_requires_service_from_new_account_and_uses_its_tariff(): void
    {
        $this->useWorkbookReference();
        $data = $this->payload([
            'kode_rek' => '4.1.02.01.01.0001',
            'jenis_pelayanan' => [$this->layanan(['uraian' => 'Pelayanan Rawat Jalan'])],
        ]);
        $this->post(route('rba.pendapatan.store'), $data)->assertSessionHasNoErrors();
        $plan = RencanaPendapatan::firstOrFail();
        $data['kode_rek'] = '4.1.02.02.01.0001';
        $this->put(route('rba.pendapatan.update', $plan->id), $data)->assertSessionHasErrors('jenis_pelayanan.0.uraian');
        $this->assertSame('4.1.02.01.01.0001', $plan->fresh()->kode_rek);
        $data['jenis_pelayanan'][0]['uraian'] = 'Hemoglobin (Fotometri)';
        $data['jenis_pelayanan'][0]['harga_satuan'] = 8000;
        $this->put(route('rba.pendapatan.update', $plan->id), $data)->assertSessionHasNoErrors();
        $this->assertEquals(1920000, $plan->fresh()->jumlah);
        $this->assertSame('Pendapatan Pelayanan Penunjang Diagnosis', $plan->fresh()->uraian);
    }

    public function test_unit_pelayanan_accepts_all_five_cluster_options_and_rejects_other_values(): void
    {
        $units = ['Klaster 1', 'Klaster 2', 'Klaster 3', 'Klaster 4', 'Lintas Klaster'];
        $this->post(route('rba.pendapatan.store'), $this->payload([
            'jenis_pelayanan' => array_map(fn ($unit) => $this->layanan(['unit_pelayanan' => $unit]), $units),
        ]))->assertSessionHasNoErrors();
        $plan = RencanaPendapatan::firstOrFail();
        $this->assertSame($units, $plan->jenisPelayanans->pluck('unit_pelayanan')->all());
        $this->put(route('rba.pendapatan.update', $plan->id), $this->payload([
            'jenis_pelayanan' => [$this->layanan(['unit_pelayanan' => 'Poli Umum'])],
        ]))->assertSessionHasErrors('jenis_pelayanan.0.unit_pelayanan');
        $this->assertSame($units, $plan->fresh()->jenisPelayanans->pluck('unit_pelayanan')->all());
    }

    public function test_all_six_additional_accounts_can_be_saved_with_manual_tariffs(): void
    {
        $referensi = $this->useWorkbookReference();
        $accounts = [
            '4.1.04.16.02.0001' => 'Pendapatan BLUD dari Jasa Layanan',
            '4.2.01.01.01.0001' => 'Pendapatan Hibah',
            '4.3.01.01.01.0001' => 'Hasil Kerja Sama',
            '4.4.01.01.01.0001' => 'Pendapatan BLUD dari APBD',
            '4.5.01.01.01.0001' => 'Jasa Giro/Bunga',
            '4.5.05.01.01.0001' => 'Investasi',
        ];
        foreach ($accounts as $code => $description) {
            $this->assertNull($referensi->pelayanan($code, $description)['harga_satuan']);
            $this->post(route('rba.pendapatan.store'), $this->payload([
                'kode_rek' => $code, 'jenis_pelayanan' => [$this->layanan(['uraian' => $description, 'harga_satuan' => 20000])],
            ]))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('rencana_pendapatans', ['kode_rek' => $code, 'uraian' => $description, 'jumlah' => 4800000]);
        }
        $this->assertDatabaseCount('rencana_pendapatans', 6);
        $this->get(route('rba.pendapatan'))->assertOk()
            ->assertSee('optgroup label="4.1 - JASA LAYANAN"', false)
            ->assertSee('optgroup label="4.2 - HIBAH"', false)
            ->assertSee('optgroup label="4.3 - HASIL KERJASAMA"', false)
            ->assertSee('optgroup label="4.4 - APBD"', false)
            ->assertSee('optgroup label="4.5 - LAIN-LAIN PENDAPATAN BLUD YANG SAH"', false);
    }
}
