<?php

namespace Tests\Feature;

use App\Models\AmbangBatas;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AmbangBatasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'ambang_testing', 'database.connections.ambang_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->withoutVite();
        (require database_path('migrations/2026_10_20_000001_create_ambang_batas_table.php'))->up();
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
        });
        DB::table('unit_bluds')->insert([['nama' => 'Puskesmas A'], ['nama' => 'Puskesmas B']]);
    }

    protected function tearDown(): void
    {
        DB::purge('ambang_testing');
        parent::tearDown();
    }

    private function login(string $role = 'Kepala Puskesmas'): void
    {
        $user = new User(['role' => $role, 'upt' => 'Puskesmas A']);
        $user->id = 1;
        $this->actingAs($user);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['tahun' => 2025, 'anggaran' => 650000000, 'realisasi' => 700000000], $overrides);
    }

    private function record(string $blud = 'Puskesmas A', array $overrides = []): AmbangBatas
    {
        return AmbangBatas::create($this->data($overrides) + ['nama_blud' => $blud]);
    }

    public function test_document_uses_only_the_two_years_before_its_budget_year_for_the_selected_puskesmas(): void
    {
        foreach ([2023, 2024, 2025, 2026] as $year) {
            $this->record('Puskesmas A', ['tahun' => $year]);
        }
        $this->record('Puskesmas B', ['tahun' => 2024]);

        $this->assertSame([2024, 2025], AmbangBatas::forDocumentYear('Puskesmas A', 2026)
            ->pluck('tahun')->all());
        $this->assertSame([2025, 2026], AmbangBatas::forDocumentYear('Puskesmas A', 2027)
            ->pluck('tahun')->all());
    }

    public function test_login_is_required(): void
    {
        $this->get(route('rba.ambang-batas'))->assertRedirect(route('login'));
        $this->post(route('rba.ambang-batas.store'), $this->data())->assertRedirect(route('login'));
        $this->put(route('rba.ambang-batas.update', 1), $this->data())->assertRedirect(route('login'));
        $this->delete(route('rba.ambang-batas.destroy', 1))->assertRedirect(route('login'));
    }

    public function test_calculation_matches_both_examples_and_orders_latest_year_first(): void
    {
        $this->login();
        $first = $this->record();
        $second = $this->record('Puskesmas A', ['tahun' => 2024, 'anggaran' => 700000000, 'realisasi' => 701000000]);
        $this->assertEquals(50000000, $first->selisih);
        $this->assertSame(7.69, $first->persentase);
        $this->assertEquals(1000000, $second->selisih);
        $this->assertSame(0.14, $second->persentase);
        $this->get(route('rba.ambang-batas'))->assertOk()
            ->assertSeeInOrder(['2025', 'Rp 650.000.000', 'Rp 700.000.000', 'Rp 50.000.000', '7,69%', '2024', '0,14%']);
    }

    public function test_create_update_and_delete_recalculate_values_and_ignore_forged_fields(): void
    {
        $this->login();
        $this->post(route('rba.ambang-batas.store'), $this->data([
            'nama_blud' => 'Puskesmas B', 'selisih' => 999, 'persentase' => 999,
        ]))->assertRedirect(route('rba.ambang-batas'))->assertSessionHasNoErrors();
        $record = AmbangBatas::firstOrFail();
        $this->assertSame('Puskesmas A', $record->nama_blud);
        $this->assertSame(7.69, $record->persentase);
        $this->get(route('rba.ambang-batas', ['edit' => $record->id]))
            ->assertOk()->assertSee('Simpan Perubahan');
        $this->put(route('rba.ambang-batas.update', $record->id), $this->data(['realisasi' => 585000000]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame(-65000000.0, $record->selisih);
        $this->assertSame(-10.0, $record->persentase);
        $this->delete(route('rba.ambang-batas.destroy', $record->id))->assertRedirect();
        $this->assertDatabaseCount('ambang_batas', 0);
    }

    public function test_zero_realisation_and_decimal_values_are_supported(): void
    {
        $this->login();
        $this->post(route('rba.ambang-batas.store'), $this->data(['anggaran' => '10.50', 'realisasi' => 0]))
            ->assertSessionHasNoErrors();
        $record = AmbangBatas::firstOrFail();
        $this->assertSame(-10.5, $record->selisih);
        $this->assertSame(-100.0, $record->persentase);
        $this->put(route('rba.ambang-batas.update', $record->id), $this->data(['anggaran' => '10.50', 'realisasi' => '11.75']))
            ->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame(1.25, $record->selisih);
        $this->assertSame(11.9, $record->persentase);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->login();
        foreach ([
            ['anggaran' => 0], ['anggaran' => -1], ['anggaran' => '1.001'],
            ['anggaran' => '10000000000000'], ['realisasi' => -1], ['realisasi' => 'abc'],
            ['tahun' => 1899], ['tahun' => '2025.5'], ['tahun' => null],
        ] as $invalid) {
            $this->post(route('rba.ambang-batas.store'), $this->data($invalid))
                ->assertSessionHasErrors(array_keys($invalid));
        }
        $this->assertDatabaseCount('ambang_batas', 0);
    }

    public function test_year_is_unique_per_blud_and_update_can_keep_its_year(): void
    {
        $this->login();
        $record = $this->record();
        $this->post(route('rba.ambang-batas.store'), $this->data())->assertSessionHasErrors('tahun');
        $this->put(route('rba.ambang-batas.update', $record->id), $this->data())->assertSessionHasNoErrors();
        $this->record('Puskesmas B');
        $this->assertDatabaseCount('ambang_batas', 2);
    }

    public function test_other_blud_data_cannot_be_read_edited_or_deleted(): void
    {
        $this->login('Klaster 2');
        $this->record();
        $other = $this->record('Puskesmas B', ['tahun' => 2024]);
        $this->get(route('rba.ambang-batas', ['puskesmas' => 'Puskesmas B']))
            ->assertOk()->assertViewHas('ambangBatas', fn ($rows) => $rows->total() === 1 && $rows->first()->nama_blud === 'Puskesmas A');
        $this->get(route('rba.ambang-batas', ['edit' => $other->id]))->assertNotFound();
        $this->put(route('rba.ambang-batas.update', $other->id), $this->data())->assertNotFound();
        $this->delete(route('rba.ambang-batas.destroy', $other->id))->assertNotFound();
        $this->assertDatabaseCount('ambang_batas', 2);
    }

    public function test_regular_staff_can_add_edit_and_delete_within_their_blud(): void
    {
        $this->login('Klaster 2');
        $record = $this->record();
        $this->get(route('rba.ambang-batas'))->assertOk()
            ->assertSee('Tambah Data')->assertSee('ambang-batas-form')->assertSee('Simpan Data')
            ->assertSee('Edit Ambang Batas')->assertSee('Hapus Ambang Batas')
            ->assertSee('data-confirm-delete', false);
        $this->get(route('rba.ambang-batas', ['edit' => $record->id]))->assertOk()->assertSee('Simpan Perubahan');
        $this->post(route('rba.ambang-batas.store'), $this->data([
            'tahun' => 2024, 'nama_blud' => 'Puskesmas B',
        ]))->assertRedirect(route('rba.ambang-batas'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ambang_batas', ['tahun' => 2024, 'nama_blud' => 'Puskesmas A']);
        $this->assertDatabaseMissing('ambang_batas', ['tahun' => 2024, 'nama_blud' => 'Puskesmas B']);
        $this->put(route('rba.ambang-batas.update', $record->id), $this->data([
            'realisasi' => 585000000, 'nama_blud' => 'Puskesmas B',
        ]))->assertRedirect(route('rba.ambang-batas'))->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame('Puskesmas A', $record->nama_blud);
        $this->assertSame(-10.0, $record->persentase);
        $this->delete(route('rba.ambang-batas.destroy', $record->id))->assertRedirect(route('rba.ambang-batas'));
        $this->assertDatabaseMissing('ambang_batas', ['id' => $record->id]);
        $this->assertDatabaseHas('ambang_batas', ['tahun' => 2024, 'nama_blud' => 'Puskesmas A']);
    }

    public function test_add_edit_and_delete_are_available_for_any_authenticated_role(): void
    {
        foreach (['Klaster 3', 'Klaster 4', 'Lintas Klaster', 'Bendahara', 'Dinas Kesehatan', 'Pengguna Baru'] as $index => $role) {
            $this->login($role);
            $this->get(route('rba.ambang-batas'))->assertOk()->assertSee('Tambah Data')->assertSee('ambang-batas-form');
            $this->post(route('rba.ambang-batas.store'), $this->data(['tahun' => 2000 + $index]))
                ->assertRedirect(route('rba.ambang-batas'))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('ambang_batas', ['tahun' => 2000 + $index, 'nama_blud' => 'Puskesmas A']);
            $record = AmbangBatas::where('tahun', 2000 + $index)->firstOrFail();
            $this->get(route('rba.ambang-batas', ['edit' => $record->id]))->assertOk()->assertSee('Simpan Perubahan');
            $this->put(route('rba.ambang-batas.update', $record->id), $this->data([
                'tahun' => 2000 + $index, 'realisasi' => 585000000,
            ]))->assertRedirect(route('rba.ambang-batas'))->assertSessionHasNoErrors();
            $this->assertSame(-10.0, $record->refresh()->persentase);
            $this->delete(route('rba.ambang-batas.destroy', $record->id))->assertRedirect(route('rba.ambang-batas'));
            $this->assertDatabaseMissing('ambang_batas', ['id' => $record->id]);
        }
    }

    public function test_superuser_can_filter_and_manage_selected_blud(): void
    {
        $this->login('Superuser');
        $this->record();
        $this->record('Puskesmas B');
        $this->get(route('rba.ambang-batas'))->assertOk()
            ->assertViewHas('ambangBatas', fn ($rows) => $rows->total() === 2);
        $this->get(route('rba.ambang-batas', ['puskesmas' => 'Puskesmas B']))->assertOk()
            ->assertViewHas('ambangBatas', fn ($rows) => $rows->total() === 1 && $rows->first()->nama_blud === 'Puskesmas B');
        $this->post(route('rba.ambang-batas.store'), $this->data(['nama_blud' => 'Puskesmas B', 'tahun' => 2024]))
            ->assertSessionHasNoErrors()->assertRedirect(route('rba.ambang-batas', ['puskesmas' => 'Puskesmas B']));
        $this->assertDatabaseHas('ambang_batas', ['nama_blud' => 'Puskesmas B', 'tahun' => 2024]);
        $this->post(route('rba.ambang-batas.store'), $this->data(['nama_blud' => 'Tidak ada', 'tahun' => 2023]))
            ->assertSessionHasErrors('nama_blud');
    }

    public function test_empty_state_and_pagination_preserve_filter(): void
    {
        $this->login('Superuser');
        $this->get(route('rba.ambang-batas'))->assertOk()->assertSee('Belum ada data ambang batas.');
        for ($year = 2000; $year < 2016; $year++) {
            $this->record('Puskesmas B', ['tahun' => $year]);
        }
        $this->get(route('rba.ambang-batas', ['puskesmas' => 'Puskesmas B']))->assertOk()
            ->assertSee('puskesmas=Puskesmas%20B')
            ->assertViewHas('ambangBatas', fn ($rows) => $rows->total() === 16 && $rows->first()->tahun === 2015);
        $this->get(route('rba.ambang-batas', ['puskesmas' => 'Puskesmas B', 'page' => 2]))->assertOk()
            ->assertViewHas('ambangBatas', fn ($rows) => $rows->count() === 1 && $rows->first()->tahun === 2000);
    }
}
