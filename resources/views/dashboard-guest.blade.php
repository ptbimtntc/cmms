@extends('layouts.guest')

@section('content')

<x-landing.navbar />

<div class="bg-gradient-to-b from-slate-50 to-white min-h-screen py-16">
    
    <div class="max-w-7xl mx-auto px-6">
        
        <div class="mb-12">
            <h1 class="text-4xl font-extrabold text-slate-900 mb-4">Dashboard Preview</h1>
            <p class="text-lg text-slate-600">Lihat informasi statistik dan aktivitas maintenance secara real-time</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">

            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-slate-600 font-semibold text-sm">PM This Month</h3>
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900 mb-2">--</p>
                <p class="text-xs text-slate-500">Jadwal PM bulan ini</p>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-slate-600 font-semibold text-sm">Overdue PM</h3>
                    <div class="bg-red-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900 mb-2">--</p>
                <p class="text-xs text-slate-500">PM yang terlewat</p>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-slate-600 font-semibold text-sm">Problems</h3>
                    <div class="bg-yellow-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900 mb-2">--</p>
                <p class="text-xs text-slate-500">Masalah mesin</p>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-slate-600 font-semibold text-sm">Sparepart Usage</h3>
                    <div class="bg-green-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900 mb-2">--</p>
                <p class="text-xs text-slate-500">Penggunaan sparepart</p>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                <h3 class="text-lg font-bold text-slate-900 mb-6">Panduan Penggunaan</h3>
                <div class="space-y-4">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-blue-100">
                                <span class="text-blue-600 font-bold">1</span>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-slate-900">Scan QR Code</h4>
                            <p class="text-sm text-slate-600 mt-1">Gunakan fitur scan QR di landing page untuk melihat riwayat mesin secara real-time</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-blue-100">
                                <span class="text-blue-600 font-bold">2</span>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-slate-900">Lihat Statistik</h4>
                            <p class="text-sm text-slate-600 mt-1">Pantau PM bulan ini, PM yang terlewat, dan masalah mesin dalam satu dashboard</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-blue-100">
                                <span class="text-blue-600 font-bold">3</span>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-slate-900">Login untuk Akses Penuh</h4>
                            <p class="text-sm text-slate-600 mt-1">Dapatkan akses penuh ke semua fitur maintenance dengan login ke sistem</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-600 to-blue-700 p-6 rounded-xl shadow-sm border border-blue-200 text-white">
                <h3 class="text-lg font-bold mb-4">Mulai Sekarang</h3>
                <p class="text-blue-100 text-sm mb-6">Akses fitur lengkap dan kelola preventive maintenance dengan lebih efisien</p>
                
                <div class="space-y-3">
                    <a href="{{ route('qr.scan') }}" class="flex items-center gap-2 w-full justify-center px-4 py-3 rounded-lg bg-white text-blue-600 font-semibold hover:bg-blue-50 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7V4h3M20 7V4h-3M4 17v3h3M20 17v3h-3M8 8h2v2H8zM14 8h2v2h-2zM8 14h2v2H8zM14 14h2v2h-2z" />
                        </svg>
                        Scan Mesin
                    </a>
                    
                    @guest
                        <a href="{{ route('login') }}" class="flex items-center gap-2 w-full justify-center px-4 py-3 rounded-lg border-2 border-white text-white font-semibold hover:bg-blue-600 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                            </svg>
                            Login
                        </a>
                    @endguest
                </div>
            </div>

        </div>

    </div>

</div>

<x-landing.footer />

@endsection
