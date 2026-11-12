@extends('layouts.app')

@section('title', 'Oil Change Requirements')

@section('content')
    @php($fullWidth = true)

    <div class="space-y-5">
        <x-ui.page-header title="Oil Change Requirements" description="Which machine types require an oil change during PM. Adding a machine type here makes its Oil Change field show up on Fill PM immediately, with no code change needed.">
            <x-slot:actions>
                <x-ui.button href="{{ route('machine-maintenance-requirements.create') }}">Add Machine Type</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        @if ($requirements->isEmpty())
            <x-ui.empty-state title="No machine types configured" description="Add a machine type to show the Oil Change field on its Fill PM form." />
        @else
            <div class="card overflow-hidden">
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Machine Type</th>
                                <th scope="col">Requires Oil Change</th>
                                <th scope="col"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requirements as $requirement)
                                <tr>
                                    <td class="font-semibold text-text">{{ $requirement->machine_type }}</td>
                                    <td><x-ui.badge :tone="$requirement->requires_oil_change ? 'success' : 'neutral'">{{ $requirement->requires_oil_change ? 'YES' : 'NO' }}</x-ui.badge></td>
                                    <td>@include('partials.master.row-actions', [
                                        'edit' => route('machine-maintenance-requirements.edit', $requirement),
                                        'delete' => route('machine-maintenance-requirements.destroy', $requirement),
                                        'confirm' => 'Remove '.$requirement->machine_type.'? Its Oil Change field will stop showing on Fill PM.',
                                    ])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
