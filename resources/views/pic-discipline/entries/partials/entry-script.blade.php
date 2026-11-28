{{--
    Saves a completed date as soon as a .pic-discipline-entry date input
    changes, and updates its .pic-discipline-entry-badge with the server's
    recomputed status — same delegated change-listener shape as
    partials/assign-pic-script. Param: baseUrl (e.g. '/pic-discipline/entries').
--}}
<script>
    document.addEventListener('change', function (e) {
        const input = e.target.closest('.pic-discipline-entry');
        if (!input) return;

        const id = input.dataset.id;
        const badge = document.querySelector(`.pic-discipline-entry-badge[data-for="${id}"]`);
        const toneClass = { PENDING: 'badge-neutral', ACHIEVED: 'badge-success', BREACH: 'badge-danger' };
        const labelText = { PENDING: 'Pending', ACHIEVED: 'A', BREACH: 'B' };

        fetch(@js(rtrim($baseUrl, '/')) + `/${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ completed_date: input.value || null })
        })
            .then((r) => r.ok ? r.json() : Promise.reject())
            .then((data) => {
                if (!badge) return;
                badge.textContent = labelText[data.status] ?? data.status;
                badge.className = badge.className.replace(/badge-\S+/g, '').trim();
                badge.classList.add(toneClass[data.status] || 'badge-neutral');
            })
            .catch(() => {
                if (badge) badge.textContent = '!';
            });
    });
</script>
