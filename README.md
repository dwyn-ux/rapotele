# E-Raport KumerBot

Aplikasi e-rapor PHP murni untuk shared hosting tanpa Node.js.

## Fitur

- Login multi-role: admin, operator, guru.
- Data sekolah, guru, siswa, kelas, mata pelajaran dari pembelajaran berguru, dan pembelajaran.
- Input nilai akhir sederhana per pembelajaran.
- Absensi siswa per mata pelajaran dan per pertemuan.
- Absensi guru harian.
- Jurnal harian guru.
- Laporan ringkas kelas.
- Webhook Telegram untuk login guru, input absensi, dan input jurnal.
- Reminder Telegram untuk guru sebelum jadwal mengajar dimulai.
- WhatsApp weekly report untuk wali santri, dengan mode simulasi dan WhatsApp Cloud API.
- Upload logo sekolah untuk logo aplikasi, favicon, dan dokumen; tersedia juga logo dinas untuk kop SKL/ijazah/transkrip.

## Instal lokal

Default config memakai SQLite di `storage/eraport.sqlite`.

```bash
php tools/install.php
php -S 127.0.0.1:8000 -t public
```

Buka `http://127.0.0.1:8000`.

Installer menampilkan password awal acak satu kali untuk akun `administrator`. Simpan password tersebut saat instalasi dan segera ganti dari menu Profil.

## Logo aplikasi dan dokumen

Buka menu `Data Logo dan TTD`.

- Upload tipe `Logo Sekolah` untuk mengganti logo aplikasi, ikon browser/favicon, dan logo sekolah di dokumen.
- Upload tipe `Logo Dinas` untuk logo dinas pada kop dokumen kelulusan seperti SKL.
- Pada dokumen kelulusan, logo dinas ditampilkan di kiri dan logo sekolah di kanan.

## Instal shared hosting

1. Upload paket aplikasi ke hosting.
2. Jika hosting bisa mengatur document root, arahkan domain ke folder `public`.
3. Jika hosting hanya menyediakan `public_html`, upload seluruh isi paket ke `public_html`; file `.htaccess` root akan meneruskan request ke folder `public` dan memblokir folder internal.
4. Edit `config/config.php`.
5. Isi `APP_URL`, host, database, username, dan password MySQL.
6. Pastikan folder `storage` writable.
7. Buka `https://domain-anda/install.php` sekali.
8. Login ke `https://domain-anda/index.php?page=login`.
9. Setelah berhasil, hapus atau batasi akses `install.php` bila hosting mengizinkan.

Gunakan installer aplikasi untuk membuat skema dan akun admin pertama. Import SQL dengan akun/password bawaan sengaja tidak lagi disediakan.

## Telegram

1. Buat bot via BotFather dan isi token di `config/config.php`.
2. Buka menu Bot Telegram untuk melihat URL webhook.
3. Set webhook:

```text
https://api.telegram.org/botTOKEN_ANDA/setWebhook?url=https%3A%2F%2Fdomain-anda%2Ftelegram_webhook.php
```

Perintah bot:

```text
/daftar
/daftar Nama Guru Mapel
/daftar Nama Guru | Mapel | Kelas
/login password
/login username password
/menu
/web
/jadwal
/requestjadwal
/kelas
/absensi
/absensiguru
/hadir ID_PEMBELAJARAN YYYY-MM-DD [PERTEMUAN] [topik]
/absen ID_PEMBELAJARAN YYYY-MM-DD NIS status [catatan]
/jurnal ID_PEMBELAJARAN YYYY-MM-DD | topik | kegiatan | materi | kendala | tindak_lanjut
/profil
/logout
```

Status absensi siswa: `hadir`, `sakit`, `izin`, `alpa`, `terlambat`.

Ketik `/start` untuk membuka menu home. Kalau belum login, bot menampilkan kartu pilihan login. Kalau sudah login, bot menampilkan kartu menu guru.

Ketik `/login` tanpa parameter untuk menampilkan kartu pilihan login dengan tombol. Bot juga menerima format tanpa garis miring, misalnya `login password`. Tombol web dibuka sebagai miniweb di Telegram. Untuk akun yang sudah login di bot, tombol menu guru memakai token satu-kali agar halaman miniweb langsung masuk tanpa login web ulang.

Ketik `/daftar` tanpa isian untuk membuka miniweb pendaftaran guru. Form ini membuat data guru dan akun pengguna sekaligus. Setelah form selesai, guru bisa login bot dengan `/login password`. Format `/login username password` tetap tersedia untuk akun lama atau akun yang belum terhubung Telegram.

Perintah `/menu` dan `/web` menampilkan link halaman web untuk pekerjaan yang lebih nyaman dikerjakan lewat form, seperti jadwal, nilai, absensi, dan jurnal. Pastikan `APP_URL` atau `base_url` di config sudah berisi domain aplikasi agar link Telegram mengarah ke web yang benar.

### Reminder jadwal guru

Aplikasi bisa mengirim notifikasi Telegram ke guru sebelum jadwal mengajar dimulai.

1. Isi `TELEGRAM_BOT_TOKEN`.
2. Isi `SCHEDULE_REMINDER_SECRET` dengan token random.
3. Pastikan guru sudah punya `telegram_chat_id` lewat `/login` atau `/daftar`.
4. Jalankan migrasi:

```bash
php tools/install.php
```

5. Set cron tiap 1 menit atau 5 menit:

```bash
php tools/schedule_reminders.php
```

Untuk shared hosting yang hanya mendukung cron URL, panggil:

```text
https://domain-anda/schedule_reminders.php?secret=SECRET_ANDA
```

Default reminder dikirim 10 menit sebelum jadwal. Bisa diubah lewat `SCHEDULE_REMINDER_MINUTES_BEFORE`.

## Import Data Dapodik

Menu `Update Data` hanya menerima JSON hasil export Dapodik yang diunggah manual. Aplikasi tidak menyediakan sinkronisasi online, pengiriman balik nilai, bridge, helper portable, atau penyimpanan token Dapodik.

Untuk data peserta didik, menu `Import Data` juga menerima file export Dapodik `daftar_pd-*.xlsx`. Import tersebut hanya menyimpan data siswa dan kelas; akun login siswa dibuat terpisah agar setiap password unik.

## WhatsApp Weekly Report

Buka menu `WhatsApp Report`.

Fitur yang tersedia:

- Data wali santri dan nomor WhatsApp per siswa.
- Template pesan weekly report, pelanggaran, absensi, dan pesan manual.
- Antrian dan log pengiriman.
- Mode `Simulasi / Log Saja` untuk tes tanpa token.
- Mode `WhatsApp Cloud API` untuk pengiriman produksi.
- Mode `Fonnte Gateway` untuk gateway WhatsApp pihak ketiga berbasis token device.
- Tombol kirim kondisional dari menu pelanggaran siswa.
- Opsi kirim WA dari absensi untuk siswa selain hadir.

Alur produksi WhatsApp Cloud API:

1. Isi `Phone Number ID`, `WABA ID`, `Access Token`, dan `Graph Version` di menu `WhatsApp Report`.
2. Pilih mode `WhatsApp Cloud API`.
3. Untuk weekly report rutin, set cron:

```bash
php tools/whatsapp_weekly.php
```

Untuk shared hosting yang hanya mendukung cron URL, panggil:

```text
https://domain-anda/whatsapp_weekly.php?secret=SECRET_ANDA
```

Jika memakai template resmi WhatsApp, isi `Nama Template Cloud`, `Language Code`, dan `Urutan Parameter` pada template di aplikasi, lalu pilih `Jenis Kirim Cloud: Template Message`.

Untuk Fonnte:

1. Hubungkan device WhatsApp di dashboard Fonnte.
2. Salin token device.
3. Buka menu `WhatsApp Report`.
4. Pilih mode `Fonnte Gateway`.
5. Isi `Token Fonnte` dan `Country Code Fonnte` dengan `62`.
6. Jalankan pengiriman dari antrian atau cron weekly seperti biasa.
