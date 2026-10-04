// Admin: open/close the sidebar on small screens
document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById('admin-sidebar')?.classList.toggle('hidden');
    });
});
