{{--
    Visible label + control + hint + validation error.
    <x-ui.field label="Nama mesin" for="name" error="name" hint="Sesuai plat nama mesin.">
        <input id="name" name="name" class="input" value="{{ old('name') }}">
    </x-ui.field>
    error: the validation key to read from $errors.
--}}
@props([
    'label',
    'for' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php $message = $error ? $errors->first($error) : null; @endphp

<div {{ $attributes->merge(['class' => 'grid gap-1.5']) }}>
    <label @if ($for) for="{{ $for }}" @endif class="form-label mb-0">
        {{ $label }}
        {{-- Visual marker only; the control's own `required` attribute is what assistive tech announces. --}}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
        @endif
    </label>

    {{ $slot }}

    @if ($message)
        <p class="form-error" @if ($for) id="{{ $for }}-error" @endif>{{ $message }}</p>
    @elseif ($hint)
        <p class="form-hint" @if ($for) id="{{ $for }}-hint" @endif>{{ $hint }}</p>
    @endif
</div>
