@extends('layouts.app')

@section('title')Backup & Data Protection@endsection

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-5xl';
    @endphp

    <div class="space-y-5">
        <header>
            <h1 class="font-signage text-2xl font-semibold leading-tight text-text sm:text-3xl">Backup &amp; Data Protection</h1>
            <p class="mt-1 max-w-prose text-sm text-text-muted sm:text-base">
                Create and download a backup of the database and of the source code running on the server. ADMIN only.
                Backup files hold sensitive data and configuration (including <code>.env</code>); keep them somewhere safe and restricted.
            </p>
        </header>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif

        @include('backup.partials.reminder')

        @include('backup.partials.automation')

        @include('backup.partials.section', [
            'type' => \App\Models\BackupRun::TYPE_DATABASE,
            'title' => 'A. Backup Database',
            'description' => 'Holds the table structure, indexes, constraints, and all data from every application table, as .sql.gz.',
            'action' => route('backup.database.store'),
            'buttonLabel' => 'Create Database Backup',
            'section' => $sections[\App\Models\BackupRun::TYPE_DATABASE],
            'notes' => 'Taken from a consistent read transaction. The session, cache, and queue job tables are included because they live in the database.',
        ])

        @include('backup.partials.section', [
            'type' => \App\Models\BackupRun::TYPE_SOURCE,
            'title' => 'B. Backup Source Code Production',
            'description' => 'A .zip archive of the code running on the server, including .env, vendor, public/build, and hidden files.',
            'action' => route('backup.source.store'),
            'buttonLabel' => 'Create Source Code Backup',
            'section' => $sections[\App\Models\BackupRun::TYPE_SOURCE],
            'notes' => 'Not included: the contents of storage/ (uploads such as avatars, logs, cache, sessions, and older backups), bootstrap/cache, node_modules, and symbolic links. The database and the storage files are backed up separately.',
        ])
    </div>
@endsection
