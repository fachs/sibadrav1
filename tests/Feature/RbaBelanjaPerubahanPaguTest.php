<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RbaBelanjaPerubahanPaguTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-11 12:00:00', 'Asia/Jakarta'));
        config(['database.default' => 'rba_belanja_pagu_testing', 'database.connections.rba_belanja_pagu_testing' => [
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
        DB::table('akuns')->insert([
            ['id' => 1, 'kode_rek' => '5.1.02.01', 'uraian' => 'Belanja Barang'],
            ['id' => 2, 'kode_rek' => '5.2.02.01', 'uraian' => 'Belanja Modal'],
        ]);
        Schema::create('rencana_kebutuhans', function (Blueprint $table) {
            $table->id(); $table->string('nama_blud'); $table->string('nama_unit')->nullable();
            $table->string('tahun_anggaran'); $table->unsignedBigInteger('akun_id');
            $table->string('program')->nullable(); $table->string('kegiatan')->nullable();
            $table->string('subkegiatan')->nullable(); $table->string('ruang')->nullable();
            $table->string('sumber_dana')->nullable(); $table->string('spesifikasi')->nullable();
            $table->string('status')->nullable(); $table->string('dokumen')->nullable();
            $table->string('uraian'); $table->decimal('jumlah', 15, 2)->default(0);
            $table->string('satuan')->nullable(); $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('jumlah_harga', 15, 2)->default(0); $table->text('keterangan')->nullable();
            $table->string('uraian_perubahan')->nullable(); $table->decimal('jumlah_perubahan', 15, 2)->nullable();
            $table->string('satuan_perubahan')->nullable(); $table->decimal('harga_satuan_perubahan', 15, 2)->nullable();
            $table->decimal('jumlah_harga_perubahan', 15, 2)->nullable(); $table->text('keterangan_perubahan')->nullable();
            $table->string('status_perubahan')->nullable(); $table->string('verifikator_perubahan')->nullable();
            $table->string('rekening_awal')->nullable(); $table->string('rekening_akhir')->nullable();
            $table->boolean('is_hasil_perubahan')->default(false); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('bku_bendahara_pengeluarans', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('unit_blud_id'); $table->date('tanggal');
            $table->string('kode_rekening')->nullable(); $table->unsignedBigInteger('parent_bku_id')->nullable();
            $table->boolean('is_saldo_awal')->default(false); $table->bigInteger('pengeluaran')->default(0);
        });
        $user = new User(['role' => 'Klaster 2', 'upt' => 'Puskesmas A']);
        $user->id = 7;
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::purge('rba_belanja_pagu_testing');
        parent::tearDown();
    }

    private function rba(int $akunId, int $pagu, array $attributes = []): int
    {
        return DB::table('rencana_kebutuhans')->insertGetId(array_replace([
            'nama_blud' => 'Puskesmas A', 'tahun_anggaran' => '2027', 'akun_id' => $akunId,
            'uraian' => 'Rincian belanja', 'jumlah' => 1, 'satuan' => 'Paket',
            'harga_satuan' => $pagu, 'jumlah_harga' => $pagu,
        ], $attributes));
    }

    private function realisasi(int $jumlah, array $attributes = []): int
    {
        return DB::table('bku_bendahara_pengeluarans')->insertGetId(array_replace([
            'unit_blud_id' => 1, 'tanggal' => '2027-01-10', 'kode_rekening' => '5.1.02.01',
            'pengeluaran' => $jumlah,
        ], $attributes));
    }

    private function editPayload(int $nilai): array
    {
        return ['uraian' => 'Usulan belanja', 'jumlah' => 1, 'satuan' => 'Paket', 'harga_satuan' => $nilai];
    }

    public function test_edit_and_zero_out_cannot_reduce_rekening_below_its_bku_realisasi(): void
    {
        $id = $this->rba(1, 500000);
        $parent = $this->realisasi(250000);
        $this->realisasi(5000, ['parent_bku_id' => $parent]);
        $this->realisasi(200000, ['unit_blud_id' => 2]);
        $this->realisasi(100000, ['tanggal' => '2026-12-31']);

        $this->put(route('rba-perubahan.belanja.update', $id), $this->editPayload(249999))
            ->assertSessionHasErrors('jumlah');
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $id, 'status_perubahan' => null]);
        $this->patch(route('rba-perubahan.belanja.zero-out', $id))->assertSessionHasErrors('jumlah');
        $this->put(route('rba-perubahan.belanja.update', $id), $this->editPayload(250000))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rencana_kebutuhans',
            ['id' => $id, 'status_perubahan' => 'pending', 'jumlah_harga_perubahan' => 250000]);
    }

    public function test_shift_form_displays_current_rekening_budget_and_bku_realisasi(): void
    {
        $this->rba(1, 500000);
        $this->realisasi(250000);
        $this->get(route('rba-perubahan.belanja.pergeseran-lintas-akun.create'))
            ->assertOk()->assertSee('data-pagu-rekening="500000"', false)
            ->assertSee('data-realisasi-bku="250000"', false)
            ->assertSee('data-sisa-pagu="250000"', false);
    }

    public function test_multiple_pending_changes_share_the_same_rekening_limit(): void
    {
        $first = $this->rba(1, 300000);
        $second = $this->rba(1, 200000);
        $this->realisasi(350000);

        $this->put(route('rba-perubahan.belanja.update', $first), $this->editPayload(250000))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('rba-perubahan.belanja.update', $second), $this->editPayload(50000))
            ->assertSessionHasErrors('jumlah');
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $second, 'status_perubahan' => null]);
        $this->put(route('rba-perubahan.belanja.update', $second), $this->editPayload(100000))
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_shift_cannot_move_amount_already_realized_to_another_rekening(): void
    {
        $source = $this->rba(1, 500000);
        $destination = $this->rba(2, 100000);
        $this->realisasi(250000);
        $payload = [
            'sumber_renbut_id' => $source, 'sumber_uraian' => 'Sumber',
            'sumber_jumlah' => 1, 'sumber_satuan' => 'Paket', 'sumber_harga_satuan' => 200000,
            'tipe_tujuan' => 'existing', 'tujuan_renbut_id' => $destination, 'tujuan_akun_id' => 2,
            'tujuan_uraian' => 'Tujuan', 'tujuan_jumlah' => 1,
            'tujuan_satuan' => 'Paket', 'tujuan_harga_satuan' => 400000,
        ];
        $this->post(route('rba-perubahan.belanja.pergeseran-lintas-akun'), $payload)
            ->assertSessionHasErrors('sumber_jumlah');
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $source, 'status_perubahan' => null]);
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $destination, 'status_perubahan' => null]);

        $payload['sumber_harga_satuan'] = 250000;
        $this->post(route('rba-perubahan.belanja.pergeseran-lintas-akun'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $source, 'jumlah_harga_perubahan' => 250000]);
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $destination, 'jumlah_harga_perubahan' => 400000]);
    }

    public function test_shift_to_new_object_respects_realisasi_and_does_not_create_row_when_rejected(): void
    {
        $source = $this->rba(1, 500000);
        $this->realisasi(250000);
        $payload = [
            'sumber_renbut_id' => $source, 'sumber_uraian' => 'Sumber',
            'sumber_jumlah' => 1, 'sumber_satuan' => 'Paket', 'sumber_harga_satuan' => 240000,
            'tipe_tujuan' => 'baru', 'tujuan_akun_id_baru' => 2,
            'tujuan_uraian_baru' => 'Objek baru', 'tujuan_jumlah_baru' => 1,
            'tujuan_satuan_baru' => 'Paket', 'tujuan_harga_satuan_baru' => 260000,
        ];
        $this->post(route('rba-perubahan.belanja.pergeseran-lintas-akun'), $payload)
            ->assertSessionHasErrors('sumber_jumlah');
        $this->assertDatabaseCount('rencana_kebutuhans', 1);

        $payload['sumber_harga_satuan'] = 250000;
        $this->post(route('rba-perubahan.belanja.pergeseran-lintas-akun'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rencana_kebutuhans', 2);
        $this->assertDatabaseHas('rencana_kebutuhans', [
            'akun_id' => 2, 'nama_blud' => 'Puskesmas A', 'is_hasil_perubahan' => true,
            'jumlah_harga_perubahan' => 260000,
        ]);
    }

    public function test_verification_rechecks_spending_recorded_after_the_proposal(): void
    {
        $id = $this->rba(1, 500000);
        $this->realisasi(250000);
        $this->put(route('rba-perubahan.belanja.update', $id), $this->editPayload(300000))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->realisasi(100000);

        $user = new User(['role' => 'Kepala Puskesmas', 'upt' => 'Puskesmas A']);
        $user->id = 8;
        $this->actingAs($user);
        $this->patch(route('rba-perubahan.belanja.verifikasi', $id))
            ->assertSessionHasErrors('jumlah_harga_perubahan');
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $id, 'status_perubahan' => 'pending']);
    }

    public function test_bulk_verification_is_atomic_when_one_rekening_exceeds_its_proposal(): void
    {
        $first = $this->rba(1, 500000);
        $second = $this->rba(2, 100000);
        $this->realisasi(250000);
        $this->put(route('rba-perubahan.belanja.update', $first), $this->editPayload(300000))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('rba-perubahan.belanja.update', $second), $this->editPayload(100000))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->realisasi(100000);

        $user = new User(['role' => 'Kepala Puskesmas', 'upt' => 'Puskesmas A']);
        $user->id = 8;
        $this->actingAs($user);
        $this->patch(route('rba-perubahan.belanja.verifikasi-semua'))
            ->assertSessionHasErrors('jumlah_harga_perubahan');
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $first, 'status_perubahan' => 'pending']);
        $this->assertDatabaseHas('rencana_kebutuhans', ['id' => $second, 'status_perubahan' => 'pending']);
    }
}
