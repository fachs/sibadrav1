@php $selectedRoles = is_array($selectedRoles ?? null) ? $selectedRoles : []; @endphp
<div class="col-12 mb-3" data-role-assignment>
    <p class="form-label mb-1">Role pengguna (maksimal 4)</p>
    <p class="small text-muted">Hak akses seluruh role yang dipilih akan digabung.</p>
    <div class="row g-2">
        @for($index = 0; $index < ($roleSlotCount ?? 4); $index++)
            <div class="col-md-6">
                <label for="{{ $roleFieldId }}-{{ $index }}" class="form-label">Role {{ $index + 1 }}{{ $index === 0 ? '' : ' (opsional)' }}</label>
                <select id="{{ $roleFieldId }}-{{ $index }}" name="roles[]" class="form-select" @required($index === 0 && ($requireFirstRole ?? true))>
                    <option value="">{{ $index === 0 && ($requireFirstRole ?? true) ? 'Pilih role' : 'Tidak ditambahkan' }}</option>
                    @foreach($roleOptions ?? \App\Models\User::AVAILABLE_ROLES as $roleOption)<option value="{{ $roleOption }}" @selected(($selectedRoles[$index] ?? '') === $roleOption)>{{ $roleOption }}</option>@endforeach
                </select>
            </div>
        @endfor
    </div>
    @foreach($errors->getBag($roleErrorBag)->get('roles*') as $messages)
        @foreach((array) $messages as $message)<div class="text-danger small mt-1">{{ $message }}</div>@endforeach
    @endforeach
</div>
