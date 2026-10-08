document.querySelectorAll('.planner-toast').forEach((toast) => {
    bootstrap.Toast.getOrCreateInstance(toast).show();
});

document.querySelectorAll('.completion-toggle').forEach((checkbox) => {
    checkbox.addEventListener('change', () => checkbox.form.requestSubmit());
});

const deleteModal = document.getElementById('delete-confirm');
const deleteSubmit = document.getElementById('delete-submit');

deleteModal.addEventListener('show.bs.modal', (event) => {
    const form = document.getElementById(event.relatedTarget?.dataset.deleteForm);
    if (!form?.matches('.delete-task')) {
        event.preventDefault();
        return;
    }
    deleteSubmit.setAttribute('form', form.id);
    deleteSubmit.disabled = false;
});

deleteModal.addEventListener('shown.bs.modal', () => {
    document.getElementById('delete-cancel').focus();
});

deleteModal.addEventListener('hidden.bs.modal', () => {
    deleteSubmit.removeAttribute('form');
    deleteSubmit.disabled = true;
});

document.querySelectorAll('.task-form').forEach((form) => {
    form.querySelector('.scheduled-date').addEventListener('change', (event) => {
        form.querySelector('[name="due_date"]').min = event.target.value;
    });
});

document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('shown.bs.modal', () => {
        (modal.querySelector('.is-invalid') || modal.querySelector('[name="title"]'))?.focus();
    });
    if (modal.dataset.reopen === 'true') bootstrap.Modal.getOrCreateInstance(modal).show();
});
