@extends('layouts.app')

@section('title', 'Tambah Row')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
    @endphp

    <x-ui.form-page title="Tambah Row" description="Setelah disimpan, Anda diarahkan ke halaman Edit untuk mencentang mesin di row ini."
        :action="route('small-pm-rows.store')" :back="route('small-pm-rows.index')" back-label="Kembali ke Row Small PM" submit="Simpan" cancel-label="Batal">
        @include('small-pm-rows._fields', ['row' => null])
    </x-ui.form-page>
@endsection
