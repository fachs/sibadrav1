<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RencanaPendapatan extends Model
{
    protected $casts = [
        'jumlah' => 'decimal:2', 'jumlah_perubahan' => 'decimal:2',
        'jenis_pelayanan_perubahan' => 'array',
    ];

    protected $fillable = [
        'tahun_anggaran',
        'kode_rek',
        'uraian',
        'jumlah',
        'user_id',
        'nama_blud',
        'uraian_perubahan',
        'jumlah_perubahan',
        'status_perubahan',
        'verifikator_perubahan',
        'is_hasil_perubahan',
        'jenis_pelayanan_perubahan',
        'rekening_awal', 'rekening_akhir', 'pergeseran_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jenisPelayanans()
    {
        return $this->hasMany(JenisPelayananPendapatan::class)->orderBy('id');
    }

    public function pelayananPerubahan()
    {
        if ($this->jenis_pelayanan_perubahan === null) {
            return $this->jenisPelayanans;
        }

        return collect($this->jenis_pelayanan_perubahan)->map(fn (array $row) => new JenisPelayananPendapatan($row));
    }

    public function dataEditorPerubahan(): array
    {
        return [
            'id' => $this->id, 'kode_rek' => $this->kode_rek,
            'uraian' => $this->uraian_perubahan ?? $this->uraian,
            'nama_blud' => $this->nama_blud,
            'jenis_pelayanans' => $this->pelayananPerubahan()->toArray(),
        ];
    }
}
