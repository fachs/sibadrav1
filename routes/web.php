<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\DataUserController;
use App\Http\Controllers\DokumenRbaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RenbutController;
use App\Http\Controllers\RoController;
use App\Http\Controllers\PerkiraanHargaController;
use App\Http\Controllers\AmbangBatasController;
use App\Http\Controllers\RencanaPendapatanController;
use App\Http\Controllers\RencanaPendapatanPerubahanController;
use App\Http\Controllers\RbaPergeseranController;
use App\Http\Controllers\RbaPerubahanController;
use App\Http\Controllers\PrakiraanMajuController;
use App\Http\Controllers\UnitBludController;
use App\Http\Controllers\RekeningBludController;
use App\Http\Controllers\AkunController;
use App\Http\Controllers\BuktiDukungPenerimaanController;
use App\Http\Controllers\PenerimaanController;
// use App\Http\Controllers\PenyetoranController;
use App\Http\Controllers\PpdUpController;
use App\Http\Controllers\OpdUpController;
use App\Http\Controllers\SpdUpController;
use App\Http\Controllers\PencairanLsController;
use App\Http\Controllers\PpdLsController;
use App\Http\Controllers\OpdLsController;
use App\Http\Controllers\SpdLsController;
use App\Http\Controllers\PpdGuController;
use App\Http\Controllers\OpdGuController;
use App\Http\Controllers\SpdGuController;
use App\Http\Controllers\CetakBukuController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PengembalianController;
use App\Http\Controllers\AngkasController;
use App\Http\Controllers\BkuPejabatKeuanganController;
use App\Http\Controllers\BkuBendaharaPengeluaranController;
use App\Http\Controllers\BkuBendaharaPerubahanRequestController;
use App\Http\Controllers\LaporanPenatausahaanController;
use App\Http\Controllers\PencairanUpController;
use App\Http\Controllers\PencairanGuController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

require __DIR__ . '/auth.php';

// Route::group(['prefix' => '/', 'middleware' => 'auth'], function () {
//     Route::get('', [RoutingController::class, 'index'])->name('root');
//     Route::get('{first}/{second}/{third}', [RoutingController::class, 'thirdLevel'])->name('third');
//     Route::get('{first}/{second}', [RoutingController::class, 'secondLevel'])->name('second');
//     Route::get('{any}', [RoutingController::class, 'root'])->name('any');
// });

Route::middleware(['auth', \App\Http\Middleware\GunakanTahunAnggaran::class])->group(function () {
    Route::post('/tahun-anggaran', [\App\Http\Controllers\TahunAnggaranController::class, 'update'])->name('tahun-anggaran.update');
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/home', [DashboardController::class, 'index'])->name('home');
    Route::get('/lra', [LaporanPenatausahaanController::class, 'lra'])->name('lra.index');
    Route::get('/lra/unduh', [LaporanPenatausahaanController::class, 'downloadLra'])->name('lra.download');
    Route::get('/lpj', [LaporanPenatausahaanController::class, 'lpj'])->name('lpj.index');
    Route::get('/lpj/unduh', [LaporanPenatausahaanController::class, 'downloadLpj'])->name('lpj.download');
    Route::get('/index', [DashboardController::class, 'index'])->name('dashboard');

    Route::redirect('/dokumen-rba', '/dokumen-rba/murni')->name('dokumen-rba.index');
    Route::prefix('dokumen-rba/{jenis}')->whereIn('jenis', ['murni', 'perubahan'])->group(function () {
        Route::get('/', [DokumenRbaController::class, 'show'])->name('dokumen-rba.show');
        Route::post('/spm', [DokumenRbaController::class, 'storeSpm'])->name('dokumen-rba.spm.store');
        Route::get('/preview', [DokumenRbaController::class, 'preview'])->name('dokumen-rba.preview');
        Route::get('/unduh/{format}', [DokumenRbaController::class, 'download'])
            ->whereIn('format', ['word', 'pdf'])->name('dokumen-rba.download');
    });
    Route::get('/dokumen-rba/{invalidPath}', fn () => abort(404))->where('invalidPath', '.*');

    Route::middleware('superuser')->group(function () {
        Route::post('data-blud', [UnitBludController::class, 'store'])->name('data-blud.store');
        Route::put('data-blud/{data_blud}', [UnitBludController::class, 'update'])->name('data-blud.update');
        Route::delete('data-blud/{data_blud}', [UnitBludController::class, 'destroy'])->name('data-blud.destroy');
    });

    Route::get('/api/akun', [AkunController::class, 'select2']);
    Route::get('/renbut-per-unit',[RenbutController::class, 'indexPerUnit'])->name('renbut-per-unit');
    Route::get('/prakiraan-maju', [PrakiraanMajuController::class, 'index'])->name('prakiraan-maju.index');
    Route::post('/prakiraan-maju', [PrakiraanMajuController::class, 'store'])->name('prakiraan-maju.store');
    Route::get('/rba/belanja',[RoController::class, 'index'])->name('rba.belanja');
    Route::get('/rba/perkiraan-harga', [PerkiraanHargaController::class, 'index'])->name('rba.perkiraan-harga');
    Route::get('/rba/ambang-batas', [AmbangBatasController::class, 'index'])->name('rba.ambang-batas');
    Route::post('/rba/ambang-batas', [AmbangBatasController::class, 'store'])->name('rba.ambang-batas.store');
    Route::put('/rba/ambang-batas/{id}', [AmbangBatasController::class, 'update'])->whereNumber('id')->name('rba.ambang-batas.update');
    Route::delete('/rba/ambang-batas/{id}', [AmbangBatasController::class, 'destroy'])->whereNumber('id')->name('rba.ambang-batas.destroy');
    Route::post('/rba/kinerja-kegiatan',[RoController::class, 'storeKinerjaKegiatan'])->name('rba.kinerja-kegiatan.store');
    Route::get('/rba/pendapatan',[RoController::class, 'pendapatan'])->name('rba.pendapatan');
    Route::get('/rba/pendapatan/create', [RencanaPendapatanController::class, 'create'])->name('rba.pendapatan.create');
    Route::get('/rba/pendapatan/{id}/edit', [RencanaPendapatanController::class, 'edit'])->whereNumber('id')->name('rba.pendapatan.edit');
    Route::post('/rba/pendapatan', [RencanaPendapatanController::class, 'store'])->name('rba.pendapatan.store');
    Route::put('/rba/pendapatan/{id}', [RencanaPendapatanController::class, 'update'])->whereNumber('id')->name('rba.pendapatan.update');
    Route::delete('/rba/pendapatan/{id}', [RencanaPendapatanController::class, 'destroy'])->whereNumber('id')->name('rba.pendapatan.destroy');
    Route::get('/rba/pembiayaan',[RoController::class, 'pembiayaan'])->name('rba.pembiayaan');
    Route::post('/rba/pembiayaan',[RoController::class, 'storePembiayaan'])->name('rba.pembiayaan.store');
    Route::put('/rba/pembiayaan/{id}', [RoController::class, 'updatePembiayaan'])->name('rba.pembiayaan.update');
    Route::delete('/rba/pembiayaan/{id}', [RoController::class, 'destroyPembiayaan'])->name('rba.pembiayaan.destroy');
    Route::get('/rba/ringkasan', [RoController::class, 'ringkasan'])->name('rba.ringkasan');
    Route::get('/rba/ringkasan/pdf', [RoController::class, 'downloadRingkasanPdf'])->name('rba.ringkasan.pdf');
    Route::get('/rba-pergeseran', [RbaPergeseranController::class, 'index'])->name('rba-pergeseran.index');
    Route::patch('/rba-pergeseran/{id}/zero-out', [RbaPergeseranController::class, 'zeroOut'])->name('rba-pergeseran.zero-out');
    Route::post('/rba-pergeseran', [RbaPergeseranController::class, 'store'])->name('rba-pergeseran.store');
    Route::get('/rba-perubahan', [RbaPerubahanController::class, 'index'])->name('rba-perubahan.index');
    Route::get('/rba-perubahan/pendapatan', [RoController::class, 'pendapatanPerubahan'])->name('rba-perubahan.pendapatan');
    Route::get('/rba-perubahan/pendapatan/pdf', [RoController::class, 'downloadPendapatanPerubahanPdf'])->name('rba-perubahan.pendapatan.pdf');
    Route::get('/rba-perubahan/pendapatan/pergeseran-lintas-akun/create', [RencanaPendapatanPerubahanController::class, 'createShift'])->name('rba-perubahan.pendapatan.pergeseran-lintas-akun.create');
    Route::post('/rba-perubahan/pendapatan/pergeseran-lintas-akun', [RencanaPendapatanPerubahanController::class, 'storeShift'])->name('rba-perubahan.pendapatan.pergeseran-lintas-akun');
    Route::patch('/rba-perubahan/pendapatan/verifikasi-semua', [RencanaPendapatanPerubahanController::class, 'verifyAll'])->name('rba-perubahan.pendapatan.verifikasi-semua');
    Route::patch('/rba-perubahan/pendapatan/tolak-semua', [RencanaPendapatanPerubahanController::class, 'rejectAll'])->name('rba-perubahan.pendapatan.tolak-semua');
    Route::patch('/rba-perubahan/pendapatan/{id}/zero-out', [RencanaPendapatanPerubahanController::class, 'zeroOut'])->name('rba-perubahan.pendapatan.zero-out');
    Route::patch('/rba-perubahan/pendapatan/{id}/verifikasi', [RencanaPendapatanPerubahanController::class, 'verify'])->name('rba-perubahan.pendapatan.verifikasi');
    Route::patch('/rba-perubahan/pendapatan/{id}/tolak', [RencanaPendapatanPerubahanController::class, 'reject'])->name('rba-perubahan.pendapatan.tolak');
    Route::put('/rba-perubahan/pendapatan/{id}', [RencanaPendapatanPerubahanController::class, 'update'])->name('rba-perubahan.pendapatan.update');
    Route::get('/rba-perubahan/belanja', [RoController::class, 'belanjaPerubahan'])->name('rba-perubahan.belanja');
    Route::get('/rba-perubahan/belanja/pdf', [RoController::class, 'downloadBelanjaPerubahanPdf'])->name('rba-perubahan.belanja.pdf');
    Route::get('/rba-perubahan/belanja/pergeseran-lintas-akun/create', [RoController::class, 'createPergeseranLintasAkun'])->name('rba-perubahan.belanja.pergeseran-lintas-akun.create');
    Route::post('/rba-perubahan/kinerja-kegiatan', [RoController::class, 'storeKinerjaKegiatanPerubahan'])->name('rba-perubahan.kinerja-kegiatan.store');
    Route::patch('/rba-perubahan/kinerja-kegiatan/{id}/verifikasi', [RoController::class, 'verifikasiKinerjaKegiatanPerubahan'])->name('rba-perubahan.kinerja-kegiatan.verifikasi');
    Route::patch('/rba-perubahan/kinerja-kegiatan/{id}/tolak', [RoController::class, 'tolakKinerjaKegiatanPerubahan'])->name('rba-perubahan.kinerja-kegiatan.tolak');
    Route::post('/rba-perubahan/belanja/pergeseran-lintas-akun', [RoController::class, 'pergeseranLintasAkun'])->name('rba-perubahan.belanja.pergeseran-lintas-akun');
    Route::patch('/rba-perubahan/belanja/verifikasi-semua', [RoController::class, 'verifikasiSemuaBelanjaPerubahan'])->name('rba-perubahan.belanja.verifikasi-semua');
    Route::patch('/rba-perubahan/belanja/tolak-semua', [RoController::class, 'tolakSemuaBelanjaPerubahan'])->name('rba-perubahan.belanja.tolak-semua');
    Route::patch('/rba-perubahan/belanja/{id}/zero-out', [RoController::class, 'zeroOutBelanjaPerubahan'])->name('rba-perubahan.belanja.zero-out');
    Route::patch('/rba-perubahan/belanja/{id}/verifikasi', [RoController::class, 'verifikasiBelanjaPerubahan'])->name('rba-perubahan.belanja.verifikasi');
    Route::patch('/rba-perubahan/belanja/{id}/tolak', [RoController::class, 'tolakBelanjaPerubahan'])->name('rba-perubahan.belanja.tolak');
    Route::put('/rba-perubahan/belanja/{id}', [RoController::class, 'updateBelanjaPerubahan'])->name('rba-perubahan.belanja.update');
    Route::get('/rba-perubahan/pembiayaan', [RoController::class, 'pembiayaanPerubahan'])->name('rba-perubahan.pembiayaan');
    Route::get('/rba-perubahan/pembiayaan/pdf', [RoController::class, 'downloadPembiayaanPerubahanPdf'])->name('rba-perubahan.pembiayaan.pdf');
    Route::patch('/rba-perubahan/pembiayaan/verifikasi-semua', [RoController::class, 'verifikasiSemuaPembiayaanPerubahan'])->name('rba-perubahan.pembiayaan.verifikasi-semua');
    Route::patch('/rba-perubahan/pembiayaan/tolak-semua', [RoController::class, 'tolakSemuaPembiayaanPerubahan'])->name('rba-perubahan.pembiayaan.tolak-semua');
    Route::patch('/rba-perubahan/pembiayaan/{id}/zero-out', [RoController::class, 'zeroOutPembiayaanPerubahan'])->name('rba-perubahan.pembiayaan.zero-out');
    Route::patch('/rba-perubahan/pembiayaan/{id}/verifikasi', [RoController::class, 'verifikasiPembiayaanPerubahan'])->name('rba-perubahan.pembiayaan.verifikasi');
    Route::patch('/rba-perubahan/pembiayaan/{id}/tolak', [RoController::class, 'tolakPembiayaanPerubahan'])->name('rba-perubahan.pembiayaan.tolak');
    Route::put('/rba-perubahan/pembiayaan/{id}', [RoController::class, 'updatePembiayaanPerubahan'])->name('rba-perubahan.pembiayaan.update');
    Route::get('/rba-perubahan/ringkasan', [RoController::class, 'ringkasanPerubahan'])->name('rba-perubahan.ringkasan');
    Route::get('/rba-perubahan/ringkasan/pdf', [RoController::class, 'downloadRingkasanPerubahanPdf'])->name('rba-perubahan.ringkasan.pdf');

    Route::get('/buku-penerimaan-penyetoran', [CetakBukuController::class, 'index'])->name('buku-penerimaan-penyetoran.index');
    Route::get('/buku-penerimaan-penyetoran/pdf', [CetakBukuController::class, 'pdf'])->name('buku-penerimaan-penyetoran.pdf');

    Route::post('/rencana-kebutuhan/update-akun', [RenbutController::class, 'updateAkun']);
    Route::put('/rencana-kebutuhan/{id}', [RenbutController::class, 'update'])->name('rencana-kebutuhan.update');
    Route::get('/rencana-kebutuhan-input',[RenbutController::class, 'input'])->name('rencana-kebutuhan.input');
    Route::get('/rencana-kebutuhan/data', [RenbutController::class, 'data']);
    Route::post('/rencana-kebutuhan/update-sumber-dana', [RenbutController::class, 'updateSumberDana']);

    Route::resource('rencana-kebutuhan', RenbutController::class);
    Route::resource('pengembalian', PengembalianController::class);
    Route::get('/rincian-objek/pdf', [RenbutController::class, 'downloadPdf'])->name('rincian.pdf');
    Route::get('/rba/pdf', [RoController::class, 'downloadPdf'])->name('rba.pdf');
    Route::get('/rba/pendapatan/pdf', [RoController::class, 'downloadPendapatanPdf'])->name('rba.pendapatan.pdf');
    Route::get('/rba/pembiayaan/pdf', [RoController::class, 'downloadPembiayaanPdf'])->name('rba.pembiayaan.pdf');

    Route::resource('penerimaan', PenerimaanController::class);
    Route::get('/penerimaan/{uuid}/transaksi', [\App\Http\Controllers\TransaksiPenerimaanController::class, 'show'])->whereUuid('uuid')->name('penerimaan.transaksi');
    Route::post('/penerimaan/{uuid}/transaksi/manual', [\App\Http\Controllers\TransaksiPenerimaanController::class, 'storeManual'])->whereUuid('uuid')->name('penerimaan.transaksi.manual');
    Route::post('/penerimaan/{uuid}/transaksi/import', [\App\Http\Controllers\TransaksiPenerimaanController::class, 'importExcel'])->whereUuid('uuid')->name('penerimaan.transaksi.import');
    Route::delete('/transaksi-penerimaan/{id}', [\App\Http\Controllers\TransaksiPenerimaanController::class, 'destroy'])->name('transaksi-penerimaan.destroy');
    Route::post('/penerimaan/bukti-dukung', [BuktiDukungPenerimaanController::class, 'store'])
    ->name('penerimaan.upload-bukti-dukung');
    Route::patch('/penerimaan/{id}/update-nomor-tbp', [PenerimaanController::class, 'updateNomorTbp'])
    ->name('penerimaan.update-nomor-tbp');
    Route::patch('/penerimaan/{id}/update-tanggal-setor', [PenerimaanController::class, 'updateTanggalSetor'])
    ->name('penerimaan.update-tanggal-setor');
    Route::get('/penyetoran', [PenerimaanController::class, 'indexPenyetoran'])
        ->name('penyetoran.index');

    Route::get('/penyetoran-dinkes', [PenerimaanController::class, 'indexPenyetoranDinkes'])
        ->name('penyetoran.dinkes.index');
    
    Route::resource('rincian-objek', RoController::class);
    Route::resource('data-user', DataUserController::class);
    Route::put('/data-user/{id}/roles', [DataUserController::class, 'updateRoles'])->name('data-user.roles.update');
    Route::resource('data-uptd', UnitBludController::class)->only(['index', 'show']);
    Route::get('/rekening-blud', [RekeningBludController::class, 'index'])->name('rekening-blud.index');
    Route::post('/rekening-blud', [RekeningBludController::class, 'store'])->name('rekening-blud.store');
    Route::put('/rekening-blud/{id}', [RekeningBludController::class, 'update'])->whereNumber('id')->name('rekening-blud.update');
    Route::delete('/rekening-blud/{id}', [RekeningBludController::class, 'destroy'])->whereNumber('id')->name('rekening-blud.destroy');

    Route::middleware('kapusktu')->group(function () {
        Route::delete('/rencana-kebutuhan/{id}', [RenbutController::class, 'destroy'])
        ->name('rencana-kebutuhan.destroy');
    });

    Route::resource('akun', AkunController::class);

    Route::get('/pencairan-up', [PencairanUpController::class, 'index'])->name('pencairan-up.index');
    Route::get('/pencairan-gu', [PencairanGuController::class, 'index'])->name('pencairan-gu.index');
    Route::get('/pencairan-ls', [PencairanLsController::class, 'index'])->name('pencairan-ls.index');

    Route::get('/ppd-up', [PpdUpController::class, 'index'])->name('ppd-up.index');
    Route::post('/ppd-up', [PpdUpController::class, 'store'])->name('ppd-up.store');
    Route::put('/ppd-up/{id}', [PpdUpController::class, 'update'])->name('ppd-up.update');
    Route::delete('/ppd-up/{id}', [PpdUpController::class, 'destroy'])->name('ppd-up.destroy');
    Route::get('/ppd-up/{id}/unduh-surat', [PpdUpController::class, 'unduhSurat'])->name('ppd-up.unduh-surat');

    Route::get('/opd-up', [OpdUpController::class, 'index'])->name('opd-up.index');
    Route::post('/opd-up', [OpdUpController::class, 'store'])->name('opd-up.store');
    Route::put('/opd-up/{id}', [OpdUpController::class, 'update'])->name('opd-up.update');
    Route::delete('/opd-up/{id}', [OpdUpController::class, 'destroy'])->name('opd-up.destroy');
    Route::get('/opd-up/{id}/unduh-surat', [OpdUpController::class, 'unduhSurat'])->name('opd-up.unduh-surat');

    Route::get('/spd-up', [SpdUpController::class, 'index'])->name('spd-up.index');
    Route::post('/spd-up', [SpdUpController::class, 'store'])->name('spd-up.store');
    Route::put('/spd-up/{id}', [SpdUpController::class, 'update'])->name('spd-up.update');
    Route::delete('/spd-up/{id}', [SpdUpController::class, 'destroy'])->name('spd-up.destroy');
    Route::get('/spd-up/{id}/unduh-surat', [SpdUpController::class, 'unduhSurat'])->name('spd-up.unduh-surat');

    Route::get('/ppd-gu', [PpdGuController::class, 'index'])->name('ppd-gu.index');
    Route::post('/ppd-gu', [PpdGuController::class, 'store'])->name('ppd-gu.store');
    Route::put('/ppd-gu/{id}', [PpdGuController::class, 'update'])->name('ppd-gu.update');
    Route::delete('/ppd-gu/{id}', [PpdGuController::class, 'destroy'])->name('ppd-gu.destroy');
    Route::get('/ppd-gu/{id}/unduh-surat', [PpdGuController::class, 'unduhSurat'])->name('ppd-gu.unduh-surat');

    Route::get('/opd-gu', [OpdGuController::class, 'index'])->name('opd-gu.index');
    Route::post('/opd-gu', [OpdGuController::class, 'store'])->name('opd-gu.store');
    Route::put('/opd-gu/{id}', [OpdGuController::class, 'update'])->name('opd-gu.update');
    Route::delete('/opd-gu/{id}', [OpdGuController::class, 'destroy'])->name('opd-gu.destroy');
    Route::get('/opd-gu/{id}/unduh-surat', [OpdGuController::class, 'unduhSurat'])->name('opd-gu.unduh-surat');

    Route::get('/spd-gu', [SpdGuController::class, 'index'])->name('spd-gu.index');
    Route::post('/spd-gu', [SpdGuController::class, 'store'])->name('spd-gu.store');
    Route::put('/spd-gu/{id}', [SpdGuController::class, 'update'])->name('spd-gu.update');
    Route::delete('/spd-gu/{id}', [SpdGuController::class, 'destroy'])->name('spd-gu.destroy');
    Route::get('/spd-gu/{id}/unduh-surat', [SpdGuController::class, 'unduhSurat'])->name('spd-gu.unduh-surat');

    Route::get('/ppd-ls', [PpdLsController::class, 'index'])->name('ppd-ls.index');
    Route::post('/ppd-ls', [PpdLsController::class, 'store'])->name('ppd-ls.store');
    Route::put('/ppd-ls/{id}', [PpdLsController::class, 'update'])->name('ppd-ls.update');
    Route::delete('/ppd-ls/{id}', [PpdLsController::class, 'destroy'])->name('ppd-ls.destroy');
    Route::get('/ppd-ls/{id}/unduh-surat', [PpdLsController::class, 'unduhSurat'])->name('ppd-ls.unduh-surat');

    Route::get('/opd-ls', [OpdLsController::class, 'index'])->name('opd-ls.index');
    Route::post('/opd-ls', [OpdLsController::class, 'store'])->name('opd-ls.store');
    Route::put('/opd-ls/{id}', [OpdLsController::class, 'update'])->name('opd-ls.update');
    Route::delete('/opd-ls/{id}', [OpdLsController::class, 'destroy'])->name('opd-ls.destroy');
    Route::get('/opd-ls/{id}/unduh-surat', [OpdLsController::class, 'unduhSurat'])->name('opd-ls.unduh-surat');

    Route::get('/spd-ls', [SpdLsController::class, 'index'])->name('spd-ls.index');
    Route::post('/spd-ls', [SpdLsController::class, 'store'])->name('spd-ls.store');
    Route::put('/spd-ls/{id}', [SpdLsController::class, 'update'])->name('spd-ls.update');
    Route::delete('/spd-ls/{id}', [SpdLsController::class, 'destroy'])->name('spd-ls.destroy');
    Route::get('/spd-ls/{id}/unduh-surat', [SpdLsController::class, 'unduhSurat'])->name('spd-ls.unduh-surat');

    Route::get('/angkas', [AngkasController::class, 'index'])->name('angkas.index');
    Route::post('/angkas', [AngkasController::class, 'store'])->name('angkas.store');
    Route::patch('/angkas/inline', [AngkasController::class, 'updateInline'])->name('angkas.update-inline');
    Route::patch('/angkas/alokasi-inline', [AngkasController::class, 'updateAlokasiInline'])->name('angkas.update-alokasi-inline');
    Route::post('/angkas/modal', [AngkasController::class, 'updateModal'])->name('angkas.update-modal');
    Route::put('/angkas/{id}', [AngkasController::class, 'update'])->name('angkas.update');
    Route::delete('/angkas/{id}', [AngkasController::class, 'destroy'])->name('angkas.destroy');

    Route::get('bku-pejabat-keuangan/pdf', [BkuPejabatKeuanganController::class, 'pdf'])->name('bku-pejabat-keuangan.pdf');
    Route::post('bku-pejabat-keuangan/set-saldo-awal', [BkuPejabatKeuanganController::class, 'setSaldoAwal'])->name('bku-pejabat-keuangan.set-saldo-awal');
    Route::resource('bku-pejabat-keuangan', BkuPejabatKeuanganController::class)->except(['create', 'show', 'edit']);

    Route::get('bku-bendahara-pengeluaran/pdf', [BkuBendaharaPengeluaranController::class, 'pdf'])->name('bku-bendahara-pengeluaran.pdf');
    Route::get('bku-bendahara-pengeluaran/pengajuan', [BkuBendaharaPerubahanRequestController::class, 'index'])->name('bku-bendahara-pengajuan.index');
    Route::post('bku-bendahara-pengeluaran/{bku}/pengajuan', [BkuBendaharaPerubahanRequestController::class, 'store'])->name('bku-bendahara-pengajuan.store');
    Route::patch('bku-bendahara-pengeluaran/pengajuan/{pengajuan}', [BkuBendaharaPerubahanRequestController::class, 'review'])->name('bku-bendahara-pengajuan.review');
    Route::resource('bku-bendahara-pengeluaran', BkuBendaharaPengeluaranController::class)->except(['create', 'show', 'edit']);

    // Route dinamis (fallback) untuk view template dipindahkan ke paling bawah
    Route::get('{first}/{second}/{third}', [RoutingController::class, 'thirdLevel'])->name('third');
    Route::get('{first}/{second}', [RoutingController::class, 'secondLevel'])->name('second');
    Route::get('{any}', [RoutingController::class, 'root'])->name('any');
});
