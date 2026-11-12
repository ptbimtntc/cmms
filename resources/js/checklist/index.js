let currentRemarkIndex = null;

function initChecklistRemarks() {
    const modal = document.getElementById('remarkModal');
    const textarea = document.getElementById('remarkTextarea');
    const itemLabel = document.getElementById('remarkItem');

    if (!modal || !textarea || !itemLabel) {
        return;
    }

    document.querySelectorAll('.remark-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            currentRemarkIndex = this.dataset.index;
            itemLabel.innerText = this.dataset.item;
            textarea.value = document.getElementById('remark-' + currentRemarkIndex).value;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    });

    document.getElementById('cancelRemark')?.addEventListener('click', function () {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });

    document.getElementById('saveRemark')?.addEventListener('click', function () {
        const input = document.getElementById('remark-' + currentRemarkIndex);

        if (!input) {
            return;
        }

        input.value = textarea.value;

        const button = document.querySelector(`.remark-btn[data-index="${currentRemarkIndex}"]`);

        if (textarea.value.trim() !== '') {
            button?.classList.remove('text-gray-500');
            button?.classList.add('text-green-600');
            button.textContent = 'Edit note';
        } else {
            button?.classList.remove('text-green-600');
            button?.classList.add('text-gray-500');
            button.textContent = 'Add note';
        }

        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChecklistRemarks);
} else {
    initChecklistRemarks();
}
