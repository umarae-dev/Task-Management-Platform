
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('themeIcon');
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    icon.innerHTML = newTheme === 'dark'
        ? '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/>'
        : '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
}
(function () {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    const icon = document.getElementById('themeIcon');
    if (icon) {
        icon.innerHTML = savedTheme === 'dark'
            ? '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/>'
            : '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
    }
})();
function toggleMobileMenu() {
    const nav = document.querySelector('.bottom-nav');
    nav.style.transform = nav.style.transform === 'translateY(0px)' ? 'translateY(100%)' : 'translateY(0px)';
}

function toggleDetails(taskId) {
    const row = document.getElementById('task-' + taskId);
    const details = document.getElementById('details-' + taskId);
    const allRows = document.querySelectorAll('.task-row');
    const allDetails = document.querySelectorAll('.task-details');
    const isOpen = details.classList.contains('open');
    allRows.forEach(r => r.classList.remove('expanded'));
    allDetails.forEach(d => d.classList.remove('open'));
    if (!isOpen) {
        row.classList.add('expanded');
        details.classList.add('open');
        setTimeout(() => {
            const rect = row.getBoundingClientRect();
            if (rect.top < 80 || rect.bottom > window.innerHeight) {
                row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 400);
    }
}

function toggleSave(btn) {
    if (btn.disabled) { alert('Restricted: Cannot save tasks.'); return; }
    const taskId = btn.dataset.taskId;
    const svg = btn.querySelector('svg');
    const originalFill = svg.getAttribute('fill');
    btn.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>';
    btn.disabled = true;
    fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle_save&task_id=' + encodeURIComponent(taskId)
    })
    .then(r => r.json())
    .then(j => {
        btn.disabled = false;
        if (j.status === 'saved') {
            btn.classList.add('saved');
            btn.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
            btn.title = 'Remove from saved';
            showToast('Task saved!', 'success');
        } else if (j.status === 'removed') {
            btn.classList.remove('saved');
            btn.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
            btn.title = 'Save task';
            showToast('Removed from saved', 'info');
        } else if (j.status === 'restricted') {
            btn.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="' + originalFill + '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
            alert('Restricted: Cannot save tasks.');
        } else {
            btn.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="' + originalFill + '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
            alert('Error saving task');
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="' + originalFill + '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
        alert('Error: ' + e);
    });
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.style.cssText = `position: fixed; bottom: 100px; right: 24px; background: ${type === 'success' ? 'linear-gradient(135deg, var(--emerald-500), #059669)' : 'linear-gradient(135deg, var(--blue-500), var(--blue-400))'}; color: white; padding: 16px 24px; border-radius: 14px; font-weight: 700; font-size: 14px; box-shadow: 0 12px 32px rgba(0,0,0,0.2); z-index: 9999; animation: fadeInUp 0.4s ease; display: flex; align-items: center; gap: 10px; letter-spacing: 0.3px;`;
    toast.innerHTML = `<svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">${type === 'success' ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>'}</svg> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(12px)'; setTimeout(() => toast.remove(), 350); }, 3000);
}
