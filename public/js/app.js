// public/js/app.js — JobTracker Frontend v2

// ── Applications Page ───────────────────────────────────────────────────────
function initApplicationsPage() {
  const modal    = new bootstrap.Modal(document.getElementById('appModal'));
  const form     = document.getElementById('appForm');
  const saveBtn  = document.getElementById('saveApp');
  const btnNew   = document.getElementById('btnNewApp');
  const viewBtns = document.querySelectorAll('#viewToggle button');
  const kanban   = document.getElementById('kanbanView');
  const list     = document.getElementById('listView');

  // View toggle
  viewBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      viewBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      if (btn.dataset.view === 'kanban') {
        kanban.style.display = '';
        list.style.display   = 'none';
      } else {
        kanban.style.display = 'none';
        list.style.display   = '';
      }
    });
  });

  // New app
  if (btnNew) {
    btnNew.addEventListener('click', () => {
      resetForm();
      document.getElementById('modalTitle').textContent = 'New Application';
      modal.show();
    });
  }

  // Prevent accidental form submission via Enter key
  form.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
      e.preventDefault();
    }
  });

  // Disable save button by default - enable only on user interaction
  saveBtn.disabled = true;
  saveBtn.textContent = 'Save Application';
  
  form.addEventListener('change', () => {
    saveBtn.disabled = false;
  });
  form.addEventListener('focus', () => {
    saveBtn.disabled = false;
  }, true);
  saveBtn.addEventListener('mouseenter', () => {
    saveBtn.disabled = false;
  });

  // Edit buttons (kanban)
  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      openEditModal(parseInt(btn.dataset.id));
    });
  });

  // View buttons
  document.querySelectorAll('.btn-view').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
        window.location = window.APP_URL + '/application-detail.php?id=' + btn.dataset.id;
    });
  });

  // Delete buttons (list view)
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Delete this application? This cannot be undone.')) return;
      const res = await postAction({ action: 'delete', id: btn.dataset.id, csrf_token: csrfVal() });
      if (res.ok) window.location.reload();
    });
  });

  // Save - require genuine user click (not form filler)
  let userClickOnly = false;
  document.addEventListener('mousedown', () => { userClickOnly = true; });
  document.addEventListener('touchstart', () => { userClickOnly = true; });
  form.addEventListener('keydown', () => { userClickOnly = true; });
  
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      // Reject if triggered by form filler (no user interaction detected)
      if (!userClickOnly) {
        return;
      }
      userClickOnly = false; // Reset for next action
      
      const company   = document.getElementById('f_company').value.trim();
      const job_title = document.getElementById('f_job_title').value.trim();
      if (!company || !job_title) {
        alert('Company and Job Title are required.');
        return;
      }
      saveBtn.disabled    = true;
      saveBtn.textContent = 'Saving…';
      const data = new FormData(form);
      data.set('csrf_token', csrfVal());
      const res = await postFormData(data);
      saveBtn.disabled    = false;
      saveBtn.textContent = 'Save Application';
      if (res.ok) {
        modal.hide();
        // Reload page to show new application
        window.location = window.APP_URL + '/applications.php';
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
    document.getElementById('modalTitle').textContent  = 'Edit Application';
    document.getElementById('formAction').value        = 'update';
    document.getElementById('formId').value            = id;
    document.getElementById('f_company').value         = app.company;
    document.getElementById('f_job_title').value       = app.job_title;
    document.getElementById('f_job_url').value         = app.job_url    || '';
    document.getElementById('f_job_type').value        = app.job_type;
    document.getElementById('f_status').value          = app.status;
    document.getElementById('f_salary').value          = app.salary_range || '';
    document.getElementById('f_applied_at').value      = app.applied_at   || '';
    document.getElementById('f_notes').value           = app.notes        || '';
    const resumeSel = document.getElementById('f_resume');
    if (resumeSel && app.resume_id) resumeSel.value = app.resume_id;
    modal.show();
  }

  function resetForm() {
    form.reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value     = '';
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
            window.location = window.APP_URL + '/applications.php?search=' + encodeURIComponent(q);
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
// Called from analytics.php inline script after Chart.js is loaded
function initAnalytics() {
  // intentionally empty — chart + bar animation are bootstrapped
  // inline in analytics.php so they run after the DOM and Chart.js are ready
}

function paymentHandler(response) {
     fetch('verify_payment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(response)  
     })
     .then(res => res.json())
     .then(data => {
          if (data.success) {
                alert('Payment successful! Premium features unlocked.');
                window.location.href = "dashboard.php";
          } else {
                alert('Payment verification failed.');
          }
     });
}

// ── Resume Page ─────────────────────────────────────────────────────────────
function initResumePage() {
  const dropzone  = document.getElementById('dropzone');
  const fileInput = document.getElementById('resumeFile');
  const fileName  = document.getElementById('fileName');
  if (!dropzone || !fileInput) return;

  dropzone.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) fileName.textContent = '📎 ' + fileInput.files[0].name;
  });

  dropzone.addEventListener('dragover', e => {
    e.preventDefault();
    dropzone.classList.add('drag-over');
  });

  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));

  dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) {
      const dt = new DataTransfer();
      dt.items.add(file);
      fileInput.files     = dt.files;
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
    const url = (typeof window.APP_URL !== 'undefined' ? window.APP_URL : '') + '/applications.php';
    const res = await fetch(url, { method: 'POST', body: fd });
    const json = await res.json();
    console.log('Response:', json);
    if (json.error) {
      alert('Error: ' + json.error);
      return json;
    }
    return json;
  } catch (e) {
    console.error('Fetch error:', e);
    alert('Network error: ' + e.message);
    return { error: 'Network error' };
  }
}

// ── DOMContentLoaded — Global Initialization ────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

  // Auto-dismiss alerts after 4s
  document.querySelectorAll('.alert-dismissible').forEach(el => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      if (bsAlert) bsAlert.close();
    }, 4000);
  });

  // ── Mobile Sidebar Toggle + Overlay ──────────────────────────────────────
  const sidebar    = document.querySelector('.sidebar');
  const overlay    = document.getElementById('sidebarOverlay');
  const toggleBtn  = document.getElementById('sidebarToggle');
  const closeBtn   = document.getElementById('sidebarClose');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
    document.body.style.overflow = '';
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      if (sidebar && sidebar.classList.contains('open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', closeSidebar);
  }

  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  // Close sidebar on clicking main content (mobile)
  document.addEventListener('click', (e) => {
    if (sidebar && sidebar.classList.contains('open')) {
      if (!sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn?.contains(e.target)) {
        closeSidebar();
      }
    }
  });

  // ── Stagger Animation (IntersectionObserver) ─────────────────────────────
  const animateEls = document.querySelectorAll('.animate-in');
  if (animateEls.length > 0 && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry, i) => {
        if (entry.isIntersecting) {
          setTimeout(() => {
            entry.target.classList.add('visible');
          }, i * 80);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    animateEls.forEach(el => observer.observe(el));
  }

  // ── Animated Number Counters ─────────────────────────────────────────────
  document.querySelectorAll('.stat-value').forEach(el => {
    const text = el.textContent.trim();
    const num = parseInt(text);
    if (!isNaN(num) && num > 0 && num < 10000) {
      const suffix = text.replace(num.toString(), '');
      el.textContent = '0' + suffix;
      let current = 0;
      const step = Math.max(1, Math.floor(num / 30));
      const duration = 600;
      const interval = duration / (num / step);
      
      const counter = setInterval(() => {
        current += step;
        if (current >= num) {
          current = num;
          clearInterval(counter);
        }
        el.textContent = current + suffix;
      }, interval);
    }
  });

});