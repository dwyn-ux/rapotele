<?php

require 'app/bootstrap.php';

$raw = <<<'SCHED'
1. SENIN
06.45 - 07.00: APEL

Jam 1 (07.00 - 07.35)

VII A: PJOK (Ust. Kasfaril Ramadani / Ust. Muhammad Abdullah)

VIII A: PJOK (Ust. Kasfaril Ramadani / Ust. Muhammad Abdullah)

IX A: PJOK (Ust. Kasfaril Ramadani / Ust. Muhammad Abdullah)

VII B: PJOK (Ust. Kasfaril Ramadani / Ust. Muhammad Abdullah)

VIII B: PJOK (Ust. Kasfaril Ramadani / Ust. Muhammad Abdullah)

IX B: PJOK (Ust. Kasfaril Ramadani / Ust. Muhammad Abdullah)

X: SOSIOLOGI (Ust. Setiyoko, S. Pd)

XI: MATEMATIKA (Ust. Kodir, S. T.)

XII A: BIOLOGI (Ust. Rohmad Sigid Affandi, S.H)

XII B: BAHASA INDONESIA (Usth. Mekha Eka Sari, S.Sos)

Jam 2 (07.35 - 08.10)

VII A - IX B: PJOK (Ust. Kasfaril Ramadani / Ust. Muhammad Abdullah)

X: SOSIOLOGI (Ust. Setiyoko, S. Pd)

XI: MATEMATIKA (Ust. Kodir, S. T.)

XII A: BIOLOGI (Ust. Rohmad Sigid Affandi, S.H)

XII B: BAHASA INDONESIA (Usth. Mekha Eka Sari, S.Sos)

08.10 - 08.25: ISTIRAHAT

Jam 3 (08.25 - 09.00)

VII A: PRAKARYA (Usth. Rosita Nova)

VIII A: TAHFIDZ (Ust. Salafi Hafizh Azzikri)

IX A: TARIQ (Ust. Asyik Nur Rahman)

VII B: BAHASA INGGRIS (Usth. Mesya Sukmayati, S. Pd)

VIII B: BAHASA INDONESIA (Usth. Mekha Eka Sari, S.Sos)

IX B: FIQIH (Ust. Muhammad Abdullah)

X: MATEMATIKA (Ust. Kodir, S. T.)

XI: SOSIOLOGI (Ust. Setiyoko, S. Pd)

XII A: TAUHID (Ust. Fahmi Dwi Payana, S. H)

XII B: SHOROF (Ust. Rohmad Sigid Affandi, S.H)

Jam 4 (09.00 - 09.35)

VII A: PRAKARYA (Usth. Rosita Nova) | VIII A: TAHFIDZ (Ust. Salafi) | IX A: TARIQ (Ust. Asyik) | VII B: BAHASA INGGRIS (Usth. Mesya) | VIII B: BAHASA INDONESIA (Usth. Mekha) | IX B: FIQIH (Ust. Abdullah) | X: MATEMATIKA (Ust. Kodir) | XI: SOSIOLOGI (Ust. Setiyoko) | XII A: TAUHID (Ust. Fahmi) | XII B: SHOROF (Ust. Rohmad)

Jam 5 (09.35 - 10.10)

VII A: BAHASA INGGRIS (Usth. Mesya)

VIII A: TARIQ (Ust. Asyik)

IX A: AKIDAH AKHLAK (Ust. Abdullah)

VII B: TAHFIDZ (Usth. Amelia Septiana)

VIII B: BAHASA JAWA (Usth. Maya Yulaicha)

IX B: BAHASA INDONESIA (Usth. Mekha)

X: PKN (Ust. Rahmat Yulianto)

XI: TAUHID (Ust. Fahmi)

XII A: MATEMATIKA (Ust. Kodir)

XII B: SEJARAH (Ust. Setiyoko)

Jam 6 (10.10 - 10.45)

VII A: BAHASA INGGRIS (Usth. Mesya) | VIII A: IPA (Usth. Rosita Nova) | IX A: AKIDAH AKHLAK (Ust. Abdullah) | VII B: MATEMATIKA (Usth. Ina Rusiana) | VIII B: BAHASA JAWA (Usth. Maya) | IX B: BAHASA INDONESIA (Usth. Mekha) | X: PKN (Ust. Rahmat) | XI: TAUHID (Ust. Fahmi) | XII A: MATEMATIKA (Ust. Kodir) | XII B: SEJARAH (Ust. Setiyoko)

Jam 7 (10.45 - 11.20)

VII A: IPS (Ust. Rahmat Yulianto)

VIII A: IPA (Usth. Rosita Nova)

IX A: MATEMATIKA NALARIA (Ust. Kodir)

VII B: MATEMATIKA (Usth. Ina)

VIII B: TARIQ (Ust. Asyik)

IX B: BAHASA ARAB (Usth. Ella Nur Fatimah)

X: BAHASA INDONESIA (Usth. Mekha)

XI: BAHASA JAWA (Usth. Maya)

XII A & XII B: LATIHAN SOAL (Ust. Fahmi Dwi Payana)

11.20 - 12.15: ISHOMA

Jam 8 (12.15 - 12.50)

VII A: QUR'AN HADITS (Usth. Maria Hindun)

VIII A: PKN (Ust. Rahmat)

IX A: IPA (Usth. Rosita Nova)

VII B: TAHFIDZ (Usth. Amelia)

VIII B: TARIQ (Ust. Asyik)

IX B: BAHASA ARAB (Usth. Ella)

X: BAHASA INDONESIA (Usth. Mekha)

XI: BAHASA JAWA (Usth. Maya)

XII A & XII B: LATIHAN SOAL (Ust. Fahmi)

Jam 9 (12.50 - 13.25)

VII A: QUR'AN HADITS (Usth. Maria) | VIII A: PKN (Ust. Rahmat) | IX A: IPA (Usth. Rosita) | VII B: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | VIII B: BAHASA ARAB (Usth. Ella) | IX B: BAHASA JAWA (Usth. Maya) | X & XI: QIRAATUL QUTUB (Ust. Abdullah) | XII A: SEJARAH (Ust. Setiyoko) | XII B: TIK (Ust. Fahmi)

Jam 10 (13.25 - 14.00)

VII A: TAHFIDZ (Ust. Anis Musthova) | VIII A: TARIQ (Ust. Asyik) | IX A: PKN (Ust. Rahmat) | VII B: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | VIII B: BAHASA ARAB (Usth. Ella) | IX B: BAHASA JAWA (Usth. Maya) | X & XI: QIRAATUL QUTUB (Ust. Abdullah) | XII A: SEJARAH (Ust. Setiyoko) | XII B: TIK (Ust. Fahmi)

2. SELASA
06.45 - 07.00: APEL

Jam 1 (07.00 - 07.35)

VII A: TAHFIDZ (Ust. Anis Musthova) | VIII A: TAHFIDZ (Ust. Salafi) | IX A: TAHFIDZ (Ust. Adin Rahmatullah) | VII B: TAHFIDZ (Usth. Amelia) | VIII B: MATEMATIKA (Ust. Kodir) | IX B: TAHFIDZ (Usth. Nafidzah Ilma) | X: TIK (Ust. Fahmi) | XI: IMLA' (Usth. Alfiyaturradhiyah) | XII A: BAHASA INDONESIA (Usth. Mekha) | XII B: TARIQ (Ust. Rohmad Sigid)

Jam 2 (07.35 - 08.10)

VII A: BAHASA JAWA (Usth. Maya) | VIII A: AKIDAH AKHLAK (Ust. Abdullah) | IX A: PKN (Ust. Rahmat) | VII B: MATEMATIKA (Usth. Ina) | VIII B: MATEMATIKA (Ust. Kodir) | IX B: TAHFIDZ (Usth. Nafidzah) | X: TIK (Ust. Fahmi) | XI: IMLA' (Usth. Alfiyaturradhiyah) | XII A: BAHASA INDONESIA (Usth. Mekha) | XII B: TARIQ (Ust. Rohmad Sigid)

Jam 3 (08.10 - 08.45)

VII A: BAHASA JAWA (Usth. Maya) | VIII A: AKIDAH AKHLAK (Ust. Abdullah) | IX A: MATEMATIKA (Ust. Kodir) | VII B: MATEMATIKA (Usth. Ina) | VIII B: TAHFIDZ (Usth. Amelia) | IX B: BAHASA INGGRIS (Usth. Mesya) | X: BIOLOGI (Usth. Rosita Nova) | XI: BAHASA INDONESIA (Usth. Mekha) | XII A: TIK (Ust. Fahmi) | XII B: HADITS (Ust. Rohmad Sigid)

08.45 - 09.00: ISTIRAHAT

Jam 4 (09.00 - 09.35)

VII A: BAHASA ARAB (Usth. Ella) | VIII A: TAHFIDZ (Ust. Salafi) | IX A: MATEMATIKA (Ust. Kodir) | VII B: PKN (Ust. Rahmat) | VIII B: AKIDAH AKHLAK (Ust. Abdullah) | IX B: BAHASA INGGRIS (Usth. Mesya) | X: BIOLOGI (Usth. Rosita Nova) | XI: BAHASA INDONESIA (Usth. Mekha) | XII A: TIK (Ust. Fahmi) | XII B: HADITS (Ust. Rohmad Sigid)

Jam 5 (09.35 - 10.10)

VII A: BAHASA INDONESIA (Usth. Mekha) | VIII A: BAHASA INGGRIS (Usth. Mesya) | IX A: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | VII B: PKN (Ust. Rahmat) | VIII B: AKIDAH AKHLAK (Ust. Abdullah) | IX B: MATEMATIKA (Ust. Kodir) | X: TARIQ (Ust. Kasfaril) | XI: BIOLOGI (Usth. Rosita Nova) | XII A: BAHASA JAWA (Usth. Maya) | XII B: FIQIH (Ust. Fahmi)

Jam 6 (10.10 - 10.45)

VII A: BAHASA INDONESIA (Usth. Mekha) | VIII A: BAHASA INGGRIS (Usth. Mesya) | IX A: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | VII B: AKIDAH AKHLAK (Ust. Abdullah) | VIII B: PKN (Ust. Rahmat) | IX B: MATEMATIKA (Ust. Kodir) | X: TARIQ (Ust. Kasfaril) | XI: BIOLOGI (Usth. Rosita Nova) | XII A: BAHASA JAWA (Usth. Maya) | XII B: FIQIH (Ust. Fahmi)

Jam 7 (10.45 - 11.20)

VII A: SENI BUDAYA (Usth. Maya) | VIII A: TAHFIDZ (Ust. Salafi) | IX A: TAHFIDZ (Ust. Adin) | VII B: AKIDAH AKHLAK (Ust. Abdullah) | VIII B: PKN (Ust. Rahmat) | IX B: TARIQ (Ust. Asyik) | X: MATEMATIKA NALARIA (Ust. Kodir) | XI: HADITS (Ust. Rohmad Sigid) | XII A: TARIQ (Ust. Kasfaril) | XII B: BAHASA INDONESIA (Usth. Mekha)

11.20 - 12.15: ISHOMA

Jam 8 (12.15 - 12.50)

VII A: SENI BUDAYA (Usth. Maya) | VIII A: TAHFIDZ (Ust. Salafi) | IX A: FIQIH (Ust. Abdullah) | VII B: TAHFIDZ (Usth. Amelia) | VIII B: IPS (Ust. Rahmat) | IX B: IPA (Usth. Rosita Nova) | X: IMLA' (Usth. Alfiyaturradhiyah) | XI: HADITS (Ust. Rohmad Sigid) | XII A: TARIQ (Ust. Kasfaril) | XII B: BAHASA INDONESIA (Usth. Mekha)

Jam 9 (12.50 - 13.25)

VII A: TIK (Usth. Ekasari Kurniawati) | VIII A: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | IX A: BAHASA INDONESIA (Usth. Mekha) | VII B: SENI BUDAYA (Usth. Maya) | VIII B: IPS (Ust. Rahmat) | IX B: IPA (Usth. Rosita Nova) | X & XI: QIRAATUL QUTUB (Ust. Abdullah) | XII A: HADITS (Ust. Rohmad Sigid) | XII B: TAHFIDZ (Usth. Nafidzah)

Jam 10 (13.25 - 14.00)

VII A: TIK (Usth. Ekasari Kurniawati) | VIII A: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | IX A: BAHASA INDONESIA (Usth. Mekha) | VII B: SENI BUDAYA (Usth. Maya) | VIII B: TAHFIDZ (Usth. Nafidzah) | IX B: TAHFIDZ (Usth. Amelia) | X & XI: QIRAATUL QUTUB (Ust. Abdullah) | XII A: HADITS (Ust. Rohmad Sigid) | XII B: PKN (Ust. Rahmat)

3. RABU
06.45 - 07.00: APEL

Jam 1 (07.00 - 07.35)

VII A: TAHFIDZ (Ust. Anis) | VIII A: TAHFIDZ (Ust. Salafi) | IX A: TAHFIDZ (Ust. Adin) | VII B: TAHFIDZ (Usth. Amelia) | VIII B: TAHFIDZ (Usth. Amelia) | IX B: TAHFIDZ (Usth. Nafidzah) | X: FIQIH (Ust. Fahmi) | XI: MATEMATIKA PEMINATAN (Ust. Kodir) | XII A: BAHASA INDONESIA (Usth. Mekha) | XII B: BAHASA JAWA (Usth. Maya)

Jam 2 (07.35 - 08.10)

VII A: MATEMATIKA (Usth. Ina) | VIII A: PRAKARYA (Usth. Rosita Nova) | IX A: BAHASA INGGRIS (Usth. Mesya) | VII B: TAHFIDZ (Usth. Amelia) | VIII B: TAHFIDZ (Usth. Nafidzah) | IX B: AKIDAH AKHLAK (Ust. Abdullah) | X: FIQIH (Ust. Fahmi) | XI: MATEMATIKA PEMINATAN (Ust. Kodir) | XII A: BAHASA INDONESIA (Usth. Mekha) | XII B: BAHASA JAWA (Usth. Maya)

Jam 3 (08.10 - 08.45)

VII A: MATEMATIKA (Usth. Ina) | VIII A: PRAKARYA (Usth. Rosita Nova) | IX A: BAHASA INGGRIS (Usth. Mesya) | VII B: BAHASA ARAB (Usth. Ella) | VIII B: SENI BUDAYA (Usth. Maya) | IX B: AKIDAH AKHLAK (Ust. Abdullah) | X: MATEMATIKA (Ust. Kodir) | XI: KIMIA (Ust. Imam Aditya) | XII A: SHOROF (Ust. Rohmad Sigid) | XII B: TAUHID (Ust. Fahmi)

08.45 - 09.00: ISTIRAHAT

Jam 4 (09.00 - 09.35)

VII A: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | VIII A: IPS (Ust. Rahmat) | IX A: PRAKARYA (Usth. Rosita Nova) | VII B: BAHASA ARAB (Usth. Ella) | VIII B: SENI BUDAYA (Usth. Maya) | IX B: TIK (Usth. Ekasari) | X: MATEMATIKA (Ust. Kodir) | XI: KIMIA (Ust. Imam Aditya) | XII A: SHOROF (Ust. Rohmad Sigid) | XII B: TAUHID (Ust. Fahmi)

Jam 5 (09.35 - 10.10)

VII A: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | VIII A: IPS (Ust. Rahmat) | IX A: PRAKARYA (Usth. Rosita Nova) | VII B: IPA (Usth. Rosita Nova) | VIII B: BIMBINGAN KONSELING (Usth. Mekha) | IX B: TIK (Usth. Ekasari) | X: KIMIA (Ust. Imam Aditya) | XI: SHOROF (Ust. Rohmad Sigid) | XII A: BAHASA INGGRIS (Usth. Mesya) | XII B: MATEMATIKA (Ust. Kodir)

Jam 6 (10.10 - 10.45)

VII A: AKIDAH AKHLAK (Ust. Abdullah) | VIII A: TAHFIDZ (Ust. Salafi) | IX A: TIK (Usth. Ekasari) | VII B: IPA (Usth. Rosita Nova) | VIII B: TAHFIDZ (Usth. Amelia) | IX B: IPS (Ust. Rahmat) | X: KIMIA (Ust. Imam Aditya) | XI: SHOROF (Ust. Rohmad Sigid) | XII A: BAHASA INGGRIS (Usth. Mesya) | XII B: MATEMATIKA (Ust. Kodir)

Jam 7 (10.45 - 11.20)

VII A: AKIDAH AKHLAK (Ust. Abdullah) | VIII A: BAHASA INDONESIA (Usth. Mekha) | IX A: TIK (Usth. Ekasari) | VII B: TARIQ (Ust. Asyik) | VIII B: IPA (Usth. Rosita Nova) | IX B: MATEMATIKA NALARIA (Ust. Kodir) | X: HADITS (Ust. Rohmad Sigid) | XI: BAHASA INGGRIS (Usth. Mesya) | XII A & XII B: LATIHAN SOAL (Ust. Fahmi)

11.20 - 12.15: ISHOMA

Jam 8 (12.15 - 12.50)

VII A: BAHASA ARAB (Usth. Ella) | VIII A: BAHASA INDONESIA (Usth. Mekha) | IX A: FIQIH (Ust. Abdullah) | VII B: TARIQ (Ust. Asyik) | VIII B: IPA (Usth. Rosita Nova) | IX B: IPS (Ust. Rahmat) | X: HADITS (Ust. Rohmad Sigid) | XI: BAHASA INGGRIS (Usth. Mesya) | XII A & XII B: LATIHAN SOAL (Ust. Fahmi)

Jam 9 (12.50 - 13.25)

VII A: TARIQ (Ust. Asyik) | VIII A: SENI BUDAYA (Usth. Maya) | IX A: BIMBINGAN KONSELING (Usth. Mekha) | VII B: IPS (Ust. Rahmat) | VIII B: BAHASA INDONESIA (Usth. Mekha) | IX B: QUR'AN HADITS (Usth. Maria) | X: GEOGRAFI (Ust. Rohmad Sigid) | XI: TIK (Ust. Fahmi) | XII A: TAHFIDZ (Ust. Kasfaril) | XII B: IMLA' (Usth. Alfiyaturradhiyah)

Jam 10 (13.25 - 14.00)

VII A: TARIQ (Ust. Asyik) | VIII A: SENI BUDAYA (Usth. Maya) | IX A: TAHFIDZ (Ust. Adin) | VII B: IPS (Ust. Rahmat) | VIII B: BAHASA INDONESIA (Usth. Mekha) | IX B: QUR'AN HADITS (Usth. Maria) | X: GEOGRAFI (Ust. Rohmad Sigid) | XI: TIK (Ust. Fahmi) | XII A: TAHFIDZ (Ust. Kasfaril) | XII B: IMLA' (Usth. Alfiyaturradhiyah)

4. KAMIS
06.45 - 08.00: UPACARA

08.00 - 08.30 (Jam 1):

VII A - IX A: MATEMATIKA / QUR'AN HADITS / TAHFIDZ

IX B: BAHASA JAWA (Usth. Maya Yulaicha)

X: FISIKA (Usth. Evi Nurhayati)

XI: FIQIH (Ust. Fahmi Dwi Payana)

XII A: BIOLOGI (Ust. Rohmad Sigid Affandi)

08.30 - 09.00 (Jam 2):

VII A: MATEMATIKA (Usth. Ina) | VIII A: MATEMATIKA (Ust. Kodir) | IX A: QUR'AN HADITS (Usth. Maria) | VII B - IX B: TAHFIDZ | X: BAHASA JAWA (Usth. Maya) | XI: FISIKA (Usth. Evi) | XII A: FIQIH (Ust. Fahmi) | XII B: BIOLOGI (Ust. Rohmad)

09.00 - 09.15: ISTIRAHAT

09.15 - 09.45 (Jam 3):

VII A: MATEMATIKA (Usth. Ina) | VIII A: MATEMATIKA (Ust. Kodir) | IX A: QUR'AN HADITS (Usth. Maria) | VII B: BAHASA INDONESIA (Usth. Mekha) | VIII B: BAHASA INGGRIS (Usth. Mesya) | IX B: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | X: BAHASA JAWA (Usth. Maya) | XI: FISIKA (Usth. Evi) | XII A: FIQIH (Ust. Fahmi) | XII B: BIOLOGI (Ust. Rohmad)

09.45 - 10.15 (Jam 4):

VII A: TAHFIDZ (Ust. Gesang) | VIII A: BAHASA JAWA (Usth. Maya) | IX A: MATEMATIKA (Ust. Kodir) | VII B: BAHASA INDONESIA (Usth. Mekha) | VIII B: PRAKARYA (Usth. Rosita Nova) | IX B: BAHASA INGGRIS (Usth. Mesya) | X: FISIKA (Usth. Evi) | XI: FIQIH (Ust. Fahmi) | XII A: IMLA' (Usth. Alfiyaturradhiyah) | XII B: PKN (Ust. Rahmat)

10.15 - 10.45 (Jam 5):

VII A: PKN (Ust. Rahmat) | VIII A: BAHASA JAWA (Usth. Maya) | IX A: MATEMATIKA (Ust. Kodir) | VII B: SEJARAH / BK (Usth. Mekha) | VIII B: PRAKARYA (Usth. Rosita Nova) | IX B: FIQIH (Ust. Muhammad Abdullah) | X: FISIKA (Usth. Evi) | XI: FIQIH (Ust. Fahmi) | XII A: IMLA' (Usth. Alfiyaturradhiyah) | XII B: BAHASA INGGRIS (Usth. Mesya)

10.45 - 11.15 (Jam 6):

VII A: PKN (Ust. Rahmat) | VIII A: QUR'AN HADITS (Usth. Maria) | IX A: BAHASA INDONESIA (Usth. Mekha) | VII B: BAHASA JAWA (Usth. Maya) | VIII B: MATEMATIKA (Ust. Kodir) | IX B: FIQIH (Ust. Abdullah) | X: SHOROF (Ust. Rohmad Sigid) | XI: TAHFIDZ (Ust. Kasfaril) | XII A: FISIKA (Usth. Evi) | XII B: BAHASA INGGRIS (Usth. Mesya)

11.15 - 12.15: ISHOMA

12.15 - 12.45 (Jam 7):

VII A: IPA (Usth. Rosita Nova) | VIII A: QUR'AN HADITS (Usth. Maria) | IX A: BAHASA INDONESIA (Usth. Mekha) | VII B: BAHASA JAWA (Usth. Maya) | VIII B: TAHFIDZ (Ust. Salafi) | IX B: TIK (Usth. Ekasari) | X: SHOROF (Ust. Rohmad Sigid) | XI: PKN (Ust. Rahmat) | XII A: FISIKA (Usth. Evi) | XII B: TAHFIDZ (Usth. Nafidzah)

12.45 - 13.15 (Jam 8):

VII A: IPA (Usth. Rosita Nova) | VIII A: BIMBINGAN KONSELING (Usth. Mekha) | IX A: BAHASA JAWA (Usth. Maya) | VII B: TAHFIDZ (Usth. Amelia) | VIII B: TIK (Usth. Ekasari) | IX B: QUR'AN HADITS (Usth. Maria) | X: IMLA' (Usth. Alfiyaturradhiyah) | XI: PKN (Ust. Rahmat) | XII A: TAHFIDZ (Ust. Kasfaril) | XII B: FISIKA (Usth. Evi)

13.15 - 13.45 (Jam 9):

VII A: BIMBINGAN KONSELING (Usth. Mekha) | VIII A: BAHASA ARAB (Usth. Ella) | IX A: BAHASA JAWA (Usth. Maya) | VII B: TIK (Usth. Ekasari) | VIII B: QUR'AN HADITS (Usth. Maria) | IX B: QUR'AN HADITS (Usth. Maria) | X & XI: QIRAATUL QUTUB (Ust. Abdullah) | XII A: PKN (Ust. Rahmat) | XII B: FISIKA (Usth. Evi)

13.45 - 14.15 (Jam 10):

VII A: TAHFIDZ (Ust. Anis) | VIII A: BAHASA ARAB (Usth. Ella) | IX A: TAHFIDZ (Ust. Adin) | VII B: TIK (Usth. Ekasari) | VIII B: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah) | IX B: BIMBINGAN KONSELING (Usth. Mekha) | X & XI: QIRAATUL QUTUB (Ust. Abdullah) | XII A: PKN (Ust. Rahmat) | XII B: FISIKA (Usth. Evi)

5. JUM'AT
06.45 - 07.00: APEL

07.00 - 07.25: JUM'AT BERSIH / SEHAT

Jam 1 (07.25 - 08.00) & Jam 2 (08.00 - 08.25)

VII A - IX B: PJOK (Ust. Rohmad Sigid / Ust. Amelia Septiana)

Jam 3 (08.25 - 08.50)

VII A: FIQIH (Ust. Abdullah) | VIII A: TIK (Usth. Ekasari) | IX A: IPS (Ust. Rahmat) | VII B: PRAKARYA (Usth. Rosita Nova) | VIII B: MATEMATIKA (Ust. Kodir) | IX B: SENI BUDAYA (Usth. Maya)

Jam 4 (08.50 - 09.15)

VII A: FIQIH (Ust. Abdullah) | VIII A: TIK (Usth. Ekasari) | IX A: PRAKARYA (Usth. Rosita Nova) | VII B: IPS (Ust. Rahmat) | VIII B: MATEMATIKA (Ust. Kodir) | IX B: SENI BUDAYA (Usth. Maya)

Jam 5 (09.15 - 09.40)

VII A: TAHFIDZ (Ust. Anis) | VIII A: MATEMATIKA (Ust. Kodir) | IX A: SENI BUDAYA (Usth. Maya) | VII B: FIQIH (Ust. Abdullah) | VIII B: PRAKARYA (Usth. Rosita Nova) | IX B: KEMUHAMMADIYAHAN (Usth. Alfiyaturradhiyah)

09.40 - 09.55: ISTIRAHAT

Jam 6 (09.55 - 10.20)

VII A: IPS (Ust. Rahmat) | VIII A: MATEMATIKA (Ust. Kodir) | IX A: SENI BUDAYA (Usth. Maya) | VII B: FIQIH (Ust. Abdullah) | VIII B: PRAKARYA (Usth. Rosita Nova) | IX B: TARIQ (Ust. Asyik)

Jam 7 (10.20 - 10.45)

VII A: TAHFIDZ (Ust. Anis) | VIII A: FIQIH (Ust. Abdullah) | IX A: BAHASA ARAB (Usth. Ella) | VII B: QUR'AN HADITS (Usth. Maria) | VIII B: PRAKARYA (Usth. Rosita Nova) | IX B: PKN (Ust. Rahmat) | XII A: MATEMATIKA NALARIA (Ust. Kodir)

Jam 8 (10.45 - 11.10)

VII A: TAHFIDZ (Ust. Anis) | VIII A: FIQIH (Ust. Abdullah) | IX A: BAHASA ARAB (Usth. Ella) | VII B: QUR'AN HADITS (Usth. Maria) | VIII B: TAHFIDZ (Usth. Amelia) | IX B: PKN (Ust. Rahmat) | XII A: MATEMATIKA NALARIA (Ust. Kodir)

11.10 - 12.15: ISHOMA (Sholat Jum'at)

Jam 10 & 11 (12.15 - 12.40): MATEMATIKA NALARIA (Ust. Kodir S.T.) - SMA

12.40 - 14.00: EKSTRAKURIKULER

6. SABTU
06.45 - 07.00: APEL

Jam 1 (07.00 - 07.30)

IX B: NAHWU (Ust. Dirjo) | X: EKONOMI (Ust. Nur Wahyudi) | XII A: MATEMATIKA PEMINATAN (Ust. Kodir) | XII B: BAHASA INGGRIS (Usth. Vivi Nurwulan)

Jam 2 (07.30 - 08.00)

IX B: NAHWU (Ust. Dirjo) | X: EKONOMI (Ust. Nur Wahyudi) | XII A: MATEMATIKA PEMINATAN (Ust. Kodir) | XII B: BAHASA INGGRIS (Usth. Vivi Nurwulan)

Jam 3 (08.00 - 08.30)

IX B: EKONOMI (Ust. Nur Wahyudi) | X: NAHWU (Ust. Dirjo) | XII A: BAHASA INGGRIS (Usth. Vivi Nurwulan) | XII B: MATEMATIKA PEMINATAN (Ust. Kodir)

Jam 4 (08.30 - 09.00)

IX B: EKONOMI (Ust. Nur Wahyudi) | X: NAHWU (Ust. Dirjo) | XII A: BAHASA INGGRIS (Usth. Vivi Nurwulan) | XII B: MATEMATIKA PEMINATAN (Ust. Kodir)

09.00 - 09.30: ISTIRAHAT

Jam 5 (09.30 - 10.00)

IX B: TAUHID (Ust. Fahmi) | X: BAHASA INGGRIS (Usth. Vivi) | XII A: NAHWU (Ust. Dirjo) | XII B: EKONOMI (Ust. Nur Wahyudi)

Jam 6 (10.00 - 10.30)

IX B: TAUHID (Ust. Fahmi) | X: BAHASA INGGRIS (Usth. Vivi) | XII A: NAHWU (Ust. Dirjo) | XII B: EKONOMI (Ust. Nur Wahyudi)

Jam 7 (10.30 - 11.00)

IX B: BAHASA INGGRIS (Usth. Vivi) | X: TARIQ (Ust. Rohmad Sigid) | XII A: EKONOMI (Ust. Nur Wahyudi) | XII B: NAHWU (Ust. Dirjo)

Jam 8 (11.00 - 11.30)

IX B: BAHASA INGGRIS (Usth. Vivi) | X: TARIQ (Ust. Rohmad Sigid) | XII A: EKONOMI (Ust. Nur Wahyudi) | XII B: NAHWU (Ust. Dirjo)

11.30 - 12.15: ISHOMA

Jam 9 (12.15 - 12.50)

IX B, X, XII A, XII B: QIRAATUL QUTUB (Ust. Fadholi)

Jam 10 (12.50 - 13.25)

IX B, X, XII A, XII B: QIRAATUL QUTUB (Ust. Fadholi)

Jam 11 (13.25 - 14.00)

IX B, X, XII A, XII B: QIRAATUL QUTUB (Ust. Fadholi)
SCHED;

$dayMap = ['SENIN' => 1, 'SELASA' => 2, 'RABU' => 3, 'KAMIS' => 4, "JUM'AT" => 5, 'SABTU' => 6];
$gradeOrder = ['VII' => 1, 'VIII' => 2, 'IX' => 3, 'X' => 4, 'XI' => 5, 'XII' => 6];
$classList = ['VII A', 'VII B', 'VIII A', 'VIII B', 'IX A', 'IX B', 'X', 'XI', 'XII A', 'XII B'];

function import_class_ids(array $classList): array
{
    $map = [];
    foreach (fetch_all('SELECT id, name FROM classes') as $c) {
        $map[trim((string)$c['name'])] = (int)$c['id'];
    }
    return $map;
}

function import_expand_classes(string $part, array $classList, array $gradeOrder): array
{
    $part = trim($part);
    if (str_contains($part, '&')) {
        return array_map('trim', explode('&', $part));
    }
    if (!str_contains($part, '-')) {
        return [$part];
    }
    [$start, $end] = array_map('trim', explode('-', $part, 2));
    preg_match('/^(VII|VIII|IX|X|XI|XII)\s*([AB])?$/', $start, $ms);
    preg_match('/^(VII|VIII|IX|X|XI|XII)\s*([AB])?$/', $end, $me);
    if (!$ms || !$me) {
        return [$part];
    }
    $gStart = $gradeOrder[$ms[1]];
    $gEnd = $gradeOrder[$me[1]];
    $lStart = $ms[2] ?? '';
    $lEnd = $me[2] ?? '';
    $out = [];
    foreach ($classList as $cls) {
        preg_match('/^(VII|VIII|IX|X|XI|XII)\s*([AB])?$/', $cls, $mc);
        $grade = $gradeOrder[$mc[1]];
        if ($grade < $gStart || $grade > $gEnd) {
            continue;
        }
        if ($lStart !== '' && $lEnd !== '') {
            if ($mc[2] !== $lStart) {
                continue;
            }
        }
        $out[] = $cls;
    }
    return $out;
}

function import_norm(string $s): array
{
    $t = strtolower((string)preg_replace('/[.,]/', ' ', $s));
    $t = (string)preg_replace('/[^a-z0-9\s]/', ' ', $t);
    return array_values(array_filter(preg_split('/\s+/', $t) ?: []));
}

function import_teacher_ids(array $teachers): array
{
    $map = [];
    foreach ($teachers as $t) {
        $tokens = import_norm($t['name']);
        $key = implode(' ', array_slice($tokens, 1));
        $map[$key] = (int)$t['id'];
    }
    return $map;
}

function import_match_teacher(string $name, array $teacherIndex): ?int
{
    $tokens = import_norm($name);
    if (!$tokens) {
        return null;
    }
    $rest = array_slice($tokens, 1);
    $key = implode(' ', $rest);
    if (isset($teacherIndex[$key])) {
        return $teacherIndex[$key];
    }
    foreach ($teacherIndex as $stored => $id) {
        $storedTokens = preg_split('/\s+/', $stored) ?: [];
        if ($rest && count(array_intersect($rest, $storedTokens)) === count($rest)) {
            return $id;
        }
    }
    return null;
}

function import_subject_id(string $name): ?int
{
    $norm = import_norm($name);
    $key = implode(' ', $norm);
    foreach (fetch_all('SELECT id, name, short_name FROM subjects') as $s) {
        if (implode(' ', import_norm($s['name'])) === $key || strtolower(trim($s['short_name'])) === strtolower(trim($name))) {
            return (int)$s['id'];
        }
    }
    return null;
}

function import_ensure_assignment(int $classId, int $subjectId, int $teacherId): int
{
    $existing = fetch_one(
        'SELECT id FROM teaching_assignments WHERE class_id = ? AND subject_id = ? AND academic_year = ? AND semester = ? ORDER BY id LIMIT 1',
        [$classId, $subjectId, '2025/2026', 'Genap']
    );
    if ($existing) {
        return (int)$existing['id'];
    }
    execute_sql(
        'INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active) VALUES (?, ?, ?, ?, ?, 1)',
        [$teacherId, $classId, $subjectId, '2025/2026', 'Genap']
    );
    return (int)db()->lastInsertId();
}

$classIds = import_class_ids($classList);
$teacherIndex = import_teacher_ids(fetch_all('SELECT id, name FROM teachers'));
$subjectNames = array_column(fetch_all('SELECT id, name FROM subjects'), 'name');

$stats = ['schedules' => 0, 'assignments' => 0, 'skipped_no_teacher' => 0, 'skipped_dup' => 0, 'skipped_no_subject' => 0, 'skipped_no_class' => 0, 'warnings' => []];

$currentDay = 0;
$currentPeriod = null;
$currentTime = [null, null];

foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    if (preg_match('/^\d+\.\s*(SENIN|SELASA|RABU|KAMIS|JUM\'AT|SABTU)/i', $line, $m)) {
        $currentDay = $dayMap[strtoupper($m[1])];
        $currentPeriod = null;
        continue;
    }
    if (preg_match('/\bJam\s+(\d+)/i', $line, $m) && preg_match('/^\s*(?:\d{2}\.\d{2}\s*-\s*\d{2}\.\d{2}\s*)?(?:\(?Jam|Jam)/i', $line)) {
        preg_match_all('/Jam\s+(\d+)/i', $line, $jm);
        $nums = array_map('intval', $jm[1]);
        $currentPeriod = $nums[0];
        preg_match_all('/\((\d{2})\.(\d{2})\s*-\s*(\d{2})\.(\d{2})\)/', $line, $tm);
        if (!empty($tm[0])) {
            $currentTime = [($tm[1][0] ?? '') . ':' . ($tm[2][0] ?? '00'), ($tm[3][count($tm[3]) - 1] ?? '') . ':' . ($tm[4][count($tm[4]) - 1] ?? '00')];
        } else {
            $currentTime = [null, null];
        }
        continue;
    }
    if ($currentDay === 0) {
        continue;
    }
    if (preg_match('/^(?:APEL|ISTIRAHAT|ISHOMA|UPACARA|JUM\'AT\s+BERSIH|BERSIH|EKSTRAKURIKULER|\d{2}\.\d{2}\s*-\s*\d{2}\.\d{2}:)/i', $line)) {
        continue;
    }
    if (preg_match('/^\d{2}\.\d{2}\s*-\s*\d{2}\.\d{2}:\s*[A-Z\']+$/', $line)) {
        continue;
    }
    if ($currentPeriod === null) {
        continue;
    }

    foreach (explode('|', $line) as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }
        if (!preg_match('/^(.+?):\s*(.+?)(?:\((.*?)\))?\s*$/', $entry, $em)) {
            continue;
        }
        $classPart = trim($em[1]);
        $subjectPart = trim($em[2]);
        $teacherPart = trim($em[3] ?? '');

        $classes = [];
        foreach (explode('&', $classPart) as $cp) {
            foreach (explode(',', $cp) as $cp2) {
                $classes = array_merge($classes, import_expand_classes(trim($cp2), $classList, $gradeOrder));
            }
        }
        $classes = array_values(array_unique($classes));

        $subjects = array_values(array_filter(array_map('trim', explode('/', $subjectPart))));
        if (!$subjects) {
            continue;
        }

        $teacherTokens = $teacherPart !== '' ? array_map('trim', explode('/', $teacherPart)) : [];
        $teacherId = null;
        foreach ($teacherTokens as $tt) {
            $teacherId = import_match_teacher($tt, $teacherIndex);
            if ($teacherId !== null) {
                break;
            }
        }

        $subjectName = $subjects[0];
        $subjectId = import_subject_id($subjectName);
        if ($subjectId === null) {
            $stats['skipped_no_subject']++;
            $stats['warnings'][] = "Mapel tidak ditemukan: $subjectName";
            continue;
        }
        if ($teacherId === null) {
            $stats['skipped_no_teacher']++;
            $stats['warnings'][] = "Guru tidak ditemukan: $teacherPart (Hari " . $currentDay . " Jam " . $currentPeriod . ")";
            continue;
        }

        foreach ($classes as $cls) {
            if (!isset($classIds[$cls])) {
                $stats['skipped_no_class']++;
                $stats['warnings'][] = "Kelas tidak ditemukan: $cls";
                continue;
            }
            $classId = $classIds[$cls];
            $assignmentId = import_ensure_assignment($classId, $subjectId, $teacherId);
            $stats['assignments']++;
            $stats['schedules']++;
            try {
                execute_sql(
                    'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$assignmentId, $teacherId, $classId, $subjectId, $currentDay, $currentPeriod, $currentTime[0], $currentTime[1]]
                );
            } catch (Throwable $e) {
                $stats['skipped_dup']++;
                $stats['warnings'][] = $cls . ' jam ' . $currentPeriod . ': ' . $e->getMessage();
            }
        }
    }
}

echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
