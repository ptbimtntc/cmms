@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">
                Edit User
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Update user account, role, or password.
            </p>
        </div>

        <form
            action="{{ route('users.update', $user) }}"
            method="POST"
            enctype="multipart/form-data"
            class="space-y-5 p-6"
            novalidate
        >
            @csrf
            @method('PUT')

            <div
                x-data="{
                    cropOpen: false,
                    img: null,
                    scale: 1,
                    minScale: 1,
                    maxScale: 1,
                    offsetX: 0,
                    offsetY: 0,
                    dragging: false,
                    startX: 0,
                    startY: 0,
                    lastX: 0,
                    lastY: 0,
                    editorSize: 288,
                    outputSize: 480,
                    previewUrl: @js($user->photo_url),
                    onFileChosen(e) {
                        const file = e.target.files[0];
                        if (! file) return;
                        const image = new Image();
                        const objectUrl = URL.createObjectURL(file);
                        const reset = () => {
                            URL.revokeObjectURL(objectUrl);
                            this.$refs.avatarInput.value = '';
                        };
                        image.onload = () => {
                            // Guard against a corrupt/non-image file slipping past
                            // the accept filter with zero intrinsic dimensions —
                            // without this, minScale/maxScale become Infinity and
                            // the zoom slider ends up in an invalid, unsubmittable state.
                            if (! image.width || ! image.height) {
                                reset();
                                alert('This file could not be read as an image. Please choose a different file.');
                                return;
                            }
                            this.img = image;
                            this.minScale = this.editorSize / Math.min(image.width, image.height);
                            this.maxScale = this.minScale * 3;
                            this.scale = this.minScale;
                            this.offsetX = 0;
                            this.offsetY = 0;
                            this.cropOpen = true;
                            this.$nextTick(() => this.draw());
                        };
                        image.onerror = () => {
                            reset();
                            alert('This file could not be read as an image. Please choose a different file.');
                        };
                        image.src = objectUrl;
                    },
                    draw() {
                        if (! this.img || ! this.$refs.canvas) return;
                        const canvas = this.$refs.canvas;
                        const ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        const w = this.img.width * this.scale;
                        const h = this.img.height * this.scale;
                        const cx = canvas.width / 2 + this.offsetX;
                        const cy = canvas.height / 2 + this.offsetY;
                        ctx.drawImage(this.img, cx - w / 2, cy - h / 2, w, h);
                    },
                    clamp() {
                        const w = this.img.width * this.scale;
                        const h = this.img.height * this.scale;
                        const maxX = Math.max(0, (w - this.editorSize) / 2);
                        const maxY = Math.max(0, (h - this.editorSize) / 2);
                        this.offsetX = Math.min(maxX, Math.max(-maxX, this.offsetX));
                        this.offsetY = Math.min(maxY, Math.max(-maxY, this.offsetY));
                    },
                    pointer(e) {
                        return e.touches && e.touches.length ? e.touches[0] : e;
                    },
                    startDrag(e) {
                        const p = this.pointer(e);
                        this.dragging = true;
                        this.startX = p.clientX;
                        this.startY = p.clientY;
                        this.lastX = this.offsetX;
                        this.lastY = this.offsetY;
                    },
                    onDrag(e) {
                        if (! this.dragging) return;
                        if (e.cancelable) e.preventDefault();
                        const p = this.pointer(e);
                        this.offsetX = this.lastX + (p.clientX - this.startX);
                        this.offsetY = this.lastY + (p.clientY - this.startY);
                        this.clamp();
                        this.draw();
                    },
                    endDrag() {
                        this.dragging = false;
                    },
                    onZoom() {
                        this.clamp();
                        this.draw();
                    },
                    applyCrop() {
                        const out = document.createElement('canvas');
                        out.width = this.outputSize;
                        out.height = this.outputSize;
                        const ctx = out.getContext('2d');
                        const ratio = this.outputSize / this.editorSize;
                        const w = this.img.width * this.scale * ratio;
                        const h = this.img.height * this.scale * ratio;
                        const cx = this.outputSize / 2 + this.offsetX * ratio;
                        const cy = this.outputSize / 2 + this.offsetY * ratio;
                        ctx.drawImage(this.img, cx - w / 2, cy - h / 2, w, h);
                        out.toBlob((blob) => {
                            const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            this.$refs.avatarInput.files = dt.files;
                            this.previewUrl = out.toDataURL('image/jpeg', 0.9);
                            this.cropOpen = false;
                        }, 'image/jpeg', 0.9);
                    },
                    cancelCrop() {
                        this.$refs.avatarInput.value = '';
                        this.cropOpen = false;
                    },
                }"
            >
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Profile Photo
                </label>

                <div class="flex items-center gap-4">
                    <img
                        x-show="previewUrl"
                        :src="previewUrl"
                        alt="{{ $user->name }}"
                        class="h-16 w-16 rounded-full object-cover"
                    >
                    <div
                        x-show="! previewUrl"
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-600"
                    >
                        {{ $user->initials() }}
                    </div>

                    <div>
                        <input
                            type="file"
                            name="avatar"
                            accept="image/*"
                            x-ref="avatarInput"
                            @change="onFileChosen($event)"
                            class="block text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                        >

                        <p class="mt-1 text-xs text-slate-500">
                            JPG or PNG, max 2MB. Leave blank to keep current photo.
                        </p>

                        @if ($user->avatar_path)
                            <label class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                                <input type="checkbox" name="remove_avatar" value="1">
                                Remove current photo
                            </label>
                        @endif
                    </div>
                </div>

                @error('avatar')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror

                {{-- Modal crop/posisi foto --}}
                <div
                    x-show="cropOpen"
                    x-cloak
                    style="display: none;"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                >
                    <div class="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-lg" @click.outside="cancelCrop()">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">Adjust Photo</h2>
                            <p class="text-sm text-slate-500">Drag to reposition, use the slider to zoom.</p>
                        </div>

                        <div class="flex justify-center">
                            <div
                                class="relative h-72 w-72 touch-none select-none overflow-hidden rounded-full border-4 border-slate-200 bg-slate-100"
                                :class="dragging ? 'cursor-grabbing' : 'cursor-grab'"
                                x-on:mousedown="startDrag($event)"
                                x-on:mousemove.window="onDrag($event)"
                                x-on:mouseup.window="endDrag()"
                                x-on:touchstart="startDrag($event)"
                                x-on:touchmove="onDrag($event)"
                                x-on:touchend="endDrag()"
                            >
                                <canvas x-ref="canvas" width="288" height="288" class="h-72 w-72"></canvas>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 px-4">
                            <span class="text-sm text-slate-400">&minus;</span>
                            <input
                                type="range"
                                x-model.number="scale"
                                :min="minScale"
                                :max="maxScale"
                                step="0.001"
                                x-on:input="onZoom()"
                                class="w-full accent-blue-600"
                            >
                            <span class="text-sm text-slate-400">+</span>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="cancelCrop()" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                                Cancel
                            </button>
                            <button type="button" @click="applyCrop()" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Save Photo
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Name
                </label>

                <input
                    type="text"
                    name="name"
                    value="{{ old('name', $user->name) }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                    required
                >

                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $user->email) }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                    required
                >

                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    New Password
                </label>

                <input
                    type="password"
                    name="password"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                    placeholder="Leave blank to keep current password"
                >

                <p class="mt-1 text-xs text-slate-500">
                    Leave blank if you don't want to change the password.
                </p>

                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Role
                </label>

                @if (auth()->id() === $user->id)

                    <input
                        type="text"
                        value="{{ $user->role }}"
                        class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm font-medium text-slate-500"
                        disabled
                    >

                    <input
                        type="hidden"
                        name="role"
                        value="{{ $user->role }}"
                    >

                    <p class="mt-1 text-xs text-slate-500">
                        You cannot change your own role.
                    </p>

                @else

                    <select
                        name="role"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                        required
                    >
                        @foreach ($roles as $role)
                            <option
                                value="{{ $role }}"
                                @selected(old('role', $user->role) === $role)
                            >
                                {{ $role }}
                            </option>
                        @endforeach
                    </select>

                @endif

                @error('role')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label
                    for="is_active"
                    class="mb-2 block text-sm font-medium text-slate-700"
                >
                    Status
                </label>

                @if (auth()->id() === $user->id)

                    <input
                        type="text"
                        value="Active"
                        class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm font-medium text-slate-500"
                        disabled
                    >

                    <input
                        type="hidden"
                        name="is_active"
                        value="1"
                    >

                    <p class="mt-1 text-xs text-slate-500">
                        You cannot deactivate your own account.
                    </p>

                @else

                    <select
                        id="is_active"
                        name="is_active"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                        required
                    >
                        <option
                            value="1"
                            @selected(old('is_active', $user->is_active) == true)
                        >
                            Active
                        </option>

                        <option
                            value="0"
                            @selected(old('is_active', $user->is_active) == false)
                        >
                            Inactive
                        </option>
                    </select>

                @endif

                @error('is_active')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="flex gap-3 pt-2">
                <button
                    type="submit"
                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Update User
                </button>

                <a
                    href="{{ route('users.index') }}"
                    class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection