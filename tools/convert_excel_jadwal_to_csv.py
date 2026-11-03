#!/usr/bin/env python3
"""
Script konversi jadwal dari Excel (09 AGUST-JADWAL PELAJARAN GASAL 2627.xlsx)
ke format CSV yang siap diimport ke aplikasi E-Raport KumerBot (web import-bulk).
"""

import openpyxl
import sqlite3
import re
import csv
import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
EXCEL_PATH = os.path.join(BASE_DIR, '09 AGUST-JADWAL PELAJARAN GASAL 2627.xlsx')
SQLITE_PATH = os.path.join(BASE_DIR, 'storage', 'eraport.sqlite')
OUTPUT_CSV = os.path.join(BASE_DIR, 'import_jadwal_gasal_2627.csv')

def main():
    print(f"Membuka file Excel: {EXCEL_PATH}")
    wb = openpyxl.load_workbook(EXCEL_PATH, data_only=True)
    ws_guru = wb['Guru Mapel']
    ws_jadwal = wb['Jadwal']

    conn = sqlite3.connect(SQLITE_PATH)
    c = conn.cursor()

    c.execute('SELECT id, name, short_name FROM subjects')
    subj_map = {}
    for sid, name, short in c.fetchall():
        if short:
            subj_map[short.strip().upper()] = name.strip()
        subj_map[name.strip().upper()] = name.strip()

    c.execute('SELECT id, name FROM teachers')
    teacher_rows = c.fetchall()
    conn.close()

    code_to_name = {}
    for r in range(3, 36):
        code_val = ws_guru.cell(row=r, column=10).value
        name_val = ws_guru.cell(row=r, column=11).value
        if code_val is not None and name_val:
            norm_name = re.sub(r'[^a-z0-9]', '', str(name_val).lower())
            matched_db_name = str(name_val).strip()
            for tid, tname in teacher_rows:
                if re.sub(r'[^a-z0-9]', '', tname.lower()) == norm_name:
                    matched_db_name = tname
                    break
            code_to_name[int(code_val)] = matched_db_name

    day_normalize = {
        'SENIN': 'Senin',
        'SELASA': 'Selasa',
        'RABU': 'Rabu',
        'KAMIS': 'Kamis',
        'JUM\'AT': 'Jumat',
        'SABTU': 'Sabtu'
    }

    classes = [ws_jadwal.cell(row=7, column=col).value for col in range(5, 15)]

    current_day = None
    rows = []

    for r in range(8, ws_jadwal.max_row + 1):
        c2 = ws_jadwal.cell(row=r, column=2).value
        if c2:
            for k, v in day_normalize.items():
                if k in str(c2).upper():
                    current_day = v
                    break

        jam_val = ws_jadwal.cell(row=r, column=3).value
        time_val = ws_jadwal.cell(row=r, column=4).value

        if jam_val is not None:
            try:
                jam_num = int(jam_val)
            except ValueError:
                continue

            start_time, end_time = '', ''
            if time_val:
                tm = re.search(r'(\d{2})[.:](\d{2})\s*-\s*(\d{2})[.:](\d{2})', str(time_val))
                if tm:
                    start_time = f'{tm.group(1)}:{tm.group(2)}'
                    end_time = f'{tm.group(3)}:{tm.group(4)}'

            for idx, col in enumerate(range(5, 15)):
                cls_name = classes[idx]
                mapel_code = ws_jadwal.cell(row=r, column=col).value
                guru_code = ws_jadwal.cell(row=r+1, column=col).value

                if not mapel_code:
                    continue

                mapel_str = str(mapel_code).strip()
                if mapel_str.upper() in ['UPACARA', 'APEL', 'ISTIRAHAT', 'ISHOMA', 'EKSTRAKURIKULER', 'JUMAT BERSIH/SEHAT']:
                    continue

                subj_name = subj_map.get(mapel_str.upper())
                if not subj_name or not guru_code:
                    continue

                guru_str = str(guru_code).strip()
                primary_code = guru_str.split('/')[0].strip()
                try:
                    code_int = int(float(primary_code))
                    teacher_name = code_to_name.get(code_int)
                except ValueError:
                    teacher_name = None

                if not teacher_name:
                    continue

                rows.append({
                    'hari': current_day,
                    'jam_ke': jam_num,
                    'kelas': cls_name,
                    'mapel': subj_name,
                    'guru': teacher_name,
                    'jam_mulai': start_time,
                    'jam_selesai': end_time
                })

    with open(OUTPUT_CSV, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=['hari', 'jam_ke', 'kelas', 'mapel', 'guru', 'jam_mulai', 'jam_selesai'])
        writer.writeheader()
        writer.writerows(rows)

    print(f"Berhasil mengonversi {len(rows)} slot jadwal ke: {OUTPUT_CSV}")

if __name__ == '__main__':
    main()

