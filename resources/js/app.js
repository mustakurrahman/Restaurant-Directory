// Menu buttons (admin sidebar, public phone menu): <button data-toggle="element-id"> shows/hides that element
document.querySelectorAll('[data-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.toggle);

        if (! target) {
            return;
        }

        const isHidden = target.classList.toggle('hidden');
        button.setAttribute('aria-expanded', String(! isHidden));
    });
});

// A menu with data-autosubmit sends its form as soon as the choice changes (the "Sort by" menu)
document.querySelectorAll('[data-autosubmit]').forEach((select) => {
    select.addEventListener('change', () => select.form?.submit());
});

// Admin opening hours: grey out the times of closed days, and copy Monday to every day
document.querySelectorAll('[data-hours-form]').forEach((form) => {
    const rows = [...form.querySelectorAll('[data-hours-row]')];
    const field = (row, name) => row.querySelector(`[data-field="${name}"]`);

    // A closed day sends no times (the server ignores them anyway)
    const refresh = (row) => {
        const closed = field(row, 'is_closed').checked;
        field(row, 'opens_at').disabled = closed;
        field(row, 'closes_at').disabled = closed;
    };

    rows.forEach((row) => {
        refresh(row);
        field(row, 'is_closed').addEventListener('change', () => refresh(row));
    });

    form.querySelector('[data-copy-hours]')?.addEventListener('click', () => {
        const [monday, ...others] = rows;

        others.forEach((row) => {
            field(row, 'is_closed').checked = field(monday, 'is_closed').checked;
            field(row, 'opens_at').value = field(monday, 'opens_at').value;
            field(row, 'closes_at').value = field(monday, 'closes_at').value;
            refresh(row);
        });
    });
});
