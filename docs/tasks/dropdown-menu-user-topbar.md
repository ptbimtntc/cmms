# Task: Gabungkan Info User + Logout di Topbar Jadi 1 Tombol (Avatar) dengan Dropdown

**Tujuan:** Saat ini di topbar (pojok kanan atas semua halaman) ada 3 elemen terpisah: foto/inisial user, blok teks "My Profile / Nama / Role", dan tombol "Logout" berwarna merah. Ganti jadi **1 tombol** berupa foto profil saja. Kalau foto itu diklik, muncul dropdown kecil berisi nama+role, link "Edit Profile", dan tombol "Logout".

**Level:** Junior developer / AI model murah. Hanya 1 file yang diubah. Ikuti langkah di bawah secara berurutan.

---

## Konteks yang perlu diketahui dulu

- File yang diubah: `resources/views/partials/topbar.blade.php`. File ini di-include oleh `resources/views/layouts/app.blade.php` dan tampil di **semua** halaman yang login (dashboard, users, machines, dll).
- Project ini sudah pakai **Alpine.js** (bukan jQuery, bukan Livewire) untuk interaksi kecil seperti buka/tutup sidebar mobile — lihat `resources/views/partials/sidebar.blade.php` dan tombol hamburger di baris atas `topbar.blade.php` yang sudah pakai `@click="$store.sidebar.open = true"`. Untuk dropdown ini kita pakai pola Alpine yang sama (`x-data`, `@click`, `x-show`), **bukan** komponen Livewire/Flux — karena sebelumnya sudah ketahuan komponen Livewire/Flux di project ini bermasalah (halaman blank), jadi kita hindari.
- Link "Edit Profile" nanti tetap mengarah ke `route('profile.edit')` — sama seperti link "My Profile" yang sekarang. **Tidak perlu diubah tujuannya**, itu di luar scope task ini.

---

## Langkah 1 — Ganti bagian kanan topbar

Buka `resources/views/partials/topbar.blade.php`. Cari blok ini (baris 26-55, dari `<div class="flex items-center justify-end gap-3">` sampai `</div>` penutup form Logout):

```blade
        <div class="flex items-center justify-end gap-3">
            @if (auth()->user()->avatar_path)
                <img
                    src="{{ auth()->user()->photo_url }}"
                    alt="{{ auth()->user()->name }}"
                    class="h-9 w-9 rounded-full object-cover"
                >
            @else
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
                    {{ auth()->user()->initials() }}
                </div>
            @endif

            <div class="text-right">
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">
                    My Profile
                    <div class="text-sm font-medium text-slate-800">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-slate-500">{{ auth()->user()->role }}</div>
                </a>

            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    class="rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-red-700">
                    Logout
                </button>
            </form>
        </div>
```

Ganti **seluruh blok di atas** dengan kode ini:

```blade
        <div class="flex items-center justify-end gap-3" x-data="{ open: false }">
            <div class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="block rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    title="Account menu"
                >
                    @if (auth()->user()->avatar_path)
                        <img
                            src="{{ auth()->user()->photo_url }}"
                            alt="{{ auth()->user()->name }}"
                            class="h-9 w-9 rounded-full object-cover"
                        >
                    @else
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
                            {{ auth()->user()->initials() }}
                        </div>
                    @endif
                </button>

                <div
                    x-show="open"
                    x-cloak
                    @click.outside="open = false"
                    x-transition
                    class="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-slate-200 bg-white py-2 shadow-lg"
                >
                    <div class="border-b border-slate-100 px-4 py-2">
                        <div class="text-sm font-medium text-slate-800">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-slate-500">{{ auth()->user()->role }}</div>
                    </div>

                    <a
                        href="{{ route('profile.edit') }}"
                        class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                    >
                        Edit Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
```

**Penjelasan singkat tiap bagian (biar paham, bukan cuma copy-paste):**
- `x-data="{ open: false }"` — bikin variabel lokal `open` yang mengontrol dropdown kebuka/tertutup, defaultnya tertutup.
- `@click="open = !open"` di tombol avatar — tiap tombol diklik, nilai `open` dibalik (kebuka jadi tertutup, tertutup jadi kebuka).
- `x-show="open"` di kotak dropdown — kotak ini cuma tampil kalau `open` bernilai `true`.
- `@click.outside="open = false"` — kalau user klik di luar kotak dropdown, otomatis tertutup lagi.
- `x-transition` — biar muncul/hilangnya ada animasi halus (fade), bukan tiba-tiba.
- `x-cloak` — mencegah kotak dropdown kelihatan sekilas (flash) sebelum Alpine.js selesai load saat halaman pertama kali dibuka.

---

## Langkah 2 — Bersihkan cache Blade & cek tidak ada error

Jalankan:

```
php artisan view:clear
php artisan view:cache
php artisan view:clear
```

Kalau ada error saat `view:cache` (misal typo tanda kurung Blade), akan langsung kelihatan di output — perbaiki dulu sebelum lanjut.

---

## Langkah 3 — Checklist testing manual di browser

Buka `http://cmms.test` (atau domain lokal yang dipakai), login, lalu cek di halaman mana saja (dashboard, users, machines, dll — karena topbar ini tampil di semua halaman):

- [ ] Yang tampil di topbar sekarang cuma **1 lingkaran foto/inisial**, tidak ada lagi teks "My Profile" atau tombol merah "Logout" yang selalu kelihatan.
- [ ] Klik foto/inisial itu → muncul kotak dropdown di bawahnya berisi: nama + role user, link "Edit Profile", tombol "Logout".
- [ ] Klik lagi foto/inisial saat dropdown terbuka → dropdown tertutup.
- [ ] Klik area lain di halaman (bukan tombol foto, bukan area dropdown) saat dropdown terbuka → dropdown otomatis tertutup.
- [ ] Klik "Edit Profile" → diarahkan ke halaman yang sama seperti sebelumnya (link `profile.edit`, tidak berubah).
- [ ] Klik "Logout" → berhasil logout seperti biasa.
- [ ] Coba di ukuran layar HP (resize browser jadi sempit / buka DevTools mode mobile) → dropdown tetap muncul dengan benar, tidak kepotong di luar layar.
- [ ] User yang belum punya foto profil → tetap tampil lingkaran inisial (misal "AD"), tombolnya tetap bisa diklik dan dropdown tetap berfungsi sama seperti user yang sudah punya foto.
