{{--
    Saves a PIC as soon as an .assign-pic dropdown changes, and says whether it worked in the
    .assign-pic-status element(s) of the same record. Delegated, so rows appended later by
    infinite scroll work too. Shared by the PM Schedule and Greasing lists.
    Param: baseUrl (e.g. url('pm-schedules')); the request goes to {baseUrl}/{id}/assign-pic.
--}}
<script>
    document.addEventListener('change', function (e) {
        const select = e.target.closest('.assign-pic');
        if (!select) return;

        const id = select.dataset.id;
        const statuses = document.querySelectorAll(`.assign-pic-status[data-for="${id}"]`);
        const say = (text, tone) => statuses.forEach((s) => {
            s.textContent = text;
            s.classList.toggle('text-danger', tone === 'error');
            s.classList.toggle('text-success', tone === 'ok');
        });

        // Keep the desktop and phone dropdowns of the same record in step.
        document.querySelectorAll(`.assign-pic[data-id="${id}"]`).forEach((other) => {
            if (other !== select) other.value = select.value;
        });

        say('Saving...');
        fetch(@js(rtrim($baseUrl, '/')) + `/${id}/assign-pic`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ pic: select.value })
        })
            .then((r) => say(r.ok ? 'Saved' : 'Not saved, try again', r.ok ? 'ok' : 'error'))
            .catch(() => say('Not saved, check the connection', 'error'));
    });
</script>
