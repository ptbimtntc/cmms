@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-3xl';
        $isSelf = auth()->id() === $user->id;
    @endphp

    <x-ui.form-page title="Edit User" description="Update user account, role, or password."
        :action="route('users.update', $user)" method="PUT" :back="route('users.index')" back-label="Kembali ke User Management" submit="Update User"
        enctype="multipart/form-data" novalidate>
        <input type="hidden" name="return_page" value="{{ request('page') }}">

            <div class="grid gap-1.5"
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
                            // the accept filter with zero intrinsic dimensions -
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
            <span class="form-label mb-0" id="avatar-label">Profile Photo</span>
            <div class="flex items-center gap-4">
                <img x-show="previewUrl" :src="previewUrl" alt="{{ $user->name }}" class="h-16 w-16 shrink-0 rounded-full object-cover">
                <div x-show="! previewUrl" class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary-light text-sm font-semibold text-primary-hover">{{ $user->initials() }}</div>
                <div class="min-w-0">
                    <input type="file" name="avatar" accept="image/*" x-ref="avatarInput" @change="onFileChosen($event)" aria-labelledby="avatar-label" aria-describedby="avatar-hint"
                        class="block w-full text-sm text-text-muted file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-border-strong file:bg-surface file:px-3 file:text-sm file:font-semibold file:text-text hover:file:bg-surface-muted lg:file:min-h-9">
                    <p id="avatar-hint" class="form-hint mt-1">JPG or PNG, max 2MB. Leave blank to keep current photo.</p>
                    @if ($user->avatar_path)
                        <label class="mt-1 inline-flex min-h-11 items-center gap-2 text-sm text-text-muted lg:min-h-9">
                            <input type="checkbox" name="remove_avatar" value="1" class="h-4 w-4 rounded border-border-strong text-primary">
                            Remove current photo
                        </label>
                    @endif
                </div>
            </div>
            @error('avatar')
                <p class="form-error">{{ $message }}</p>
            @enderror

            {{-- Crop / position the photo --}}
            <div x-show="cropOpen" x-cloak style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="crop-title">
                <div class="w-full max-w-md space-y-4 rounded-card border border-border bg-surface p-5 shadow-popover sm:p-6" @click.outside="cancelCrop()">
                    <div>
                        <h2 id="crop-title" class="text-lg font-semibold text-text">Adjust Photo</h2>
                        <p class="text-sm text-text-muted">Drag to reposition, use the slider to zoom.</p>
                    </div>
                    <div class="flex justify-center">
                        <div class="relative h-72 w-72 max-w-full touch-none select-none overflow-hidden rounded-full border-4 border-border bg-surface-muted"
                            :class="dragging ? 'cursor-grabbing' : 'cursor-grab'"
                            x-on:mousedown="startDrag($event)"
                            x-on:mousemove.window="onDrag($event)"
                            x-on:mouseup.window="endDrag()"
                            x-on:touchstart="startDrag($event)"
                            x-on:touchmove="onDrag($event)"
                            x-on:touchend="endDrag()">
                            <canvas x-ref="canvas" width="288" height="288" class="h-72 w-72"></canvas>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 px-4">
                        <span class="text-sm text-text-muted" aria-hidden="true">&minus;</span>
                        <input type="range" x-model.number="scale" :min="minScale" :max="maxScale" step="0.001" x-on:input="onZoom()" aria-label="Zoom" class="w-full accent-[var(--color-primary)]">
                        <span class="text-sm text-text-muted" aria-hidden="true">+</span>
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="cancelCrop()" class="btn btn-ghost min-h-11">Cancel</button>
                        <button type="button" @click="applyCrop()" class="btn btn-primary min-h-11">Save Photo</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.field label="Name" for="ue-name" error="name">
                <input id="ue-name" type="text" name="name" value="{{ old('name', $user->name) }}" required @class(['input', 'is-invalid' => $errors->has('name')])>
            </x-ui.field>
            <x-ui.field label="Email" for="ue-email" error="email">
                <input id="ue-email" type="email" name="email" value="{{ old('email', $user->email) }}" required @class(['input', 'is-invalid' => $errors->has('email')])>
            </x-ui.field>
        </div>

        <x-ui.field label="New Password" for="ue-password" error="password" hint="Leave blank if you don't want to change the password.">
            <input id="ue-password" type="password" name="password" placeholder="Leave blank to keep current password" autocomplete="new-password" @class(['input', 'is-invalid' => $errors->has('password')])>
        </x-ui.field>

        @if ($isSelf)
            <x-ui.field label="Role" for="ue-role-ro" error="role" hint="You cannot change your own role.">
                <input id="ue-role-ro" type="text" value="{{ $user->role }}" class="input" disabled>
                <input type="hidden" name="role" value="{{ $user->role }}">
            </x-ui.field>
        @else
            <x-ui.field label="Role" for="role" error="role">
                <select id="role" name="role" onchange="toggleAreaField(this.value)" required @class(['select', 'is-invalid' => $errors->has('role')])>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        @endif

        {{-- Area: only meaningful for KOORDINATOR/PIC; ADMIN sees every area, GUEST has none,
             SUPERVISOR uses the multi-select below. Options come from the Area master list. --}}
        <x-ui.field label="Area" for="area_id" error="area_id" id="areaField">
            <select id="area_id" name="area_id" @class(['select', 'is-invalid' => $errors->has('area_id')])>
                <option value="">Select Area</option>
                @foreach ($areas as $area)
                    <option value="{{ $area->id }}" @selected((string) old('area_id', $user->area_id) === (string) $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>
        </x-ui.field>

        {{-- Areas: SUPERVISOR only. None checked = all areas, otherwise limited to the checked areas. --}}
        <fieldset id="supervisorAreasField" style="display: none" class="grid gap-1.5">
            <legend class="form-label mb-1.5">Areas <span class="font-normal text-text-muted">(leave empty for all areas)</span></legend>
            <div class="flex flex-wrap gap-x-5 gap-y-1 rounded-control border border-border bg-surface-sunken px-3 py-1">
                @foreach ($areas as $area)
                    <label class="inline-flex min-h-11 items-center gap-2 text-sm text-text">
                        <input type="checkbox" name="area_ids[]" value="{{ $area->id }}" class="h-4 w-4 rounded border-border-strong text-primary"
                            @checked(in_array($area->id, array_map('intval', (array) old('area_ids', $user->areas->pluck('id')->all()))))>
                        {{ $area->name }}
                    </label>
                @endforeach
            </div>
            @error('area_ids.*')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </fieldset>

        @if ($isSelf)
            <x-ui.field label="Status" for="ue-active-ro" error="is_active" hint="You cannot deactivate your own account.">
                <input id="ue-active-ro" type="text" value="Active" class="input" disabled>
                <input type="hidden" name="is_active" value="1">
            </x-ui.field>
        @else
            <x-ui.field label="Status" for="is_active" error="is_active">
                <select id="is_active" name="is_active" required @class(['select', 'is-invalid' => $errors->has('is_active')])>
                    <option value="1" @selected(old('is_active', $user->is_active) == true)>Active</option>
                    <option value="0" @selected(old('is_active', $user->is_active) == false)>Inactive</option>
                </select>
            </x-ui.field>
        @endif
    </x-ui.form-page>

    <script>
        function toggleAreaField(role) {
            const field = document.getElementById('areaField');
            const needsArea = role === 'KOORDINATOR' || role === 'PIC';
            field.style.display = needsArea ? '' : 'none';
            document.getElementById('area_id').required = needsArea;
            document.getElementById('supervisorAreasField').style.display = role === 'SUPERVISOR' ? '' : 'none';
        }
        toggleAreaField(document.getElementById('role')?.value ?? '{{ $user->role }}');
    </script>
@endsection
