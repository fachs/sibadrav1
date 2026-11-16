<?php

namespace Tests\Feature;

use App\Models\RencanaKebutuhan;
use App\Models\RencanaPembiayaan;
use App\Models\RencanaPendapatan;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class RbaRingkasanTahunTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['rba.tahun_anggaran' => 2027, 'database.default' => 'ringkasan_year_testing',
            'database.connections.ringkasan_year_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $this->withoutVite();
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id(); $table->string('nama');
        });
        Schema::create('akuns', function (Blueprint $table) {
            $table->id(); $table->string('kode_rek'); $table->string('uraian');
        });
        Schema::create('rencana_pendapatans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedSmallInteger('tahun_anggaran');
            foreach (['nama_blud', 'kode_rek', 'uraian', 'uraian_perubahan', 'status_perubahan',
                'verifikator_perubahan', 'rekening_awal', 'rekening_akhir'] as $field) $table->string($field)->nullable();
            $table->decimal('jumlah', 15, 2); $table->decimal('jumlah_perubahan', 15, 2)->nullable();
            $table->boolean('is_hasil_perubahan')->default(false);
            $table->json('jenis_pelayanan_perubahan')->nullable(); $table->unsignedBigInteger('pergeseran_id')->nullable();
            $table->timestamps();
        });
        Schema::create('jenis_pelayanan_pendapatans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('rencana_pendapatan_id');
        });
        Schema::create('rencana_kebutuhans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('akun_id'); $table->string('nama_blud');
            $table->string('tahun_anggaran'); $table->string('uraian');
            $table->decimal('jumlah_harga', 15, 2); $table->decimal('jumlah_harga_perubahan', 15, 2)->nullable();
            $table->string('status_perubahan')->nullable(); $table->boolean('is_hasil_perubahan')->default(false);
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('users', fn (Blueprint $table) => $table->id());
        DB::table('users')->insert(['id' => 7]);
        foreach (['2026_09_02_065239_create_rencana_pembiayaans_table.php',
            '2026_09_21_063401_add_perubahan_columns_to_rencana_pembiayaans_table.php',
            '2026_10_21_000004_add_volume_and_satuan_to_rencana_pembiayaans.php',
            '2026_10_21_000006_add_tahun_anggaran_to_rencana_pembiayaans.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        DB::table('unit_bluds')->insert([['nama' => 'Puskesmas A'], ['nama' => 'Puskesmas B']]);
        DB::table('akuns')->insert(['id' => 1, 'kode_rek' => '5.1.02.01.001.00024', 'uraian' => 'Alat tulis kantor']);
        $this->budget(2026, 'Puskesmas A', 1);
        $this->budget(2027, 'Puskesmas A', 10);
        $this->budget(2026, 'Puskesmas B', 100);
        $this->login();
    }

    protected function tearDown(): void
    {
        DB::purge('ringkasan_year_testing');
        parent::tearDown();
    }

    private function login(string $role = 'Pejabat Keuangan'): void
    {
        $user = new User(['role' => $role, 'roles' => [$role], 'upt' => 'Puskesmas A']);
        $user->id = 7;
        $this->actingAs($user);
    }

    private function budget(int $year, string $unit, int $factor): void
    {
        RencanaPendapatan::create(['user_id' => 7, 'tahun_anggaran' => $year, 'nama_blud' => $unit,
            'kode_rek' => '4.1.02.01.01.0001', 'uraian' => 'Pendapatan '.$unit.' '.$year,
            'jumlah' => 1000 * $factor, 'jumlah_perubahan' => 1200 * $factor, 'status_perubahan' => 'verified']);
        RencanaKebutuhan::create(['tahun_anggaran' => (string) $year, 'nama_blud' => $unit, 'akun_id' => 1,
            'uraian' => 'Belanja '.$unit.' '.$year, 'jumlah_harga' => 400 * $factor,
            'jumlah_harga_perubahan' => 500 * $factor, 'status_perubahan' => 'verified']);
        foreach ([['6.1.01.01.01.0001', 300, 350], ['6.2.01.01.01.0001', 100, 120]] as [$code, $before, $after]) {
            RencanaPembiayaan::create(['user_id' => 7, 'tahun_anggaran' => $year, 'nama_blud' => $unit,
                'kode_rek' => $code, 'uraian' => 'Pembiayaan '.$unit.' '.$year,
                'jumlah' => $before * $factor, 'jumlah_perubahan' => $after * $factor, 'status_perubahan' => 'verified']);
        }
    }

    public function test_both_summaries_offer_year_filter_and_use_the_same_year_in_all_sections(): void
    {
        foreach (['rba.ringkasan' => [1000, 400, 400, '800,00'],
            'rba-perubahan.ringkasan' => [1200, 500, 470, '930,00']] as $route => [$income, $expense, $financing, $silpa]) {
            foreach ([2027 => 10, 2026 => 1] as $year => $factor) {
                $filters = $year === 2027 ? [] : ['tahun' => $year];
                $this->get(route($route, $filters))->assertOk()->assertViewHas('tahun', $year)
                    ->assertSee('id="rba-tahun"', false)->assertSee('value="'.$year.'" selected', false)
                    ->assertViewHas('pendapatan', fn ($data) => $data['grandTotalJumlah'] == $income * $factor)
                    ->assertViewHas('belanja', fn ($data) => $data['grandTotalJumlah'] == $expense * $factor)
                    ->assertViewHas('pembiayaan', fn ($data) => $data['grandTotalJumlah'] == $financing * $factor)
                    ->assertSee(route($route.'.pdf', ['tahun' => $year]));
            }
            $this->get(route($route, ['tahun' => 2026]))->assertSee('Rp '.$silpa);
        }
    }

    public function test_superuser_filters_and_reset_preserve_selected_year(): void
    {
        $this->login('Superuser');
        $filters = ['tahun' => 2026, 'puskesmas' => 'Puskesmas B'];
        foreach (['rba.ringkasan', 'rba-perubahan.ringkasan'] as $route) {
            $this->get(route($route, $filters))->assertOk()->assertViewHas('tahun', 2026)
                ->assertViewHas('pendapatan', fn ($data) => $data['grouped']->flatten()->pluck('nama_blud')->unique()->all() === ['Puskesmas B'])
                ->assertViewHas('belanja', fn ($data) => $data['grouped']->flatten()->pluck('nama_blud')->unique()->all() === ['Puskesmas B'])
                ->assertViewHas('pembiayaan', fn ($data) => $data['grouped']->flatten()->pluck('nama_blud')->unique()->all() === ['Puskesmas B'])
                ->assertSee('name="tahun" value="2026"', false)
                ->assertSee('value="Puskesmas B" selected', false)
                ->assertSee(route($route.'.pdf', $filters))->assertSee(route($route, ['tahun' => 2026]));
        }
    }

    public function test_unit_users_cannot_see_other_blud_data_by_forging_the_filter(): void
    {
        foreach (['rba.ringkasan', 'rba-perubahan.ringkasan'] as $route) {
            $this->get(route($route, ['tahun' => 2026, 'puskesmas' => 'Puskesmas B']))->assertOk()
                ->assertViewHas('pendapatan', fn ($data) => $data['grouped']->flatten()->pluck('nama_blud')->unique()->all() === ['Puskesmas A'])
                ->assertViewHas('belanja', fn ($data) => $data['grouped']->flatten()->pluck('nama_blud')->unique()->all() === ['Puskesmas A'])
                ->assertViewHas('pembiayaan', fn ($data) => $data['grouped']->flatten()->pluck('nama_blud')->unique()->all() === ['Puskesmas A']);
        }
    }

    public function test_pdf_titles_filenames_and_all_sections_follow_selected_or_default_year(): void
    {
        foreach (['rba.ringkasan.pdf', 'rba-perubahan.ringkasan.pdf'] as $route) {
            foreach ([2027, 2026] as $year) {
                $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
                Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($year) {
                    $this->assertSame($year, $data['tahun']);
                    foreach (['pendapatan', 'belanja', 'pembiayaan'] as $section) {
                        $this->assertSame([(string) $year], $data[$section]['grouped']->flatten()
                            ->pluck('tahun_anggaran')->map(fn ($value) => (string) $value)->unique()->values()->all());
                    }
                    $this->assertStringContainsString('TAHUN ANGGARAN '.$year, view($view, $data)->render());
                    return true;
                })->andReturn($pdf);
                $pdf->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
                $pdf->shouldReceive('stream')->once()->with(Mockery::pattern('/_'.$year.'\.pdf$/'))->andReturn(response('PDF ringkasan'));
                $this->get(route($route, $year === 2027 ? [] : ['tahun' => $year]))->assertOk();
            }
        }
    }

    public function test_invalid_years_are_rejected_on_both_summaries_and_pdf_exports(): void
    {
        foreach (['abc', 1999, 2101, ['2026']] as $year) {
            foreach (['rba.ringkasan', 'rba-perubahan.ringkasan', 'rba.ringkasan.pdf', 'rba-perubahan.ringkasan.pdf'] as $route) {
                $this->getJson(route($route, ['tahun' => $year]))->assertUnprocessable()->assertJsonValidationErrors('tahun');
            }
        }
    }

    public function test_global_year_applies_to_summaries_lists_and_new_financing_without_query_parameters(): void
    {
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2026])->assertSessionHas('tahun_anggaran.user_7', 2026);
        foreach (['rba.ringkasan', 'rba-perubahan.ringkasan'] as $route) {
            $this->get(route($route))->assertOk()->assertViewHas('tahun', 2026)
                ->assertSee('data-global-year="2026"', false);
        }
        foreach (['rba.pembiayaan', 'rba-perubahan.pembiayaan'] as $route) {
            $this->get(route($route))->assertOk()->assertViewHas('tahunRba', 2026)
                ->assertViewHas('grouped', fn ($groups) => $groups->flatten()->every(fn ($row) => $row->tahun_anggaran === 2026));
        }
        $this->post(route('rba.pembiayaan.store'), ['kode_rek' => '6.1.01.01.01.0001', 'uraian' => 'Pembiayaan tahun aktif',
            'harga_satuan' => 123, 'volume_1' => 1, 'satuan_1' => 'Tahun'])
            ->assertRedirect(route('rba.pembiayaan', ['tahun' => 2026]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rencana_pembiayaans', ['uraian' => 'Pembiayaan tahun aktif', 'tahun_anggaran' => 2026]);
    }

    public function test_stale_form_or_ajax_cannot_write_after_global_year_changes(): void
    {
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2027]);
        $payload = ['kode_rek' => '6.1.01.01.01.0001', 'uraian' => 'Form lama', 'harga_satuan' => 123, 'volume_1' => 1, 'satuan_1' => 'Tahun'];
        $this->postJson(route('rba.pembiayaan.store'), $payload + ['_tahun_aktif' => 2026])->assertUnprocessable()->assertJsonValidationErrors('tahun');
        $this->postJson(route('rba.pembiayaan.store'), $payload + ['tahun' => 2026])->assertUnprocessable()->assertJsonValidationErrors('tahun');
        $this->withHeader('X-Tahun-Anggaran', '2026')->postJson(route('rba.pembiayaan.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('tahun');
        $this->assertDatabaseMissing('rencana_pembiayaans', ['uraian' => 'Form lama']);
    }
}
