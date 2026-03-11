// public/js/app.js — JobTracker Frontend

// ── Applications Page ───────────────────────────────────────────────────────
function initApplicationsPage() {
  const modal     = new bootstrap.Modal(document.getElementById('appModal'));
  const form      = document.getElementById('appForm');
  const saveBtn   = document.getElementById('saveApp');
  const btnNew    = document.getElementById('btnNewApp');
  const viewBtns  = document.querySelectorAll('#viewToggle button');
  const kanban    = document.getElementById('kanbanView');
  const list      = document.getElementById('listView');

  // View toggle
  viewBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      viewBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      if (btn.dataset.view === 'kanban') {
        kanban.style.display = '';
        list.style.display = 'none';
      } else {
        kanban.style.display = 'none';
        list.style.display = '';
      }
    });
  });

  // Open new app modal
  if (btnNew) {
    btnNew.addEventListener('click', () => {
      resetForm();
      document.getElementById('modalTitle').textContent = 'New Application';
      modal.show();
    });
  }

  // Edit buttons (kanban)
  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      openEditModal(parseInt(btn.dataset.id));
    });
  });

  // View buttons
  document.querySelectorAll('.btn-view').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      window.location = APP_URL + '/application-detail.php?id=' + btn.dataset.id;
    });
  });

  // Delete buttons (list view)
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Delete this application? This cannot be undone.')) return;
      const res = await postAction({action:'delete', id:btn.dataset.id, csrf_token: csrfVal()});
      if (res.ok) window.location.reload();
    });
  });

  // Save application
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const company   = document.getElementById('f_company').value.trim();
      const job_title = document.getElementById('f_job_title').value.trim();
      if (!company || !job_title) {
        alert('Company and Job Title are required.');
        return;
      }
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';
      const data = new FormData(form);
      data.set('csrf_token', csrfVal());
      const res = await postFormData(data);
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Application';
      if (res.ok) {
        modal.hide();
        window.location.reload();
      } else {
        alert(res.error || 'An error occurred.');
      }
    });
  }

  // Check localStorage for edit trigger
  const editId = localStorage.getItem('editApp');
  if (editId) {
    localStorage.removeItem('editApp');
    openEditModal(parseInt(editId));
  }

  function openEditModal(id) {
    const app = (window.APPS_DATA || []).find(a => a.id === id);
    if (!app) return;
    document.getElementById('modalTitle').textContent = 'Edit Application';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = id;
    document.getElementById('f_company').value    = app.company;
    document.getElementById('f_job_title').value  = app.job_title;
    document.getElementById('f_job_url').value    = app.job_url || '';
    document.getElementById('f_job_type').value   = app.job_type;
    document.getElementById('f_status').value     = app.status;
    document.getElementById('f_salary').value     = app.salary_range || '';
    document.getElementById('f_applied_at').value = app.applied_at || '';
    document.getElementById('f_notes').value      = app.notes || '';
    const resumeSel = document.getElementById('f_resume');
    if (resumeSel && app.resume_id) resumeSel.value = app.resume_id;
    modal.show();
  }

  function resetForm() {
    form.reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
  }

  // Global search
  const searchInput = document.getElementById('globalSearch');
  if (searchInput) {
    let timer;
    searchInput.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        const q = searchInput.value.trim();
        if (q.length > 1) {
          window.location = APP_URL + '/applications.php?search=' + encodeURIComponent(q);
        }
      }, 500);
    });
  }
}

// ── Application Detail Page ─────────────────────────────────────────────────
function quickStatus(appId, status) {
  if (!confirm('Change status to "' + status + '"?')) return;
  postAction({ action: 'update_status', id: appId, status: status, csrf_token: csrfVal() })
    .then(res => { if (res.ok) window.location.reload(); });
}

// ── Analytics Page ──────────────────────────────────────────────────────────
function initAnalytics() {
  if (typeof Chart === 'undefined') return;

  // Timeline chart
  const ctxTimeline = document.getElementById('chartTimeline');
  if (ctxTimeline && window.MONTH_LABELS) {
    new Chart(ctxTimeline, {
      type: 'line',
      data: {
        labels: MONTH_LABELS,
        datasets: [{
          data: MONTH_DATA,
          borderColor: '#1a73e8',
          backgroundColor: 'rgba(26,115,232,.08)',
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#1a73e8',
          pointRadius: 4,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f0f4f9' } },
          x: { grid: { display: false } }
        }
      }
    });
  }
}

// ── Resume Page ─────────────────────────────────────────────────────────────
function initResumePage() {
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('resumeFile');
  const fileName  = document.getElementById('fileName');

  if (!dropzone || !fileInput) return;

  dropzone.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) {
      fileName.textContent = '📎 ' + fileInput.files[0].name;
    }
  });

  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('drag-over');
  });

  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));

  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) {
      const dt = new DataTransfer();
      dt.items.add(file);
      fileInput.files = dt.files;
      fileName.textContent = '📎 ' + file.name;
    }
  });
}

// ── Utilities ───────────────────────────────────────────────────────────────
function csrfVal() {
  const el = document.getElementById('csrfToken');
  return el ? el.value : '';
}

async function postAction(data) {
  const fd = new FormData();
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  return postFormData(fd);
}

async function postFormData(fd) {
  try {
    const url = window.APP_URL
      ? APP_URL + '/applications.php'
      : window.location.pathname;
    const res  = await fetch(url, { method: 'POST', body: fd });
    return await res.json();
  } catch (e) {
    console.error(e);
    return { error: 'Network error' };
  }
}

// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert-dismissible').forEach(el => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 4000);
  });
});
