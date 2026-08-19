# Task: Fitur Foto Profil User

**Tujuan:** Setiap user bisa upload foto profil sendiri, dan foto tersebut muncul di menu user (pojok kanan atas) serta di halaman daftar user (admin).

**Level:** Junior developer / AI model murah. Setiap langkah sudah berisi nama file lengkap dan kode yang bisa langsung dipakai. Kerjakan berurutan dari Langkah 1 sampai selesai, jangan loncat.

---

## Catatan penting sebelum mulai

Di project ini ada **dua halaman "Edit Profile"** yang bentrok nama route-nya (sama-sama bernama `profile.edit`):

1. `app/Http/Controllers/ProfileController.php` + `resources/views/profile/edit.blade.php` (URL `/profile`) — halaman lama, pakai Blade biasa.
2. `app/Livewire/Settings/Profile.php` + `resources/views/livewire/settings/profile.blade.php` (URL `/settings/profile`) — halaman baru, pakai Livewire + Flux UI.

Karena `routes/settings.php` di-load **setelah** `routes/web.php`, route `profile.edit` yang menang adalah yang nomor 2. Menu navbar (`desktop-user-menu.blade.php`, `topbar.blade.php`) memakai `route('profile.edit')`, jadi yang benar-benar dipakai user sehari-hari adalah **halaman nomor 2 (Livewire)**.

**Keputusan untuk task ini:** kerjakan upload foto di halaman **nomor 2 (Livewire)**. Halaman lama (nomor 1) tidak perlu disentuh kecuali diminta lebih lanjut.

---

## Langkah 1 — Migration: tambah kolom `avatar_path` ke tabel `users`

Buat file migration baru dengan perintah:

```
php artisan make:migration add_avatar_path_to_users_table --table=users
```

Isi file migration yang baru dibuat (ikuti pola dari `database/migrations/2026_08_13_073956_add_is_active_to_users_table.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
```

Jalankan migration:

```
php artisan migrate
```

---

## Langkah 2 — Siapkan symlink storage (sekali saja, di semua environment/server)

Jalankan:

```
php artisan storage:link
```

Ini membuat folder `public/storage` yang menunjuk ke `storage/app/public`, supaya file foto yang diupload bisa diakses lewat browser. Tanpa ini, foto tidak akan tampil (404).

> Catatan: kalau deploy ke server produksi nanti, perintah ini harus dijalankan juga di server tersebut (biasanya jadi bagian dari langkah deploy).

---

## Langkah 3 — Update Model `User`

Buka `app/Models/User.php`.

**3a.** Tambahkan `'avatar_path'` ke array `$fillable` (baris 35-41):

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
    'is_active',
    'avatar_path',
];
```

**3b.** Tambahkan accessor `photo_url` dan method `initials()` di bagian bawah class, sebelum kurung kurawal penutup `}` terakhir (setelah method `nameFormatted()`):

```php
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path)
                : null,
        );
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name));
        $words = array_filter($words);

        if (empty($words)) {
            return '?';
        }

        if (count($words) === 1) {
            return \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($words[0], 0, 2));
        }

        $first = \Illuminate\Support\Str::substr(reset($words), 0, 1);
        $last = \Illuminate\Support\Str::substr(end($words), 0, 1);

        return \Illuminate\Support\Str::upper($first . $last);
    }
```

> Kenapa `initials()` ditambahkan? Karena beberapa view (`desktop-user-menu.blade.php`) sudah memanggil `auth()->user()->initials()`, tapi method-nya belum ada di Model. Ini dipakai Flux sebagai fallback avatar kalau user belum punya foto.

Pastikan attribute `photoUrl` bisa diakses sebagai `$user->photo_url` (otomatis oleh Laravel karena pakai `Attribute::make`, tidak perlu tambahan apa-apa).

---

## Langkah 4 — Update Livewire Component `Profile`

Buka `app/Livewire/Settings/Profile.php`. Ganti seluruh isi file dengan:

```php
<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $photo = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Upload/replace the authenticated user's profile photo.
     */
    public function updatePhoto(): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'max:2048'], // max 2MB
        ]);

        $user = Auth::user();

        // Hapus foto lama supaya tidak menumpuk file yatim di storage.
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $this->photo->store('avatars', 'public');

        $user->update(['avatar_path' => $path]);

        $this->reset('photo');

        Flux::toast(variant: 'success', text: __('Profile photo updated.'));
    }

    /**
     * Remove the authenticated user's profile photo.
     */
    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        Flux::toast(variant: 'success', text: __('Profile photo removed.'));
    }
}
```

---

## Langkah 5 — Update view `resources/views/livewire/settings/profile.blade.php`

Ganti seluruh isi file dengan:

```blade
<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">

        {{-- Foto profil --}}
        <div class="my-6 flex items-center gap-4">
            <flux:avatar
                size="xl"
                :src="auth()->user()->photo_url"
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />

            <div class="flex flex-col gap-2">
                <form wire:submit="updatePhoto" class="flex items-center gap-2">
                    <input type="file" wire:model="photo" accept="image/*" />
                    <flux:button type="submit" size="sm" variant="primary">{{ __('Upload Photo') }}</flux:button>
                </form>

                <div wire:loading wire:target="photo">{{ __('Uploading...') }}</div>

                @error('photo')
                    <flux:text class="text-red-600">{{ $message }}</flux:text>
                @enderror

                @if (auth()->user()->avatar_path)
                    <flux:button wire:click="removePhoto" size="sm" variant="ghost">
                        {{ __('Remove Photo') }}
                    </flux:button>
                @endif
            </div>
        </div>

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

            <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
```

---

## Langkah 6 — Tampilkan foto di menu user (navbar)

Buka `resources/views/components/desktop-user-menu.blade.php`. Tambahkan `:src="auth()->user()->photo_url"` di dua tempat yang sudah ada (`flux:sidebar.profile` dan `flux:avatar`):

```blade
<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :src="auth()->user()->photo_url"
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

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
        <flux:menu.radio.group>
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
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
```

(Cuma menambahkan baris `:src="auth()->user()->photo_url"` di dua komponen, sisanya tetap sama.)

---

## Langkah 7 — Tampilkan foto di halaman daftar user (admin)

Buka `resources/views/users/index.blade.php`. Tambahkan kolom "Photo" di tabel:

1. Tambahkan header kolom baru, sebelum kolom "Name" (baris ke-26):

```blade
<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Photo</th>
```

2. Tambahkan cell baru di dalam `@foreach`, sebelum cell nama (baris ke-40):

```blade
<td class="px-4 py-3">
    @if ($user->avatar_path)
        <img src="{{ $user->photo_url }}" alt="{{ $user->name }}" class="h-8 w-8 rounded-full object-cover">
    @else
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
            {{ $user->initials() }}
        </div>
    @endif
</td>
```

---

## Langkah 8 (opsional/lanjutan) — Admin bisa upload foto user lain

Kalau diminta lebih lanjut: tambahkan field upload foto di `resources/views/users/edit.blade.php` (form biasa, bukan Livewire), lalu di `app/Http/Controllers/UserController.php` method `update()`:

- Tambah validasi `'avatar' => ['nullable', 'image', 'max:2048']`
- Kalau `$request->hasFile('avatar')`: hapus foto lama (`Storage::disk('public')->delete($user->avatar_path)` jika ada), lalu simpan yang baru dengan `$request->file('avatar')->store('avatars', 'public')`, masukkan hasil path ke `$data['avatar_path']`.

Ini **tidak wajib** untuk task utama, kerjakan hanya kalau diminta.

---

## Langkah 9 — Checklist testing manual

Setelah semua langkah di atas selesai, cek manual di browser (login sebagai user biasa):

- [ ] Buka `/settings/profile`, pastikan tidak ada error.
- [ ] Upload foto (jpg/png < 2MB) → foto langsung muncul di halaman itu dan di menu navbar kanan atas.
- [ ] Upload foto ke-2 → foto lama otomatis terganti (cek folder `storage/app/public/avatars`, foto lama harus sudah terhapus).
- [ ] Coba upload file bukan gambar (misal `.pdf`) → harus muncul pesan error validasi, bukan crash.
- [ ] Coba upload file gambar > 2MB → harus muncul pesan error validasi.
- [ ] Klik "Remove Photo" → foto hilang, avatar kembali ke inisial nama (misal "Budi Santoso" → "BS").
- [ ] Login sebagai admin, buka halaman daftar user (`/users`) → kolom foto tampil (foto asli atau inisial kalau belum upload).
- [ ] Buka DevTools Network tab, pastikan foto ke-load dari `/storage/avatars/...` (bukan 404). Kalau 404, cek Langkah 2 (`php artisan storage:link`) sudah dijalankan atau belum.
