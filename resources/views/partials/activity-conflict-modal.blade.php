{{--
    Active Activity conflict confirmation.

    Rendered whenever a Start Activity endpoint bounced back because the PIC
    already has an active activity (session key `activity_conflict`, flashed
    by Concerns\HandlesActivityConflict). END & START re-POSTs the same
    started_at with confirm_end_start=1.
--}}
@if (session('activity_conflict'))
    @php($conflict = session('activity_conflict'))
    <div id="activity-conflict-modal"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-800">Active Activity Conflict</h3>

            <dl class="mt-4 space-y-1.5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">PIC</dt>
                    <dd class="text-slate-800">{{ $conflict['pic'] }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Current Activity</dt>
                    <dd class="text-slate-800">{{ $conflict['current'] }}</dd>
                </div>
                @if (! empty($conflict['machine']))
                    <div class="flex justify-between gap-4">
                        <dt class="font-medium text-slate-500">Machine</dt>
                        <dd class="text-slate-800">{{ $conflict['machine'] }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4">
                    <dt class="font-medium text-slate-500">Start Time</dt>
                    <dd class="text-slate-800">{{ $conflict['started_at'] }}</dd>
                </div>
            </dl>

            <p class="mt-4 text-sm text-slate-700">
                You already have an active activity. Do you want to end the current activity and start this one?
            </p>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="activity-conflict-cancel"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                    CANCEL
                </button>
                <form method="POST" action="{{ $conflict['action'] }}">
                    @csrf
                    <input type="hidden" name="started_at" value="{{ $conflict['resume_started_at'] }}">
                    <input type="hidden" name="confirm_end_start" value="1">
                    {{-- Source-specific fields to carry through END & START (e.g. manual activity name). --}}
                    @foreach (($conflict['extra_fields'] ?? []) as $field => $value)
                        <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        END &amp; START
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('activity-conflict-modal');
            if (! modal) return;

            function close () { modal.remove(); }

            document.getElementById('activity-conflict-cancel').addEventListener('click', close);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) close();
            });
        })();
    </script>
@endif
