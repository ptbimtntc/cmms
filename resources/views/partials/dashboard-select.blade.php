{{--
    Labelled filter dropdown for the dashboard. Changing it reloads the page
    with ?{param}={value} through dashFilter() in dashboard.blade.php.
    Params: id, param, label, options (value => text), selected, allLabel (optional "all" option).
--}}
<div class="flex items-center gap-2">
    <label for="{{ $id }}" class="text-xs font-medium text-text-muted">{{ $label }}</label>
    <select id="{{ $id }}" onchange="dashFilter('{{ $param }}', this.value)"
        class="min-h-11 rounded-control border border-border-strong bg-surface px-2 text-sm text-text lg:min-h-9">
        @isset($allLabel)
            <option value="" {{ $selected ? '' : 'selected' }}>{{ $allLabel }}</option>
        @endisset
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" {{ $value == $selected ? 'selected' : '' }}>{{ $text }}</option>
        @endforeach
    </select>
</div>
