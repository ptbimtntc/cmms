# Task: Fix Import Machine Checklist & Machine Problem Finding — Excel Gagal Baca Format Zip

**Tujuan:** Import Machine Checklist **dan** Machine Problem Finding bisa menerima file CSV **maupun** Excel (`.xlsx`/`.xls`) dengan aman, dan kalau ada error saat import (file rusak, format salah, dll), user dapat pesan error yang jelas dan ramah — bukan crash/500, dan bukan juga bocoran pesan error teknis mentah-mentah.

**Level:** Junior developer / AI model murah. Setiap langkah sudah berisi nama file lengkap dan kode yang bisa langsung dipakai. Kerjakan berurutan dari Langkah 1 sampai selesai, jangan loncat.

**Status:** ✅ Selesai dieksekusi (2026-08-21). `ext-zip` sudah aktif, kedua controller sudah diperbaiki, sudah diverifikasi lewat script test (import xlsx valid berhasil, import xlsx korup dapat pesan error ramah + tercatat di log). Checklist testing manual di Langkah 3 masih perlu dicoba langsung di browser oleh user untuk verifikasi visual/UX.

---

## Ringkasan bug

Saat upload file **Excel (.xlsx)** di halaman Machine Checklist (`/machine-checklists`, form import di kanan atas), proses import gagal dengan error yang menyebutkan "zip" (mis. `Class "ZipArchive" not found`), dan **halaman jadi error/blank** — bukan pesan error yang rapi. File **CSV** untuk fitur yang sama berjalan normal.

Ini sudah direproduksi langsung (bukan dugaan):

```
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$reader->load($pathKeXlsx);
// => Error: Class "ZipArchive" not found
```

## Root cause (ada 2 penyebab yang menumpuk)

### 1. Extension PHP `ext-zip` belum terpasang di environment ini

PHP di Codespace ini adalah build custom minimal (`/usr/local/php/8.4.15`) yang **tidak** di-compile dengan extension `zip`. Ini kenapa dulu `composer install` sempat gagal dan harus dijalankan dengan `--ignore-platform-req=ext-zip`.

Library `phpoffice/phpspreadsheet` (dipakai `maatwebsite/excel`) punya dua jalur berbeda untuk file `.xlsx`:

- **Menulis/generate .xlsx** (`Excel::download(...)`, dipakai di `ImportTemplateController` untuk download template) — pakai library `maennchen/zipstream-php` (pure PHP), **tidak butuh** `ext-zip`. Makanya download template `.xlsx` selama ini terasa "baik-baik saja".
- **Membaca .xlsx** (`Excel::import(...)`, ini yang dipakai saat upload) — kode di `vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Reader/Xlsx.php` pakai class native `ZipArchive` langsung, **tidak ada fallback pure-PHP**. Karena `ZipArchive` cuma ada kalau extension `zip` aktif, ini yang error.

Itu sebabnya gejalanya membingungkan: "download template Excel jalan, tapi upload Excel yang sama malah error baca zip" — dua-duanya sama-sama `.xlsx`, tapi jalur kodenya beda.

**Fix:** compile & aktifkan extension `zip` untuk PHP 8.4.15 di environment ini (caranya sama seperti waktu kita pasang `pdo_mysql` sebelumnya — lihat Langkah 1).

> Catatan: kalau nanti deploy ke server produksi/environment lain yang PHP-nya sudah standar (biasanya `ext-zip` sudah include by default di distro/paket resmi), langkah 1 ini mungkin tidak perlu diulang — tinggal cek `php -m | grep zip` di server itu.

### 2. `MachineChecklistController::import()` tidak punya error handling sama sekali

```php
// app/Http/Controllers/MachineChecklistController.php (baris 265-280, kondisi SEKARANG)
public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:csv,txt,xlsx,xls'
    ]);

    Excel::import(
        new MachineChecklistsImport(),
        $request->file('file')
    );

    return back()->with(
        'success',
        'Machine Checklist imported successfully.'
    );
}
```

Tidak ada `try/catch` — jadi kalau `Excel::import()` melempar error apa pun (zip, format kolom salah, file korup, dll), errornya nyampe langsung ke Laravel sebagai 500/whoops page. View-nya sendiri (`resources/views/machine-checklists/index.blade.php` baris 78-82) **sudah siap** menampilkan pesan error dengan rapi (`@if ($errors->has('file'))`), tapi controller-nya belum pernah mengisi error itu.

Bandingkan dengan `MachineController::import()` dan `SparepartController::import()` yang **sudah** dibenahi (commit sebelumnya "feat: enhance file import validation...") — keduanya pakai validasi custom + `try/catch (\Throwable $e)`. Bedanya, dua controller itu sengaja **membatasi upload cuma CSV** (karena waktu itu `ext-zip` belum ada solusinya). Untuk Machine Checklist, biarkan **CSV maupun Excel tetap bisa dipakai** — sesuai request — karena setelah Langkah 1 selesai, `ext-zip` sudah aktif dan xlsx/xls sudah bisa dibaca dengan aman.

### Temuan tambahan (bukan bagian wajib task ini, cukup dicatat)

- `PMScheduleController::import()` (`app/Http/Controllers/PMScheduleController.php` baris 141-an) punya pola bug yang sama persis: validasi izinkan xlsx/xls tapi **tidak ada try/catch**. Kalau nanti diminta, perbaikannya sama seperti Langkah 2 di bawah.
- `app/Http/Controllers/MachineChecklistController.php` baris 9 ada `use App\Imports\MachineChecklistImport;` (singular) — class ini **tidak ada filenya** (yang ada cuma `MachineChecklistsImport`, plural, di baris 10). Import ini dead code, aman dihapus.

---

## Bug ke-2 (kasus sama): Import Machine Problem Finding

Sama persis root cause-nya dengan Machine Checklist (bagian "Root cause" poin 1 di atas — `ext-zip` belum aktif di environment ini) — tapi kali ini **controller-nya sudah ada try/catch**, jadi tidak sampai 500/blank page. Ini kondisi sekarang di `app/Http/Controllers/MachineProblemFindingController.php` baris 80-122:

```php
public function import(Request $request)
{
    $request->validate([
        'file' => [
            'required',
            'file',
            'max:20480',
            function ($attribute, $value, $fail) {
                $extension = strtolower($value->getClientOriginalExtension());
                $mime = strtolower($value->getClientMimeType());
                $allowedExtensions = ['csv', 'txt', 'xlsx', 'xls'];
                $allowedMimes = [
                    'text/csv',
                    'text/plain',
                    'application/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/octet-stream',
                ];

                if (!in_array($extension, $allowedExtensions, true) && !in_array($mime, $allowedMimes, true)) {
                    $fail('File must be a CSV or Excel file.');
                }
            },
        ],
    ]);

    try {
        Excel::import(
            new MachineProblemFindingImport(),
            $request->file('file')
        );
    } catch (\Throwable $e) {
        return back()->withErrors([
            'file' => 'Import failed: ' . $e->getMessage(),
        ]);
    }

    return back()->with(
        'success',
        'Machine Problem Findings imported successfully.'
    );
}
```

Validasi extension/mime-nya sudah benar (malah lebih lengkap dari Machine Checklist — sudah include mime OOXML `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`), dan `try/catch (\Throwable $e)` sudah ada. Jadi **setelah Langkah 1 (`ext-zip`) selesai, upload Excel di sini otomatis mulai jalan tanpa perlu ubah kode apa pun.**

Yang masih perlu dibenahi cuma di baris `catch`-nya — ada 2 masalah kecil:

1. **`'Import failed: ' . $e->getMessage()`** — nampilin pesan error mentah langsung ke user (mis. user bakal lihat teks `Import failed: Class "ZipArchive" not found`). Ini membingungkan buat user biasa dan bocorin detail teknis internal. Harusnya pesan generik saja, sama seperti Machine Checklist.
2. **Tidak ada `report($e)`** — jadi kalau error kejadian lagi nanti (misal gara-gara file korup, bukan gara-gara zip), tidak ada jejak di `storage/logs/laravel.log` buat investigasi lebih lanjut. User cuma lihat pesan di layar, tim dev tidak punya log.

### Langkah 2B — Perbaiki catch block di `MachineProblemFindingController::import()`

Buka `app/Http/Controllers/MachineProblemFindingController.php`, cari blok `try { ... } catch (\Throwable $e) { ... }` di method `import()` (baris 107-116), ganti jadi:

```php
        try {
            Excel::import(
                new MachineProblemFindingImport(),
                $request->file('file')
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'file' => 'Import failed. Please check the file format and data.',
            ]);
        }
```

(Cuma bagian `catch` yang berubah — validasi di atasnya dan `return back()->with('success', ...)` di bawahnya tidak perlu disentuh, sudah benar.)

---

## Langkah 1 — Compile & aktifkan extension `zip` untuk PHP 8.4.15

Pola persis seperti waktu kita compile `pdo_mysql` untuk MySQL sebelumnya.

**1a.** Install library development `libzip`:

```bash
sudo apt-get update
sudo apt-get install -y libzip-dev
```

**1b.** Download source PHP yang versinya **sama persis** dengan yang jalan sekarang (cek dulu `php -v`, di environment ini 8.4.15), lalu extract folder `ext/zip` saja:

```bash
cd /tmp
curl -fsSL -o php-src.tar.gz https://www.php.net/distributions/php-8.4.15.tar.gz
tar -xzf php-src.tar.gz php-8.4.15/ext/zip php-8.4.15/main php-8.4.15/Zend php-8.4.15/TSRM php-8.4.15/scripts php-8.4.15/build php-8.4.15/configure.ac
```

**1c.** Build extension-nya:

```bash
cd /tmp/php-8.4.15/ext/zip
/usr/local/php/8.4.15/bin/phpize
./configure --with-php-config=/usr/local/php/8.4.15/bin/php-config
make -j$(nproc)
```

Kalau `make` selesai tanpa error, akan ada file `modules/zip.so`.

**1d.** Pasang extension-nya:

```bash
sudo cp /tmp/php-8.4.15/ext/zip/modules/zip.so /usr/local/php/8.4.15/extensions/
echo "extension=zip.so" | sudo tee /usr/local/php/8.4.15/ini/conf.d/zip.ini
```

**1e.** Verifikasi:

```bash
php -m | grep -i zip
# harus muncul: zip
```

**1f.** Restart `php artisan serve` supaya proses PHP yang lama (yang belum load extension baru) diganti:

```bash
pkill -f "artisan serve"
# lalu jalankan lagi seperti biasa, mis:
# php artisan serve --host=0.0.0.0 --port=8000
```

**1g. (opsional, tapi disarankan)** Sekarang `ext-zip` sudah ada, `composer.lock` sebenarnya sudah bisa diinstall tanpa perlu `--ignore-platform-req=ext-zip` lagi. Tidak wajib diubah sekarang, tapi kalau nanti `composer install` diulang dari nol, coba tanpa flag itu dulu.

---

## Langkah 2 — Perbaiki `MachineChecklistController::import()`

Buka `app/Http/Controllers/MachineChecklistController.php`.

**2a.** Hapus baris `use App\Imports\MachineChecklistImport;` (baris 9, dead import — class-nya tidak ada). Baris `use App\Imports\MachineChecklistsImport;` (yang ada `s`-nya) **tetap dipertahankan**, karena itu yang benar-benar dipakai.

**2b.** Ganti method `import()` (baris 265-280) dengan versi ini — pola validasi & try/catch mengikuti `MachineController::import()`, tapi `allowedExtensions`/`allowedMimes` tetap mengizinkan CSV **dan** Excel:

```php
public function import(Request $request)
{
    $request->validate([
        'file' => [
            'required',
            'file',
            'max:20480', // 20 MB
            function ($attribute, $value, $fail) {
                $extension = strtolower(
                    $value->getClientOriginalExtension()
                );

                $mime = strtolower(
                    $value->getMimeType()
                );

                $allowedExtensions = [
                    'csv',
                    'txt',
                    'xlsx',
                    'xls',
                ];

                $allowedMimes = [
                    'text/csv',
                    'text/plain',
                    'application/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ];

                if (
                    ! in_array($extension, $allowedExtensions, true)
                    || ! in_array($mime, $allowedMimes, true)
                ) {
                    $fail('File must be a valid CSV or Excel file.');
                }
            },
        ],
    ]);

    try {
        Excel::import(
            new MachineChecklistsImport(),
            $request->file('file')
        );
    } catch (\Throwable $e) {
        report($e);

        return back()->withErrors([
            'file' => 'Import failed. Please check the file format and data.',
        ]);
    }

    return back()->with(
        'success',
        'Machine Checklist imported successfully.'
    );
}
```

> Kenapa `catch (\Throwable $e)` bukan `catch (\Exception $e)`? Karena error `ZipArchive` yang kita reproduksi tadi jenisnya `\Error`, bukan `\Exception` — keduanya cuma ketangkep bareng lewat `\Throwable`. Ini juga alasan kenapa `MachineController`/`SparepartController` sudah pakai `\Throwable`, bukan `\Exception`.

> `report($e)` supaya error aslinya tetap kecatat di `storage/logs/laravel.log` (buat debug kalau ada laporan bug lagi), meskipun user cuma lihat pesan yang ramah.

---

## Langkah 3 — Testing manual

Setelah Langkah 1, 2, dan 2B selesai:

**Machine Checklist (`/machine-checklists`):**

- [ ] `php -m | grep zip` menunjukkan extension `zip` aktif.
- [ ] Download template Excel dari halaman Import Template (`machine-checklist`) — masih berhasil seperti biasa.
- [ ] Isi beberapa baris di template Excel yang barusan didownload, lalu upload lewat form import di `/machine-checklists` → **harus sukses**, data checklist baru muncul di tabel.
- [ ] Coba upload file CSV yang valid → tetap sukses seperti sebelumnya (pastikan tidak regresi).
- [ ] Coba upload file **bukan** CSV/Excel (mis. `.pdf` atau `.png` diganti ekstensi jadi `.csv`) → muncul pesan error validasi yang jelas, bukan crash.
- [ ] Coba upload file Excel yang sengaja dikorup (mis. buka `.xlsx` di text editor, hapus beberapa karakter di tengah, save) → muncul pesan "Import failed. Please check the file format and data." (bukan halaman 500/whoops).
- [ ] Cek `storage/logs/laravel.log` setelah test upload file korup di atas → ada entry error tercatat (bukti `report($e)` jalan).

**Machine Problem Finding (`/machine-problem-findings`):**

- [ ] Download template Excel dari halaman Import Template (`machine-problem-finding`), isi beberapa baris, upload lewat form import → **harus sukses** (sebelumnya gagal karena `ext-zip` belum ada, sekarang otomatis jalan begitu Langkah 1 selesai).
- [ ] Coba upload file CSV yang valid → tetap sukses (pastikan tidak regresi).
- [ ] Coba upload file Excel yang sengaja dikorup → muncul pesan generik "Import failed. Please check the file format and data." — **bukan lagi** pesan mentah semacam `Import failed: Class "ZipArchive" not found`.
- [ ] Cek `storage/logs/laravel.log` setelah test upload file korup di atas → ada entry error tercatat (bukti `report($e)` yang baru ditambahkan jalan).
