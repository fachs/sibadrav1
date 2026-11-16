<?php

namespace Tests\Feature;

use App\Models\RencanaPendapatan;
use App\Models\User;
use App\Support\ReferensiPendapatan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RencanaPendapatanPerubahanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'perubahan_testing', 'database.connections.perubahan_testing' => [
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
        foreach ([
            '2026_09_02_033636_create_rencana_pendapatans_table.php',
            '2026_09_21_042641_add_perubahan_columns_to_rencana_pendapatans_table.php',
            '2026_10_20_000002_create_jenis_pelayanan_pendapatans_table.php',
            '2026_10_20_000003_add_cagr_to_jenis_pelayanan_pendapatans.php',
            '2026_10_20_000004_add_pelayanan_perubahan_to_rencana_pendapatans.php',
            '2026_10_20_000005_add_alur_pergeseran_to_rencana_pendapatans.php',
            '2026_10_21_000003_add_tahun_anggaran_to_rencana_pendapatans.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        $this->app->instance(ReferensiPendapatan::class, new ReferensiPendapatan([
            ['kode_rek' => '4.1.01', 'uraian' => 'Jasa layanan', 'pelayanan' => [
                ['uraian' => 'Pemeriksaan umum', 'harga_satuan' => '6000.50'],
                ['uraian' => 'Pemeriksaan gigi', 'harga_satuan' => '10000.00'],
            ]],
            ['kode_rek' => '4.1.01.02', 'uraian' => 'Jasa layanan lainnya', 'pelayanan' => [
                ['uraian' => 'Pemeriksaan umum', 'harga_satuan' => '6000.50'],
            ]],
            ['kode_rek' => '4.2.01', 'uraian' => 'Hibah', 'pelayanan' => [
                ['uraian' => 'Pendapatan Hibah', 'harga_satuan' => null],
            ]],
        ]));
        $this->login();
    }

    protected function tearDown(): void
    {
        DB::purge('perubahan_testing');
        parent::tearDown();
    }

    private function login(string $role = 'Klaster 2', string $upt = 'Puskesmas A'): void
    {
        $user = new User(['upt' => $upt, 'role' => $role]);
        $user->id = 7;
        $this->actingAs($user);
    }

    private function service(array $overrides = []): array
    {
        return array_replace([
            'unit_pelayanan' => 'Klaster 1', 'uraian' => 'Pemeriksaan umum',
            'volume_1' => 10, 'satuan_1' => 'Pasien', 'harga_satuan' => '6000.50',
            'volume_2' => 2, 'satuan_2' => 'Kunjungan',
            'volume_3' => 3, 'satuan_3' => 'Bulan', 'volume_4' => 4, 'satuan_4' => 'Tahun',
        ], $overrides);
    }

    private function plan(string $blud = 'Puskesmas A'): RencanaPendapatan
    {
        $plan = RencanaPendapatan::create([
            'kode_rek' => '4.1.01', 'uraian' => 'Jasa layanan', 'jumlah' => '60005.00',
            'nama_blud' => $blud, 'user_id' => 7, 'is_hasil_perubahan' => false,
        ]);
        $plan->jenisPelayanans()->create($this->service([
            'volume_2' => 1, 'volume_3' => 1, 'volume_4' => 1, 'jumlah' => '60005.00',
        ]));

        return $plan;
    }

    private function payload(RencanaPendapatan $plan, array $overrides = []): array
    {
        return array_replace([
            'pendapatan_id' => $plan->id, 'kode_rek' => $plan->kode_rek,
            'jenis_pelayanan' => [$this->service()],
        ], $overrides);
    }

    public function test_page_shows_service_details_without_edit_or_delete_actions(): void
    {
        $plan = $this->plan();
        $this->login('Kepala Puskesmas');
        $this->get(route('rba-perubahan.pendapatan'))->assertOk()
            ->assertSee('id="modalPendapatan"', false)->assertSee('data-change-mode="1"', false)
            ->assertSee('Tambah Jenis Pelayanan')->assertSee('Gunakan sebagai Vol 1')
            ->assertSee('Volume 1 / Satuan 1')->assertSee('rba-pendapatan-pelayanan.js')
            ->assertDontSee('Usulkan Perubahan Pelayanan')->assertDontSee('Nolkan Perubahan')
            ->assertDontSee(route('rba-perubahan.pendapatan.zero-out', $plan->id), false)->assertSee('60.005,00');
        $this->assertEquals(60005, $plan->fresh()->jumlah);
        $this->assertSame($plan->jenisPelayanans->toArray(), $plan->dataEditorPerubahan()['jenis_pelayanans']);
    }

    public function test_multiple_proposed_services_use_manual_prices_and_all_volumes_without_changing_originals(): void
    {
        $plan = $this->plan();
        $before = $plan->jenisPelayanans->toArray();
        $rows = [$this->service(['harga_satuan' => '7.25', 'jumlah' => 1]), $this->service([
            'uraian' => 'Pemeriksaan gigi', 'unit_pelayanan' => 'Lintas Klaster', 'harga_satuan' => '10',
        ])];
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan, [
            'uraian' => 'Palsu', 'jumlah' => 1, 'jumlah_perubahan' => 1, 'nama_blud' => 'Puskesmas B',
            'status_perubahan' => 'verified', 'jenis_pelayanan' => $rows,
        ]))->assertRedirect(route('rba-perubahan.pendapatan'))->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertSame('pending', $plan->status_perubahan);
        $this->assertSame('Jasa layanan', $plan->uraian_perubahan);
        $this->assertSame('Puskesmas A', $plan->nama_blud);
        $this->assertSame('4140.00', $plan->jumlah_perubahan);
        $this->assertSame('60005.00', $plan->jumlah);
        $this->assertSame($before, $plan->jenisPelayanans->toArray());
        $this->assertCount(2, $plan->jenis_pelayanan_perubahan);
        $this->assertSame('1740.00', $plan->jenis_pelayanan_perubahan[0]['jumlah']);
        $this->assertSame('7.25', $plan->dataEditorPerubahan()['jenis_pelayanans'][0]['harga_satuan']);
        $this->get(route('rba-perubahan.pendapatan'))->assertOk()
            ->assertSee('4.140,00')->assertSee('Lintas Klaster')->assertSee('Menunggu Verifikasi');
        $this->get(route('rba.pendapatan'))->assertOk()->assertDontSee('4.140,00');
    }

    public function test_reediting_adds_and_removes_proposed_services_and_replaces_total(): void
    {
        $plan = $this->plan();
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan, [
            'jenis_pelayanan' => [$this->service(), $this->service(['uraian' => 'Pemeriksaan gigi'])],
        ]))->assertSessionHasNoErrors();
        $this->assertCount(2, $plan->fresh()->jenis_pelayanan_perubahan);
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan, [
            'jenis_pelayanan' => [$this->service(['volume_1' => 0])],
        ]))->assertSessionHasNoErrors();
        $this->assertCount(1, $plan->fresh()->jenis_pelayanan_perubahan);
        $this->assertSame('0.00', $plan->fresh()->jumlah_perubahan);
        $this->assertDatabaseCount('jenis_pelayanan_pendapatans', 1);
    }

    public function test_cagr_is_preserved_as_helper_while_manual_values_determine_proposed_total(): void
    {
        $plan = $this->plan();
        $row = $this->service([
            'gunakan_cagr' => 1, 'realisasi_tahun_3' => 100, 'realisasi_jan_jun' => '60.50',
            'harga_satuan' => '5', 'volume_1' => 133,
        ]);
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan, [
            'jenis_pelayanan' => [$row],
        ]))->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertSame('15960.00', $plan->jumlah_perubahan);
        $this->assertTrue($plan->jenis_pelayanan_perubahan[0]['gunakan_cagr']);
        $this->assertEquals(133, $plan->jenis_pelayanan_perubahan[0]['volume_1']);
        $this->assertEquals(100, $plan->jenis_pelayanan_perubahan[0]['realisasi_tahun_3']);
        $this->assertSame('60005.00', $plan->jumlah);
    }

    public function test_invalid_proposal_is_atomic_and_reopens_modal_with_old_input(): void
    {
        $plan = $this->plan();
        $url = route('rba-perubahan.pendapatan');
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan))->assertSessionHasNoErrors();
        $saved = $plan->fresh()->jenis_pelayanan_perubahan;
        $this->from($url)->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan, [
            'jenis_pelayanan' => [$this->service(['unit_pelayanan' => 'Klaster 4', 'volume_1' => -1])],
        ]))->assertRedirect($url)->assertSessionHasErrors('jenis_pelayanan.0.volume_1');
        $this->assertSame($saved, $plan->fresh()->jenis_pelayanan_perubahan);
        $this->get($url)->assertOk()->assertSee('data-open-on-load="1"', false)
            ->assertSee('action="'.route('rba-perubahan.pendapatan.update', $plan->id).'"', false)
            ->assertSee('value="Klaster 4" selected', false);
    }

    public function test_account_service_membership_and_line_totals_are_validated(): void
    {
        $plan = $this->plan();
        foreach ([
            ['kode_rek' => '4.2.01'],
            ['jenis_pelayanan' => []],
            ['jenis_pelayanan' => [$this->service(['uraian' => 'Pendapatan Hibah'])]],
            ['jenis_pelayanan' => [$this->service(['harga_satuan' => '9999999999999.99'])]],
            ['jenis_pelayanan' => [$this->service(['unit_pelayanan' => 'Tidak ada'])]],
            ['jenis_pelayanan' => [$this->service(['gunakan_cagr' => 1, 'realisasi_tahun_3' => 0])]],
        ] as $invalid) {
            $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan, $invalid))->assertSessionHasErrors();
        }
        $this->assertNull($plan->fresh()->jumlah_perubahan);
        $this->assertNull($plan->fresh()->jenis_pelayanan_perubahan);
    }

    public function test_foreign_blud_records_cannot_be_seen_or_changed(): void
    {
        $plan = $this->plan('Puskesmas B');
        $this->get(route('rba-perubahan.pendapatan', ['puskesmas' => 'Puskesmas B']))->assertOk()
            ->assertViewHas('grouped', fn ($items) => $items->isEmpty());
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan))->assertNotFound();
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.zero-out', $plan->id))->assertNotFound();
        $this->login('Dinas');
        $plan->update(['status_perubahan' => 'pending']);
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $plan->id))->assertForbidden();
        $this->patch(route('rba-perubahan.pendapatan.tolak', $plan->id))->assertForbidden();
        $this->assertSame('pending', $plan->fresh()->status_perubahan);
    }

    public function test_superuser_edits_selected_blud_without_reassigning_account(): void
    {
        $plan = $this->plan('Puskesmas B');
        $this->login('Superuser');
        $this->get(route('rba-perubahan.pendapatan', ['puskesmas' => 'Puskesmas B']))->assertOk()
            ->assertViewHas('grouped', fn ($items) => $items->flatten()->count() === 1);
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan, [
            'nama_blud' => 'Puskesmas A',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('rba-perubahan.pendapatan', ['puskesmas' => 'Puskesmas B']));
        $this->assertSame('Puskesmas B', $plan->fresh()->nama_blud);
        $this->assertSame('pending', $plan->fresh()->status_perubahan);
    }

    public function test_zeroing_changes_only_proposed_volumes_and_requires_privileged_role(): void
    {
        $plan = $this->plan();
        $this->patch(route('rba-perubahan.pendapatan.zero-out', $plan->id))->assertForbidden();
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.zero-out', $plan->id))->assertRedirect();
        $plan->refresh();
        $this->assertSame('0.00', $plan->jumlah_perubahan);
        $this->assertSame('pending', $plan->status_perubahan);
        $this->assertEquals(0, $plan->jenis_pelayanan_perubahan[0]['volume_1']);
        $this->assertSame('0.00', $plan->jenis_pelayanan_perubahan[0]['jumlah']);
        $this->assertSame('60005.00', $plan->jumlah);
        $this->assertSame('10.0000', $plan->jenisPelayanans->first()->volume_1);
        $this->get(route('rba-perubahan.pendapatan'))->assertOk()
            ->assertDontSee('data-confirm-delete', false)
            ->assertSee('aria-label="Verifikasi"', false)->assertSee('aria-label="Tolak"', false);
    }

    public function test_review_requires_reviewer_role_and_verified_proposals_cannot_be_edited(): void
    {
        $plan = $this->plan();
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan))->assertSessionHasNoErrors();
        foreach (['verifikasi', 'tolak'] as $action) {
            $this->patch(route('rba-perubahan.pendapatan.'.$action, $plan->id))->assertForbidden();
        }
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.tolak', $plan->id))->assertRedirect();
        $this->assertNull($plan->fresh()->status_perubahan);
        $this->assertNull($plan->fresh()->jenis_pelayanan_perubahan);
        $this->login();
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan))->assertSessionHasNoErrors();
        $this->assertSame('pending', $plan->fresh()->status_perubahan);
        $this->login('Superuser');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $plan->id))->assertRedirect();
        $this->assertSame('verified', $plan->fresh()->status_perubahan);
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan))->assertForbidden();
        $this->patch(route('rba-perubahan.pendapatan.zero-out', $plan->id))->assertForbidden();
    }

    public function test_bulk_review_respects_blud_scope_and_superuser_filter(): void
    {
        $own = $this->plan();
        $other = $this->plan('Puskesmas B');
        $own->update(['status_perubahan' => 'pending']);
        $other->update(['status_perubahan' => 'pending']);
        $this->patch(route('rba-perubahan.pendapatan.verifikasi-semua'))->assertForbidden();
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi-semua'), ['puskesmas' => 'Puskesmas B'])->assertRedirect();
        $this->assertSame('verified', $own->fresh()->status_perubahan);
        $this->assertSame('pending', $other->fresh()->status_perubahan);
        $this->login('Superuser');
        $this->patch(route('rba-perubahan.pendapatan.tolak-semua'), ['puskesmas' => 'Puskesmas B'])->assertRedirect();
        $this->assertSame('verified', $own->fresh()->status_perubahan);
        $this->assertNull($other->fresh()->status_perubahan);
    }

    public function test_pdf_uses_original_summary_layout_and_only_verified_decimal_totals(): void
    {
        $plan = $this->plan();
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan, [
            'jenis_pelayanan' => [$this->service(['volume_1' => 1, 'harga_satuan' => '0.05'])],
        ]))->assertSessionHasNoErrors();
        $fakePdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $fakePdf->shouldReceive('setPaper')->andReturnSelf();
        $fakePdf->shouldReceive('download')->andReturn(response('pdf', 200, ['Content-Type' => 'application/pdf']));
        $expected = ['60005.00', '1.20'];
        Pdf::shouldReceive('loadView')->twice()->withArgs(function ($view, $data) use (&$expected) {
            $item = $data['grouped']->flatten()->first();
            $amount = array_shift($expected);
            $this->assertEquals($amount, $data['grandTotalJumlah']);
            $this->assertEquals($amount, $item->pelayananPerubahan()->sum('jumlah'));
            $html = view($view, $data)->render();
            $this->assertStringNotContainsString('Rincian Jenis Pelayanan', $html);
            $this->assertStringNotContainsString('Volume 1 / Satuan 1', $html);
            $this->assertStringContainsString('Sebelum<br>Perubahan', $html);
            $this->assertStringContainsString('Setelah<br>Perubahan', $html);
            $this->assertStringContainsString(number_format($amount, 2, ',', '.'), $html);
            return true;
        })->andReturn($fakePdf);
        $this->get(route('rba-perubahan.pendapatan.pdf'))->assertOk();
        $plan->update(['status_perubahan' => 'verified']);
        $this->get(route('rba-perubahan.pendapatan.pdf'))->assertOk();
        $this->assertSame([], $expected);
    }

    public function test_change_pdf_can_be_rendered_and_downloaded(): void
    {
        $plan = $this->plan();
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan))->assertSessionHasNoErrors();
        $plan->update(['status_perubahan' => 'verified']);
        $this->get(route('rba-perubahan.pendapatan.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_legacy_proposals_remain_visible_until_service_details_are_submitted(): void
    {
        $plan = $this->plan();
        $plan->update(['jumlah_perubahan' => 12345, 'status_perubahan' => 'pending']);
        $this->get(route('rba-perubahan.pendapatan'))->assertOk()
            ->assertSee('Usulan sebelumnya belum memiliki rincian jenis pelayanan.')->assertSee('12.345,00');
        $this->assertCount(1, $plan->dataEditorPerubahan()['jenis_pelayanans']);
    }

    public function test_guest_cannot_access_or_save_changes(): void
    {
        $plan = $this->plan();
        auth()->forgetGuards();
        $this->get(route('rba-perubahan.pendapatan'))->assertRedirect(route('login'));
        $this->put(route('rba-perubahan.pendapatan.update', $plan->id), $this->payload($plan))->assertRedirect(route('login'));
    }

    private function shiftPayload(RencanaPendapatan $source, array $overrides = []): array
    {
        $row = $this->service(['volume_1' => 5, 'volume_2' => 1, 'volume_3' => 1, 'volume_4' => 1]);

        return array_replace([
            'sumber_pendapatan_id' => $source->id, 'tipe_tujuan' => 'baru',
            'tujuan_kode_rek' => '4.1.01.02',
            'sumber_rincian' => json_encode([$row]), 'tujuan_rincian' => json_encode([$row]),
        ], $overrides);
    }

    private function destination(string $blud = 'Puskesmas A', string $code = '4.1.01.02'): RencanaPendapatan
    {
        $plan = RencanaPendapatan::create([
            'kode_rek' => $code, 'uraian' => app(ReferensiPendapatan::class)->rekening($code)['uraian'],
            'jumlah' => '60005.00', 'nama_blud' => $blud, 'user_id' => 7, 'is_hasil_perubahan' => false,
        ]);
        $plan->jenisPelayanans()->create($this->service([
            'uraian' => $code === '4.2.01' ? 'Pendapatan Hibah' : 'Pemeriksaan umum',
            'volume_2' => 1, 'volume_3' => 1, 'volume_4' => 1, 'jumlah' => '60005.00',
        ]));

        return $plan;
    }

    private function createShift(RencanaPendapatan $source, bool $cross = false): RencanaPendapatan
    {
        $extra = $cross ? [
            'tujuan_kode_rek' => '4.2.01',
            'tujuan_rincian' => json_encode([$this->service([
                'uraian' => 'Pendapatan Hibah', 'volume_1' => 5, 'volume_2' => 1, 'volume_3' => 1, 'volume_4' => 1,
            ])]),
        ] : [];
        $this->post(route('rba-perubahan.pendapatan.pergeseran-lintas-akun'), $this->shiftPayload($source, $extra))
            ->assertRedirect()->assertSessionHasNoErrors();

        return RencanaPendapatan::where('pergeseran_id', $source->fresh()->pergeseran_id)->whereKeyNot($source->id)->firstOrFail();
    }

    public function test_shift_form_uses_existing_service_modal_and_preserves_posted_rows_after_error(): void
    {
        $source = $this->plan();
        $url = route('rba-perubahan.pendapatan.pergeseran-lintas-akun.create');
        $this->get($url)->assertOk()->assertSee('Sumber Perubahan')->assertSee('Tujuan Perubahan')
            ->assertSee('Rekening yang sudah ada')->assertSee('Rekening baru')->assertSee('Gunakan sebagai Vol 1')
            ->assertSee('data-local-editor="1"', false)->assertSee('rba-pendapatan-pergeseran.js');
        $payload = $this->shiftPayload($source, ['tujuan_rincian' => '[]']);
        $this->from($url)->post(route('rba-perubahan.pendapatan.pergeseran-lintas-akun'), $payload)
            ->assertRedirect($url)->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee('name="sumber_rincian"', false)
            ->assertSee('Pemeriksaan umum')->assertSee('Sumber Perubahan');
        $this->assertNull($source->fresh()->pergeseran_id);
    }

    public function test_shift_to_new_account_keeps_original_amounts_and_starts_at_kepala_puskesmas(): void
    {
        $source = $this->plan();
        $before = $source->jenisPelayanans->toArray();
        $target = $this->createShift($source);
        $source->refresh();
        $this->assertSame('60005.00', $source->jumlah);
        $this->assertSame($before, $source->jenisPelayanans->toArray());
        $this->assertSame('30002.50', $source->jumlah_perubahan);
        $this->assertSame('0.00', $target->jumlah);
        $this->assertSame('30002.50', $target->jumlah_perubahan);
        $this->assertTrue((bool) $target->is_hasil_perubahan);
        $this->assertSame($source->pergeseran_id, $target->pergeseran_id);
        foreach ([$source, $target] as $record) {
            $this->assertSame('pending', $record->status_perubahan);
            $this->assertSame('kepala puskesmas', $record->verifikator_perubahan);
            $this->assertSame('4.1.01', $record->rekening_awal);
            $this->assertSame('4.1.01.02', $record->rekening_akhir);
        }
        $this->get(route('rba.pendapatan'))->assertOk()
            ->assertViewHas('grouped', fn ($items) => !$items->flatten()->contains('id', $target->id));
        $this->get(route('rba-perubahan.pendapatan'))->assertOk()->assertSee('Rekening Baru')
            ->assertSee('Menunggu Verifikasi (Kepala Puskesmas)')->assertDontSee('Edit Pengajuan Pergeseran');
        $this->post(route('rba.pendapatan.store'), [
            'kode_rek' => $target->kode_rek, 'jenis_pelayanan' => [$this->service()],
        ])->assertSessionHasErrors('kode_rek');
    }

    public function test_shift_to_existing_account_keeps_both_original_service_lists(): void
    {
        $source = $this->plan();
        $target = $this->destination();
        $before = $target->jenisPelayanans->toArray();
        $this->post(route('rba-perubahan.pendapatan.pergeseran-lintas-akun'), $this->shiftPayload($source, [
            'tipe_tujuan' => 'existing', 'tujuan_pendapatan_id' => $target->id,
        ]))->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertDatabaseCount('rencana_pendapatans', 2);
        $this->assertSame('60005.00', $target->jumlah);
        $this->assertSame($before, $target->jenisPelayanans->toArray());
        $this->assertSame($source->fresh()->pergeseran_id, $target->pergeseran_id);
    }

    public function test_same_prefix_shift_is_verified_for_both_accounts_by_kepala_puskesmas(): void
    {
        $source = $this->plan();
        $target = $this->createShift($source);
        $this->login('Dinas Kesehatan', 'Dinas Kesehatan');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $source->id))->assertForbidden();
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $target->id))->assertRedirect();
        $this->assertSame('verified', $source->fresh()->status_perubahan);
        $this->assertSame('verified', $target->fresh()->status_perubahan);
    }

    public function test_cross_prefix_shift_requires_kepala_then_dinas_and_cannot_be_partially_approved(): void
    {
        $source = $this->plan();
        $target = $this->createShift($source, true);
        $this->login('Kepala Puskesmas', 'Puskesmas B');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $source->id))->assertNotFound();
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $source->id))->assertRedirect();
        foreach ([$source, $target] as $record) {
            $this->assertSame('pending', $record->fresh()->status_perubahan);
            $this->assertSame('dinas kesehatan', $record->fresh()->verifikator_perubahan);
        }
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $target->id))->assertForbidden();
        $this->login('Dinas Kesehatan', 'Dinas Kesehatan');
        $this->get(route('rba-perubahan.pendapatan'))->assertOk()->assertSee('Puskesmas A')
            ->assertSee('Menunggu Verifikasi (Dinas Kesehatan)')->assertSee('Verifikasi Usulan');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $target->id))->assertRedirect();
        $this->assertSame('verified', $source->fresh()->status_perubahan);
        $this->assertSame('verified', $target->fresh()->status_perubahan);
    }

    public function test_bulk_review_does_not_process_same_pair_twice_and_respects_current_stage(): void
    {
        $source = $this->plan();
        $target = $this->createShift($source, true);
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi-semua'))->assertRedirect();
        $this->assertSame('pending', $source->fresh()->status_perubahan);
        $this->assertSame('pending', $target->fresh()->status_perubahan);
        $this->assertSame('dinas kesehatan', $target->fresh()->verifikator_perubahan);
        $this->patch(route('rba-perubahan.pendapatan.tolak-semua'))->assertRedirect();
        $this->assertDatabaseCount('rencana_pendapatans', 2);
        $this->login('Dinas', 'Dinas Kesehatan');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi-semua'), ['puskesmas' => 'Puskesmas B'])->assertRedirect();
        $this->assertSame('pending', $source->fresh()->status_perubahan);
        $this->patch(route('rba-perubahan.pendapatan.verifikasi-semua'), ['puskesmas' => 'Puskesmas A'])->assertRedirect();
        $this->assertSame('verified', $source->fresh()->status_perubahan);
        $this->assertSame('verified', $target->fresh()->status_perubahan);
    }

    public function test_rejecting_shift_removes_new_destination_and_restores_source(): void
    {
        $source = $this->plan();
        $target = $this->createShift($source);
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.tolak', $target->id))->assertRedirect();
        $source->refresh();
        $this->assertNull($source->pergeseran_id);
        $this->assertNull($source->jumlah_perubahan);
        $this->assertNull($source->jenis_pelayanan_perubahan);
        $this->assertNull($source->status_perubahan);
        $this->assertSame('60005.00', $source->jumlah);
        $this->assertDatabaseMissing('rencana_pendapatans', ['id' => $target->id]);
        $this->assertDatabaseCount('jenis_pelayanan_pendapatans', 1);
    }

    public function test_rejecting_existing_destination_restores_both_accounts_instead_of_deleting_them(): void
    {
        $source = $this->plan();
        $target = $this->destination('Puskesmas A', '4.2.01');
        $this->post(route('rba-perubahan.pendapatan.pergeseran-lintas-akun'), $this->shiftPayload($source, [
            'tipe_tujuan' => 'existing', 'tujuan_pendapatan_id' => $target->id,
            'tujuan_rincian' => json_encode([$this->service(['uraian' => 'Pendapatan Hibah'])]),
        ]))->assertSessionHasNoErrors();
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $source->id))->assertRedirect();
        $this->login('Dinas Kesehatan', 'Dinas Kesehatan');
        $this->patch(route('rba-perubahan.pendapatan.tolak-semua'))->assertRedirect();
        foreach ([$source, $target] as $record) {
            $this->assertNull($record->fresh()->pergeseran_id);
            $this->assertNull($record->fresh()->jumlah_perubahan);
            $this->assertSame('60005.00', $record->fresh()->jumlah);
        }
        $this->assertDatabaseCount('rencana_pendapatans', 2);
        $this->assertDatabaseCount('jenis_pelayanan_pendapatans', 2);
    }

    public function test_invalid_or_foreign_shift_is_rejected_without_partial_changes(): void
    {
        $source = $this->plan();
        $other = $this->destination('Puskesmas B');
        $url = route('rba-perubahan.pendapatan.pergeseran-lintas-akun');
        $this->post($url, $this->shiftPayload($source, [
            'tipe_tujuan' => 'existing', 'tujuan_pendapatan_id' => $other->id,
        ]))->assertNotFound();
        foreach ([
            ['tipe_tujuan' => 'existing', 'tujuan_pendapatan_id' => $source->id],
            ['tujuan_kode_rek' => $source->kode_rek],
            ['sumber_rincian' => 'invalid json'],
            ['tujuan_rincian' => json_encode([$this->service(['uraian' => 'Tidak ada'])])],
            ['sumber_rincian' => json_encode([$this->service(['harga_satuan' => '-1'])])],
        ] as $invalid) {
            $this->post($url, $this->shiftPayload($source, $invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('rencana_pendapatans', 2);
        $this->assertNull($source->fresh()->pergeseran_id);
        $this->assertNull($source->fresh()->jumlah_perubahan);
    }

    public function test_editing_shift_updates_both_sides_and_restarts_kepala_review(): void
    {
        $source = $this->plan();
        $target = $this->createShift($source, true);
        $group = $source->fresh()->pergeseran_id;
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $source->id))->assertRedirect();
        $this->login();
        $this->get(route('rba-perubahan.pendapatan.pergeseran-lintas-akun.create', ['edit_pergeseran' => $group]))
            ->assertOk()->assertSee('value="'.$group.'"', false);
        $this->put(route('rba-perubahan.pendapatan.update', $source->id), $this->payload($source))->assertForbidden();
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.zero-out', $source->id))->assertForbidden();
        $this->post(route('rba-perubahan.pendapatan.pergeseran-lintas-akun'), $this->shiftPayload($source, [
            'pergeseran_id' => $group, 'tipe_tujuan' => 'existing', 'tujuan_pendapatan_id' => $target->id,
            'sumber_rincian' => json_encode([$this->service(['volume_1' => 0])]),
            'tujuan_rincian' => json_encode([$this->service(['uraian' => 'Pendapatan Hibah'])]),
        ]))->assertSessionHasNoErrors();
        $this->assertSame($group, $source->fresh()->pergeseran_id);
        $this->assertSame('0.00', $source->fresh()->jumlah_perubahan);
        $this->assertSame('kepala puskesmas', $target->fresh()->verifikator_perubahan);
        $this->assertDatabaseCount('rencana_pendapatans', 2);
    }

    public function test_verified_shift_is_locked_and_guests_cannot_submit(): void
    {
        $source = $this->plan();
        $target = $this->createShift($source);
        $group = $source->fresh()->pergeseran_id;
        $this->login('Superuser');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $source->id))->assertRedirect();
        $this->assertSame('verified', $target->fresh()->status_perubahan);
        $this->get(route('rba-perubahan.pendapatan.pergeseran-lintas-akun.create', ['edit_pergeseran' => $group]))->assertForbidden();
        $this->post(route('rba-perubahan.pendapatan.pergeseran-lintas-akun'), $this->shiftPayload($source, [
            'pergeseran_id' => $group, 'tipe_tujuan' => 'existing', 'tujuan_pendapatan_id' => $target->id,
        ]))->assertForbidden();
        auth()->forgetGuards();
        $this->get(route('rba-perubahan.pendapatan.pergeseran-lintas-akun.create'))->assertRedirect(route('login'));
        $this->post(route('rba-perubahan.pendapatan.pergeseran-lintas-akun'), $this->shiftPayload($source))->assertRedirect(route('login'));
    }

    public function test_pdf_omits_new_destination_until_both_stages_finish(): void
    {
        $source = $this->plan();
        $target = $this->createShift($source, true);
        $this->login('Kepala Puskesmas');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $source->id))->assertRedirect();
        $fakePdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $fakePdf->shouldReceive('setPaper')->andReturnSelf();
        $fakePdf->shouldReceive('download')->andReturn(response('pdf'));
        $counts = [1, 2];
        Pdf::shouldReceive('loadView')->twice()->withArgs(function ($view, $data) use (&$counts) {
            $this->assertCount(array_shift($counts), $data['grouped']->flatten());
            $this->assertEquals(60005, $data['grandTotalJumlah']);
            return true;
        })->andReturn($fakePdf);
        $this->get(route('rba-perubahan.pendapatan.pdf'))->assertOk();
        $this->login('Dinas Kesehatan', 'Dinas Kesehatan');
        $this->patch(route('rba-perubahan.pendapatan.verifikasi', $target->id))->assertRedirect();
        $this->get(route('rba-perubahan.pendapatan.pdf', ['puskesmas' => 'Puskesmas A']))->assertOk();
        $this->assertSame([], $counts);
    }
}
