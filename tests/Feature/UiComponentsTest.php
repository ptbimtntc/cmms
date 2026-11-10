<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

function renderUi(string $template, array $data = []): string
{
    view()->share('errors', $data['errors'] ?? new ViewErrorBag);

    return Blade::render($template, $data);
}

test('button renders a button by default and a link when href is given', function () {
    $button = renderUi('<x-ui.button data-test="save" wire:click="save">Simpan</x-ui.button>');
    expect($button)
        ->toContain('<button type="button"')
        ->toContain('btn btn-primary min-h-11')
        ->toContain('data-test="save"')
        ->toContain('wire:click="save"')
        ->toContain('Simpan');

    $link = renderUi('<x-ui.button variant="outline" href="/machines">Kembali</x-ui.button>');
    expect($link)
        ->toContain('<a href="/machines"')
        ->toContain('btn-outline')
        ->not->toContain('<button');
});

test('button falls back to primary for an unknown variant', function () {
    expect(renderUi('<x-ui.button variant="rainbow">Ok</x-ui.button>'))->toContain('btn-primary');
});

test('badge keeps only known tones', function () {
    expect(renderUi('<x-ui.badge tone="danger">Terlambat</x-ui.badge>'))->toContain('badge badge-danger');
    expect(renderUi('<x-ui.badge tone="sparkle">Baru</x-ui.badge>'))->toContain('badge badge-neutral');
});

test('page header renders a single h1 and optional actions', function () {
    $html = renderUi(<<<'BLADE'
        <x-ui.page-header title="Jadwal PM" description="Mesin bulan ini.">
            <x-slot:actions><a href="/x">Tambah</a></x-slot:actions>
        </x-ui.page-header>
    BLADE);

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('Jadwal PM')
        ->and($html)->toContain('Mesin bulan ini.')
        ->and($html)->toContain('href="/x"');
});

test('empty state shows cause and action, error tone is announced', function () {
    $empty = renderUi('<x-ui.empty-state title="Belum ada jadwal" description="Buat dari menu PM."><a href="/new">Buat</a></x-ui.empty-state>');
    expect($empty)->toContain('Belum ada jadwal')->toContain('href="/new"')->not->toContain('role="alert"');

    $error = renderUi('<x-ui.empty-state tone="error" title="Gagal memuat data" />');
    expect($error)->toContain('role="alert"');
});

test('field shows a visible label and the validation error for its key', function () {
    $errors = (new ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['name' => 'Nama wajib diisi.']));

    $html = renderUi(
        '<x-ui.field label="Nama mesin" for="name" error="name" hint="Sesuai plat."><input id="name" name="name"></x-ui.field>',
        ['errors' => $errors],
    );

    expect($html)
        ->toMatch('/<label\s+for="name"/')
        ->toContain('Nama mesin')
        ->toContain('Nama wajib diisi.')
        ->not->toContain('Sesuai plat.');
});

test('alert uses role alert only for danger', function () {
    expect(renderUi('<x-ui.alert tone="danger">Gagal</x-ui.alert>'))->toContain('role="alert"');
    expect(renderUi('<x-ui.alert tone="success">Tersimpan</x-ui.alert>'))->toContain('role="status"');
});

test('progress clamps the value and exposes it to assistive tech', function () {
    $html = renderUi('<x-ui.progress :value="15" :max="10" label="PM selesai" />');
    expect($html)->toContain('aria-valuenow="100"')->toContain('width: 100%');

    $half = renderUi('<x-ui.progress :value="3" :max="6" label="PM selesai" />');
    expect($half)->toContain('aria-valuenow="50"')->toContain('50%');

    $zeroMax = renderUi('<x-ui.progress :value="0" :max="0" label="PM selesai" />');
    expect($zeroMax)->toContain('aria-valuenow="0"');
});

test('table wraps content in a scroll container', function () {
    expect(renderUi('<x-ui.table caption="Mesin"><tbody><tr><td>A</td></tr></tbody></x-ui.table>'))
        ->toContain('overflow-x-auto')
        ->toContain('<caption')
        ->toContain('class="data-table');
});

test('card renders title, actions and body', function () {
    $html = renderUi(<<<'BLADE'
        <x-ui.card title="Hari ini">
            <x-slot:actions><a href="/all">Semua</a></x-slot:actions>
            Isi kartu
        </x-ui.card>
    BLADE);

    expect($html)
        ->toContain('<h2')
        ->toContain('Hari ini')
        ->toMatch('/gap-2">\s*<a href="\/all">Semua<\/a>\s*<\/div>/')
        ->toContain('Isi kartu')
        ->not->toContain('@endslot');
});

test('brand logo is labelled, or hidden when decorative, with unique gradient ids', function () {
    $labelled = renderUi('<x-brand-logo />');
    expect($labelled)->toContain('role="img"')->toContain('aria-label="FreeDOMS"');

    $hidden = renderUi('<x-brand-logo aria-hidden="true" />');
    expect($hidden)->not->toContain('role="img"');

    preg_match_all('/id="(bl[0-9a-f]+)-people"/', renderUi('<x-brand-logo /><x-brand-logo />'), $ids);
    expect(array_unique($ids[1]))->toHaveCount(2);

    expect(renderUi('<x-brand-logo mono :framed="false" />'))->not->toContain('linearGradient');
});
