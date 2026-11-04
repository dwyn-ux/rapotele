# E-Rapor Ashidiq Mobile (Android)

Aplikasi mobile Android resmi untuk **E-Rapor Kurikulum Merdeka SMP Muhammadiyah Unggulan Ashidiq**.

## Fitur Utama

1. **Dashboard & Status KBM Real-time**
   - Menampilkan salam dan profil guru/santri.
   - Banner status KBM hari ini dengan countdown ("Sedang Berlangsung" / "15 menit lagi").
   - Ringkasan antrian & pengiriman WhatsApp harian.

2. **Monitor & Update WhatsApp**
   - Melihat antrian pesan WhatsApp (Presensi santri, Surat/Pemberitahuan Pelanggaran, Laporan Mingguan, Pesan Manual).
   - Log pengiriman terkirim & gagal beserta detail response.
   - Tombol **Kirim WA** untuk memicu pemrosesan antrian langsung dari ponsel.
   - Modal detail isi pesan lengkap.

3. **Jadwal Pelajaran & KBM**
   - Tampilan jadwal KBM hari ini lengkap dengan jam ke, waktu mulai - selesai, kelas, dan mapel.
   - Tampilan jadwal mingguan (Senin s/d Sabtu).
   - Tombol pemicu reminder KBM.

4. **Pusat Notifikasi & Triggers**
   - Menerima trigger notifikasi lokal di status bar Android ketika:
     - 15 menit sebelum KBM dimulai.
     - KBM sedang aktif.
     - Ada siswa tidak hadir (Alfa, Sakit, Izin).
     - Ada catatan pelanggaran santri.
     - Terdapat kendala pengiriman pesan WhatsApp.
   - Dilengkapi suara & getaran notifikasi sistem Android.
   - Tombol uji coba trigger notifikasi langsung ke bilah status Android.

5. **Pengaturan Fleksibel**
   - Pengaturan Base URL Server dinamis (bisa diarahkan ke server produksi `https://raport.smpmuhashidiq.sch.id` maupun server lokal saat pengujian).

## Cara Menjalankan

### Menjalankan di Emulator / Device Android
```bash
cd mobile
flutter run
```

### Build APK Debug
```bash
cd mobile
flutter build apk --debug
```
File APK output terletak di:
`mobile/build/app/outputs/flutter-apk/app-debug.apk`

### Build APK Release
```bash
cd mobile
flutter build apk --release
```
