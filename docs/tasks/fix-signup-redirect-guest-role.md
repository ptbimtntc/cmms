# Task: Fix Redirect Signup — User Baru (Role GUEST) Harus ke Dashboard Guest

**Tujuan:** Setelah signup (dan login) berhasil, user dengan role `GUEST` diarahkan ke `/dashboard-guest` — bukan `/dashboard` (yang bukan untuknya). Navbar juga harus berubah dari tombol "Login" jadi avatar/menu user begitu ada yang sudah login (termasuk yang role-nya GUEST).

**Level:** Junior developer / AI model murah. Setiap langkah sudah berisi nama file lengkap dan kode yang bisa langsung dipakai. Kerjakan berurutan dari Langkah 1 sampai selesai, jangan loncat.

**Status:** ✅ Selesai dieksekusi (2026-08-21). Langkah 1-5 sudah diimplementasikan, diverifikasi lewat request HTTP sungguhan (signup baru → redirect `/dashboard-guest`, navbar tampil avatar; login admin → tetap ke `/dashboard`; login user existing role GUEST → ke `/dashboard-guest`). Test suite terkait (`AuthenticationTest`, `RegistrationTest`, `DashboardTest`) sudah diupdate supaya sesuai behavior baru — lihat catatan di bagian "Temuan tambahan".

---

## Ringkasan bug

Sekarang, kalau user baru signup (form di `/register`):

1. `CreateNewUser` (action Fortify) **sudah benar** kasih role `GUEST` ke user baru.
2. Tapi setelah signup sukses, Fortify redirect ke `config('fortify.home')` = **`/dashboard`**.
3. Route `/dashboard` di-guard middleware `role:ADMIN,KOORDINATOR WWD,KOORDINATOR BUL,PIC WWD,PIC BUL` — **`GUEST` tidak termasuk**.
4. Middleware `RoleMiddleware` langsung `abort(403, 'Unauthorized')` kalau role user tidak ada di daftar itu.

**Hasilnya: user baru selesai signup, langsung kena halaman 403 Unauthorized.** Bukan cuma "diarahkan ke dashboard yang salah" — beneran error, bukan pengalaman yang mulus.

Ini juga **bukan cuma soal signup** — user existing yang role-nya `GUEST` dan **login biasa** juga kena masalah yang sama persis, karena tidak ada custom `LoginResponse` di project ini (cuma pakai default bawaan Fortify, yang juga redirect ke `config('fortify.home')`).

> Bukti tambahan: ini juga akar masalah kenapa test `tests/Feature/DashboardTest.php` (`authenticated_users_can_visit_the_dashboard`) gagal — `User::factory()->create()` di situ menghasilkan user dengan role default `GUEST` (karena `UserFactory` tidak set kolom `role` sama sekali), lalu test itu expect `/dashboard` return 200, padahal kena 403. Root cause-nya sama.

Selain redirect salah, ada bug ke-2 yang disebut user: **navbar landing page (`x-landing.navbar`) tidak pernah cek status login sama sekali** — tombol "Login" selalu muncul, walaupun yang buka halaman itu sebenarnya sudah login (baik baru signup, baru login biasa, atau navigasi balik ke landing page setelah login). Padahal komponen avatar/dropdown user **sudah ada** di project ini (`desktop-user-menu.blade.php`, dipakai di topbar/sidebar area aplikasi utama) — cuma belum dipasang di navbar landing page.

## Kondisi sekarang (bukti dari kode)

**`app/Actions/Fortify/CreateNewUser.php`** — role assignment sudah benar:
```php
return User::create([
    'name' => $input['name'],
    'email' => $input['email'],
    'password' => $input['password'],
    'role' => User::ROLE_GUEST,
]);
```

**`config/fortify.php`** baris 76:
```php
'home' => '/dashboard',
```

**`routes/web.php`** baris 93-97 — `/dashboard` tidak mengizinkan GUEST:
```php
Route::middleware([
    'auth',
    'role:ADMIN,KOORDINATOR WWD,KOORDINATOR BUL,PIC WWD,PIC BUL',
])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    ...
});
```

**`routes/web.php`** baris 21 — dashboard guest **sudah ada**, sudah public (tidak perlu login untuk lihat):
```php
Route::view('/dashboard-guest', 'dashboard-guest')->name('dashboard-guest');
```

**`app/Http/Middleware/RoleMiddleware.php`** — hard abort, tidak ada redirect:
```php
public function handle(Request $request, Closure $next, ...$roles): Response
{
    if (!Auth::check()) {
        abort(403, 'Unauthorized');
    }

    if (!in_array(Auth::user()->role, $roles, true)) {
        abort(403, 'Unauthorized');
    }

    return $next($request);
}
```

**`resources/views/components/landing/navbar.blade.php`** baris 93-100 — Login link tanpa cek auth sama sekali:
```blade
<div class="flex items-center gap-3">
    <a href="{{ route('login') }}" class="hidden md:inline-flex ...">
        Login
    </a>
    <a href="{{ route('qr.scan') }}" ...>Scan Mesin</a>
</div>
```

**Tidak ada** binding `LoginResponse`/`RegisterResponse` custom di mana pun (`grep -rn "LoginResponse\|RegisterResponse" app/` kosong) — jadi keduanya pakai default bawaan `laravel/fortify` yang redirect ke `Fortify::redirects('login'|'register')`, yang jatuh balik ke `config('fortify.home')` kalau tidak di-override.

---

## Rencana perbaikan

### Langkah 1 — Tambah helper `isGuest()` di Model `User`

Biar konsisten dengan helper role lain yang sudah ada (`isAdmin()`, `isPic()`, dst — lihat `app/Models/User.php` baris 74-123), tapi anehnya belum ada `isGuest()` padahal `GUEST` yang paling sering perlu dicek khusus.

Buka `app/Models/User.php`, tambahkan method baru **persis setelah `isPicBul()`** (baris 110-113), sebelum `isActive()`:

```php
    public function isGuest(): bool
    {
        return $this->role === self::ROLE_GUEST;
    }
```

### Langkah 2 — Buat custom `LoginResponse` yang redirect sesuai role

Buat file baru `app/Http/Responses/LoginResponse.php`:

```php
<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = Auth::user();

        if ($user && $user->isGuest()) {
            return redirect()->route('dashboard-guest');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
```

> Kenapa tetap cek `$request->wantsJson()`? Supaya perilaku untuk request AJAX/Two-Factor tetap sama seperti default Fortify (lihat `vendor/laravel/fortify/src/Http/Responses/LoginResponse.php`) — kita cuma menambahkan cabang untuk GUEST, bukan mengganti semuanya.

### Langkah 3 — Buat custom `RegisterResponse` yang sama polanya

Buat file baru `app/Http/Responses/RegisterResponse.php`:

```php
<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        $user = Auth::user();

        if ($user && $user->isGuest()) {
            return redirect()->route('dashboard-guest');
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
```

> Kenapa dua class terpisah (bukan satu class dipakai dua-duanya)? Karena `LoginResponse` dan `RegisterResponse` itu dua contract/interface yang berbeda di Fortify (`Laravel\Fortify\Contracts\LoginResponse` vs `Laravel\Fortify\Contracts\RegisterResponse`), meskipun isinya kebetulan mirip. Bisa juga digabung pakai satu class yang `implements` kedua interface itu sekaligus kalau mau lebih ringkas — tapi dua file terpisah lebih gampang dibaca dan konsisten dengan cara Fortify sendiri memisahkan tiap response.

### Langkah 4 — Daftarkan kedua Response custom itu ke Fortify

Buka `app/Providers/FortifyServiceProvider.php`. Tambahkan `use` baru di atas:

```php
use App\Http\Responses\LoginResponse;
use App\Http\Responses\RegisterResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
```

Lalu tambahkan binding di awal method `boot()` (baris 29-33), sebelum `$this->configureActions();`:

```php
    public function boot(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);

        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();

        Fortify::authenticateUsing(function (Request $request) {
            ...
        });
    }
```

(Isi method `authenticateUsing(...)` di bawahnya **tidak perlu diubah**, tetap sama seperti sekarang.)

---

### Langkah 5 — Perbaiki navbar: Login jadi avatar kalau sudah login

Buka `resources/views/components/landing/navbar.blade.php`. Ganti bagian "Action" (baris 92-100):

```blade
            <!-- Action -->
            <div class="flex items-center gap-3">

                <a href="{{ route('login') }}"
                    class="hidden md:inline-flex px-3 py-2 rounded-xl border border-slate-300 hover:bg-slate-100 transition">

                    Login

                </a>
```

Jadi:

```blade
            <!-- Action -->
            <div class="flex items-center gap-3">

                @guest
                    <a href="{{ route('login') }}"
                        class="hidden md:inline-flex px-3 py-2 rounded-xl border border-slate-300 hover:bg-slate-100 transition">

                        Login

                    </a>
                @else
                    <flux:dropdown position="bottom end" align="end">
                        <button type="button" class="hidden md:inline-flex">
                            <flux:avatar
                                size="sm"
                                :src="auth()->user()->photo_url"
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                            />
                        </button>

                        <flux:menu>
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :src="auth()->user()->photo_url"
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                            <flux:menu.separator />
                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                                {{ __('Settings') }}
                            </flux:menu.item>
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item
                                    as="button"
                                    type="submit"
                                    icon="arrow-right-start-on-rectangle"
                                    class="w-full cursor-pointer"
                                >
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                @endguest
```

**Bagian `<a href="{{ route('qr.scan') }}">...Scan Mesin</a>` di bawahnya tidak perlu diubah**, biarkan tetap tampil baik untuk guest maupun yang sudah login.

> Kenapa tidak pakai `<x-desktop-user-menu />` yang sudah ada langsung? Karena component itu pakai trigger `flux:sidebar.profile` yang didesain untuk baris footer sidebar gelap (lebar penuh, ada teks nama + chevron) — kalau ditaruh di navbar landing yang sempit dan terang, tampilannya bakal aneh/kebesaran. Makanya di sini triggernya diganti jadi `flux:avatar` kecil saja (`size="sm"`), isi dropdown-nya (Settings + Logout) tetap sama persis supaya user experience-nya konsisten dengan menu yang sudah ada di aplikasi utama.

> Kalau nanti dirasa functionality "Settings"/"Logout" ini dipakai berulang di banyak tempat (sudah ada 2: sini + `desktop-user-menu.blade.php`), pertimbangkan ekstrak ke Blade component baru yang menerima parameter ukuran/trigger. **Tidak wajib untuk task ini**, cukup dicatat sebagai potential future cleanup.

---

## Temuan tambahan (bukan bagian wajib task ini, cukup dicatat)

- **`RoleMiddleware` masih hard `abort(403)`** untuk siapa pun yang manual ketik URL `/dashboard` di address bar walau sudah login sebagai GUEST (setelah Langkah 2-4 di atas, ini cuma kejadian kalau user sengaja buka `/dashboard` manual — bukan lagi dari redirect otomatis setelah login/signup). Kalau mau UX yang lebih halus (redirect ke `/dashboard-guest` dibanding halaman error), middleware ini bisa diubah supaya redirect alih-alih abort. **Di luar scope task ini** kecuali diminta lebih lanjut.
- **`resources/views/dashboard-guest.blade.php`** baris 108-109 & 127-132 — halaman ini didesain buat pengunjung anonim ("Login untuk Akses Penuh", tombol "Login" di CTA card kanan bawah). Kalau nanti user **role GUEST yang sudah login** mendarat di sini (dari Langkah 2-3 di atas), pesan "Login untuk Akses Penuh" + tombol "Login" itu jadi sedikit membingungkan (dia kan sudah login, cuma rolenya terbatas). Perbaikan pesan supaya beda untuk "visitor anonim" vs "user GUEST yang sudah login" **di luar scope task ini** kecuali diminta lebih lanjut — cukup dicatat sebagai potensi UX improvement lanjutan.
- **`database/factories/UserFactory.php`** tidak set kolom `role` — otomatis jatuh ke default DB (`GUEST`). Ini kenapa `tests/Feature/DashboardTest.php`, `AuthenticationTest.php`, dan `RegistrationTest.php` awalnya gagal setelah fix ini masuk (mereka assert redirect ke `/dashboard` padahal user hasil factory/register sekarang benar redirect ke `/dashboard-guest`). **Sudah diperbaiki** sebagai bagian dari eksekusi task ini:
  - `AuthenticationTest.php`: test "users can authenticate..." sekarang eksplisit `create(['role' => User::ROLE_ADMIN])` supaya tetap menguji redirect ke `/dashboard`; ditambah test baru "guest role users are redirected to the guest dashboard after login".
  - `RegistrationTest.php`: assertion diganti ke `route('dashboard-guest')` (karena `CreateNewUser` selalu kasih role GUEST ke user baru, tidak ada skenario lain untuk endpoint ini).
  - `DashboardTest.php`: test "authenticated users can visit the dashboard" sekarang eksplisit `create(['role' => User::ROLE_ADMIN])`; ditambah test baru "guest role users cannot visit the dashboard" yang assert `403` (mendokumentasikan behavior middleware yang ada sekarang — lihat poin pertama di atas soal `RoleMiddleware`).

---

## Langkah 6 — Testing manual

Setelah Langkah 1-5 selesai:

- [ ] Buka `/register` (belum login), isi form, submit → **harus** redirect ke `/dashboard-guest`, bukan error 403.
- [ ] Di halaman itu, cek navbar bagian kanan atas → **harus** tampil avatar (bukan tombol "Login").
- [ ] Klik avatar → dropdown muncul, ada "Settings" (ke `/settings/profile` atau `/profile`) dan "Log out". Coba klik Logout → berhasil logout, kembali ke landing page, navbar balik nampilin tombol "Login" lagi.
- [ ] Buat/pakai user existing dengan role selain GUEST (mis. admin `admin@example.com` / `password` dari sebelumnya) → login → **harus** tetap redirect ke `/dashboard` (bukan `/dashboard-guest`, tidak boleh regresi).
- [ ] Login pakai user existing yang rolenya GUEST (kalau belum ada, register dulu satu, atau ubah role salah satu user di database jadi `GUEST` manual) → login → harus ke `/dashboard-guest`, sama seperti hasil signup.
- [ ] Saat login sebagai role GUEST, coba akses manual `/dashboard` lewat address bar → sesuai kondisi sekarang, ini masih boleh 403 (lihat "Temuan tambahan" di atas — perbaikannya di luar scope task ini).
- [ ] Jalankan `php artisan test --filter=DashboardTest` → cek hasilnya (kemungkinan masih gagal dengan alasan baru, lihat catatan di "Temuan tambahan" — putuskan bareng apakah test itu perlu diupdate sekalian).
