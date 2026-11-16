# Template dokumen RBA

`template-rba.docx` digunakan untuk RBA Murni dan RBA Perubahan. Generator mengisi
`[[Nama Puskesmas]]` dari `user.upt` dan `[[Tahun Anggaran]]` dari pilihan tahun
topbar (`TahunRba::selected`). Isian lain dan data contoh pada template tetap
mengikuti berkas sumber.

Tabel F. Ambang Batas mengambil data UPT yang sama untuk dua tahun sebelum
tahun anggaran dokumen. Contoh: dokumen 2026 menampilkan data 2024 dan 2025.
Perubahan data ambang batas pada kedua tahun tersebut menghasilkan dokumen baru.
Uraian di bawah tabel memakai total RBA Pendapatan, jumlah persentase dibagi
jumlah data ambang batas, hasil perkaliannya, dan total batas belanja yang dihitung.

Bagian G. Prakiraan Maju menggunakan lima jenis belanja dan nilai yang sama
dengan halaman Prakiraan Maju untuk tahun anggaran terpilih. Judul tahun dasar,
tahun +1, dan tahun +2 serta keterangannya mengikuti tahun tersebut. Perubahan
nilai Prakiraan Maju atau baseline RBA Belanja menghasilkan dokumen baru.

Bagian B. Kesimpulan memakai total RBA Pendapatan dan rata-rata persentase
Ambang Batas yang sama dengan perhitungan pada bagian F.

Nama UPT pada sampul menggunakan spasi tanpa pemisahan baris dan kotak teks
yang lebih lebar agar tetap satu baris dengan posisi rata tengah.

Word diisi langsung pada teks OOXML agar gambar, text box, tabel, gaya, dan
section tetap terjaga, termasuk placeholder yang terpisah ke beberapa run.
Preview dan unduhan PDF dikonversi dari Word yang sudah diisi. Berkas PDF statis
lama tidak dipakai oleh endpoint dokumen RBA.

Konversi memakai Microsoft Word yang terpasang di server Windows melalui
`scripts/export-rba-template.ps1`. Akun proses PHP harus dapat menjalankan
PowerShell dan Word COM. Jika diperlukan, atur `RBA_POWERSHELL` ke path executable
PowerShell. Kegagalan konversi memberi HTTP 503 dan dapat dicoba lagi; unduhan
Word tetap tersedia.

Konverter meneruskan lingkungan Windows asli (`getenv()`) secara eksplisit.
Lingkungan bawaan Symfony Process dapat kehilangan variabel seperti SystemRoot,
APPDATA, dan USERPROFILE pada request web ketika variabel tersebut tidak ada di
`$_SERVER`. Tanpa variabel ini, PowerShell dapat gagal dengan kode `8009001d`.

Uji konversi asli dengan `RBA_TEST_NATIVE_PDF=1 php artisan test
--filter=DokumenRbaNativePdfTest` (atur environment variable sesuai shell).
Uji ini memerlukan Word terpasang dan tidak dijalankan secara default.

Hasil disimpan di `storage/app/private/dokumen-rba` dengan kunci hash template,
UPT, dan tahun. Perubahan template otomatis menghasilkan dokumen baru.
