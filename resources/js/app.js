// Admin: open/close the sidebar on small screens
document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById('admin-sidebar')?.classList.toggle('hidden');
    });
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
