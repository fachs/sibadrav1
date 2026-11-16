<?php

namespace App\Http\Controllers;
use App\Models\UnitBlud;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DataUserController extends Controller
{

    private function ensureSuperuser(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->hasRole('Superuser'),
            403,
            'Akses ditolak. Hanya Superuser.'
        );
    }

    public function index()
    {
        $user = auth()->user();

        $unitBludOptions = UnitBlud::when(!$user->hasRole('Superuser'), fn ($query) => $query->where('nama', $user->upt))
            ->orderBy('nama')->pluck('nama');
        $createRoleOptions = $this->createRoleOptions($user);
        $query = User::query();

        if (!$user->hasRole('Superuser')) {
            $query->where('upt', $user->upt)->whereNot(fn ($query) => $query->withRole('Superuser'));
        }

        $data_user = $query->get();
        $lockedRolesByUser = $data_user->mapWithKeys(fn ($member) => [$member->id => $this->lockedRoles($member)]);

        return view('data-user', compact('data_user', 'unitBludOptions', 'createRoleOptions', 'lockedRolesByUser'));
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && $actor->hasAnyRole(['Superuser', 'Kepala Puskesmas']), 403, 'Akses ditolak.');

        if (!$actor->hasRole('Superuser')) {
            // Kepala Puskesmas hanya dapat membuat akun untuk unitnya sendiri.
            $request->merge(['upt' => $actor->upt]);
        }
        $this->prepareRoles($request);

        $validated = $request->validateWithBag('createUser', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'nip' => ['required', 'string', 'max:255', 'unique:users,nip'],
            'roles' => ['required', 'array', 'min:1', 'max:4'],
            'roles.*' => ['required', 'string', 'distinct:ignore_case', Rule::in($this->createRoleOptions($actor))],
            'upt' => ['required', 'string', 'exists:unit_bluds,nama'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nip' => $validated['nip'],
            'role' => $validated['roles'][0],
            'roles' => $validated['roles'],
            'upt' => $validated['upt'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('data-user.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $this->ensureSuperuser();
        $this->prepareRoles($request);

        $user = User::findOrFail($id);

        $validated = $request->validateWithBag('updateUser', [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:255', Rule::unique('users', 'nip')->ignore($user->id)],
            'roles' => ['required', 'array', 'min:1', 'max:4'],
            'roles.*' => ['required', 'string', 'distinct:ignore_case', Rule::in(User::AVAILABLE_ROLES)],
            'upt' => ['required', 'string', 'exists:unit_bluds,nama'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'edit_id' => ['nullable'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'nip' => $validated['nip'],
            'role' => $validated['roles'][0],
            'roles' => $validated['roles'],
            'upt' => $validated['upt'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        return redirect()->route('data-user.index')->with('success', 'User berhasil diupdate.');
    }

    public function updateRoles(Request $request, $id)
    {
        $actor = $request->user();
        abort_unless($actor && $actor->hasAnyRole(['Superuser', 'Kepala Puskesmas']), 403, 'Akses ditolak.');
        $user = User::findOrFail($id);

        if (!$actor->hasRole('Superuser')) {
            abort_unless(
                filled($actor->upt) && $user->upt === $actor->upt && !$user->hasRole('Superuser'),
                403,
                'Anda hanya dapat mengubah role pengguna di puskesmas Anda.'
            );
        }

        $lockedRoles = $actor->hasRole('Superuser') ? [] : $this->lockedRoles($user);
        $request->merge(['edit_role_id' => (string) $user->id]);
        $this->prepareRoles($request);
        $validated = $request->validateWithBag('updateRoles', [
            'roles' => [$lockedRoles === [] ? 'required' : 'present', 'array',
                'min:'.($lockedRoles === [] ? 1 : 0), 'max:'.max(0, 4 - count($lockedRoles))],
            'roles.*' => ['required', 'string', 'distinct:ignore_case', Rule::in($this->createRoleOptions($actor))],
        ]);

        $roles = array_merge($lockedRoles, $validated['roles']);
        $user->update(['role' => $roles[0], 'roles' => $roles]);

        return redirect()->route('data-user.index')->with('success', 'Role user berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $this->ensureSuperuser();

        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('data-user.index')->with('success', 'User berhasil dihapus.');
    }

    private function createRoleOptions(User $user): array
    {
        return $user->hasRole('Superuser') ? User::AVAILABLE_ROLES : User::PUSKESMAS_ROLES;
    }

    private function lockedRoles(User $user): array
    {
        $localRoles = array_map('strtolower', User::PUSKESMAS_ROLES);

        return array_values(array_filter($user->roleNames(), fn ($role) => !in_array(strtolower($role), $localRoles, true)));
    }

    private function prepareRoles(Request $request): void
    {
        $roles = $request->input('roles', $request->filled('role') ? [$request->input('role')] : null);
        if (is_array($roles)) {
            $request->merge(['roles' => array_values(array_filter($roles, fn ($role) => $role !== '' && $role !== null))]);
        }
    }

}
