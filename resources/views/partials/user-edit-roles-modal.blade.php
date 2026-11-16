@php
    $editableRoles = array_values(array_diff($item->roleNames(), $lockedRoles));
    $roleSlots = max(0, 4 - count($lockedRoles));
@endphp
<div id="roleModal-{{ $item->id }}" class="modal fade" tabindex="-1"
    aria-labelledby="roleModalLabel-{{ $item->id }}" aria-hidden="true"
    data-user-error-modal="{{ $errors->updateRoles->any() && old('edit_role_id') == $item->id ? '1' : '0' }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="roleModalLabel-{{ $item->id }}" class="modal-title">Edit Role User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form method="POST" action="{{ route('data-user.roles.update', $item->id) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="edit_role_id" value="{{ $item->id }}">
                <div class="modal-body">
                    <p class="mb-1"><strong>{{ $item->name }}</strong></p>
                    <p class="text-muted">{{ $item->upt }}</p>
                    @if ($lockedRoles !== [])
                        <p class="mb-1">Role tetap:</p>
                        <p>@foreach($lockedRoles as $lockedRole)<span class="badge bg-secondary me-1">{{ $lockedRole }}</span>@endforeach</p>
                        <p class="small text-muted">Role tetap dikelola Superuser dan termasuk dalam batas maksimal 4 role.</p>
                    @endif
                    <div class="row">
                        @include('partials.user-role-fields', [
                            'selectedRoles' => old('edit_role_id') == $item->id ? old('roles', []) : $editableRoles,
                            'roleFieldId' => 'edit-local-role-'.$item->id,
                            'roleErrorBag' => 'updateRoles',
                            'roleOptions' => $createRoleOptions,
                            'roleSlotCount' => $roleSlots,
                            'requireFirstRole' => $lockedRoles === [],
                        ])
                    </div>
                    @if ($roleSlots === 0)<input type="hidden" name="roles[]" value="">@endif
                </div>
                <div class="modal-footer">
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-save">Simpan Role</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
