@extends('layouts.app')

@php($fullWidth = true)

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header title="Edit Small PM Schedule" description="Action date dan status diisi dari halaman Execute." />

        @if ($errors->any())
            <x-ui.alert tone="danger" title="Belum tersimpan">Periksa isian yang ditandai di bawah.</x-ui.alert>
        @endif

        <form action="{{ route('small-pm.update', $schedule) }}" method="POST" class="card space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            @include('small-pm._form', ['schedule' => $schedule])

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('small-pm.index', ['tab' => $tab]) }}">Batal</x-ui.button>
                <x-ui.button type="submit">Simpan Perubahan</x-ui.button>
            </div>
        </form>
    </div>
@endsection
