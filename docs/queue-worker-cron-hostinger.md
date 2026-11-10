# Cron queue worker di Hostinger (shared hosting)

Notifikasi email Oil Audit KRITIS dikirim lewat queue. Queue butuh worker yang dijalankan cron. Jadwalnya: **setiap Kamis, 08.00–16.59 WIB**. Audit KRITIS di luar jadwal itu tetap antre dan terkirim pada jadwal berikutnya.

Pakai cron mode **PHP**, bukan Custom. Di Hostinger yang sudah diuji, mode Custom tidak bisa menjalankan program luar (`date`, `php`), jadi worker tidak pernah jalan.

## 1. Buat script lewat SSH

Ganti `DOMAIN` dengan domain aplikasi (contoh `pm.flash14.id`), lalu jalankan seluruh blok ini di SSH:

```bash
cat > ~/queue-worker.php <<'EOF'
<?php
// Cron hPanel (mode PHP): queue worker CMMS, Kamis 08.00-16.59 WIB.
// Buat file queue-worker.force di folder ini untuk tes di luar jadwal.
$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$inSchedule = $now->format('N') === '4' && (int) $now->format('G') >= 8 && (int) $now->format('G') < 17;
if (! $inSchedule && ! file_exists(__DIR__.'/queue-worker.force')) {
    exit(0);
}
$base = __DIR__.'/domains/DOMAIN/public_html';
chdir($base);
require $base.'/vendor/autoload.php';
$app = require_once $base.'/bootstrap/app.php';
exit($app->handleCommand(new Symfony\Component\Console\Input\ArgvInput(
    ['artisan', 'queue:work', '--stop-when-empty', '--max-time=50', '--tries=3']
)));
EOF
```

Cek: `cat ~/queue-worker.php`. File ini ada di folder home, jadi tidak terlihat di File Manager hPanel. Lihat atau edit lewat SSH (`nano`) atau SFTP (WinSCP/FileZilla).

`$base` harus mengarah ke folder yang berisi file `artisan`. Kalau aplikasinya tidak di `public_html`, sesuaikan baris itu.

## 2. Buat cron di hPanel

**Websites → (domain) → Advanced → Cron Jobs**

- Pilih **PHP**
- Kolom path (setelah `/usr/bin/php /home/USERNAME/`): `queue-worker.php`
- Minute, Hour, Day, Month, Weekday: semua `*` (every)
- Save

Cron memanggil script tiap menit. Di luar jadwal, script langsung keluar sebelum memuat Laravel, jadi bebannya sangat kecil.

## 3. Tes

```bash
touch ~/queue-worker.force
```

Tunggu 2–3 menit, lalu buka **System → Notifikasi Email** di aplikasi (login Admin). Status harus **Queue worker: AKTIF** dan tetap AKTIF saat di-refresh.

Setelah berhasil, **wajib** matikan mode tes:

```bash
rm ~/queue-worker.force
```

Kalau file ini lupa dihapus, worker jalan tiap menit setiap hari.

Kalau tetap TIDAK AKTIF, klik **View Output** di cron tersebut dan kirim isinya ke developer.

## Catatan

- Di luar jadwal, halaman Notifikasi Email menampilkan TIDAK AKTIF. Itu normal.
- Ganti jadwal dengan mengedit baris `$inSchedule` di `queue-worker.php`. Cron di hPanel tidak perlu diubah.
- Prasyarat: kode terbaru sudah di-deploy, `php artisan migrate` sudah dijalankan, dan `QUEUE_CONNECTION=database` di `.env`.
