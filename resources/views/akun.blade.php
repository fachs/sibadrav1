@extends('layouts.master')
@section('title') Data Akun @endsection
@section('css')
<!-- DataTables -->
<link href="{{URL::asset('assets/libs/datatables/dataTables.min.css')}}" rel="stylesheet" type="text/css">
{{-- <link href="{{URL::asset('assets/libs/chartist/chartist.min.css')}}" rel="stylesheet"> --}}
@endsection
@section('body') <body data-sidebar="dark"> @endsection
    @section('content')
    
    <div class="page-title-box">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h6 class="page-title">Data Akun</h6>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="#">SIBADRA</a></li>
                <li class="breadcrumb-item active" aria-current="page">Data Akun</li>
            </ol>
        </div>
        <div class="col-md-4">
            <div class="float-end d-none d-md-block">
                <div class="d-grid">
                    <button type="button" class="btn font-size-16 btn-primary" id="btn-new-event" data-bs-toggle="modal" data-bs-target="#myModal"><i class="mdi mdi-plus-circle-outline"></i> Tambah Akun</button>
                </div>
            </div>

                <div id="myModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header py-3 px-4">
                                <h5 class="modal-title">Tambah Akun</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <form method="POST" action="{{ route('akun.store') }}" class="needs-validation" name="event-form" id="form-event" novalidate>
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-1">
                                            <div class="mb-3">
                                                <label class="form-label">Akun</label>
                                                <input class="form-control @error('akun') is-invalid @enderror" type="text" name="akun" id="akun" required value="5" autocomplete="akun" disabled/>
                                                <div class="invalid-feedback">Please provide a valid event name</div>

                                                @error('akun')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                                @enderror
                                            </div>      
                                        </div>
                                        <div class="col-md-1">
                                            <div class="mb-3">
                                                <label class="form-label">Kel.</label>
                                                <input class="form-control @error('kelompok') is-invalid @enderror" type="text" name="kelompok" id="kelompok" value="{{ old('kelompok') }}" required autocomplete="kelompok" />
                                                <div class="invalid-feedback">Please provide a valid event name</div>
                                                
                                                @error('kelompok')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-1">
                                            <div class="mb-3">
                                                <label class="form-label">Jenis</label>
                                                <input class="form-control @error('jenis') is-invalid @enderror" type="text" name="jenis" id="jenis" value="{{ old('jenis') }}" required autocomplete="jenis" />
                                                <div class="invalid-feedback">Please provide a valid event name</div>
                                                
                                                @error('jenis')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <div class="mb-3">
                                                <label class="form-label">Objek</label>
                                                <input class="form-control @error('objek') is-invalid @enderror" type="text" name="objek" id="objek" value="{{ old('objek') }}" required autocomplete="objek" />
                                                <div class="invalid-feedback">Please provide a valid event name</div>
                                                
                                                @error('objek')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="mb-3">
                                                <label class="form-label">Rincian Objek</label>
                                                <input class="form-control" type="text" name="rincian_objek" id="rincian_objek" required value="{{ old('rincian_objek') }}" />
                                                <div class="invalid-feedback">Please provide a valid event name</div>

                                                @error('rincian_objek')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Sub Rincian Objek</label>
                                                <input class="form-control" type="text" name="sub_ro" id="sub_ro" required value="{{ old('sub_ro') }}" />
                                                <div class="invalid-feedback">Please provide a valid event name</div>

                                                @error('sub_ro')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Kode Rekening</label>
                                                <input class="form-control" type="text" name="kode_rek" id="kode_rek" required value="{{ old('kode_rek') }}" />
                                                <div class="invalid-feedback">Please provide a valid event name</div>

                                                @error('kode_rek')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Urain Akun</label>
                                                <input class="form-control" type="text" name="urain" id="urain" required value="{{ old('urain') }}" />
                                                <div class="invalid-feedback">Please provide a valid event name</div>

                                                @error('urain')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Keterangan</label>
                                                <input class="form-control" type="text" name="keterangan" id="keterangan" required value="{{ old('keterangan') }}" />
                                                <div class="invalid-feedback">Please provide a valid event name</div>

                                                @error('keterangan')
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
            </div>

        </div>

    </div>
</div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                <table id="datatable-buttons" class="table table-striped table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                  <thead>
                     <tr>
                        <th>No</th>
                        <th>Akun</th>
                        <th>Kelompok</th>
                        <th>Jenis</th>
                        <th>Objek</th>
                        <th>RO</th>
                        <th>Sub RO</th>
                        <th>Kode Rekening</th>
                        <th>Uraian</th>
                        <th>Keterangan</th>
                     </tr>
                  </thead>
                    <tbody>
                     @foreach ($akuns as $item)
                     <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->akun }}</td>
                        <td>{{ $item->kelompok }}</td>
                        <td>{{ $item->jenis }}</td>
                        <td>{{ $item->objek }}</td>
                        <td>{{ $item->rincian_objek }}</td>
                        <td>{{ $item->sub_ro }}</td>
                        <td>{{ $item->kode_rek }}</td>
                        <td>{{ $item->uraian }}</td>
                        <td>{{ $item->keterangan }}</td>

                        </tr>
                        @endforeach
                        </tbody>
                </table>
                    
                </div>
            </div>

            
        </div> <!-- end col -->
    </div> <!-- end row -->

    @endsection
    @section('scripts')

    <!-- Required datatable js -->
        <script src="{{URL::asset('assets/libs/moment/moment.min.js')}}"></script>
    <script src="{{URL::asset('assets/libs/jquery-ui-dist/jquery-ui.min.js')}}"></script>
    <script src="{{URL::asset('assets/libs/datatables/dataTables.min.js')}}"></script>

    <!-- Datatable init js -->
    <script src="{{URL::asset('assets/js/pages/datatables.init.js')}}"></script>

    <script src="{{URL::asset('assets/js/app.js')}}"></script>

    @endsection
