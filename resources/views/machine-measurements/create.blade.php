@extends('layouts.app')

@section('content')
    <div class="p-6 bg-gray-50">

        {{-- HEADER --}}
        <div class="mb-6">

            <h1 class="text-2xl font-bold text-gray-800">
                Add Measurement Master
            </h1>

            <p class="text-sm text-gray-500">
                Tambahkan measurement berdasarkan tipe mesin
            </p>

        </div>

        <div class="bg-white rounded-lg shadow p-6 max-w-4xl">

            <form action="{{ route('machine-measurements.store') }}" method="POST">

                @csrf

                {{-- MACHINE TYPE --}}
                <div class="mb-4">

                    <label class="block mb-2">
                        Machine Type
                    </label>

                    <select name="machine_type" class="w-full border p-3 rounded">

                        <option value="">
                            Select Machine Type
                        </option>

                        @foreach ($machines as $machine)
                            <option value="{{ $machine->machine_type }}">
                                {{ $machine->machine_type }}
                            </option>
                        @endforeach

                    </select>

                    @error('machine_type')
                        <p class="text-red-500 text-sm mt-1">
                            {{ $message }}
                        </p>
                    @enderror

                </div>

                {{-- ITEMS --}}
                <div>

                    <label class="block mb-2">
                        Measurement Items
                    </label>

                    <div id="measurement-wrapper">

                        <div class="grid md:grid-cols-2 gap-3 mb-3">

                            <input type="text" name="measurements[]" placeholder="Measurement Item"
                                class="border p-3 rounded">

                            <input type="text" name="units[]" placeholder="Unit (mm, °C, bar, mm/s)"
                                class="border p-3 rounded">

                        </div>

                    </div>

                    <button type="button" onclick="addMeasurement()"
                        class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">

                        + Add More

                    </button>

                </div>

                {{-- BUTTON --}}
                <div class="mt-6">

                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">

                        Save

                    </button>

                </div>

            </form>

        </div>

    </div>

    <script>
        function addMeasurement() {

            let wrapper = document.getElementById('measurement-wrapper');

            let html = `
        <div class="grid md:grid-cols-2 gap-3 mb-3">

            <input type="text"
                name="measurements[]"
                placeholder="Measurement Item"
                class="border p-3 rounded">

            <input type="text"
                name="units[]"
                placeholder="Unit (mm, °C, bar, mm/s)"
                class="border p-3 rounded">

        </div>
    `;

            wrapper.insertAdjacentHTML('beforeend', html);
        }
    </script>
@endsection
