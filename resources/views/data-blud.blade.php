@extends('layouts.vertical', ['title' => 'Data UPTD','subTitle' => 'Pengaturan'])


@section('body') 
@endsection
    @php
    $isSuperuser = auth()->user()->hasRole('Superuser');
@endphp
    @section('content')

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    @if($isSuperuser)
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" class="btn btn-primary" id="btn-new-event"
                                data-bs-toggle="modal" data-bs-target="#myModal">
                                <i class="ri-add-line"></i> Tambah UPTD
                            </button>
                        </div>

                        <div id="myModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header py-3 px-4">
                                        <h5 class="modal-title">Tambah UPTD</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <form method="POST" action="{{ route('data-blud.store') }}" class="needs-validation" name="event-form" id="form-event" novalidate>
                                            @csrf
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama</label>
                                                        <input class="form-control @error('nama') is-invalid @enderror" type="text" name="nama" id="nama" required value="{{ old('nama') }}" autocomplete="nama"/>
                                                        <div class="invalid-feedback">Please provide a valid event name</div>

                                                        @error('nama')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                        @enderror
                                                    </div>      
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Kepala/Direktur</label>
                                                        <input class="form-control @error('kepala') is-invalid @enderror" type="text" name="kepala" id="kepala" value="{{ old('kepala') }}" required autocomplete="kepala" />
                                                        <div class="invalid-feedback">Please provide a valid event name</div>
                                                        
                                                        @error('kepala')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">NIP Kepala/Direktur</label>
                                                        <input class="form-control @error('kepala') is-invalid @enderror" type="text" name="nip_kapus" id="nip_kapus" value="{{ old('nip_kapus') }}" required autocomplete="nip_kapus" />
                                                        <div class="invalid-feedback">Please provide a valid event name</div>
                                                        
                                                        @error('nip_kapus')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Kasubbag TU</label>
                                                        <input class="form-control @error('kasubbag_tu') is-invalid @enderror" type="text" name="kasubbag_tu" id="kasubbag_tu" value="{{ old('kasubbag_tu') }}" required autocomplete="kasubbag_tu" />
                                                        <div class="invalid-feedback">Please provide a valid event name</div>
                                                        
                                                        @error('kasubbag_tu')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">NIP Kasubbag TU</label>
                                                        <input class="form-control @error('nip_ktu') is-invalid @enderror" type="text" name="nip_ktu" id="nip_ktu" value="{{ old('nip_ktu') }}" required autocomplete="nip_ktu" />
                                                        <div class="invalid-feedback">Please provide a valid event name</div>
                                                        
                                                        @error('nip_ktu')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Kontak</label>
                                                        <input class="form-control @error('kontak') is-invalid @enderror" type="text" name="kontak" id="kontak" value="{{ old('kontak') }}" required autocomplete="kontak" />
                                                        <div class="invalid-feedback">Please provide a valid event name</div>
                                                        
                                                        @error('kontak')
                                                        <span class="invalid-feedback" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Alamat</label>
                                                        <input class="form-control" type="text" name="alamat" id="alamat" required value="{{ old('alamat') }}" />
                                                        <div class="invalid-feedback">Please provide a valid event name</div>

                                                        @error('alamat')
                                                        <span class="invalid-feedback" role="alert">
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
                                                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-save" id="btn-save-event">Simpan</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div> <!-- end modal-content-->
                        </div> <!-- end modal dialog-->
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Kepala/Direktur</th>
                                    <th>Kasubbag TU</th>
                                    <th>No. Kontak</th>
                                    <th>Alamat</th>
                                    @if($isSuperuser)<th>Aksi</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data_blud as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $item->nama }}</td>
                                    <td>{{ $item->kepala }}</td>
                                    <td>{{ $item->kasubbag_tu }}</td>
                                    <td>{{ $item->kontak }}</td>
                                    <td>{{ $item->alamat }}</td>
                                    @if($isSuperuser)
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-info me-2"
                                                data-bs-toggle="modal" data-bs-target="#myModal2-{{ $item->id }}">
                                                <i class="ri-edit-box-line"></i>
                                            </button>
                                            <form action="{{ route('data-blud.destroy', $item->id) }}" method="POST" class="m-0"
                                                data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="ri-delete-bin-2-line"></i>
                                                </button>
                                            </form>
                                        </div>
                                        {{-- modal edit tetap di sini --}}
                                        <div id="myModal2-{{ $item->id }}" class="modal fade" tabindex="-1" aria-labelledby="editModalLabel-{{ $item->id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header py-3 px-4">
                                                    <h5 class="modal-title" id="editModalLabel-{{ $item->id }}">Edit UPTD</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <div class="modal-body p-4">
                                                    <form method="POST" action="{{ route('data-blud.update', $item->id) }}" novalidate>
                                                        @csrf
                                                        @method('PUT')

                                                        <div class="mb-3">
                                                            <label class="form-label">Nama</label>
                                                            <input class="form-control" type="text" name="nama" value="{{ old('nama', $item->nama) }}" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Kepala/Direktur</label>
                                                            <input class="form-control" type="text" name="kepala" value="{{ old('kepala', $item->kepala) }}" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">NIP Kepala/Direktur</label>
                                                            <input class="form-control" type="text" name="nip_kapus" value="{{ old('nip_kapus', $item->nip_kapus) }}" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Kasubbag TU</label>
                                                            <input class="form-control" type="text" name="kasubbag_tu" value="{{ old('kasubbag_tu', $item->kasubbag_tu) }}" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">NIP Kasubbag TU</label>
                                                            <input class="form-control" type="text" name="nip_ktu" value="{{ old('nip_ktu', $item->nip_ktu) }}" required>
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Kontak</label>
                                                                <input class="form-control" type="text" name="kontak" value="{{ old('kontak', $item->kontak) }}" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Alamat</label>
                                                                <input class="form-control" type="text" name="alamat" value="{{ old('alamat', $item->alamat) }}" required>
                                                            </div>
                                                        </div>

                                                        <div class="text-end">
                                                            <div class="form-actions">
                                                                <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-save">Update</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </td>
                                    @endif
                                </tr>
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
        <script src="{{URL::asset('assets/libs/moment/moment.min.js')}}"></script>


    <script src="{{URL::asset('assets/js/app.js')}}"></script>

    @if ($errors->createBlud->any())
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('myModal');
        if (modalEl) new bootstrap.Modal(modalEl).show();
    });
    </script>
    @endif

    @endsection
