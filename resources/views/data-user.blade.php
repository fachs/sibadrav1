@extends('layouts.vertical', ['title' => 'Pengaturan','subTitle' => 'Data User'])
@section('css')
    <!-- DataTables CDN CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endsection

@section('body')

    <body data-sidebar="dark">
    @endsection
    @section('content')

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @php
                            $isSuperuser = auth()->user()->hasRole('Superuser');
                            $canEditRoles = auth()->user()->hasAnyRole(['Superuser', 'Kepala Puskesmas']);
                        @endphp

                        @if (auth()->user()->hasAnyRole(['Superuser', 'Kepala Puskesmas']))
                            <div class="d-flex justify-content-end mb-3">
                                <button type="button" class="btn btn-primary" id="btn-new-event"
                                    data-bs-toggle="modal" data-bs-target="#myModal">
                                    <i class="ri-add-line"></i> Tambah User
                                </button>
                            </div>

                            <div id="myModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-user-error-modal="{{ $errors->createUser->any() ? '1' : '0' }}">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header py-3 px-4">
                                            <h5 class="modal-title">Tambah User</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <form method="POST" action="{{ route('data-user.store') }}"
                                                class="needs-validation" id="form-create-user" novalidate>
                                                @csrf
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Nama</label>
                                                            <input
                                                                class="form-control @error('name', 'createUser') is-invalid @enderror"
                                                                type="text" name="name" id="name" required
                                                                value="{{ old('name') }}" autocomplete="name" />
                                                            <div class="invalid-feedback">Please provide a valid event name
                                                            </div>

                                                            @error('name', 'createUser')
                                                                <span class="invalid-feedback d-block" role="alert">
                                                                    <strong>{{ $message }}</strong>
                                                                </span>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">NIP</label>
                                                            <input
                                                                class="form-control @error('nip', 'createUser') is-invalid @enderror"
                                                                type="text" name="nip" id="nip"
                                                                value="{{ old('nip') }}" required autocomplete="nip" />
                                                            <div class="invalid-feedback">Please provide a valid event name
                                                            </div>

                                                            @error('nip', 'createUser')
                                                                <span class="invalid-feedback d-block" role="alert">
                                                                    <strong>{{ $message }}</strong>
                                                                </span>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    @include('partials.user-role-fields', ['selectedRoles' => old('roles', []), 'roleFieldId' => 'create-role', 'roleErrorBag' => 'createUser', 'roleOptions' => $createRoleOptions])

                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">BLUD</label>
                                                            @if ($isSuperuser)
                                                            <select
                                                                class="form-select @error('upt', 'createUser') is-invalid @enderror"
                                                                name="upt" id="upt" required>
                                                                <option value=""> --Pilih-- </option>
                                                                @foreach ($unitBludOptions as $uptNama)
                                                                    <option value="{{ $uptNama }}"
                                                                        {{ old('upt') == $uptNama ? 'selected' : '' }}>
                                                                        {{ $uptNama }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @else
                                                                <input type="text" name="upt" id="upt" value="{{ auth()->user()->upt }}"
                                                                    class="form-control @error('upt', 'createUser') is-invalid @enderror" readonly>
                                                                <div class="form-text">Mengikuti puskesmas akun Anda.</div>
                                                            @endif
                                                            <div class="invalid-feedback">Please provide a valid event name
                                                            </div>

                                                            @error('upt', 'createUser')
                                                                <span class="invalid-feedback d-block" role="alert">
                                                                    <strong>{{ $message }}</strong>
                                                                </span>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="col-md-12">
                                                        <div class="mb-3">
                                                            <label class="form-label">Email</label>
                                                            <input
                                                                class="form-control @error('email', 'createUser') is-invalid @enderror"
                                                                type="email" name="email" id="email" required
                                                                value="{{ old('email') }}" />
                                                            <div class="invalid-feedback">Please provide a valid event name
                                                            </div>

                                                            @error('email', 'createUser')
                                                                <span class="invalid-feedback d-block" role="alert">
                                                                    <strong>{{ $message }}</strong>
                                                                </span>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Password</label>
                                                            <div class="input-group has-validation">
                                                            <input
                                                                class="form-control @error('password', 'createUser') is-invalid @enderror"
                                                                type="password" name="password" id="password" autocomplete="new-password" required />
                                                            <button type="button" class="btn btn-outline-secondary" data-user-password-toggle
                                                                aria-controls="password" aria-pressed="false" aria-label="Tampilkan password" title="Tampilkan password"><i class="ri-eye-line" aria-hidden="true"></i></button>
                                                            <div class="invalid-feedback">Please provide a valid event name
                                                            </div>
                                                            </div>

                                                            @error('password', 'createUser')
                                                                <span class="invalid-feedback d-block" role="alert">
                                                                    <strong>{{ $message }}</strong>
                                                                </span>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Konfirmasi Password</label>
                                                            <div class="input-group has-validation">
                                                            <input
                                                                class="form-control @error('password_confirmation', 'createUser') is-invalid @enderror"
                                                                type="password" name="password_confirmation"
                                                                id="password-confirm" autocomplete="new-password" required />
                                                            <button type="button" class="btn btn-outline-secondary" data-user-password-toggle
                                                                aria-controls="password-confirm" aria-pressed="false" aria-label="Tampilkan password" title="Tampilkan password"><i class="ri-eye-line" aria-hidden="true"></i></button>
                                                            <div class="invalid-feedback">Please provide a valid event name
                                                            </div>
                                                            </div>

                                                            @error('password_confirmation', 'createUser')
                                                                <span class="invalid-feedback d-block" role="alert">
                                                                    <strong>{{ $message }}</strong>
                                                                </span>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                </div>
                                        </div>
                                        <div class="row m-3">
                                            <div class="col-6">

                                            </div>
                                            <div class="col-6 text-end">
                                                <div class="form-actions">
                                                    <button type="button" class="btn btn-cancel"
                                                    data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-save"
                                                    id="btn-save-event">Simpan</button>
                                                </div>
                                            </div>
                                        </div>
                                        </form>
                                    </div>
                                </div> <!-- end modal-content-->
                            </div> <!-- end modal dialog-->
                        @endif
                            <table id="userTable" class="table table-striped table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>NIP</th>
                                        <th>Nama Pegawai</th>
                                        <th>Role</th>
                                        <th>BLUD</th>
                                        @if ($isSuperuser)
                                            <th>Email</th>
                                        @endif
                                        @if ($canEditRoles)
                                            <th>Aksi</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data_user as $item)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $item->nip }}</td>
                                            <td>{{ $item->name }}</td>
                                            <td>@foreach($item->roleNames() as $assignedRole)<span class="badge bg-secondary me-1 mb-1">{{ $assignedRole }}</span>@endforeach</td>
                                            <td>{{ $item->upt }}</td>
                                            @if ($isSuperuser)
                                                <td>{{ $item->email }}</td>
                                            @endif
                                            @if ($canEditRoles)
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        @if ($isSuperuser)
                                                        <button type="button" class="btn btn-info"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#myModal2-{{ $item->id }}">
                                                            <i class="ri-edit-box-line"></i>
                                                        </button>

                                                        <form action="{{ route('data-user.destroy', $item->id) }}"
                                                            method="POST" class="m-0"
                                                            data-confirm-delete data-confirm-message="Yakin ingin menghapus user ini?">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="ri-delete-bin-2-line"></i>
                                                            </button>
                                                        </form>
                                                        @else
                                                            <button type="button" class="btn btn-info" data-bs-toggle="modal"
                                                                data-bs-target="#roleModal-{{ $item->id }}" title="Edit role" aria-label="Edit role {{ $item->name }}">
                                                                <i class="ri-edit-box-line" aria-hidden="true"></i>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                        @if ($canEditRoles && !$isSuperuser)
                                            @include('partials.user-edit-roles-modal', ['lockedRoles' => $lockedRolesByUser[$item->id]])
                                        @endif
                                        @if ($isSuperuser)
                                            <div id="myModal2-{{ $item->id }}" class="modal fade" tabindex="-1" data-user-error-modal="{{ $errors->updateUser->any() && old('edit_id') == $item->id ? '1' : '0' }}"
                                                aria-labelledby="editUserLabel-{{ $item->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header py-3 px-4">
                                                            <h5 class="modal-title"
                                                                id="editUserLabel-{{ $item->id }}">Edit User</h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>

                                                        <div class="modal-body p-4">
                                                            <form method="POST"
                                                                action="{{ route('data-user.update', $item->id) }}"
                                                                class="needs-validation" novalidate>
                                                                @csrf
                                                                @method('PUT')
                                                                <input type="hidden" name="edit_id"
                                                                    value="{{ $item->id }}">

                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Nama</label>
                                                                        <input
                                                                            class="form-control @error('name', 'updateUser') is-invalid @enderror"
                                                                            type="text" name="name"
                                                                            value="{{ old('edit_id') == $item->id ? old('name') : $item->name }}"
                                                                            required>
                                                                    </div>

                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">NIP</label>
                                                                        <input
                                                                            class="form-control @error('nip', 'updateUser') is-invalid @enderror"
                                                                            type="text" name="nip"
                                                                            value="{{ old('edit_id') == $item->id ? old('nip') : $item->nip }}"
                                                                            required>
                                                                    </div>

                                                                    @include('partials.user-role-fields', ['selectedRoles' => old('edit_id') == $item->id ? old('roles', []) : $item->roleNames(), 'roleFieldId' => 'edit-role-'.$item->id, 'roleErrorBag' => 'updateUser'])

                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">BLUD</label>
                                                                        @php
                                                                            $selectedUpt =
                                                                                old('edit_id') == $item->id
                                                                                    ? old('upt')
                                                                                    : $item->upt;
                                                                        @endphp
                                                                        <select
                                                                            class="form-select @error('upt', 'updateUser') is-invalid @enderror"
                                                                            name="upt" required>
                                                                            <option value=""> --Pilih-- </option>
                                                                            @foreach ($unitBludOptions as $uptNama)
                                                                                <option value="{{ $uptNama }}"
                                                                                    {{ $selectedUpt == $uptNama ? 'selected' : '' }}>
                                                                                    {{ $uptNama }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>

                                                                    <div class="col-md-12 mb-3">
                                                                        <label class="form-label">Email</label>
                                                                        <input
                                                                            class="form-control @error('email', 'updateUser') is-invalid @enderror"
                                                                            type="email" name="email"
                                                                            value="{{ old('edit_id') == $item->id ? old('email') : $item->email }}"
                                                                            required>
                                                                    </div>

                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Password Baru
                                                                            (opsional)</label>
                                                                        <div class="input-group has-validation">
                                                                        <input
                                                                            class="form-control @error('password', 'updateUser') is-invalid @enderror"
                                                                            type="password" name="password" id="edit-password-{{ $item->id }}" autocomplete="new-password">
                                                                        <button type="button" class="btn btn-outline-secondary" data-user-password-toggle
                                                                            aria-controls="edit-password-{{ $item->id }}" aria-pressed="false" aria-label="Tampilkan password" title="Tampilkan password"><i class="ri-eye-line" aria-hidden="true"></i></button>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Konfirmasi
                                                                            Password</label>
                                                                        <div class="input-group has-validation">
                                                                        <input class="form-control" type="password"
                                                                            name="password_confirmation" id="edit-password-confirm-{{ $item->id }}" autocomplete="new-password">
                                                                        <button type="button" class="btn btn-outline-secondary" data-user-password-toggle
                                                                            aria-controls="edit-password-confirm-{{ $item->id }}" aria-pressed="false" aria-label="Tampilkan password" title="Tampilkan password"><i class="ri-eye-line" aria-hidden="true"></i></button>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="text-end">
                                                                    <div class="form-actions">
                                                                        <button type="button" class="btn btn-cancel"
                                                                        data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit"
                                                                        class="btn btn-save">Update</button>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


            </div> <!-- end col -->
        </div> <!-- end row -->

    @endsection
    @section('scripts')
    <!-- Required datatable js -->
    <script src="{{ URL::asset('assets/libs/moment/moment.min.js') }}"></script>

    <!-- DataTables CDN JS -->

    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#userTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                responsive: true,
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    infoEmpty: "Tidak ada data",
                    zeroRecords: "Data tidak ditemukan",
                    paginate: {
                        first: "Awal",
                        last: "Akhir",
                        next: "Next",
                        previous: "Prev"
                    }
                }
            });
        });
    </script>

    <script src="{{ URL::asset('assets/js/app.js') }}"></script>

    @if ($errors->createUser->any())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modalEl = document.getElementById('myModal');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        </script>
    @endif

    @if ($errors->updateUser->any() && old('edit_id'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modalEl = document.getElementById('myModal2-{{ old('edit_id') }}');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        </script>
    @endif
@endsection


@section('script')
    <script src="{{ asset('js/user-roles.js') }}"></script>
@endsection
