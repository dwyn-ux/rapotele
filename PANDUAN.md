# Buku Panduan E-Rapor KumerBot

> Panduan lengkap penggunaan aplikasi e-rapor untuk Admin, Guru, dan Siswa.
> Versi: 2.0 | Tahun Ajaran 2025/2026

---

## Daftar Isi

1. [Pengenalan](#1-pengenalan)
2. [Instalasi & Setup](#2-instalasi--setup)
3. [Panduan Admin](#3-panduan-admin)
4. [Panduan Guru](#4-panduan-guru)
5. [Panduan Siswa](#5-panduan-siswa)
6. [Cetak Rapor & Dokumen](#6-cetak-rapor--dokumen)
7. [Integrasi Dapodik](#7-integrasi-dapodik)
8. [Backup & Restore](#8-backup--restore)
9. [Integrasi Telegram](#9-integrasi-telegram)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Pengenalan

E-Rapor KumerBot adalah aplikasi web untuk mengelola rapor digital sekolah berbasis Kurikulum Merdeka. Aplikasi ini berjalan di PHP 8+ tanpa framework, menggunakan SQLite atau MySQL sebagai database.

### Fitur Utama

- Input dan pengelolaan nilai siswa
- Cetak rapor PDF otomatis dengan tanda tangan digital
- Presensi siswa per mata pelajaran
- Absensi mengajar guru berbasis geolokasi
- Jurnal harian mengajar
- Pelanggaran siswa
- Integrasi Dapodik (sinkronisasi data)
- Integrasi Telegram (notifikasi, input nilai via bot)
- Backup dan restore data
- Portal siswa untuk melihat progres

### Hak Akses

| Role | Keterangan |
|------|-----------|
| `admin` | Akses penuh ke semua fitur pengaturan dan operasional |
| `guru` | Input nilai, presensi, jurnal, cetak rapor (terbatas pada kelas/mapel yang diampu) |
| `operator` | Sama seperti guru (didefinisikan untuk keperluan administrasi) |
| `siswa` | Melihat progres nilai, kehadiran, pelanggaran, download dokumen |

---

## 2. Instalasi & Setup

### 2.1 Persyaratan Server

- PHP 8.0+ dengan ekstensi: PDO, SQLite3/Mysqlnd, GD, mbstring, zlib
- Web server dengan mod_rewrite (Apache) atau rewrite rules (Nginx)
- Writable directory: `storage/`
- Untuk MySQL: buat database `eraport` terlebih dahulu

### 2.2 Instalasi

1. Upload semua file ke web server
2. Pastikan folder `storage/` writable oleh web server
3. Buka `public/` di browser → otomatis ke halaman login
4. Jika belum terinstall, akan muncul halaman instalasi
5. Klik **Install** → database akan dibuat otomatis
6. Login default: `administrator` / `administrator`

### 2.3 Konfigurasi .env

Buat file `.env` di root project (satu level di atas `public/`):

```env
APP_NAME=E-Rapor KumerBot
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rapor.sekolah.sch.id
APP_TIMEZONE=Asia/Jakarta

# SQLite (default)
DB_DRIVER=sqlite

# Atau MySQL
# DB_DRIVER=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=eraport
# DB_USERNAME=root
# DB_PASSWORD=your_password
```

### 2.4 Setup Awal

Setelah login sebagai admin:

1. **Isi Profil Sekolah** (`?page=school`)
   - Nama sekolah, alamat, NPSN
   - Nama dan NIP Kepala Sekolah
   - Tahun ajaran dan semester aktif
   - Koordinat lat/lng sekolah (untuk absensi geolokasi)
   - Radius absensi (meter)

2. **Upload Logo & Tanda Tangan** (`?page=data-logo-ttd`)
   - Logo sekolah
   - Logo dinas pendidikan
   - Tanda tangan Kepala Sekolah (upload gambar)
   - Tanda tangan Wali Kelas (upload per guru)
   - Stempel (opsional)

3. **Atur Tanggal Rapor** (`?page=tanggal-rapor`)
   - Isi untuk setiap tingkat: tanggal rapor, tempat TTD, catatan

---

## 3. Panduan Admin

### 3.1 Manajemen Guru

**Halaman:** `?page=teachers`

1. Klik **Tambah Guru**
2. Isi data: Nama, NIP, NUPTK, Jenis Kelamin, Telepon, Email, Jabatan
3. Isi Telegram Chat ID jika ingin mengaktifkan notifikasi Telegram
4. Centang **Aktif**
5. Klik **Simpan**

### 3.2 Manajemen Siswa

**Halaman:** `?page=students`

1. Klik **Tambah Siswa**
2. Isi data: NIS, NISN, Nama, Jenis Kelamin, Tempat/Tanggal Lahir, Agama, Pilih Kelas
3. Centang **Aktif**
4. Klik **Simpan**

### 3.3 Manajemen Kelas

**Halaman:** `?page=classes`

1. Klik **Tambah Kelas**
2. Isi data: Nama Kelas (contoh: "7A"), Tingkat (contoh: "7"), Jurusan/Fase
3. Pilih **Wali Kelas** dari dropdown guru
4. Isi Tahun Ajaran
5. Klik **Simpan**

### 3.4 Manajemen Mata Pelajaran

**Halaman:** `?page=subjects`

1. Klik **Tambah Mapel**
2. Isi: Nama Mapel (contoh: "Matematika"), Nama Singkat (contoh: "MTK")
3. Isi Kelompok (contoh: "Kelompok A", "Kelompok B", "Muatan Lokal")
4. Klik **Simpan**

### 3.5 Pembelajaran (Guru - Mapel - Kelas)

**Halaman:** `?page=assignments`

Ini adalah hubungan antara Guru, Mapel, dan Kelas.

1. Klik **Tambah Pembelajaran**
2. Pilih: Guru, Kelas, Mapel
3. Isi Tahun Ajaran dan Semester
4. Klik **Simpan**

> **Penting:** Pembelajaran harus dibuat untuk setiap kombinasi guru-mapel-kelas. Tanpa ini, guru tidak bisa input nilai.

### 3.6 Data Mapping Rapor

**Halaman:** `?page=data-mapping`

Menentukan mapel mana yang tampil di rapor per tingkat.

1. Pilih Tingkat (contoh: "7")
2. Pilih Mapel dari dropdown
3. Pilih Kelompok (opsional)
4. Atur Urutan tampil
5. Centang **Tampil** jika ingin mapel muncul di rapor
6. Klik **Simpan**

> **Catatan:** Jika tidak ada mapping, semua mapel aktif akan tampil di rapor.

### 3.7 Data Kelompok Mapel

**Halaman:** `?page=data-kelompok`

Mengelompokkan mapel di rapor (misal: "Kelompok A: Wajib", "Kelompok B: Peminatan").

1. Isi Kode (contoh: "WAJIB")
2. Isi Nama Kelompok
3. Atur Urutan tampil
4. Klik **Simpan**

### 3.8 Data Ekskul

**Halaman:** `?page=data-ekskul`

1. Isi Nama Rombel Ekskul (contoh: "7A")
2. Isi Jenis (contoh: "Wajib", "Pilihan")
3. Isi Nama Ekskul (contoh: "Pramuka")
4. Pilih Pembina dari dropdown guru
5. Klik **Simpan**

### 3.9 Input Nilai Ekskul

**Halaman:** `?page=input-nilai-ekskul`

1. Pilih Kelas
2. Tabel akan menampilkan siswa × ekskul
3. Isi nilai/keterangan untuk setiap siswa per ekskul (contoh: "Sangat Baik", "Baik", "Cukup")
4. Klik **Simpan Nilai**

### 3.10 Kokurikuler

**Halaman:**
- `?page=tema-kokurikuler` → Daftar tema kokurikuler
- `?page=kegiatan-kokurikuler` → Kegiatan per tema
- `?page=kelompok-kokurikuler` → Kelompok siswa per kegiatan

Alur:
1. Buat Tema (contoh: "Bhinneka Tunggal Ika")
2. Buat Kegiatan (contoh: "Aku Cinta Lingkungan Sekolah", pilih fase)
3. Buat Kelompok (pilih tema, tentukan siswa anggota)

### 3.11 Tujuan Pembelajaran (TP)

**Halaman:** `?page=data-tp`

TP digunakan untuk auto-generate deskripsi capaian kompetensi di rapor.

1. Pilih Mapel dan Tingkat
2. Isi deskripsi Tujuan Pembelajaran
3. Klik **Simpan**

> **Tips:** Semakin banyak TP yang diisi, semakin baik deskripsi rapor yang dihasilkan otomatis.

### 3.12 Upload Foto Siswa

**Halaman:** `?page=foto-siswa`

1. Pilih Siswa
2. Pilih file foto (JPG/PNG)
3. Klik **Upload**

### 3.13 Input Kelulusan (Kelas 9)

**Halaman:** `?page=input-kelulusan`

1. Untuk setiap siswa, pilih Status: Lulus / Tidak Lulus / Naik Kelas / Tinggal Kelas
2. Isi Nomor Ijazah, Nomor Transkrip
3. Isi Tanggal Kelulusan
4. Klik **Simpan**

### 3.14 Pengaturan SKL & Transkrip

- `?page=setting-skl` → Pengaturan tampilan SKL
- `?page=setting-transkrip` → Pengaturan tampilan transkrip
- `?page=mapping-mapel-skl` → Mapping mapel untuk transkrip

### 3.15 Manajemen User

**Halaman:** `?page=users`

1. Klik **Tambah User**
2. Isi Username, Password, Nama, Email
3. Pilih Role: admin / guru / operator / siswa
4. Hubungkan dengan Guru atau Siswa (opsional)
5. Isi Telegram Chat ID jika perlu
6. Klik **Simpan**

> **Tips untuk Siswa:** Buat user dengan role `siswa`, lalu hubungkan dengan record siswa. Siswa akan mendapat portal terpisah.

---

## 4. Panduan Guru

### 4.1 Input Nilai

**Halaman:** `?page=grades`

1. Pilih Pembelajaran (Kelas + Mapel yang diampu)
2. Tabel siswa akan muncul
3. Isi **Nilai** (0-100) dan **Deskripsi** untuk setiap siswa
4. Klik **Simpan**

> **Catatan:**
> - Nilai yang diinput adalah nilai harian (daily assessment)
> - Nilai ujian akhir bisa diinput terpisah oleh admin di `?page=input-nilai-skl`
> - Predikat otomatis dihitung berdasarkan pengaturan admin

### 4.2 Presensi Siswa per Mapel

**Halaman:** `?page=student-attendance`

1. Pilih Pembelajaran (Kelas + Mapel)
2. Pilih Tanggal dan Nomor Pertemuan
3. Isi Topik/ Materi hari ini
4. Atur status kehadiran siswa: Hadir / Sakit / Izin / Alpa / Terlambat
5. Opsional: centang untuk kirim notifikasi WhatsApp ke wali murid yang anaknya alpa
6. Klik **Simpan**

### 4.3 Absensi Mengajar

**Halaman:** `?page=teacher-attendance`

1. Pilih Tanggal
2. Tabel jadwal pelajaran hari itu akan muncul
3. Untuk setiap jadwal: atur Status (Hadir / Sakit / Izin / Dinas Luar), Jam Masuk, Jam Keluar, Catatan
4. Klik **Simpan**

### 4.4 Absensi Kehadiran Guru (Geolokasi)

**Halaman:** `?page=teacher-attendance-self`

1. Aktifkan lokasi di browser
2. Klik **Check In** saat tiba di sekolah (otomatis deteksi lokasi)
3. Klik **Check Out** saat pulang
4. Sistem memvalidasi Anda berada dalam radius sekolah

> **Catatan:** Hanya bisa check in/out sekali per hari. Pastikan GPS aktif.

### 4.5 Jurnal Harian

**Halaman:** `?page=journals`

1. Klik **Tambah Jurnal**
2. Isi: Pembelajaran, Tanggal, Nomor Pertemuan, Topik
3. Isi: Kegiatan, Materi, Hambatan, Tindak Lanjut
4. Klik **Simpan**

### 4.6 Keterangan Naik Kelas

**Halaman:** `?page=naik-kelas` (hanya semester genap)

1. Untuk setiap siswa, pilih: Naik Kelas / Tinggal Kelas
2. Isi Catatan (opsional)
3. Klik **Simpan**

### 4.7 Status Penilaian

**Halaman:** `?page=status-penilaian`

Melihat progres input nilai seluruh guru per kelas. Admin dan guru bisa melihat:
- Berapa siswa yang sudah/s belum dinilai
- Persentase kelengkapan

---

## 5. Panduan Siswa

Siswa login dengan akun yang sudah dibuat admin. Dashboard siswa berbeda dari admin/guru.

### 5.1 Dashboard

Menampilkan:
- Rata-rata Nilai
- Jumlah Hadir
- Jumlah Alpa
- Jumlah Pelanggaran
- Link cepat ke fitur siswa

### 5.2 Progres Nilai

**Halaman:** `?page=student-progress`

Menampilkan daftar semua mata pelajaran beserta:
- Rata-rata nilai
- Deskripsi/capaian kompetensi

### 5.3 Kehadiran

**Halaman:** `?page=student-attendance-view`

Menampilkan:
- Ringkasan: Hadir, Sakit, Izin, Alpa, Terlambat
- Detail per pertemuan: Tanggal, Mapel, Pertemuan ke-, Topik, Status, Catatan

### 5.4 Pelanggaran

**Halaman:** `?page=student-violations`

Menampilkan:
- Total pelanggaran dan total poin
- Detail: Tanggal, Jenis, Deskripsi, Poin, Tindak Lanjut

### 5.5 Dokumen Kelulusan (Kelas 9+)

**Halaman:** `?page=student-documents`

Hanya untuk siswa kelas 9:
- Status kelulusan
- Nomor ijazah dan transkrip
- Link download: SKL, Ijazah, Transkrip (PDF)

---

## 6. Cetak Rapor & Dokumen

### 6.1 Cetak Rapor per Siswa

**Halaman:** `?page=cetak-nilai-rapor`

1. Pilih Kelas
2. Klik **Generate Rapor Kelas Ini** untuk generate semua PDF sekaligus
3. Untuk per siswa: klik **Download** atau **Tampilkan** (preview di browser)

### 6.2 Struktur Rapor PDF

**Halaman 1:**
- Identitas siswa (nama, NIS/NISN, kelas, semester, sekolah, tahun ajaran)
- Tabel "LAPORAN HASIL BELAJAR" dengan kolom: No, Mata Pelajaran, KKM, Nilai, Predikat, Capaian Kompetensi
- Di-group berdasarkan kelompok mapel

**Halaman 2:**
- Kokurikuler (teks justifikasi)
- Ekstrakurikuler (tabel No, Nama, Keterangan/Nilai)
- Ketidakhadiran (Sakit, Izin, Tanpa Keterangan)
- Catatan Wali Kelas (teks justifikasi)
- Tanggapan Orang Tua/Wali Murid (kosong untuk diisi manual)
- Keterangan Kenaikan Kelas
- Tanda tangan: Orang Tua, Kepala Sekolah, Wali Kelas

### 6.3 Dokumen Lainnya

**Halaman:** `?page=cetak-nilai-rapor` → pilih tipe di menu

| Menu | Keterangan |
|------|-----------|
| Cetak Biodata | Tabel biodata siswa (Nama, NIS, NISN, JK, TTL, Agama) |
| Leger Rapor | Tabel nilai seluruh siswa × mapel + rata-rata |
| Leger PTS | Leger untuk Penilaian Tengah Semester |
| Rapor PTS | Rapor khusus PTS per siswa |
| Buku Induk | Sama dengan biodata |

### 6.4 Pengaturan Cetak

Untuk setiap dokumen cetak, tersedia opsi:
- **Posisi TTD Kepsek:** Sejajar Wali Kelas / Di Bawah Wali Kelas
- **Tanda Tangan:** Tanpa / Dengan Tanda Tangan
- **Ukuran Kertas:** A4 / F4
- **Margin:** Kiri, Kanan, Atas, Bawah (mm)

---

## 7. Integrasi Dapodik

### 7.1 Update Data (Tarik dari Dapodik)

**Halaman:** `?page=update-data`

**Cara Online (jika Dapodik bisa diakses langsung):**
1. Isi URL Dapodik (default: `http://127.0.0.1:5774`)
2. Isi Token Web Service
3. Isi NPSN
4. Klik **Test Koneksi**

**Cara Offline (menggunakan Portable Helper):**
1. Download config dan ZIP helper dari halaman ini
2. Jalankan helper di komputer yang bisa akses Dapodik
3. Helper akan menampilkan JSON → copy-paste ke kolom import
4. Klik **Import JSON**

**Cara Offline (menggunakan file):**
1. Export JSON dari helper Dapodik
2. Upload file JSON di halaman ini
3. Klik **Import**

**Tipe data yang bisa diimport:**
- `sekolah` → Data sekolah
- `guru` → Data guru
- `siswa` → Data siswa
- `rombel` → Rombongan belajar (kelas)
- `anggota_rombel` → Anggota rombel (siswa per kelas + ekskul)
- `mapel` → Mata pelajaran
- `pembelajaran` → Jadwal pembelajaran
- `all` → Semua data sekaligus

### 7.2 Kirim Data (Push ke Dapodik)

**Halaman:** `?page=kirim-data-dapodik`

Mengirim nilai siswa dari e-rapor ke Dapodik:
1. Pilih tipe data: `nilai` (nilai siswa) atau `matev` (materi evaluasi)
2. Klik **Kirim Data Nilai**

---

## 8. Backup & Restore

### 8.1 Backup

**Halaman:** `?page=backup-restore`

1. Klik **Backup Data**
2. File JSON akan diunduh otomatis
3. Backup berisi: semua data database + file upload (logo, foto, rapor)

### 8.2 Restore

1. Klik **Restore Data**
2. Upload file backup JSON
3. Klik **Proses**
4. **PERINGATAN:** Restore akan menghapus semua data yang ada dan menggantinya dengan data backup

### 8.3 Tips Backup

- Backup secara berkala (sebelum input nilai besar, akhir semester, dll)
- Simpan backup di tempat aman (cloud storage, external disk)
- File backup berformat `.json` dengan nama `backup-YYYYMMDD-HHMMSS.json`
- Jangan restore backup dari versi aplikasi yang berbeda tanpa hati-hati

---

## 9. Integrasi Telegram

### 9.1 Setup Bot

**Halaman:** `?page=telegram`

1. Buat bot Telegram via @BotFather → dapatkan Bot Token
2. Isi Bot Token di pengaturan aplikasi atau `.env`
3. Set webhook: klik link yang tersedia di halaman Telegram

### 9.2 Registrasi Guru via Telegram

1. Guru chat ke bot: `/daftar`
2. Bot akan memberikan link registrasi web
3. Guru isi data: nama, username, password, NIP, NUPTK, mata pelajaran, kelas
4. Setelah registrasi, akun web dan Telegram terhubung

### 9.3 Notifikasi

- Notifikasi pelanggaran ke wali murid (via WhatsApp)
- Reminder jadwal mengajar
- Login via Telegram web (one-time link)

---

## 10. Troubleshooting

### 10.1 Masalah Umum

| Masalah | Solusi |
|---------|--------|
| "Aplikasi sudah terpasang" | Aplikasi terkunci setelah install. Jika perlu reinstall, jalankan `php public/install.php` via CLI |
| Login gagal terus | Cek rate limiting (5 percobaan per 15 menit). Tunggu atau hapus file di `storage/rate-limit/` |
| PDF tidak bisa di-download | Pastikan folder `storage/reports/` writable |
| Logo/ttd tidak muncul di PDF | Pastikan file terupload ke `storage/uploads/signatures/` |
| Nilai tidak tampil di rapor | Cek apakah ada Pembelajaran (guru-mapel-kelas) yang sudah benar |
| Mapel tidak tampil di rapor | Cek Data Mapping Rapor (`?page=data-mapping`) — pastikan mapel di-set "Tampil" |
| Absensi geolokasi gagal | Pastikan GPS aktif, koordinat sekolah benar, radius cukup |
| Import Dapodik gagal | Cek token dan NPSN harus sama dengan yang di Dapodik |
| "Token tidak diterima" di bridge | Isi Token Web Service di menu Update Data secara manual |

### 10.2 Perintah CLI

```bash
# Install/reset database
php public/install.php

# Seed data demo
php seed_report.php

# Generate rapor manual untuk siswa ID 1
php -r "
require 'app/bootstrap.php';
require 'app/web.php';
require 'app/Services/pdf.php';
echo generate_student_report_pdf(1);
"

# Smoke test
php tools/smoke_test.php
```

### 10.3 Struktur File Penting

```
erapotkumerbot/
├── .env                    # Konfigurasi environment
├── config/
│   └── config.php          # Konfigurasi utama
├── public/
│   ├── index.php           # Entry point web
│   ├── .htaccess           # URL rewrite + security
│   ├── install.php         # Installer
│   ├── media.php           # Serving file upload
│   ├── dapodik_bridge.php  # Dapodik API endpoint
│   └── assets/
│       ├── app.css         # Stylesheet
│       └── app.js          # JavaScript
├── app/
│   ├── web.php             # Bootstrapper
│   ├── routes.php          # Route definitions
│   ├── actions.php         # POST action map
│   ├── Core/
│   │   ├── database.php    # PDO wrapper
│   │   ├── security.php    # Auth, CSRF, rate limit
│   │   ├── http.php        # URL helpers, dispatch
│   │   ├── config.php      # Config reader
│   │   └── helpers.php     # Utility functions
│   ├── Pages/              # Halaman web
│   ├── Actions/            # Action handlers
│   └── Services/           # PDF, Telegram, WhatsApp, Schedule
└── storage/
    ├── eraport.sqlite      # Database file (SQLite)
    ├── reports/            # Generated PDFs
    ├── backups/            # Backup files
    └── uploads/            # User uploads
        ├── signatures/     # Logo, TTD
        └── student-photos/ # Foto siswa
```

### 10.4 Keamanan

Untuk shared hosting:
1. Pastikan `.htaccess` aktif (mod_rewrite)
2. Set `APP_DEBUG=false` di `.env`
3. Ganti password admin setelah install
4. Hapus `seed_report.php` dari server production
5. Backup database secara berkala
6. Pastikan `storage/` tidak bisa diakses langsung (sudah ada `.htaccess`)

---

## Catatan untuk Pengembang

### Penambahan Menu

Edit `app/Pages/render.php` → array `$menu` di function `render_header()`.

### Penambahan Halaman

1. Buat function `page_xxx()` di file `app/Pages/xxx.php`
2. Tambah route di `app/routes.php`: `'xxx' => 'page_xxx'`
3. Tambah ke array menu di `app/Pages/render.php`

### Penambahan Action

1. Buat function `action_xxx()` di file `app/Actions/xxx.php`
2. Tambah di `app/actions.php`: `'xxx' => 'action_xxx'`

---

*Panduan ini dibuat untuk E-Rapor KumerBot v2.0. Untuk pertanyaan dan dukungan, hubungi administrator.*
