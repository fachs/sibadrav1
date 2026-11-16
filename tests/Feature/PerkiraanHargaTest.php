<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerkiraanHargaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'perkiraan_harga_testing', 'database.connections.perkiraan_harga_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->withoutVite();

        Schema::create('rencana_kebutuhans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_blud');
            $table->string('tahun_anggaran')->default('2027');
            $table->string('uraian');
            $table->decimal('harga_satuan', 15, 2);
            $table->string('satuan');
            $table->string('keterangan')->nullable();
            $table->softDeletes();
        });
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
        });
        DB::table('unit_bluds')->insert([
            ['nama' => 'Puskesmas A'], ['nama' => 'Puskesmas B'],
        ]);
        DB::table('rencana_kebutuhans')->insert([
            ['nama_blud' => 'Puskesmas A', 'uraian' => 'Kertas A4', 'harga_satuan' => 55000.5, 'satuan' => 'Rim', 'deleted_at' => null],
            ['nama_blud' => 'Puskesmas B', 'uraian' => 'Kertas F4', 'harga_satuan' => 60000, 'satuan' => 'Rim', 'deleted_at' => null],
            ['nama_blud' => 'Puskesmas A', 'uraian' => 'Barang dihapus', 'harga_satuan' => 1000, 'satuan' => 'Buah', 'deleted_at' => now()],
        ]);
        DB::table('rencana_kebutuhans')->where('uraian', 'Kertas A4')
            ->update(['keterangan' => 'Kebutuhan administrasi']);
    }

    protected function tearDown(): void
    {
        DB::purge('perkiraan_harga_testing');
        parent::tearDown();
    }

    private function login(string $role = 'Kepala Puskesmas'): void
    {
        $user = new User(['role' => $role, 'upt' => 'Puskesmas A']);
        $user->id = 1;
        $this->actingAs($user);
    }

    public function test_page_requires_login(): void
    {
        $this->get(route('rba.perkiraan-harga'))->assertRedirect(route('login'));
    }

    public function test_page_shows_original_prices_and_only_users_blud_even_with_filter(): void
    {
        $this->login();

        $this->get(route('rba.perkiraan-harga', ['puskesmas' => 'Puskesmas B', 'search' => 'Rim']))
            ->assertOk()
            ->assertSee('Kertas A4')
            ->assertSee('55.000,50')
            ->assertSee('Rim')
            ->assertSee('Keterangan')
            ->assertSee('Kebutuhan administrasi')
            ->assertDontSee('Kertas F4')
            ->assertDontSee('Barang dihapus');
    }

    public function test_superuser_can_see_all_bluds_and_filter_by_blud(): void
    {
        $this->login('Superuser');

        $this->get(route('rba.perkiraan-harga'))
            ->assertOk()->assertSee('Kertas A4')->assertSee('Kertas F4');
        $this->get(route('rba.perkiraan-harga', ['puskesmas' => 'Puskesmas B']))
            ->assertOk()->assertSee('Kertas F4')->assertDontSee('Kertas A4');
    }

    public function test_search_filters_uraian_and_displays_empty_state(): void
    {
        $this->login();

        $this->get(route('rba.perkiraan-harga', ['search' => 'A4']))
            ->assertOk()->assertSee('Kertas A4');
        $this->get(route('rba.perkiraan-harga', ['search' => 'tidak ditemukan']))
            ->assertOk()->assertSee('Tidak ada data perkiraan harga.')->assertDontSee('Kertas A4');
    }

    public function test_pagination_preserves_search_and_blud_filter(): void
    {
        $this->login('Superuser');
        for ($i = 1; $i <= 16; $i++) {
            DB::table('rencana_kebutuhans')->insert([
                'nama_blud' => 'Puskesmas B', 'uraian' => sprintf('Alat %02d', $i),
                'harga_satuan' => 2000, 'satuan' => 'Buah',
            ]);
        }

        $filters = ['search' => 'Alat', 'puskesmas' => 'Puskesmas B'];
        $this->get(route('rba.perkiraan-harga', $filters))
            ->assertOk()->assertSee('Alat 01')->assertDontSee('Alat 16')
            ->assertSee('search=Alat')->assertSee('puskesmas=Puskesmas%20B');
        $this->get(route('rba.perkiraan-harga', $filters + ['page' => 2]))
            ->assertOk()->assertSee('Alat 16')->assertDontSee('Alat 01');
    }
}
