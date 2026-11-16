<?php

namespace Tests\Feature;

use App\Models\PrakiraanMaju;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrakiraanMajuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'prakiraan_testing', 'database.connections.prakiraan_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->withoutVite();
        (require database_path('migrations/2026_11_03_000001_create_prakiraan_majus_table.php'))->up();
        Schema::create('rencana_kebutuhans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_blud');
            $table->string('tahun_anggaran');
            $table->unsignedBigInteger('akun_id')->nullable();
            $table->boolean('is_hasil_perubahan')->nullable();
            $table->decimal('jumlah_harga', 18, 2)->default(0);
            $table->softDeletes();
        });
        Schema::create('akuns', function (Blueprint $table) {
            $table->id();
            $table->string('kode_rek');
        });

        $user = new User(['role' => 'Pejabat Keuangan', 'upt' => 'Puskesmas A']);
        $user->id = 1;
        $this->actingAs($user);
        $this->withSession(['tahun_anggaran.user_1' => 2027]);
    }

    protected function tearDown(): void
    {
        DB::purge('prakiraan_testing');
        parent::tearDown();
    }

    public function test_numeric_values_with_dotted_account_codes_are_saved_and_loaded(): void
    {
        $codes = ['5.1.01', '5.1.02', '5.2.02', '5.2.03', '5.2.04'];
        $yearOne = array_fill_keys($codes, '12.345,67');
        $yearTwo = array_fill_keys($codes, '23.456,78');
        $yearOne['5.1.02'] = '500.000.000';

        $this->get(route('prakiraan-maju.index'))->assertOk()
            ->assertSee('class="form-control text-end format-rupiah" type="text" inputmode="decimal"', false);
        $this->post(route('prakiraan-maju.store'), [
            'nilai_tahun_1' => $yearOne,
            'nilai_tahun_2' => $yearTwo,
        ])->assertRedirect(route('prakiraan-maju.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('prakiraan_majus', 5);
        $this->assertDatabaseHas('prakiraan_majus', [
            'nama_blud' => 'Puskesmas A', 'tahun_baseline' => 2027, 'kode_rek' => '5.1.01',
            'nilai_tahun_1' => 12345.67, 'nilai_tahun_2' => 23456.78,
        ]);
        $this->assertDatabaseHas('prakiraan_majus', [
            'kode_rek' => '5.1.02', 'nilai_tahun_1' => 500000000,
        ]);
        $this->get(route('prakiraan-maju.index'))->assertOk()
            ->assertSee('value="12.345,67"', false)->assertSee('value="500.000.000"', false);

        $yearOne['5.1.01'] = '34.567,89';
        $this->post(route('prakiraan-maju.store'), [
            'nilai_tahun_1' => $yearOne,
            'nilai_tahun_2' => $yearTwo,
        ])->assertRedirect(route('prakiraan-maju.index'))->assertSessionHasNoErrors();
        $this->assertSame('34567.89', PrakiraanMaju::where('kode_rek', '5.1.01')->firstOrFail()->nilai_tahun_1);
    }

    public function test_missing_numeric_value_is_rejected_instead_of_saving_zero(): void
    {
        $codes = ['5.1.01', '5.1.02', '5.2.02', '5.2.03', '5.2.04'];
        $yearOne = array_fill_keys($codes, '100');
        unset($yearOne['5.1.01']);

        $this->post(route('prakiraan-maju.store'), [
            'nilai_tahun_1' => $yearOne,
            'nilai_tahun_2' => array_fill_keys($codes, '200'),
        ])->assertSessionHasErrors('nilai_tahun_1');
        $this->assertDatabaseCount('prakiraan_majus', 0);

        $yearOne['5.1.01'] = 'bukan angka';
        $this->post(route('prakiraan-maju.store'), [
            'nilai_tahun_1' => $yearOne,
            'nilai_tahun_2' => array_fill_keys($codes, '200'),
        ])->assertSessionHasErrors('nilai_tahun_1.5.1.01');
        $this->assertDatabaseCount('prakiraan_majus', 0);
    }

    public function test_table_columns_baseline_and_saved_values_follow_the_selected_budget_year(): void
    {
        DB::table('akuns')->insert(['id' => 1, 'kode_rek' => '5.1.01.01']);
        DB::table('rencana_kebutuhans')->insert([
            ['nama_blud' => 'Puskesmas A', 'tahun_anggaran' => '2026', 'akun_id' => 1, 'jumlah_harga' => 100000],
            ['nama_blud' => 'Puskesmas A', 'tahun_anggaran' => '2027', 'akun_id' => 1, 'jumlah_harga' => 200000],
        ]);
        $codes = ['5.1.01', '5.1.02', '5.2.02', '5.2.03', '5.2.04'];

        $this->withSession(['tahun_anggaran.user_1' => 2026]);
        $this->get(route('prakiraan-maju.index'))->assertOk()
            ->assertSee('2026 (RBA)')->assertSee('>2027</th>', false)->assertSee('>2028</th>', false)
            ->assertSee('100.000');
        $this->post(route('prakiraan-maju.store'), [
            '_tahun_aktif' => 2026,
            'nilai_tahun_1' => array_fill_keys($codes, '110000'),
            'nilai_tahun_2' => array_fill_keys($codes, '120000'),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('prakiraan_majus', [
            'tahun_baseline' => 2026, 'kode_rek' => '5.1.01',
            'nilai_baseline' => 100000, 'nilai_tahun_1' => 110000, 'nilai_tahun_2' => 120000,
        ]);

        $this->withSession(['tahun_anggaran.user_1' => 2027]);
        $this->get(route('prakiraan-maju.index'))->assertOk()
            ->assertSee('2027 (RBA)')->assertSee('>2028</th>', false)->assertSee('>2029</th>', false)
            ->assertSee('200.000')->assertSee('value="0"', false);
        $this->post(route('prakiraan-maju.store'), [
            '_tahun_aktif' => 2027,
            'nilai_tahun_1' => array_fill_keys($codes, '210000'),
            'nilai_tahun_2' => array_fill_keys($codes, '220000'),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('prakiraan_majus', [
            'tahun_baseline' => 2027, 'kode_rek' => '5.1.01',
            'nilai_baseline' => 200000, 'nilai_tahun_1' => 210000, 'nilai_tahun_2' => 220000,
        ]);
        $this->assertDatabaseCount('prakiraan_majus', 10);

        $this->withSession(['tahun_anggaran.user_1' => 2026]);
        $this->get(route('prakiraan-maju.index'))->assertOk()->assertSee('value="110.000"', false);
    }

    public function test_old_form_cannot_save_after_the_budget_year_changes(): void
    {
        $codes = ['5.1.01', '5.1.02', '5.2.02', '5.2.03', '5.2.04'];
        $this->post(route('prakiraan-maju.store'), [
            '_tahun_aktif' => 2026,
            'nilai_tahun_1' => array_fill_keys($codes, '100'),
            'nilai_tahun_2' => array_fill_keys($codes, '200'),
        ])->assertSessionHasErrors('tahun');
        $this->assertDatabaseCount('prakiraan_majus', 0);
    }
}
