# FreeDOMS: arah desain

Dokumen ini jadi acuan setiap perubahan UI. Token warna dan bentuk ada di `resources/css/theme.css`, kelas komponen di `resources/css/components.css`, komponen Blade di `resources/views/components/ui/`.

## Siapa yang memakai dan untuk apa

Teknisi (PIC) yang mengerjakan PM, greasing, dan oil audit di lantai pabrik, sering lewat HP dengan sarung tangan atau di bawah lampu yang kurang. Koordinator dan supervisor yang merencanakan, memantau, dan membaca laporan di laptop atau layar monitor. Admin yang mengatur master data dan user.

Pertanyaan utama di hampir setiap layar: **apa yang harus dikerjakan sekarang, dan apa yang terlambat.**

## Design Read

Aplikasi operasional CMMS untuk teknisi dan supervisor maintenance pabrik, bahasa visual modern ramah yang diturunkan dari logo FreeDOMS, dial **ENERGY 2 / RHYTHM 2 / MOTION 1**.

- **ENERGY 2:** ramah dan percaya diri, tidak berisik. Satu fokus per layar, sisanya tenang.
- **RHYTHM 2:** pola halaman konsisten (header, filter, daftar) supaya teknisi tidak belajar ulang, dengan pengecualian di dashboard dan papan monitor.
- **MOTION 1:** hanya hover, fokus, dan transisi state (buka menu, progress berubah). Semua mati di `prefers-reduced-motion`.

## Logo

Sumber: SVG dari pemilik (huruf F dan R slate, dua "E" dari tiga batang membulat bergradasi). Komponen: `<x-brand-logo />`.

- `framed` (bawaan): dengan bingkai slate muda, untuk latar gelap seperti sidebar.
- `:framed="false"`: tanpa bingkai, untuk latar terang.
- `mono`: satu warna `currentColor`, untuk latar foto atau latar berwarna brand.

Tiga batang di logo bermakna People, Process, Technology. Warnanya jadi sumber palet.

## Warna

| Peran | Token | Nilai | Alasan |
|---|---|---|---|
| Teks utama | `text` (neutral-800) | #1E293B | warna huruf logo |
| Latar halaman | `background` (neutral-50) | #F8FAFC | isi bingkai logo |
| Garis | `border-strong` (neutral-300) | #CBD5E1 | bingkai logo |
| Aksi (primary) | `primary` (primary-600) | #0369A1 | biru "Process" logo (#0284C7) digelapkan satu tingkat supaya teks putih 5.9:1 (AA). #0284C7 sendiri hanya 4.1:1 |
| Berhasil | `success` (success-600) | #047857 | emerald "Technology" logo, teks putih 5.5:1 |
| Informasi | `info` (info-700) | #1E40AF | biru "Technology" logo, dipisah dari primary supaya info tidak terbaca sebagai tombol |
| Perlu perhatian | amber `secondary` | #F59E0B | status (menunggu, perlu tindakan), bukan aksen brand |
| Peringatan | `warning` | #ED6C02 | status |
| Bahaya | `danger` | #D32F2F | status (terlambat, kritis, hapus) |

Aturan:

- Palet inti: slate + biru primary + emerald. Amber, oranye, merah hanya untuk **status**, tidak untuk dekorasi.
- **Gradasi brand** sky (#0EA5E9) ke emerald (#10B981) hanya dipakai di logo dan motif batang progres. Tidak pernah ada teks di atas gradasi (sisi sky gagal kontras).
- Kelas Tailwind bawaan (`bg-blue-600`, `text-slate-500`, `bg-emerald-50`) sudah dipetakan ke token di `theme.css`. Markup baru sebaiknya memakai token semantik (`bg-primary`, `text-text-muted`, `bg-success-light`).
- Semua pasangan teks/latar minimal AA. Badge warning memakai `warning-text` karena `warning-hover` hanya 3.9:1 di latar muda.

## Tipografi

- **Instrument Sans** untuk teks (`font-sans`, bawaan). Alasan: bentuk huruf terbuka dan jelas di ukuran kecil, cocok untuk tabel padat dan label form.
- **Barlow Semi Condensed** untuk judul halaman dan angka besar (`font-signage`). Alasan: huruf sempit muat lebih banyak angka di kartu dan papan monitor, dan nadanya seperti papan tanda di pabrik. Angka memakai `tabular-nums`.
- Tidak ada label huruf kapital dengan spasi lebar. Header tabel memakai huruf biasa.
- `body` diberi `word-spacing: 0.05em`. Alasan: spasi bawaan Instrument Sans sempit (sekitar 0.17em), sehingga di teks 12 sampai 13 px kata-kata terlihat menempel.

## Bentuk dan kedalaman

- Radius tiga tingkat: kontrol `--radius-control` 10px, kartu `--radius-card` 16px, pill (`--radius-pill`) hanya untuk badge status dan batang progres.
- Shadow hanya untuk yang benar-benar terangkat: dropdown, modal, bar aksi yang menempel. Kartu biasa cukup garis tipis (`shadow-card` sangat halus).
- Tidak ada glassmorphism, glow, pola latar, atau garis warna di tepi kiri kartu sebagai hiasan.

## Motif identitas

**Batang membulat dari huruf "E" logo.** Dipakai sebagai batang progres (`<x-ui.progress>`) untuk nilai nyata seperti PM selesai dibanding rencana. Satu layar maksimal satu batang bergradasi sebagai fokus; batang lain memakai warna solid.

## Komponen

| Komponen | Pakai untuk |
|---|---|
| `<x-ui.page-header>` | judul halaman (h1), deskripsi singkat, tombol aksi |
| `<x-ui.button>` | semua tombol dan link berbentuk tombol. Varian: primary, success, danger, outline, ghost. Tinggi minimal 44px di HP |
| `<x-ui.card>` | kelompok konten dengan judul opsional dan aksi |
| `<x-ui.badge>` | label status nyata saja |
| `<x-ui.table>` | tabel dengan wadah scroll horizontal sendiri |
| `<x-ui.field>` | label terlihat + kontrol + hint + error validasi |
| `<x-ui.alert>` | pesan flash dan peringatan |
| `<x-ui.empty-state>` | daftar kosong (sebab + satu aksi), atau `tone="error"` |
| `<x-ui.progress>` | motif batang progres |

Semua komponen meneruskan atribut tambahan (`wire:*`, `x-*`, `data-*`, `id`, `name`), jadi atribut yang dipakai JS atau dicek test tetap bisa dipasang.

## Teks

- Bahasa mengikuti halaman yang sudah ada. Label tombol menyebut aksinya ("Simpan jadwal", "Mulai PM"), bukan "Submit" atau "Klik di sini".
- Tidak ada em dash di teks UI. Pakai koma, titik, titik dua, atau kurung.
- Empty state menyebut sebabnya dan langkah berikutnya, bukan "Tidak ada data".

## Aksesibilitas dan HP

- Fokus keyboard selalu terlihat (ring primary sebagai cadangan di `components.css`).
- Kontrol yang bisa diklik memakai kursor pointer; yang nonaktif memakai not-allowed.
- Target sentuh minimal 44 x 44 px. Tabel lebar discroll di dalam wadahnya, halaman tidak boleh geser ke samping.
- Diverifikasi di lebar 360, 820, dan 1440 px.

## Mode gelap

Belum diaktifkan. Token `.dark` sudah disiapkan di `theme.css` (dikomentari). Kalau diaktifkan, kedua mode harus diverifikasi di setiap halaman.

## Landing page

> Pintu depan publik FreeDOMS untuk tim produksi dan manajemen PT BIM yang tidak punya akun, dalam bahasa visual pelat industri terang turunan dokumen ini, dial **ENERGY 2 / RHYTHM 2 / MOTION 1**, dengan satu pengecualian tertulis: hero (lihat di bawah).

Dirombak 2026-11-13 dari arah sinematik lama (ENERGY 3 / RHYTHM 3 / MOTION 1, lihat `anti-slop/audit-001-*.md`) karena tidak lagi sinkron dengan app di balik login, dan empat tombol sejajar di hero/CTA tidak punya fokus konversi. Bahasa Inggris penuh, menyamai app (keputusan user 2026-11-13; sebelumnya landing sengaja dibiarkan Indonesia).

- **ENERGY 2 (kecuali hero):** satu fokus per layar di sebagian besar section.
- **RHYTHM 2:** pola section konsisten (terang), dengan dua patahan gelap: hero (atas, lihat pengecualian) dan "How it works" (`bg-blue-950` solid).
- **MOTION 1 (kecuali hero):** sisa halaman hanya hover, fokus, dan transisi state. Section "How it works" tanpa reveal saat scroll.

**Pengecualian tertulis untuk hero (R-37):** pada revisi kedua yang sama (2026-11-13), user eksplisit minta hero dikembalikan ke versi sinematik lama setelah melihat hasil ENERGY 2/MOTION 1/satu-CTA: animasi ilustrasi (lampu menyala berurutan, capstan berputar, parallax, tombol jeda) dan empat aksi sejajar (Scan a Machine, View Dashboard, View Monitor, Sign in). Ditanya dulu ("mending balikin lagi" setelah ditunjukkan konsekuensinya terhadap dial MOTION dan fokus konversi), dijawab "balikin penuh". Jadi khusus section hero: **ENERGY 3 / MOTION 3**, bukan 2/1. Konsekuensinya:
- `<x-landing.factory-scene uid="hero">` dipanggil tanpa `:still`/`:toggle` (default aktif penuh), dengan `pause-label="Pause animation" play-label="Play animation"` (prop baru di `factory-scene.blade.php`, default Indonesia dipertahankan untuk auth, landing pakai Inggris).
- `.cine-in` + `@keyframes cine-rise` di `landing.css` aktif lagi untuk hero.
- Sisa halaman (pm-overview, public-access, how-it-works, final-cta, footer) **tidak ikut berubah**, tetap satu fokus dan MOTION 1. Final CTA tetap satu tombol per area, bukan empat tombol; ini bukan revert penuh ke arah lama, hanya hero.
- Teks hero diterjemahkan ke Inggris (bukan dikembalikan ke Indonesia aslinya), supaya bahasa tetap konsisten satu halaman; ini keputusan sepihak yang diambil saat eksekusi, bukan ditanyakan user secara eksplisit.

Landing memakai komponen `x-ui.*` yang sama dengan app (`.card`, `x-ui.button`, `x-ui.progress`, `x-ui.empty-state`, `x-ui.field`) di luar hero, bukan anchor buatan tangan atau bayangan keras seperti versi lama. Angka di section "Preventive maintenance, bulan berjalan" (`<x-landing.pm-overview>`) dibaca langsung dari `App\Services\PublicPmOverview`, sumber yang sama dipakai `/dashboard-guest`, supaya kedua halaman tidak pernah menampilkan angka berbeda untuk bulan yang sama.
