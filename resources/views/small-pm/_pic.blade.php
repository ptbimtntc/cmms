{{--
    PIC cell: dropdown for admin/koordinator (saved on change by the script in index), plain
    name otherwise. The status line under the dropdown says whether the save went through.
--}}
@php($areaId = $s->row?->area_id)
@if ($canManage && $areaId)
    <div class="grid gap-1">
        <label for="pic-{{ $s->id }}-{{ $suffix }}" class="sr-only">PIC {{ $s->row?->name }}</label>
        <select id="pic-{{ $s->id }}-{{ $suffix }}" data-url="{{ route('small-pm.assign-pic', $s) }}" data-id="{{ $s->id }}"
            class="assign-small-pm-pic select w-full min-w-44 lg:min-h-9">
            <option value="">Belum ada (siapa saja)</option>
            @foreach ($picsByArea[$areaId] ?? [] as $p)
                <option value="{{ $p->id }}" @selected($s->pic_user_id === (int) $p->id)>{{ \Illuminate\Support\Str::title(strtolower($p->name)) }}</option>
            @endforeach
        </select>
        <span class="assign-small-pm-pic-status min-h-4 text-xs text-text-muted" data-for="{{ $s->id }}" aria-live="polite"></span>
    </div>
@else
    {{ $s->pic?->name ?? 'Belum ada' }}
@endif
