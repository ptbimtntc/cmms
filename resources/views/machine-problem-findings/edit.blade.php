@extends('layouts.app')

@section('content')

    <div class="container mx-auto px-6 py-6">

        <div class="bg-white shadow rounded-lg p-6 max-w-2xl mx-auto">

            <h1 class="text-2xl font-bold mb-6">
                Edit Machine Problem Finding
            </h1>

            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">

                    <ul class="list-disc list-inside">

                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach

                    </ul>

                </div>
            @endif

            <form action="{{ route('machine-problem-findings.update', $machineProblemFinding->id) }}" method="POST">

                @csrf
                @method('PUT')
                <input type="hidden" name="return_page" value="{{ request('page') }}">

                {{-- CATEGORY --}}
                <div class="mb-4">

                    <label class="block font-semibold mb-2">
                        Category
                    </label>

                    <select name="category" class="border rounded w-full px-3 py-2" required>

                        @foreach ($categories as $category)
                            <option value="{{ $category }}"
                                {{ old('category', $machineProblemFinding->category) == $category ? 'selected' : '' }}>

                                {{ $category }}

                            </option>
                        @endforeach

                    </select>

                </div>

                {{-- FINDING --}}
                <div class="mb-4">

                    <label class="block font-semibold mb-2">
                        Finding
                    </label>

                    <input type="text" name="finding" value="{{ old('finding', $machineProblemFinding->finding) }}"
                        class="border rounded w-full px-3 py-2" required>

                </div>

                                <div class="flex justify-between">

                    <a href="{{ route('machine-problem-findings.index') }}"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded">

                        Back

                    </a>

                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">

                        Update

                    </button>

                </div>

            </form>

        </div>

    </div>

@endsection
