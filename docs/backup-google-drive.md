# Backup otomatis ke Google Drive

Database (`.sql.gz`) dibuat otomatis tiap 7 hari dan source code (`.zip`) tiap 14 hari. Setiap backup yang berhasil, otomatis maupun dari tombol di menu Backup & Data Protection, langsung disalin ke satu folder Google Drive. Di Drive disimpan 8 file database dan 6 file source code terbaru. File yang lebih lama dihapus dari Drive oleh aplikasi. Yang dihapus hanya file yang diunggah aplikasi ini.

Di server tetap tersimpan 5 file terbaru per jenis, seperti sebelumnya.

## Cara kerja

- Scheduler Laravel menjalankan `php artisan backup:scheduled` setiap hari pukul 02:00 (zona `APP_TIMEZONE`).
- Perintah itu hanya membuat backup yang sudah jatuh tempo. Hitungannya dari backup **otomatis** terakhir yang berhasil, jadi backup manual tidak menggeser jadwal. Kalau cron sempat mati, backup dibuat pada pengecekan berikutnya.
- Kalau upload ke Drive gagal, backup tetap tercatat berhasil dan file tetap ada di server. Pesan gagalnya tampil di riwayat backup dan perintah keluar dengan kode error.

## Setup (sekali saja)

### 1. Google Cloud project

1. Buka https://console.cloud.google.com, buat project baru (misal `freedoms-backup`).
2. **APIs & Services → Library** → cari **Google Drive API** → **Enable**.
3. **APIs & Services → OAuth consent screen**:
   - User type: **External**.
   - Isi nama aplikasi dan email.
   - Scope: tambahkan `https://www.googleapis.com/auth/drive.file`.
   - Test users: tambahkan akun Gmail tujuan backup.
   - Setelah selesai, klik **Publish app** (status *In production*). Kalau tetap *Testing*, refresh token kedaluwarsa setelah 7 hari dan upload akan gagal dengan `invalid_grant`. Untuk scope `drive.file` tidak perlu verifikasi Google; peringatan "unverified app" saat login cukup dilewati (Advanced → Go to …).
4. **APIs & Services → Credentials → Create credentials → OAuth client ID**:
   - Application type: **Web application**.
   - Authorized redirect URI: `https://developers.google.com/oauthplayground`
   - Simpan **Client ID** dan **Client secret**.

### 2. Refresh token

1. Buka https://developers.google.com/oauthplayground
2. Klik ikon gear (kanan atas) → centang **Use your own OAuth credentials** → isi Client ID dan Client secret.
3. Di kotak *Input your own scopes* isi `https://www.googleapis.com/auth/drive.file` → **Authorize APIs** → login dengan akun Gmail tujuan.
4. Klik **Exchange authorization code for tokens** → salin **Refresh token**.

### 3. Isi `.env` di server

```
GOOGLE_DRIVE_CLIENT_ID=...
GOOGLE_DRIVE_CLIENT_SECRET=...
GOOGLE_DRIVE_REFRESH_TOKEN=...
GOOGLE_DRIVE_FOLDER_ID=
```

### 4. Folder Drive

Dengan scope `drive.file`, aplikasi hanya bisa menulis ke folder yang dibuatnya sendiri. Buat foldernya dari server:

```
php artisan config:clear
php artisan backup:drive-test --create-folder
```

Salin ID yang dicetak ke `GOOGLE_DRIVE_FOLDER_ID`, lalu:

```
php artisan config:cache
php artisan backup:drive-test
```

Hasil yang benar: `Berhasil: file uji diunggah ke folder Google Drive lalu dihapus.`

Folder "FreeDOMS Backups" muncul di My Drive. Folder boleh dipindah atau diganti nama di Drive; ID-nya tetap.

### 5. Cron di Hostinger

hPanel → **Advanced → Cron Jobs** → Custom, setiap menit (`* * * * *`):

```
cd /home/<user>/<folder-aplikasi> && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Sesuaikan path. Cron ini juga menjalankan job terjadwal lain (`pm-schedules:mark-overdue-missed`).

### 6. Uji sekali

```
php artisan backup:scheduled --force
```

Cek file baru di folder Drive dan kolom Google Drive di menu Backup.

## Pengaturan opsional (`.env`)

| Key | Default | Arti |
|---|---|---|
| `BACKUP_SCHEDULE_ENABLED` | `true` | Matikan backup otomatis tanpa menghapus cron |
| `BACKUP_SCHEDULE_TIME` | `02:00` | Jam pengecekan harian |
| `BACKUP_INTERVAL_DAYS_DATABASE` | `7` | Jarak backup database otomatis |
| `BACKUP_INTERVAL_DAYS_SOURCE` | `14` | Jarak backup source code otomatis |
| `BACKUP_DRIVE_KEEP_DATABASE` | `8` | Jumlah file database di Drive |
| `BACKUP_DRIVE_KEEP_SOURCE` | `6` | Jumlah file source code di Drive |

## Perintah

- `php artisan backup:scheduled`: buat backup yang jatuh tempo.
- `php artisan backup:scheduled --force --type=database`: paksa satu jenis sekarang.
- `php artisan backup:drive-test`: cek koneksi dan izin tulis ke folder.

## Keamanan

- Zip source code berisi `.env`, termasuk kredensial database dan refresh token Drive ini. Siapa pun yang bisa membuka akun Google tersebut bisa membaca semuanya. Aktifkan verifikasi 2 langkah di akun itu dan jangan bagikan foldernya.
- Scope `drive.file` membatasi aplikasi hanya ke file yang dibuatnya sendiri, bukan seluruh isi Drive.
- Untuk mencabut akses: https://myaccount.google.com/permissions → hapus aplikasi, lalu kosongkan `GOOGLE_DRIVE_REFRESH_TOKEN`.

## Masalah umum

| Pesan | Penyebab |
|---|---|
| `invalid_grant` | Refresh token kedaluwarsa atau dicabut. Pastikan consent screen *In production*, buat token baru. |
| `HTTP 404, notFound` saat upload | `GOOGLE_DRIVE_FOLDER_ID` salah atau folder bukan buatan aplikasi. Jalankan `--create-folder`. |
| `storageQuotaExceeded` | Kuota Drive penuh. Kurangi `BACKUP_DRIVE_KEEP_*` atau kosongkan Drive. |
| `Tidak dapat terhubung ke Google Drive` | Hosting memblokir koneksi keluar atau sedang gangguan jaringan. |
