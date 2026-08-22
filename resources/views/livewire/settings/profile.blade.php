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
                <div class="flex items-center gap-2">
                    <input type="file" wire:model="photo" accept="image/*" />
                </div>

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

        {{-- Modal crop/posisi foto --}}
        <div
            x-data="{
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
                init() {
                    Livewire.on('photo-selected', ({ url }) => this.openEditor(url));
                },
                openEditor(url) {
                    const image = new Image();
                    image.onload = () => {
                        this.img = image;
                        this.minScale = this.editorSize / Math.min(image.width, image.height);
                        this.maxScale = this.minScale * 3;
                        this.scale = this.minScale;
                        this.offsetX = 0;
                        this.offsetY = 0;
                        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'crop-photo' }));
                        this.$nextTick(() => this.draw());
                    };
                    image.src = url;
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
                save() {
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
                    $wire.saveCroppedPhoto(out.toDataURL('image/jpeg', 0.9));
                },
            }"
        >
            <flux:modal name="crop-photo" class="max-w-md" @close="cancelPhotoCrop">
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Adjust Photo') }}</flux:heading>
                        <flux:subheading>{{ __('Drag to reposition, use the slider to zoom.') }}</flux:subheading>
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
                        <span class="text-sm text-slate-400">−</span>
                        <input
                            type="range"
                            x-model.number="scale"
                            :min="minScale"
                            :max="maxScale"
                            step="0.001"
                            x-on:input="onZoom()"
                            class="w-full accent-blue-600"
                        />
                        <span class="text-sm text-slate-400">+</span>
                    </div>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" x-on:click="save()">{{ __('Save Photo') }}</flux:button>
                    </div>
                </div>
            </flux:modal>
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
