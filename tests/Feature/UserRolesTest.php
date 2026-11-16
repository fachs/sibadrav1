<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureKapusKtu;
use App\Http\Middleware\EnsureSuperuser;
use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use App\Support\AlurPerubahanPendapatan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'user_roles_testing', 'database.connections.user_roles_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->withoutVite();
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email')->unique(); $table->string('nip')->unique();
            $table->string('password'); $table->string('role')->nullable(); $table->string('upt')->nullable();
            $table->timestamp('email_verified_at')->nullable(); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('unit_bluds', function (Blueprint $table) {
            $table->id(); $table->string('nama');
        });
        DB::table('unit_bluds')->insert([['nama' => 'Puskesmas A'], ['nama' => 'Puskesmas B']]);
        $this->migration()->up();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'nip' => '001',
            'password' => Hash::make('password123'), 'role' => 'Superuser', 'roles' => ['Superuser'], 'upt' => 'Puskesmas A']);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        DB::purge('user_roles_testing');
        parent::tearDown();
    }

    private function migration()
    {
        return require database_path('migrations/2026_10_21_000005_add_roles_to_users_table.php');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['name' => 'Pegawai Uji', 'email' => 'pegawai@example.test', 'nip' => '002',
            'upt' => 'Puskesmas A', 'roles' => ['PPTK', 'Pejabat Keuangan', 'Bendahara Pengeluaran', 'Kepala Puskesmas'],
            'password' => 'password123', 'password_confirmation' => 'password123'], $overrides);
    }

    private function createUser(): User
    {
        $this->post(route('data-user.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();

        return User::where('email', 'pegawai@example.test')->firstOrFail();
    }

    private function makeStaff(array $overrides = []): User
    {
        return User::create(array_replace([
            'name' => 'Staf Puskesmas', 'email' => 'staff@example.test', 'nip' => '003',
            'upt' => 'Puskesmas A', 'role' => 'Klaster 1', 'roles' => ['Klaster 1'],
            'password' => Hash::make('password123'),
        ], $overrides));
    }

    public function test_superuser_can_assign_four_distinct_roles_and_the_form_lists_all_roles(): void
    {
        $user = $this->createUser();
        $this->assertCount(4, $user->roleNames());
        $this->assertSame('PPTK', $user->role);
        $this->assertTrue($user->hasRole('Pejabat Keuangan'));
        $this->assertTrue($user->hasRole('Bendahara Pengeluaran'));
        $this->assertTrue(Hash::check('password123', $user->password));
        $response = $this->get(route('data-user.index'))->assertOk()->assertSee('Role pengguna (maksimal 4)')
            ->assertSee('Hak akses seluruh role yang dipilih akan digabung.')->assertSee('Bendahara Penerimaan');
        for ($index = 0; $index < 4; $index++) $response->assertSee('id="edit-role-'.$user->id.'-'.$index.'"', false);
    }

    public function test_empty_optional_slots_are_ignored_and_single_legacy_role_requests_remain_supported(): void
    {
        $this->post(route('data-user.store'), $this->payload(['roles' => ['Klaster 2', '', null, '']]))->assertSessionHasNoErrors();
        $user = User::where('email', 'pegawai@example.test')->firstOrFail();
        $this->assertSame(['Klaster 2'], $user->roleNames());
        $legacy = $this->payload(['email' => 'legacy@example.test', 'nip' => '003', 'role' => 'Klaster 3']);
        unset($legacy['roles']);
        $this->post(route('data-user.store'), $legacy)->assertSessionHasNoErrors();
        $this->assertSame(['Klaster 3'], User::where('email', 'legacy@example.test')->firstOrFail()->roleNames());
    }

    public function test_more_than_four_duplicate_unknown_and_empty_roles_are_rejected(): void
    {
        foreach ([['PPTK', 'Pejabat Keuangan', 'Bendahara Pengeluaran', 'Kepala Puskesmas', 'Klaster 2'],
            ['PPTK', 'PPTK'], ['Unknown'], [], 'Superuser'] as $roles) {
            $this->post(route('data-user.store'), $this->payload(['roles' => $roles]))->assertSessionHasErrorsIn('createUser');
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_updates_replace_role_memberships_and_revoke_removed_roles(): void
    {
        $user = $this->createUser();
        $this->put(route('data-user.update', $user->id), $this->payload(['roles' => ['Klaster 2', 'Pejabat Keuangan'],
            'password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('Klaster 2', $user->role);
        $this->assertSame(['Klaster 2', 'Pejabat Keuangan'], $user->roleNames());
        $this->assertFalse($user->hasRole('PPTK'));
        $this->assertFalse($user->hasRole('Bendahara Pengeluaran'));
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_secondary_superuser_can_manage_users_but_ordinary_users_cannot_assign_roles(): void
    {
        $this->admin->update(['role' => 'Klaster 2', 'roles' => ['Klaster 2', 'Superuser']]);
        $this->actingAs($this->admin->fresh());
        $user = $this->createUser();
        $user->update(['roles' => ['PPTK', 'Pejabat Keuangan', 'Bendahara Pengeluaran']]);
        $this->actingAs($user);
        $this->post(route('data-user.store'), $this->payload(['email' => 'escalate@example.test', 'nip' => '004', 'roles' => ['Superuser']]))->assertForbidden();
        $this->put(route('data-user.update', $user->id), $this->payload(['roles' => ['Superuser']]))->assertForbidden();
        $this->delete(route('data-user.destroy', $this->admin->id))->assertForbidden();
        $this->assertFalse($user->fresh()->hasRole('Superuser'));
    }

    public function test_secondary_kepala_puskesmas_can_create_users_only_in_their_own_unit(): void
    {
        $head = $this->createUser();
        $this->actingAs($head);
        $roles = ['Pejabat Keuangan', 'Bendahara Penerimaan', 'PPTK', 'Klaster 1'];
        $this->post(route('data-user.store'), $this->payload([
            'email' => 'staff@example.test', 'nip' => '003', 'upt' => 'Puskesmas B', 'roles' => $roles,
        ]))->assertRedirect(route('data-user.index'))->assertSessionHasNoErrors();

        $staff = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertSame('Puskesmas A', $staff->upt);
        $this->assertSame($roles, $staff->roleNames());
        $this->assertTrue(Hash::check('password123', $staff->password));

        $head->update(['role' => 'Kepala Puskesmas', 'roles' => ['Kepala Puskesmas']]);
        $this->actingAs($head->fresh());
        $payload = $this->payload(['email' => 'staff2@example.test', 'nip' => '004', 'roles' => ['Klaster 2']]);
        unset($payload['upt']);
        $this->post(route('data-user.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'staff2@example.test', 'upt' => 'Puskesmas A']);
    }

    public function test_kepala_puskesmas_form_uses_own_unit_and_local_roles(): void
    {
        $this->actingAs($this->createUser());
        $response = $this->get(route('data-user.index'))->assertOk()->assertSee('Tambah User')
            ->assertSee('Mengikuti puskesmas akun Anda.')
            ->assertViewHas('unitBludOptions', fn ($options) => $options->all() === ['Puskesmas A']);
        for ($index = 0; $index < 4; $index++) $response->assertSee('id="create-role-'.$index.'"', false);
        foreach (User::PUSKESMAS_ROLES as $role) $response->assertSee('value="'.$role.'"', false);
        foreach (['Superuser', 'Dinas', 'Dinas Kesehatan', 'Kepala Labkes', 'Kepala Puskesmas'] as $role) {
            $response->assertDontSee('value="'.$role.'"', false);
        }
        $response->assertDontSee('Puskesmas B')->assertDontSee('form-edit-user-', false);
    }

    public function test_kepala_puskesmas_cannot_assign_global_roles_or_exceed_four_roles(): void
    {
        $this->actingAs($this->createUser());
        foreach (['Superuser', 'Dinas', 'Dinas Kesehatan', 'Kepala Labkes', 'Kepala Puskesmas'] as $role) {
            $this->post(route('data-user.store'), $this->payload([
                'email' => 'staff@example.test', 'nip' => '003', 'roles' => ['PPTK', $role],
            ]))->assertSessionHasErrorsIn('createUser', ['roles.1']);
        }
        $legacy = $this->payload(['email' => 'staff@example.test', 'nip' => '003', 'role' => 'Superuser']);
        unset($legacy['roles']);
        $this->post(route('data-user.store'), $legacy)->assertSessionHasErrorsIn('createUser', ['roles.0']);
        $this->post(route('data-user.store'), $this->payload([
            'email' => 'staff@example.test', 'nip' => '003',
            'roles' => ['PPTK', 'Pejabat Keuangan', 'Klaster 1', 'Klaster 2', 'Klaster 3'],
        ]))->assertSessionHasErrorsIn('createUser', ['roles']);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_kepala_puskesmas_with_missing_or_unknown_unit_cannot_choose_another_unit(): void
    {
        $head = $this->createUser();
        foreach ([null, '', 'Unit tidak terdaftar'] as $upt) {
            $head->update(['upt' => $upt]);
            $this->actingAs($head->fresh());
            $this->post(route('data-user.store'), $this->payload([
                'email' => 'staff@example.test', 'nip' => '003', 'upt' => 'Puskesmas B', 'roles' => ['PPTK'],
            ]))->assertSessionHasErrorsIn('createUser', ['upt']);
        }
        $this->assertDatabaseCount('users', 2);
    }

    public function test_kepala_puskesmas_cannot_update_account_details_or_delete_users(): void
    {
        $head = $this->createUser();
        $this->actingAs($head);
        $this->put(route('data-user.update', $head->id), $this->payload(['roles' => ['Superuser']]))->assertForbidden();
        $this->delete(route('data-user.destroy', $this->admin->id))->assertForbidden();
        $this->assertFalse($head->fresh()->hasRole('Superuser'));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_kepala_puskesmas_can_replace_staff_roles_without_changing_other_account_data(): void
    {
        $head = $this->createUser();
        $staff = $this->makeStaff();
        $before = $staff->getAttributes();
        $this->actingAs($head);
        $roles = ['Pejabat Keuangan', 'Bendahara Pengeluaran', 'PPTK', 'Klaster 2'];
        $this->put(route('data-user.roles.update', $staff->id), [
            'roles' => $roles, 'name' => 'Nama lain', 'email' => 'changed@example.test', 'nip' => '999',
            'upt' => 'Puskesmas B', 'password' => 'changedpassword', 'role' => 'Superuser',
        ])->assertRedirect(route('data-user.index'))->assertSessionHasNoErrors();
        $staff->refresh();
        $this->assertSame($roles, $staff->roleNames());
        $this->assertSame('Pejabat Keuangan', $staff->role);
        $this->assertFalse($staff->hasRole('Klaster 1'));
        foreach (['name', 'email', 'nip', 'upt', 'password'] as $field) {
            $this->assertSame($before[$field], $staff->$field);
        }
        $this->assertTrue($staff->hasRole('Bendahara Pengeluaran'));
    }

    public function test_kepala_puskesmas_cannot_edit_roles_in_another_unit_or_on_superuser_accounts(): void
    {
        $head = $this->createUser();
        $foreign = $this->makeStaff(['upt' => 'Puskesmas B']);
        $this->admin->update(['role' => 'PPTK', 'roles' => ['PPTK', 'Superuser']]);
        $this->actingAs($head);
        foreach ([$foreign, $this->admin] as $target) {
            $this->put(route('data-user.roles.update', $target->id), ['roles' => ['Pejabat Keuangan']])->assertForbidden();
            $this->assertSame($target->roleNames(), $target->fresh()->roleNames());
        }
        $head->update(['upt' => null]);
        $foreign->update(['upt' => null]);
        $this->actingAs($head->fresh());
        $this->put(route('data-user.roles.update', $foreign->id), ['roles' => ['PPTK']])->assertForbidden();
    }

    public function test_only_kepala_puskesmas_and_superuser_can_use_the_role_update_endpoint(): void
    {
        $staff = $this->makeStaff();
        $this->actingAs($staff);
        $this->put(route('data-user.roles.update', $staff->id), ['roles' => ['PPTK']])->assertForbidden();
        $this->get(route('data-user.index'))->assertOk()->assertDontSee('data-bs-target="#roleModal-', false);
        $this->actingAs($this->admin);
        $this->put(route('data-user.roles.update', $staff->id), ['roles' => ['Kepala Puskesmas', 'PPTK']])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Kepala Puskesmas', 'PPTK'], $staff->fresh()->roleNames());
    }

    public function test_existing_head_roles_are_preserved_and_count_toward_the_four_role_limit(): void
    {
        $head = $this->createUser();
        $this->actingAs($head);
        $this->put(route('data-user.roles.update', $head->id), ['roles' => ['PPTK', 'Klaster 1', 'Klaster 2', 'Klaster 3']])
            ->assertSessionHasErrorsIn('updateRoles', ['roles']);
        $this->put(route('data-user.roles.update', $head->id), ['roles' => ['Klaster 2', 'PPTK', 'Pejabat Keuangan']])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Kepala Puskesmas', 'Klaster 2', 'PPTK', 'Pejabat Keuangan'], $head->fresh()->roleNames());
        $this->actingAs($head->fresh());
        $this->put(route('data-user.roles.update', $head->id), ['roles' => ['', '', '']])->assertSessionHasNoErrors();
        $this->assertSame(['Kepala Puskesmas'], $head->fresh()->roleNames());
    }

    public function test_kepala_puskesmas_role_updates_validate_roles_and_restore_the_error_modal(): void
    {
        $head = $this->createUser();
        $staff = $this->makeStaff();
        $this->actingAs($head);
        foreach ([['Superuser'], ['Dinas'], ['Dinas Kesehatan'], ['Kepala Puskesmas'], ['Kepala Labkes'],
            ['PPTK', 'PPTK'], ['Unknown'], [], [''], 'PPTK',
            ['PPTK', 'Klaster 1', 'Klaster 2', 'Klaster 3', 'Klaster 4']] as $roles) {
            $this->from(route('data-user.index'))->put(route('data-user.roles.update', $staff->id), ['roles' => $roles])
                ->assertRedirect(route('data-user.index'))->assertSessionHasErrorsIn('updateRoles');
        }
        $this->assertSame(['Klaster 1'], $staff->fresh()->roleNames());
        $this->get(route('data-user.index'))->assertOk()
            ->assertSee('id="roleModal-'.$staff->id.'"', false)->assertSee('data-user-error-modal="1"', false);
    }

    public function test_kepala_puskesmas_role_form_lists_own_staff_and_reserves_slots_for_locked_roles(): void
    {
        $head = $this->createUser();
        $staff = $this->makeStaff();
        $foreign = $this->makeStaff(['email' => 'foreign@example.test', 'nip' => '004', 'upt' => 'Puskesmas B']);
        $this->actingAs($head);
        $response = $this->get(route('data-user.index'))->assertOk()->assertSee('Edit Role User')
            ->assertSee('data-bs-target="#roleModal-'.$staff->id.'"', false)
            ->assertDontSee('data-bs-target="#roleModal-'.$foreign->id.'"', false)
            ->assertDontSee('data-bs-target="#roleModal-'.$this->admin->id.'"', false)
            ->assertDontSee('value="Kepala Puskesmas"', false)
            ->assertDontSee('data-confirm-delete', false);
        for ($slot = 0; $slot < 4; $slot++) $response->assertSee('id="edit-local-role-'.$staff->id.'-'.$slot.'"', false);
        $response->assertSee('id="edit-local-role-'.$head->id.'-2"', false)
            ->assertDontSee('id="edit-local-role-'.$head->id.'-3"', false);
    }

    public function test_role_queries_find_secondary_roles_and_do_not_leak_to_other_units(): void
    {
        $user = $this->createUser();
        $this->assertSame($user->id, User::withRole('bendahara pengeluaran')->firstOrFail()->id);
        $this->assertSame($user->id, User::withRole('Pejabat Keuangan')->where('upt', 'Puskesmas A')->firstOrFail()->id);
        $this->assertNull(User::withRole('Pejabat Keuangan')->where('upt', 'Puskesmas B')->first());
        $this->actingAs($user);
        $this->get(route('data-user.index'))->assertOk()->assertViewHas('data_user', fn ($rows) => $rows->count() === 1 && $rows[0]->id === $user->id);
    }

    public function test_all_middlewares_and_review_helpers_recognize_secondary_roles(): void
    {
        $user = new User(['role' => 'Klaster 2', 'roles' => ['Klaster 2', 'Kepala Puskesmas', 'Superuser', 'Dinas Kesehatan'], 'upt' => 'Puskesmas A']);
        $this->actingAs($user);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $next = fn () => response('ok');
        $this->assertSame(200, (new EnsureSuperuser())->handle($request, $next)->getStatusCode());
        $this->assertSame(200, (new EnsureKapusKtu())->handle($request, $next)->getStatusCode());
        $this->assertSame(200, (new RoleMiddleware())->handle($request, $next, 'Kepala Puskesmas')->getStatusCode());
        $this->assertTrue(AlurPerubahanPendapatan::isSuperuser($user));
        $this->assertTrue(AlurPerubahanPendapatan::isDinas($user));
    }

    public function test_stale_primary_role_does_not_restore_removed_privileges(): void
    {
        $user = $this->admin;
        $user->update(['roles' => ['Klaster 2']]);
        $this->assertFalse($user->hasRole('Superuser'));
        $this->assertFalse(User::withRole('Superuser')->whereKey($user->id)->exists());
    }

    public function test_migration_backfills_existing_roles_without_assigning_additional_permissions(): void
    {
        $this->migration()->down();
        $this->migration()->up();
        $user = $this->admin->fresh();
        $this->assertSame(['Superuser'], $user->roleNames());
        $this->assertSame('Superuser', $user->role);
        $this->assertSame('Puskesmas A', $user->upt);
        $legacy = new User(['role' => 'Bendahara Pengeluaran']);
        $this->assertTrue($legacy->hasRole('bendahara pengeluaran'));
    }

    public function test_one_user_can_complete_ls_ppd_opd_and_spd_using_combined_roles(): void
    {
        foreach (['2026_09_28_033029_create_ppd_lss_table.php', '2026_09_28_075323_add_rekening_and_uraian_to_ppd_lss_table.php',
            '2026_09_28_033036_create_opd_lss_table.php', '2026_09_28_033046_create_spd_lss_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $user = $this->createUser();
        $this->actingAs($user);
        (require database_path('migrations/2026_10_21_000007_add_budget_year_to_angkas_and_payment_requests.php'))->up();
        foreach ([2026, 2027] as $year) {
            $this->post(route('tahun-anggaran.update'), ['tahun' => $year])->assertRedirect();
            $this->post(route('ppd-ls.store'), [
                'nomor' => 'PPD-LS-'.$year, 'jumlah' => '1000000', 'no_rek' => '12345', 'no_sk' => 'SK-001',
                'tanggal_sk' => '2026-10-21', 'bank_asal' => 'Bank A', 'kepada' => 'Penerima Uji', 'bank_tujuan' => 'Bank B',
                'no_rek_tujuan' => '67890', 'potongan' => '0', 'jumlah_akhir' => '1000000',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $ppd = DB::table('ppd_lss')->where('tahun_anggaran', $year)->first();
            $this->assertNotNull($ppd);
            $this->post(route('opd-ls.store'), ['nomor' => 'OPD-LS-'.$year, 'nomor_dba' => 'DBA-'.$year,
                'tanggal_dba' => $year.'-10-21', 'ppd_ls_id' => $ppd->id])->assertRedirect()->assertSessionHasNoErrors();
            $opd = DB::table('opd_lss')->where('tahun_anggaran', (string) $year)->first();
            $this->assertNotNull($opd);
            $this->post(route('spd-ls.store'), ['nomor' => 'SPD-LS-'.$year, 'opd_ls_id' => $opd->id])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('spd_lss', ['opd_ls_id' => $opd->id, 'unit_blud_id' => 1,
                'tahun_anggaran' => (string) $year, 'jumlah_akhir' => 1000000]);
        }
    }

    public function test_login_retains_combined_roles_without_selecting_an_active_role(): void
    {
        $this->createUser();
        auth()->logout();
        $this->post('/login', ['email' => 'pegawai@example.test', 'password' => 'password123'])->assertRedirect();
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('Pejabat Keuangan'));
        $this->assertTrue(auth()->user()->hasRole('Bendahara Pengeluaran'));
        $this->get(route('data-user.index'))->assertOk()->assertSee('BKU Pejabat Keuangan')->assertSee('BKU Bendahara Pengeluaran');
    }
}
