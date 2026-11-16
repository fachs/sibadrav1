<?php

namespace Tests\Feature;

use App\Models\RencanaKebutuhan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RenbutDanaAkunTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'renbut_dana_testing', 'database.connections.renbut_dana_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->withoutVite();
        Schema::create('akuns', function (Blueprint $table) {
            $table->id();
            $table->string('kode_rek');
            $table->string('uraian');
        });
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
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
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('unit_bluds')->insert([['nama' => 'Puskesmas A'], ['nama' => 'Puskesmas B']]);
        DB::table('akuns')->insert(['id' => 1, 'kode_rek' => '5.1.02.01.01.0001', 'uraian' => 'Belanja Alat Tulis Kantor']);
    }

    protected function tearDown(): void
    {
        DB::purge('renbut_dana_testing');
        parent::tearDown();
    }

    private function login(string $role = 'Klaster 2'): void
    {
        $user = new User(['upt' => 'Puskesmas A', 'role' => $role]);
        $user->id = 7;
        $this->actingAs($user);
    }

    private function record(string $blud = 'Puskesmas A', string $unit = 'Klaster 2'): RencanaKebutuhan
    {
        return RencanaKebutuhan::create([
            'nama_blud' => $blud, 'nama_unit' => $unit, 'uraian' => 'Kebutuhan kertas',
            'tahun_anggaran' => '2027',
            'jumlah' => 1, 'harga_satuan' => 5000, 'jumlah_harga' => 5000,
        ]);
    }

    public function test_every_role_can_see_and_fill_funding_and_account_fields(): void
    {
        foreach (['Klaster 1', 'Klaster 2', 'Klaster 3', 'Klaster 4', 'Lintas Klaster',
            'Bendahara Pengeluaran', 'Bendahara Penerimaan', 'Kepala Puskesmas', 'Kasubbag TU',
            'Kepala Labkes', 'Dinas', 'Superuser', 'Pengguna Baru'] as $role) {
            $this->login($role);
            $record = $this->record('Puskesmas A', $role);
            $this->get(route('rencana-kebutuhan.index'))->assertOk()
                ->assertViewHas('canManageDanaAkun', true)
                ->assertSee('class="col-sumber-dana"', false)
                ->assertSee('text-akun')->assertSee('/rencana-kebutuhan/update-akun')
                ->assertSee('/rencana-kebutuhan/update-sumber-dana');
            $this->postJson('/rencana-kebutuhan/update-sumber-dana', ['id' => $record->id, 'sumber_dana' => 'BLUD'])
                ->assertOk()->assertJson(['success' => true]);
            $this->postJson('/rencana-kebutuhan/update-akun', ['id' => $record->id, 'akun_id' => 1])
                ->assertOk()->assertJson(['success' => true]);
            $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $record->id, 'sumber_dana' => 'BLUD', 'akun_id' => 1]);
        }
    }

    public function test_dynamic_table_and_account_search_are_available_to_regular_role(): void
    {
        $this->login();
        $record = $this->record();
        $this->getJson('/rencana-kebutuhan/data')->assertOk()
            ->assertJsonPath('data.0.id', $record->id)
            ->assertJsonPath('data.0.sumber_dana_html', fn ($html) => str_contains($html, 'select-sumber-dana'))
            ->assertJsonPath('data.0.nama_barang_html', fn ($html) => str_contains($html, 'select-akun'));
        $this->getJson('/api/akun?q=5.1.02')->assertOk()
            ->assertJsonPath('results.0.id', 1)->assertJsonPath('results.0.text', '5.1.02.01.01.0001 - Belanja Alat Tulis Kantor');
    }

    public function test_other_blud_and_deleted_rows_cannot_be_changed(): void
    {
        $this->login();
        $other = $this->record('Puskesmas B');
        $deleted = $this->record();
        $deleted->delete();
        foreach ([$other->id, $deleted->id, 999] as $id) {
            $this->postJson('/rencana-kebutuhan/update-sumber-dana', ['id' => $id, 'sumber_dana' => 'BLUD'])->assertNotFound();
            $this->postJson('/rencana-kebutuhan/update-akun', ['id' => $id, 'akun_id' => 1])->assertNotFound();
        }
        $this->assertDatabaseMissing('rencana_kebutuhans', ['sumber_dana' => 'BLUD']);
        $this->assertDatabaseMissing('rencana_kebutuhans', ['akun_id' => 1]);
    }

    public function test_superuser_can_fill_selected_blud_fields(): void
    {
        $this->login('Superuser');
        $other = $this->record('Puskesmas B');
        $this->postJson('/rencana-kebutuhan/update-sumber-dana', ['id' => $other->id, 'sumber_dana' => 'HIBAH'])->assertOk();
        $this->postJson('/rencana-kebutuhan/update-akun', ['id' => $other->id, 'akun_id' => 1])->assertOk();
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $other->id, 'nama_blud' => 'Puskesmas B', 'sumber_dana' => 'HIBAH', 'akun_id' => 1]);
    }

    public function test_invalid_options_are_rejected_and_both_fields_can_be_cleared(): void
    {
        $this->login();
        $record = $this->record();
        $this->postJson('/rencana-kebutuhan/update-sumber-dana', ['id' => $record->id, 'sumber_dana' => 'INVALID'])
            ->assertUnprocessable()->assertJsonValidationErrors('sumber_dana');
        $this->postJson('/rencana-kebutuhan/update-akun', ['id' => $record->id, 'akun_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors('akun_id');
        $record->update(['sumber_dana' => 'APBD', 'akun_id' => 1]);
        $this->postJson('/rencana-kebutuhan/update-sumber-dana', ['id' => $record->id, 'sumber_dana' => null])->assertOk();
        $this->postJson('/rencana-kebutuhan/update-akun', ['id' => $record->id, 'akun_id' => null])->assertOk();
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $record->id, 'sumber_dana' => null, 'akun_id' => null]);
    }

    public function test_guests_cannot_fill_fields(): void
    {
        $record = $this->record();
        $this->postJson('/rencana-kebutuhan/update-sumber-dana', ['id' => $record->id, 'sumber_dana' => 'APBD'])->assertUnauthorized();
        $this->postJson('/rencana-kebutuhan/update-akun', ['id' => $record->id, 'akun_id' => 1])->assertUnauthorized();
    }

    public function test_combined_cluster_roles_see_both_assigned_units_only_in_own_blud(): void
    {
        $first = $this->record('Puskesmas A', 'Klaster 2');
        $second = $this->record('Puskesmas A', 'Klaster 3');
        $this->record('Puskesmas A', 'Klaster 4');
        $this->record('Puskesmas B', 'Klaster 3');
        $user = new User(['role' => 'Klaster 2', 'roles' => ['Klaster 2', 'Klaster 3'], 'upt' => 'Puskesmas A']);
        $user->id = 7;
        $this->actingAs($user);
        $response = $this->getJson('/rencana-kebutuhan/data')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_column($response->json('data'), 'id'));
    }
}
