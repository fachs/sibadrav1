<?php

namespace Tests\Feature;

use App\Models\RencanaPembiayaan;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RbaPembiayaanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['rba.tahun_anggaran' => 2027, 'database.default' => 'pembiayaan_testing', 'database.connections.pembiayaan_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        $this->withoutVite();
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id(); $table->string('nama');
        });
        DB::table('users')->insert(['id' => 7]);
        DB::table('unit_bluds')->insert([['nama' => 'Puskesmas A'], ['nama' => 'Puskesmas B']]);
        foreach (['2026_09_02_065239_create_rencana_pembiayaans_table.php',
            '2026_09_21_063401_add_perubahan_columns_to_rencana_pembiayaans_table.php',
            '2026_10_21_000004_add_volume_and_satuan_to_rencana_pembiayaans.php',
            '2026_10_21_000006_add_tahun_anggaran_to_rencana_pembiayaans.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->login();
    }

    protected function tearDown(): void
    {
        DB::purge('pembiayaan_testing');
        parent::tearDown();
    }

    private function login(string $role = 'Pejabat Keuangan', string $upt = 'Puskesmas A'): void
    {
        $user = new User(['role' => $role, 'upt' => $upt]);
        $user->id = 7;
        $this->actingAs($user);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'kode_rek' => '6.1.01.01.01.0001', 'uraian' => 'Penggunaan SiLPA', 'harga_satuan' => '1000.25',
            'volume_1' => 2, 'satuan_1' => 'Paket', 'volume_2' => 3, 'satuan_2' => 'Unit',
            'volume_3' => 4, 'satuan_3' => 'Triwulan', 'volume_4' => 5, 'satuan_4' => 'Kegiatan',
        ], $overrides);
    }

    private function createPlan(): RencanaPembiayaan
    {
        $this->post(route('rba.pembiayaan.store'), $this->payload())->assertSessionHasNoErrors();

        return RencanaPembiayaan::firstOrFail();
    }

    public function test_both_pages_offer_exactly_the_seven_requested_codes_and_all_volume_fields(): void
    {
        $expected = ['6.1.01.01.01.0001', '6.1.02.01.01.0001', '6.1.03.02.01.0001', '6.1.03.03.01.0001',
            '6.2.01.01.01.0001', '6.2.02.01.01.0001', '6.2.03.01.01.0001'];
        foreach (['rba.pembiayaan', 'rba-perubahan.pembiayaan'] as $route) {
            $response = $this->get(route($route))->assertOk()->assertSee('Pemberian Pinjaman-Dana Bergulir kepada Kreditur')
                ->assertSee('Hasil Penjualan Kekayaan Daerah yang Dipisahkan pada BLUD')->assertSee('rba-pembiayaan.js');
            preg_match_all('/<option value="(6\.[^"]+)"/', $response->getContent(), $matches);
            $this->assertSame($expected, $matches[1]);
            for ($i = 1; $i <= 4; $i++) {
                $response->assertSee('name="volume_'.$i.'"', false)->assertSee('name="satuan_'.$i.'"', false);
            }
            $response->assertSee('name="harga_satuan"', false)->assertDontSee('value="6.1.01"', false);
        }
    }

    public function test_create_and_update_compute_total_from_price_and_four_volumes_ignoring_forged_total(): void
    {
        $this->post(route('rba.pembiayaan.store'), $this->payload(['jumlah' => 1, 'nama_blud' => 'Puskesmas B', 'user_id' => 999]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $plan = RencanaPembiayaan::firstOrFail();
        $this->assertSame('120030.00', $plan->jumlah);
        $this->assertSame('Puskesmas A', $plan->nama_blud);
        $this->assertSame(7, $plan->user_id);
        $this->assertSame('5.0000', $plan->volume_4);
        $this->assertSame('Kegiatan', $plan->satuan_4);
        $this->get(route('rba.pembiayaan'))->assertOk()->assertSee('120.030,00')->assertSee('Volume 4 / Satuan 4');
        $this->put(route('rba.pembiayaan.update', $plan->id), $this->payload(['volume_1' => 3, 'jumlah' => 5]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('180045.00', $plan->fresh()->jumlah);
    }

    public function test_optional_factors_default_to_one_and_decimal_rounding_matches_income(): void
    {
        $this->post(route('rba.pembiayaan.store'), $this->payload(['harga_satuan' => '0.05', 'volume_1' => '6.7',
            'volume_2' => null, 'volume_3' => null, 'volume_4' => null, 'satuan_2' => null, 'satuan_3' => null, 'satuan_4' => null]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $plan = RencanaPembiayaan::firstOrFail();
        $this->assertSame('0.34', $plan->jumlah);
        $this->assertSame('1.0000', $plan->volume_4);
    }

    public function test_invalid_accounts_negative_values_precision_and_overflow_are_rejected(): void
    {
        foreach ([['kode_rek' => '6.1.01'], ['kode_rek' => '6.9.99'], ['volume_4' => -1], ['volume_1' => '1.00001'],
            ['harga_satuan' => '1.001'], ['satuan_1' => ''], ['harga_satuan' => '9999999999999.99']] as $invalid) {
            $this->postJson(route('rba.pembiayaan.store'), $this->payload($invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('rencana_pembiayaans', 0);
    }

    public function test_proposal_keeps_original_fields_and_verification_uses_proposed_details(): void
    {
        $plan = $this->createPlan();
        $data = $this->payload(['kode_rek' => '6.2.01.01.01.0001', 'volume_1' => 3, 'uraian' => 'Investasi', 'jumlah' => 1]);
        $this->put(route('rba-perubahan.pembiayaan.update', $plan->id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertSame('120030.00', $plan->jumlah);
        $this->assertSame('2.0000', $plan->volume_1);
        $this->assertSame('6.1.01.01.01.0001', $plan->kode_rek);
        $this->assertSame('180045.00', $plan->jumlah_perubahan);
        $this->assertSame('3.0000', $plan->volume_1_perubahan);
        $this->assertSame('6.2.01.01.01.0001', $plan->kode_rek_perubahan);
        $this->get(route('rba-perubahan.pembiayaan'))->assertOk()->assertSee('120.030,00')->assertSee('180.045,00');
        $this->patch(route('rba-perubahan.pembiayaan.verifikasi', $plan->id))->assertForbidden();
        $this->login('Superuser');
        $this->patch(route('rba-perubahan.pembiayaan.verifikasi', $plan->id))->assertRedirect();
        $this->assertSame('verified', $plan->fresh()->status_perubahan);
        $this->put(route('rba-perubahan.pembiayaan.update', $plan->id), $data)->assertForbidden();
        $this->patch(route('rba-perubahan.pembiayaan.zero-out', $plan->id))->assertForbidden();
    }

    public function test_zero_out_keeps_volume_calculation_consistent_and_original_amount_intact(): void
    {
        $plan = $this->createPlan();
        $this->patch(route('rba-perubahan.pembiayaan.zero-out', $plan->id))->assertRedirect();
        $plan->refresh();
        $this->assertSame('120030.00', $plan->jumlah);
        $this->assertSame('0.00', $plan->jumlah_perubahan);
        $this->assertSame('0.0000', $plan->volume_1_perubahan);
        $this->assertSame('0.00', RencanaPembiayaan::hitungJumlah($plan->rincian(true)));
        $this->assertSame('pending', $plan->status_perubahan);
    }

    public function test_other_units_cannot_update_delete_or_submit_changes(): void
    {
        $plan = $this->createPlan();
        $this->login('Pejabat Keuangan', 'Puskesmas B');
        $this->get(route('rba.pembiayaan'))->assertOk()->assertDontSee('120.030,00');
        $this->put(route('rba.pembiayaan.update', $plan->id), $this->payload())->assertNotFound();
        $this->delete(route('rba.pembiayaan.destroy', $plan->id))->assertNotFound();
        $this->put(route('rba-perubahan.pembiayaan.update', $plan->id), $this->payload())->assertNotFound();
        $this->patch(route('rba-perubahan.pembiayaan.zero-out', $plan->id))->assertNotFound();
        $this->assertDatabaseCount('rencana_pembiayaans', 1);
    }

    public function test_rejected_details_can_be_revised_and_bulk_review_obeys_blud_filter(): void
    {
        $plan = $this->createPlan();
        $this->put(route('rba-perubahan.pembiayaan.update', $plan->id), $this->payload(['volume_2' => 2]))->assertRedirect();
        $this->login('Superuser');
        $this->patch(route('rba-perubahan.pembiayaan.tolak', $plan->id))->assertRedirect();
        $this->assertSame('rejected', $plan->fresh()->status_perubahan);
        $this->login();
        $this->put(route('rba-perubahan.pembiayaan.update', $plan->id), $this->payload(['volume_2' => 4]))->assertRedirect();
        $other = $plan->fresh()->replicate();
        $other->nama_blud = 'Puskesmas B';
        $other->save();
        $this->login('Superuser');
        $this->patch(route('rba-perubahan.pembiayaan.verifikasi-semua'), ['puskesmas' => 'Puskesmas A'])->assertRedirect();
        $this->assertSame('verified', $plan->fresh()->status_perubahan);
        $this->assertSame('pending', $other->fresh()->status_perubahan);
    }

    public function test_pdf_uses_original_summary_layout_and_only_verified_proposals(): void
    {
        $plan = $this->createPlan();
        $this->put(route('rba-perubahan.pembiayaan.update', $plan->id), $this->payload(['volume_1' => 3]))->assertRedirect();
        foreach ([false, true] as $verified) {
            if ($verified) $plan->update(['status_perubahan' => 'verified']);
            $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($verified) {
                $item = $data['grouped']->flatten()->first();
                $this->assertSame($verified ? '180045.00' : '120030.00', $item->rincian(true)['jumlah']);
                $this->assertSame($verified ? '3.0000' : '2.0000', $item->rincian(true)['volume_1']);
                $html = view($view, $data)->render();
                $this->assertStringNotContainsString('Volume 4 / Satuan 4', $html);
                $this->assertStringNotContainsString('Harga satuan:', $html);
                $this->assertStringContainsString($verified ? '180.045,00' : '120.030,00', $html);
                $this->assertStringContainsString('01.01.0001', $html);

                return true;
            })->andReturn($pdf);
            $pdf->shouldReceive('setPaper')->andReturnSelf();
            $pdf->shouldReceive('download')->once()->andReturn(response('pdf'));
            $this->get(route('rba-perubahan.pembiayaan.pdf'))->assertOk();
        }
    }

    public function test_migration_preserves_existing_original_and_proposed_amounts(): void
    {
        $migration = require database_path('migrations/2026_10_21_000004_add_volume_and_satuan_to_rencana_pembiayaans.php');
        $migration->down();
        $id = DB::table('rencana_pembiayaans')->insertGetId(['user_id' => 7, 'nama_blud' => 'Puskesmas A',
            'kode_rek' => '6.1.01', 'uraian' => 'Saldo lama', 'jumlah' => '4566.25', 'jumlah_perubahan' => '4000.50', 'status_perubahan' => 'pending']);
        $migration->up();
        $plan = RencanaPembiayaan::findOrFail($id);
        $this->assertSame('6.1.01.01.01.0001', $plan->kode_rek);
        $this->assertSame('4566.25', $plan->jumlah);
        $this->assertSame('4000.50', $plan->jumlah_perubahan);
        $this->assertSame('4566.25', RencanaPembiayaan::hitungJumlah($plan->rincian()));
        $this->assertSame('4000.50', RencanaPembiayaan::hitungJumlah($plan->rincian(true)));
        $this->assertSame('pending', $plan->status_perubahan);
    }

    public function test_both_pages_filter_year_and_preserve_year_in_pdf_and_action_links(): void
    {
        $current = $this->createPlan();
        $historical = $current->replicate();
        $historical->fill(['tahun_anggaran' => 2026, 'uraian' => 'Pembiayaan tahun 2026'])->save();
        $foreign = $historical->replicate();
        $foreign->fill(['nama_blud' => 'Puskesmas B', 'uraian' => 'Pembiayaan unit lain'])->save();
        foreach (['rba.pembiayaan', 'rba-perubahan.pembiayaan'] as $route) {
            $this->get(route($route))->assertOk()->assertViewHas('tahunRba', 2027)
                ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->pluck('id')->all() === [$current->id]);
            $this->get(route($route, ['tahun' => 2026]))->assertOk()->assertSee('Tahun Anggaran')
                ->assertSee('Pembiayaan tahun 2026')->assertDontSee('Pembiayaan unit lain')
                ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->pluck('id')->all() === [$historical->id])
                ->assertSee(route($route.'.pdf', ['tahun' => 2026]))
                ->assertSee(route($route.'.update', ['id' => $historical->id, 'tahun' => 2026]));
        }
        $this->login('Superuser');
        $this->get(route('rba.pembiayaan', ['tahun' => 2026, 'puskesmas' => 'Puskesmas B']))->assertOk()
            ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->pluck('id')->all() === [$foreign->id])
            ->assertSee('name="tahun" value="2026"', false)
            ->assertSee(route('rba.pembiayaan.pdf', ['tahun' => 2026, 'puskesmas' => 'Puskesmas B']));
    }

    public function test_create_update_and_delete_keep_the_selected_year_without_moving_records(): void
    {
        $this->post(route('rba.pembiayaan.store'), $this->payload(['tahun' => 2026, 'tahun_anggaran' => 2027]))
            ->assertRedirect(route('rba.pembiayaan', ['tahun' => 2026]))->assertSessionHasNoErrors();
        $plan = RencanaPembiayaan::firstOrFail();
        $this->assertSame(2026, $plan->tahun_anggaran);
        $this->put(route('rba.pembiayaan.update', $plan->id), $this->payload(['tahun' => 2027]))->assertNotFound();
        $this->delete(route('rba.pembiayaan.destroy', $plan->id), ['tahun' => 2027])->assertNotFound();
        $this->put(route('rba.pembiayaan.update', $plan->id), $this->payload(['tahun' => 2026, 'tahun_anggaran' => 2027, 'uraian' => 'Revisi tahun 2026']))
            ->assertRedirect(route('rba.pembiayaan', ['tahun' => 2026]))->assertSessionHasNoErrors();
        $this->assertSame(2026, $plan->fresh()->tahun_anggaran);
        $this->assertSame('Revisi tahun 2026', $plan->fresh()->uraian);
        $this->delete(route('rba.pembiayaan.destroy', $plan->id), ['tahun' => 2026])
            ->assertRedirect(route('rba.pembiayaan', ['tahun' => 2026]));
        $this->assertDatabaseCount('rencana_pembiayaans', 0);
    }

    public function test_bulk_verification_only_affects_pending_proposals_in_selected_year_and_unit(): void
    {
        $current = $this->createPlan();
        $current->update(['status_perubahan' => 'pending']);
        $historical = $current->replicate();
        $historical->fill(['tahun_anggaran' => 2026])->save();
        $foreign = $historical->replicate();
        $foreign->fill(['nama_blud' => 'Puskesmas B'])->save();
        $this->login('Superuser');
        $filters = ['tahun' => 2026, 'puskesmas' => 'Puskesmas A'];
        $this->patch(route('rba-perubahan.pembiayaan.verifikasi-semua'), $filters)
            ->assertRedirect(route('rba-perubahan.pembiayaan', $filters));
        $this->assertSame('verified', $historical->fresh()->status_perubahan);
        $this->assertSame('pending', $current->fresh()->status_perubahan);
        $this->assertSame('pending', $foreign->fresh()->status_perubahan);
        $historical->refresh()->update(['status_perubahan' => 'pending']);
        $this->patch(route('rba-perubahan.pembiayaan.tolak-semua'), $filters)->assertRedirect();
        $this->assertSame('rejected', $historical->fresh()->status_perubahan);
        $this->assertSame('pending', $current->fresh()->status_perubahan);
        $this->assertSame('pending', $foreign->fresh()->status_perubahan);
    }

    public function test_single_proposal_and_review_actions_obey_selected_year(): void
    {
        $plan = $this->createPlan();
        $plan->update(['tahun_anggaran' => 2026]);
        $this->put(route('rba-perubahan.pembiayaan.update', $plan->id), $this->payload(['tahun' => 2027]))->assertNotFound();
        $this->patch(route('rba-perubahan.pembiayaan.zero-out', $plan->id), ['tahun' => 2027])->assertNotFound();
        $this->put(route('rba-perubahan.pembiayaan.update', $plan->id), $this->payload(['tahun' => 2026, 'volume_1' => 3]))
            ->assertRedirect(route('rba-perubahan.pembiayaan', ['tahun' => 2026]));
        $this->assertSame(2026, $plan->fresh()->tahun_anggaran);
        $this->assertSame('180045.00', $plan->fresh()->jumlah_perubahan);
        $this->login('Superuser');
        $this->patch(route('rba-perubahan.pembiayaan.verifikasi', $plan->id), ['tahun' => 2027])->assertNotFound();
        $this->patch(route('rba-perubahan.pembiayaan.verifikasi', $plan->id), ['tahun' => 2026])
            ->assertRedirect(route('rba-perubahan.pembiayaan', ['tahun' => 2026]));
        $this->assertSame('verified', $plan->fresh()->status_perubahan);
    }

    public function test_both_pdf_exports_only_include_selected_year_and_print_it_in_header(): void
    {
        $current = $this->createPlan();
        $historical = $current->replicate();
        $historical->fill(['tahun_anggaran' => 2026])->save();
        foreach (['rba.pembiayaan.pdf', 'rba-perubahan.pembiayaan.pdf'] as $route) {
            $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($historical) {
                $this->assertSame(2026, $data['tahunRba']);
                $this->assertSame([$historical->id], $data['grouped']->flatten()->pluck('id')->all());
                $html = view($view, $data)->render();
                $this->assertStringContainsString('TAHUN ANGGARAN 2026', $html);
                $this->assertStringNotContainsString('TAHUN ANGGARAN 2027', $html);
                $this->assertStringNotContainsString('Volume 1 / Satuan 1', $html);
                $this->assertStringNotContainsString('Harga satuan:', $html);
                return true;
            })->andReturn($pdf);
            $pdf->shouldReceive('setPaper')->andReturnSelf();
            $pdf->shouldReceive('download')->once()->with(\Mockery::pattern('/-2026-/'))->andReturn(response('pdf'));
            $this->get(route($route, ['tahun' => 2026]))->assertOk();
        }
    }

    public function test_year_migration_preserves_existing_values_and_assigns_the_default_year(): void
    {
        $migration = require database_path('migrations/2026_10_21_000006_add_tahun_anggaran_to_rencana_pembiayaans.php');
        $migration->down();
        $id = DB::table('rencana_pembiayaans')->insertGetId(['user_id' => 7, 'nama_blud' => 'Puskesmas A',
            'kode_rek' => '6.1.01.01.01.0001', 'uraian' => 'Data lama', 'jumlah' => '50000000', 'harga_satuan' => '50000000',
            'jumlah_perubahan' => '55000000', 'status_perubahan' => 'pending']);
        $migration->up();
        $plan = RencanaPembiayaan::findOrFail($id);
        $this->assertSame(2027, $plan->tahun_anggaran);
        $this->assertSame('50000000.00', $plan->jumlah);
        $this->assertSame('55000000.00', $plan->jumlah_perubahan);
        $this->assertSame('pending', $plan->status_perubahan);
    }

    public function test_invalid_years_are_rejected_before_reading_or_creating_financing(): void
    {
        foreach (['abc', 1999, 2101, '2026.5', ['2026']] as $year) {
            foreach (['rba.pembiayaan', 'rba-perubahan.pembiayaan', 'rba.pembiayaan.pdf', 'rba-perubahan.pembiayaan.pdf'] as $route) {
                $this->getJson(route($route, ['tahun' => $year]))->assertUnprocessable()->assertJsonValidationErrors('tahun');
            }
            $this->postJson(route('rba.pembiayaan.store'), $this->payload(['tahun' => $year]))
                ->assertUnprocessable()->assertJsonValidationErrors('tahun');
        }
        $this->assertDatabaseCount('rencana_pembiayaans', 0);
    }

    public function test_dummy_seeder_creates_seven_2026_records_only_for_limusnunggal_without_duplicates(): void
    {
        $existing = $this->createPlan();
        Schema::table('users', function (Blueprint $table) {
            $table->string('upt')->nullable(); $table->string('role')->nullable(); $table->json('roles')->nullable();
        });
        DB::table('unit_bluds')->where('id', 1)->update(['nama' => 'Puskesmas Limusnunggal']);
        DB::table('users')->where('id', 7)->update(['upt' => 'Puskesmas Limusnunggal', 'role' => 'PPTK', 'roles' => json_encode(['PPTK', 'Pejabat Keuangan'])]);
        $this->seed(\Database\Seeders\RbaPembiayaanDummy2026Seeder::class);
        $this->seed(\Database\Seeders\RbaPembiayaanDummy2026Seeder::class);
        $rows = RencanaPembiayaan::where('tahun_anggaran', 2026)->get();
        $this->assertCount(7, $rows);
        $this->assertSame(['Puskesmas Limusnunggal'], $rows->pluck('nama_blud')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(\App\Support\ReferensiPembiayaan::codes(), $rows->pluck('kode_rek')->all());
        $this->assertEquals(125000000, $rows->filter(fn ($row) => str_starts_with($row->kode_rek, '6.1'))->sum('jumlah'));
        $this->assertEquals(27000000, $rows->filter(fn ($row) => str_starts_with($row->kode_rek, '6.2'))->sum('jumlah'));
        foreach ($rows as $row) $this->assertSame($row->jumlah, RencanaPembiayaan::hitungJumlah($row->rincian()));
        $this->assertSame(2027, $existing->fresh()->tahun_anggaran);
        $this->assertDatabaseCount('rencana_pembiayaans', 8);
    }
}
