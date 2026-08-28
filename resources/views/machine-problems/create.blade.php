@extends('layouts.app')

@section('content')
    <div class="bg-gray-100 p-4 md:p-6">

        {{-- PAGE HEADER --}}
        <div class="mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                Add Machine Big Problem
            </h1>
            <p class="text-sm text-gray-500">
                Tambahkan problem berdasarkan tipe mesin
            </p>
        </div>

        {{-- FORM CARD --}}
        <div class="w-full bg-white shadow-md rounded-xl p-4 md:p-6">

            <form action="{{ route('machine-problems.store') }}" method="POST" class="space-y-4">

                @csrf

                {{-- MACHINE TYPE --}}
                <div>
                    <label class="block mb-2">Machine Type</label>

                    <select id="machine_type" name="machine_type" class="w-full border p-3 rounded">

                        <option value="">Select Type</option>

                        @foreach ($machines as $machine)
                            <option value="{{ $machine->machine_type }}">
                                {{ $machine->machine_type }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @error('machine_type')
                    <p class="text-red-500 text-sm mt-1">
                        {{ $message }}
                    </p>
                @enderror

                @error('problems')
                    <p class="text-red-500 text-sm mt-1">
                        {{ $message }}
                    </p>
                @enderror

                {{-- BIG PROBLEMS (MULTIPLE INPUT) --}}
                <div>
                    <label class="block mb-2">Big Problems</label>

                    <div id="problem-wrapper" class="space-y-2">

                        <input type="text" name="problems[]" class="problem-input w-full border p-2 rounded mt-2"
                            placeholder="Enter problem">

                        <small class="duplicate-msg text-red-500 hidden">
                            Already exists for this machine type
                        </small>

                    </div>

                    <button type="button" onclick="addProblem()" class="mt-2 bg-gray-200 px-3 py-1 rounded">
                        + Add More
                    </button>
                </div>

                {{-- BUTTON --}}
                <button class="bg-blue-600 text-white px-4 py-2 rounded">
                    Save
                </button>

            </form>

        </div>

    </div>


    {{-- SCRIPTS ADD PROBLEM --}}
    <script>
        function addProblem() {

            let wrapper = document.getElementById('problem-wrapper');

            let input = document.createElement('input');

            input.type = 'text';
            input.name = 'problems[]';
            input.placeholder = 'e.g Oil Leak';
            input.className = 'w-full border p-2 rounded mt-2';

            wrapper.appendChild(input);
        }
    </script>

    {{-- FORM VALIDATION --}}
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {

            let machineType = document.querySelector('[name="machine_type"]').value;

            if (!machineType) {
                e.preventDefault();
                alert('Please select Machine Type first');
                return;
            }

        });
    </script>
@endsection
