{{-- Session success/error and the full validation error list, shown above a master form. --}}
@if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
@endif
@if (session('error'))
    <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
@endif
@if ($errors->any())
    <x-ui.alert tone="danger">
        <ul class="list-disc space-y-0.5 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
