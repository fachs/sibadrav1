<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RencanaKebutuhan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'rencana_kebutuhans';

    protected $fillable = [
        'nama_blud',
        'nama_unit',
        'tahun_anggaran',
        'program',
        'kegiatan',
        'subkegiatan',
        'jumlah',
        'harga_satuan',
        'jumlah_harga',
        'satuan',
        'ruang',
        'keterangan',
        'spesifikasi',
        'sumber_dana',
        'akun_id',
        'uraian',
        'status',
        'dokumen',
        'uraian_perubahan',
        'jumlah_perubahan',
        'satuan_perubahan',
        'harga_satuan_perubahan',
        'jumlah_harga_perubahan',
        'keterangan_perubahan',
        'status_perubahan',
        'verifikator_perubahan',
        'rekening_awal',          // kode rekening sumber saat pengajuan perubahan
        'rekening_akhir',         // kode rekening tujuan saat pengajuan perubahan
        'is_hasil_perubahan',     // true jika record ini adalah tujuan dari pergeseran lintas akun
    ];

    public function getActiveUraianAttribute()
    {
        return ($this->status_perubahan === 'verified' && $this->uraian_perubahan !== null) ? $this->uraian_perubahan : $this->uraian;
    }

    public function getActiveJumlahAttribute()
    {
        return ($this->status_perubahan === 'verified' && $this->jumlah_perubahan !== null) ? $this->jumlah_perubahan : $this->jumlah;
    }

    public function getActiveSatuanAttribute()
    {
        return ($this->status_perubahan === 'verified' && $this->satuan_perubahan !== null) ? $this->satuan_perubahan : $this->satuan;
    }

    public function getActiveHargaSatuanAttribute()
    {
        return ($this->status_perubahan === 'verified' && $this->harga_satuan_perubahan !== null) ? $this->harga_satuan_perubahan : $this->harga_satuan;
    }

    public function getActiveJumlahHargaAttribute()
    {
        return ($this->status_perubahan === 'verified' && $this->jumlah_harga_perubahan !== null) ? $this->jumlah_harga_perubahan : $this->jumlah_harga;
    }

    public function getActiveKeteranganAttribute()
    {
        return ($this->status_perubahan === 'verified' && $this->keterangan_perubahan !== null) ? $this->keterangan_perubahan : $this->keterangan;
    }

    // Proposed attributes for viewing pending changes in the view
    public function getProposedUraianAttribute()
    {
        return $this->uraian_perubahan !== null ? $this->uraian_perubahan : $this->uraian;
    }

    public function getProposedJumlahAttribute()
    {
        return $this->jumlah_perubahan !== null ? $this->jumlah_perubahan : $this->jumlah;
    }

    public function getProposedSatuanAttribute()
    {
        return $this->satuan_perubahan !== null ? $this->satuan_perubahan : $this->satuan;
    }

    public function getProposedHargaSatuanAttribute()
    {
        return $this->harga_satuan_perubahan !== null ? $this->harga_satuan_perubahan : $this->harga_satuan;
    }

    public function getProposedJumlahHargaAttribute()
    {
        return $this->jumlah_harga_perubahan !== null ? $this->jumlah_harga_perubahan : $this->jumlah_harga;
    }

    public function getProposedKeteranganAttribute()
    {
        return $this->keterangan_perubahan !== null ? $this->keterangan_perubahan : $this->keterangan;
    }

    // relasi ke Akun

    // rencana_kebutuhan -> akun (many to one)
    public function akuns()
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }
}
