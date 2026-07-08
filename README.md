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

Login awal:

- username: `administrator`
- password: `administrator`

Segera ganti password dari menu Profil.

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

### Import bersih via phpMyAdmin

Jika ingin melewati installer, import file `database/import_clean_mysql.sql` lewat phpMyAdmin.

File ini akan menghapus tabel aplikasi lama, membuat ulang schema, lalu mengisi data minimal saja:

- akun awal `administrator` / `administrator`
- profil sekolah kosong
- setting dasar Dapodik, Telegram, dan WhatsApp

Tidak ada data dummy siswa, guru, kelas, nilai, absensi, jurnal, atau pelanggaran. Setelah import, buka `https://domain-anda/index.php?page=login` dan jangan jalankan `install.php`.

## Telegram

1. Buat bot via BotFather dan isi token di `config/config.php`.
2. Buka menu Bot Telegram untuk melihat URL webhook.
3. Set webhook:

```text
https://api.telegram.org/botTOKEN_ANDA/setWebhook?url=https%3A%2F%2Fdomain-anda%2Ftelegram_webhook.php
```

Perintah bot:

```text
/login username password
/menu
/web
/jadwal
/requestjadwal
/kelas
/hadir ID_PEMBELAJARAN YYYY-MM-DD [PERTEMUAN] [topik]
/absen ID_PEMBELAJARAN YYYY-MM-DD NIS status [catatan]
/jurnal ID_PEMBELAJARAN YYYY-MM-DD | topik | kegiatan | materi | kendala | tindak_lanjut
/profil
/logout
```

Status absensi siswa: `hadir`, `sakit`, `izin`, `alpa`, `terlambat`.

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

## Helper Dapodik Lokal

Buka menu `Update Data`, isi `Link Dapodik Lokal`, `Token / Key Webservice`, dan `NPSN`, lalu pilih `Unduh Portable ZIP v2.3`. Extract ZIP ke folder tetap di komputer Dapodik, misalnya `Desktop\Bridge-Dapodik`, lalu jalankan `jalankan-bridge-portable.bat` atau `eraport-dapodik-bridge-helper-v2.3.exe`.

Mode portable menyimpan konfigurasi di `eraport-bridge-config.txt` pada folder yang sama dengan EXE. Model sinkronnya mengikuti pola RaportKu: helper cukup memakai `NPSN`, `Link Web Raport`, dan `Token Web Service Dapodik`. Token yang sama dipakai untuk membaca Dapodik lokal dan mengirim ke server e-rapor. Jika token berubah, tidak perlu download EXE ulang; ubah field token lalu klik `Simpan Konfigurasi`, atau download `Unduh Config Portable` dan ganti file `eraport-bridge-config.txt`.

Gunakan tombol `Sinkron Data Dasar` di helper portable untuk menarik sekolah, guru, rombel, siswa, anggota rombel, dan pembelajaran. Rombel kelas Dapodik masuk ke menu `Data Kelas`, sedangkan rombel ekskul tidak dimasukkan ke Data Kelas. Data `anggota_rombel` dipakai untuk menempelkan siswa ke kelas masing-masing dan membuat ekskul hanya jika rombel ekskul punya anggota. Mapel dibuat dari data pembelajaran yang punya guru, sehingga referensi mapel mentah dari Dapodik tidak lagi memenuhi data mapel. Import JSON offline di menu `Update Data` tetap tersedia untuk file JSON Dapodik yang sudah ada. Jika Token / Key Webservice di server e-rapor masih kosong, bridge akan mengisi token dan NPSN server otomatis dari helper saat sinkron pertama berhasil membaca Dapodik lokal.

Jika bridge masih membalas `Token sinkron tidak valid`, berarti server e-rapor sudah punya token/NPSN berbeda atau request tidak membawa NPSN. Pastikan `NPSN` dan `Token / Key Webservice` di menu `Update Data` server tujuan sama dengan yang dipakai helper portable, lalu klik `Simpan Konfigurasi` atau ganti file `eraport-bridge-config.txt` dengan hasil `Unduh Config Portable`.

Helper v2.3 mengirim token sinkron lewat header dan body JSON sekaligus agar tetap kompatibel dengan hosting/proxy yang menghapus custom header.

Jika log helper menampilkan respons Dapodik diawali `Access`, koneksi ke Dapodik sudah tembus tetapi Web Service menolak akses. Cek ulang Token Web Service Dapodik, NPSN, dan pastikan Web Service Dapodik aktif. Beberapa versi Dapodik menolak token di URL browser biasa dan hanya menerima header `Authorization: Bearer <token>`; helper v2 sudah mencoba mode Bearer lebih dulu lalu fallback ke token query.

Untuk production, pakai token dari baris Web Service Dapodik yang benar-benar lolos test Bearer. Jika ada token lain tetap menghasilkan `403 Forbidden` walaupun NPSN benar, hapus lalu buat ulang baris aplikasi Web Service itu di Dapodik, isi IP Address dengan `localhost`, salin token baru, lalu test dari helper v2. Token yang gagal `403` jangan dipakai untuk sinkron produksi.

Catatan: Dapodik kadang mengirim status HTTP `200` tetapi isi responsnya teks `HTTP/1.0 403 Forbidden...`. Karena itu validasi helper membaca isi respons dan hanya menerima respons JSON Dapodik yang valid.

Jika source helper Windows berubah, build ulang EXE dengan:

```powershell
& .\tools\build_windows_helper.ps1
```

Jika policy PowerShell memblokir script, jalankan dulu `Set-ExecutionPolicy -Scope Process Bypass`, lalu ulangi command build. Script ini mencari `csc.exe` dari PATH lalu fallback ke .NET Framework bawaan Windows, membuat ulang `public/downloads/eraport-dapodik-bridge-helper-base.exe`, dan memvalidasi binary sudah memakai `Authorization: Bearer` serta endpoint anggota rombel.

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
