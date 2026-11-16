<?php

namespace Tests\Feature;

use App\Models\RencanaKebutuhan;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class RenbutPerUnitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['rba.tahun_anggaran' => 2027, 'database.default' => 'renbut_rekap_testing', 'database.connections.renbut_rekap_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->withoutVite();
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id(); $table->string('nama');
        });
        Schema::create('akuns', function (Blueprint $table) {
            $table->id(); $table->string('kode_rek'); $table->string('uraian');
        });
        Schema::create('rencana_kebutuhans', function (Blueprint $table) {
            $table->id();
            foreach (['nama_blud', 'nama_unit', 'uraian', 'tahun_anggaran', 'program', 'kegiatan',
                'subkegiatan', 'satuan', 'ruang', 'keterangan', 'spesifikasi', 'sumber_dana'] as $field) {
                $table->string($field)->nullable();
            }
            $table->unsignedBigInteger('akun_id')->nullable();
            foreach (['jumlah', 'harga_satuan', 'jumlah_harga'] as $field) {
                $table->decimal($field, 15, 2)->default(0);
            }
            $table->timestamps(); $table->softDeletes();
        });
        DB::table('unit_bluds')->insert([['nama' => 'Puskesmas A'], ['nama' => 'Puskesmas B']]);
        $this->login();
    }

    protected function tearDown(): void
    {
        DB::purge('renbut_rekap_testing');
        parent::tearDown();
    }

    private function login(string $role = 'Bendahara Pengeluaran', string $upt = 'Puskesmas A'): void
    {
        $user = new User(['role' => $role, 'upt' => $upt]);
        $user->id = 7;
        $this->actingAs($user);
    }

    private function record(array $overrides = []): RencanaKebutuhan
    {
        return RencanaKebutuhan::create(array_replace([
            'nama_blud' => 'Puskesmas A', 'nama_unit' => 'Klaster 2', 'uraian' => 'Kertas pemeriksaan',
            'tahun_anggaran' => '2027',
            'jumlah' => 2, 'harga_satuan' => 1250.25, 'jumlah_harga' => 2500.50,
            'satuan' => 'Rim', 'spesifikasi' => 'A4', 'ruang' => 'Pelayanan',
        ], $overrides));
    }

    public function test_recap_shows_all_units_in_own_blud_without_matching_units_to_user_role(): void
    {
        $first = $this->record();
        $second = $this->record(['nama_unit' => 'Klaster 3', 'uraian' => 'Alat pelayanan']);
        $this->record(['nama_blud' => 'Puskesmas B', 'uraian' => 'Data BLUD lain']);
        $this->record(['uraian' => 'Kebutuhan terhapus'])->delete();
        foreach (['Bendahara Pengeluaran', 'Bendahara Penerimaan', 'Pejabat Keuangan', 'Klaster 2', 'Kepala Puskesmas'] as $role) {
            $this->login($role);
            $this->get('/renbut-per-unit?puskesmas=Puskesmas%20B')->assertOk()
                ->assertSee('Kertas pemeriksaan')->assertSee('Alat pelayanan')->assertSee('5.001')
                ->assertDontSee('Data BLUD lain')->assertDontSee('Kebutuhan terhapus')
                ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->pluck('id')->all() === [$first->id, $second->id]);
        }
    }

    public function test_superuser_can_view_all_bluds_and_filter_a_single_blud(): void
    {
        $this->record();
        $other = $this->record(['nama_blud' => 'Puskesmas B', 'uraian' => 'Kebutuhan BLUD B']);
        $this->login('Superuser');
        $this->get('/renbut-per-unit')->assertOk()->assertSee('Pilih BLUD')
            ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->count() === 2);
        $this->get('/renbut-per-unit?puskesmas=Puskesmas%20B')->assertOk()->assertDontSee('Kertas pemeriksaan')
            ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->pluck('id')->all() === [$other->id]);
    }

    public function test_empty_recap_explains_that_no_data_is_available(): void
    {
        $this->get('/renbut-per-unit')->assertOk()->assertSee('Belum ada rencana kebutuhan')
            ->assertDontSee('Unduh Semua (PDF)')->assertViewHas('grouped', fn ($groups) => $groups->isEmpty());
    }

    public function test_group_details_have_valid_unique_targets_and_no_missing_item_expanders(): void
    {
        $this->record(['nama_unit' => 'Tim A/B']);
        $this->record(['nama_unit' => 'Tim A B']);
        $this->get('/renbut-per-unit')->assertOk()->assertSee('id="rekap-unit-0"', false)
            ->assertSee('id="rekap-unit-1"', false)->assertSee('collapse show')
            ->assertDontSee('toggle-detail')->assertDontSee('assets/js/pages/form-advanced.init.js');
    }

    public function test_pdf_export_uses_the_same_blud_scope_and_selected_unit_as_recap(): void
    {
        $expected = $this->record(['nama_unit' => 'Klaster 3']);
        $this->record(['uraian' => 'Unit lain']);
        $this->record(['nama_blud' => 'Puskesmas B', 'nama_unit' => 'Klaster 3']);
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('A4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('PDF rekap'));
        Pdf::shouldReceive('loadView')->once()->with('pdf.rincian-objek', Mockery::on(fn ($data) =>
            $data['uptName'] === 'Puskesmas A' && $data['namaUnit'] === 'Klaster 3'
            && $data['grouped']->flatten()->pluck('id')->all() === [$expected->id]
        ))->andReturn($pdf);
        $this->get('/rincian-objek/pdf?nama_unit=Klaster%203&puskesmas=Puskesmas%20B')->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        auth()->forgetGuards();
        $this->get('/renbut-per-unit')->assertRedirect(route('login'));
    }

    public function test_recap_defaults_to_the_configured_budget_year_and_filters_selected_year(): void
    {
        $current = $this->record();
        $historical = $this->record(['tahun_anggaran' => '2026', 'uraian' => 'Kebutuhan tahun sebelumnya', 'jumlah_harga' => 9876]);
        $this->record(['tahun_anggaran' => null, 'uraian' => 'Tahun belum ditentukan']);
        $this->record(['tahun_anggaran' => '2026', 'nama_blud' => 'Puskesmas B', 'uraian' => 'BLUD lain 2026']);
        $this->get(route('renbut-per-unit'))->assertOk()->assertSee('Tahun Anggaran')
            ->assertSee('id="rba-tahun"', false)->assertSee('<option value="2027" selected', false)->assertSee('2.501')
            ->assertDontSee('Kebutuhan tahun sebelumnya')->assertDontSee('Tahun belum ditentukan')
            ->assertViewHas('tahunRba', 2027)
            ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->pluck('id')->all() === [$current->id]);
        $response = $this->get(route('renbut-per-unit', ['tahun' => 2026, 'puskesmas' => 'Puskesmas B']))
            ->assertOk()->assertSee('<option value="2026" selected', false)->assertSee('9.876')
            ->assertDontSee('Kertas pemeriksaan')->assertDontSee('BLUD lain 2026')
            ->assertViewHas('tahunRba', 2026)
            ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->pluck('id')->all() === [$historical->id]);
        $response->assertSee(route('rincian.pdf', ['tahun' => 2026]))
            ->assertSee(route('rincian.pdf', ['tahun' => 2026, 'nama_unit' => 'Klaster 2']));
    }

    public function test_global_year_options_only_allow_2026_and_2027_even_when_historical_data_exists(): void
    {
        $this->record(['tahun_anggaran' => '2010']);
        $this->record(['tahun_anggaran' => '2001', 'nama_blud' => 'Puskesmas B']);
        $this->record(['tahun_anggaran' => '2002'])->delete();
        $this->get(route('renbut-per-unit'))->assertOk()->assertSee('value="2027"', false)->assertSee('value="2026"', false)
            ->assertDontSee('value="2010"', false)->assertDontSee('value="2001"', false);
        $this->getJson(route('renbut-per-unit', ['tahun' => 2010]))->assertUnprocessable()->assertJsonValidationErrors('tahun');
        $this->login('Superuser');
        $this->getJson(route('renbut-per-unit', ['tahun' => 2001]))->assertUnprocessable()->assertJsonValidationErrors('tahun');
    }

    public function test_superuser_can_combine_budget_year_and_blud_filters_and_export_links(): void
    {
        $this->record(['tahun_anggaran' => '2026']);
        $this->record(['nama_blud' => 'Puskesmas B']);
        $expected = $this->record(['nama_blud' => 'Puskesmas B', 'tahun_anggaran' => '2026', 'uraian' => 'BLUD B 2026']);
        $this->login('Superuser');
        $this->get(route('renbut-per-unit', ['tahun' => 2026, 'puskesmas' => 'Puskesmas B']))->assertOk()
            ->assertSee('BLUD B 2026')->assertDontSee('Kertas pemeriksaan')
            ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->pluck('id')->all() === [$expected->id])
            ->assertSee('name="tahun"', false)->assertSee('name="puskesmas"', false)
            ->assertSee(route('rincian.pdf', ['tahun' => 2026, 'puskesmas' => 'Puskesmas B']))
            ->assertSee(route('rincian.pdf', ['tahun' => 2026, 'puskesmas' => 'Puskesmas B', 'nama_unit' => 'Klaster 2']));
    }

    public function test_pdf_data_header_and_filename_follow_selected_year_for_all_units_and_single_unit(): void
    {
        $expected = $this->record(['tahun_anggaran' => '2026']);
        $this->record(['uraian' => 'Tahun berbeda']);
        $this->record(['tahun_anggaran' => '2026', 'nama_blud' => 'Puskesmas B', 'uraian' => 'BLUD berbeda']);
        foreach ([null, 'Klaster 2'] as $unit) {
            $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            $pdf->shouldReceive('setPaper')->once()->with('A4', 'landscape')->andReturnSelf();
            $filename = 'Renbut-Puskesmas A-2026'.($unit ? '-klaster-2' : '').'.pdf';
            $pdf->shouldReceive('download')->once()->with($filename)->andReturn(response('PDF rekap'));
            Pdf::shouldReceive('loadView')->once()->with('pdf.rincian-objek', Mockery::on(function ($data) use ($expected, $unit) {
                $this->assertSame(2026, $data['tahunRba']);
                $this->assertSame($unit, $data['namaUnit']);
                $this->assertSame([$expected->id], $data['grouped']->flatten()->pluck('id')->all());
                $html = view('pdf.rincian-objek', $data)->render();
                $this->assertStringContainsString('Tahun Anggaran 2026', $html);
                $this->assertStringNotContainsString('Tahun Anggaran 2027', $html);
                return true;
            }))->andReturn($pdf);
            $this->get(route('rincian.pdf', array_filter(['tahun' => 2026, 'nama_unit' => $unit])))->assertOk();
        }
    }

    public function test_invalid_budget_years_are_rejected_on_page_and_pdf(): void
    {
        foreach (['abc', 1999, 2101, '2026.5', ['2026']] as $year) {
            foreach (['renbut-per-unit', 'rincian.pdf'] as $route) {
                $this->get(route($route, ['tahun' => $year]))->assertSessionHasErrors('tahun');
            }
        }
    }
}
