document.addEventListener('DOMContentLoaded', function () {
  const container = document.querySelector('.user-list');
  if (!container) return; // Not on admin users section

  // Toolbar (search + filters + pagination)
  const toolbar = document.createElement('div');
  toolbar.className = 'form-group';
  const searchInput = document.createElement('input');
  searchInput.type = 'search';
  searchInput.placeholder = 'Search by username or email';
  searchInput.style.minWidth = '240px';
  const fAdmin = document.createElement('input'); fAdmin.type = 'checkbox';
  const fAdminLbl = document.createElement('label'); fAdminLbl.textContent = 'Admin only';
  const fActive = document.createElement('input'); fActive.type = 'checkbox'; fActive.checked = true;
  const fActiveLbl = document.createElement('label'); fActiveLbl.textContent = 'Active only';
  const fVerified = document.createElement('input'); fVerified.type = 'checkbox';
  const fVerifiedLbl = document.createElement('label'); fVerifiedLbl.textContent = 'Verified only';
  const prevBtn = document.createElement('button'); prevBtn.className = 'button tiny'; prevBtn.textContent = 'Prev';
  const nextBtn = document.createElement('button'); nextBtn.className = 'button tiny'; nextBtn.textContent = 'Next';
  const pageSpan = document.createElement('span'); pageSpan.style.margin = '0 8px';
  toolbar.appendChild(searchInput);
  toolbar.appendChild(document.createTextNode(' '));
  toolbar.appendChild(fAdmin); toolbar.appendChild(fAdminLbl);
  toolbar.appendChild(document.createTextNode(' '));
  toolbar.appendChild(fActive); toolbar.appendChild(fActiveLbl);
  toolbar.appendChild(document.createTextNode(' '));
  toolbar.appendChild(fVerified); toolbar.appendChild(fVerifiedLbl);
  toolbar.appendChild(document.createTextNode(' '));
  toolbar.appendChild(prevBtn); toolbar.appendChild(pageSpan); toolbar.appendChild(nextBtn);
  const section = container.parentElement;
  section.insertBefore(toolbar, container);

  let state = { page: 1, per_page: 20, q: '', filter: { is_admin: null, is_active: true, is_verified: null } };
  let total = 0;

  function updatePager() {
    const pages = Math.max(1, Math.ceil(total / state.per_page));
    pageSpan.textContent = `Page ${state.page} / ${pages}`;
    prevBtn.disabled = state.page <= 1;
    nextBtn.disabled = state.page >= pages;
  }

  function el(tag, attrs = {}, children = []) {
    const e = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
      if (k === 'class') e.className = v; else if (k === 'html') e.innerHTML = v; else e.setAttribute(k, v);
    });
    (Array.isArray(children) ? children : [children]).forEach(c => { if (c) e.appendChild(c); });
    return e;
  }

  function renderUsers(items) {
    container.innerHTML = '';
    if (!items || items.length === 0) {
      container.appendChild(el('div', { class: 'form-group' }, [document.createTextNode('No users found')]))
      return;
    }
    items.forEach(u => {
      const isFirst = u.id === 1;
      const userIcon = isFirst ? 'fa-user-tie' : 'fa-id-badge';

      const row = el('div', { class: 'form-group-inline', 'data-userid': u.id, 'data-email': u.email });
      const l = el('div', { class: 'user-list-row' }, [
        el('div', { title: 'Username' }, [ el('div', { class: 'user-list-icon' }, [el('i', { class: `fa-solid ${userIcon}` })]), document.createTextNode(u.username || '') ]),
        el('div', { title: 'Email' }, [ el('div', { class: 'user-list-icon' }, [el('i', { class: 'fa-solid fa-envelope' })]), el('a', { href: `mailto:${u.email}` }, [document.createTextNode(u.email || '')]) ])
      ]);

      const actions = el('div');
      // View details
      const viewBtn = el('button', { class: 'button tiny', title: 'View' }, [document.createTextNode('View')]);
      viewBtn.addEventListener('click', () => openDetail(u.id));
      actions.appendChild(viewBtn);
      if (!isFirst) {
        const delBtn = el('button', { class: 'image-button medium', title: 'Delete user' }, []);
        delBtn.addEventListener('click', () => removeUser(u.id));
        delBtn.innerHTML = document.querySelector('#delete-icon-template')?.innerHTML || '<i class="fa-solid fa-trash"></i>';
        actions.appendChild(delBtn);
      } else {
        actions.appendChild(el('button', { class: 'button tiny disabled', disabled: 'disabled', title: 'Owner' }, [document.createTextNode('Owner')]));
      }
      row.appendChild(l);
      row.appendChild(actions);
      container.appendChild(row);
    });
  }

  function csrfHeaders(h={}){ h['Content-Type']='application/json'; if (window.csrfToken) h['X-CSRF-Token']=window.csrfToken; return h; }

  async function loadUsers(page = 1) {
    try {
      state.page = page;
      const res = await fetch('endpoints/admin/users/list.php', { method: 'POST', headers: csrfHeaders({}), body: JSON.stringify(state) });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Failed to load users');
      total = data.data.total || 0;
      updatePager();
      renderUsers(data.data.items);
    } catch (e) {
      console.error(e);
    }
  }

  async function openDetail(id) {
    try {
      const res = await fetch('endpoints/admin/users/read.php?id=' + encodeURIComponent(id), { headers: window.csrfToken ? { 'X-CSRF-Token': window.csrfToken } : {} });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Failed to load user');
      showDrawer(data.data.user, data.data.stats);
    } catch (e) { console.error(e); }
  }

  function showDrawer(user, stats) {
    let drawer = document.getElementById('admin-user-drawer');
    if (!drawer) {
      drawer = document.createElement('div');
      drawer.id = 'admin-user-drawer';
      drawer.style.cssText = 'position:fixed;top:0;right:0;width:360px;max-width:90vw;height:100vh;background:var(--box-background-color,#fff);border-left:1px solid var(--box-border-color,#ddd);box-shadow:var(--box-shadow,0 2px 12px rgba(0,0,0,.2));z-index:9999;transform:translateX(0);padding:16px;overflow:auto;';
      document.body.appendChild(drawer);
    }
    drawer.innerHTML = '';
    const close = el('button', { class: 'button tiny', style: 'float:right' }, [document.createTextNode('Close')]);
    close.addEventListener('click', () => drawer.remove());
    const title = el('h3', {}, [document.createTextNode(user.username || user.email || ('User #' + user.id))]);
    const meta = el('p', {}, [document.createTextNode(`Email: ${user.email || ''}`)]);
    const meta2 = el('p', {}, [document.createTextNode(`Created: ${user.created_at || ''}`)]);
    const meta3 = el('p', {}, [document.createTextNode(`Last login: ${user.last_login || '—'}`)]);
    const statsEl = el('p', {}, [document.createTextNode(`Subscriptions: ${stats.subscription_count} • Categories: ${stats.categories_count} • Payment methods: ${stats.payment_methods_count}`)]);

    const actions = el('div', { class: 'form-group' });
    const adminToggle = el('button', { class: 'button tiny' }, [document.createTextNode(user.is_admin ? 'Demote from admin' : 'Promote to admin')]);
    adminToggle.addEventListener('click', async () => {
      await callToggleAdmin(user.id, !user.is_admin);
      openDetail(user.id); // reload
      loadUsers(state.page);
    });
    const activeToggle = el('button', { class: 'button tiny' }, [document.createTextNode(user.is_active ? 'Deactivate' : 'Activate')]);
    activeToggle.addEventListener('click', async () => {
      await callToggleActive(user.id, !user.is_active);
      openDetail(user.id);
      loadUsers(state.page);
    });
    const resetPass = el('button', { class: 'secondary-button thin' }, [document.createTextNode('Reset password')]);
    resetPass.addEventListener('click', async () => {
      const custom = prompt('Enter new password (min 8 chars) or leave blank to auto-generate:');
      await callResetPassword(user.id, custom && custom.length >= 8 ? custom : null);
    });
    actions.appendChild(adminToggle);
    actions.appendChild(activeToggle);
    actions.appendChild(resetPass);

    const verifyBtn = el('button', { class: 'button tiny' }, [document.createTextNode(user.is_verified ? 'Verified' : 'Verify')]);
    if (!user.is_verified) {
      verifyBtn.addEventListener('click', async () => {
        const r = await fetch('endpoints/admin/users/verify.php', { method: 'POST', headers: csrfHeaders({}), body: JSON.stringify({ id: user.id }) });
        const d = await r.json(); if (!d.success) return alert(d.message||'Failed to verify'); openDetail(user.id); loadUsers(state.page); });
    } else {
      verifyBtn.disabled = true;
    }
    const resendBtn = el('button', { class: 'button tiny' }, [document.createTextNode('Resend verification')]);
    resendBtn.addEventListener('click', async () => {
      const r = await fetch('endpoints/admin/users/resend_verification.php', { method: 'POST', headers: csrfHeaders({}), body: JSON.stringify({ email: user.email }) });
      const d = await r.json(); if (!d.success) alert(d.message||'Failed to resend'); else alert('Sent');
    });
    const forceLogoutBtn = el('button', { class: 'button tiny' }, [document.createTextNode('Force logout')]);
    forceLogoutBtn.addEventListener('click', async () => {
      const r = await fetch('endpoints/admin/users/force_logout.php', { method: 'POST', headers: csrfHeaders({}), body: JSON.stringify({ user_id: user.id }) });
      const d = await r.json(); if (!d.success) alert(d.message||'Failed to logout'); else alert('Revoked sessions');
    });
    const exportBtn = el('button', { class: 'button tiny' }, [document.createTextNode('Export data')]);
    exportBtn.addEventListener('click', async () => {
      const r = await fetch('endpoints/admin/users/export.php', { method: 'POST', headers: csrfHeaders({}), body: JSON.stringify({ user_id: user.id }) });
      const d = await r.json(); if (d.success && d.data && d.data.export) {
        const blob = new Blob([JSON.stringify(d.data.export, null, 2)], { type: 'application/json' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `user-${user.id}.json`; a.click(); URL.revokeObjectURL(a.href);
      } else { alert(d.message||'Export failed'); }
    });
    const deleteBtn = el('button', { class: 'secondary-button thin' }, [document.createTextNode('Delete user')]);
    deleteBtn.addEventListener('click', async () => {
      const transfer = prompt('Enter user id to transfer data to (leave blank to delete data with the user):');
      if (!confirm('Are you sure? This action cannot be undone.')) return;
      const r = await fetch('endpoints/admin/users/delete.php', { method: 'POST', headers: csrfHeaders({}), body: JSON.stringify({ user_id: user.id, transfer_to: transfer?parseInt(transfer,10):null }) });
      const d = await r.json(); if (!d.success) alert(d.message||'Delete failed'); else { alert('User deleted'); drawer.remove(); loadUsers(1); }
    });

    actions.appendChild(verifyBtn);
    actions.appendChild(resendBtn);
    actions.appendChild(forceLogoutBtn);
    actions.appendChild(exportBtn);
    actions.appendChild(deleteBtn);

    drawer.appendChild(close);
    drawer.appendChild(title);
    drawer.appendChild(meta);
    drawer.appendChild(meta2);
    drawer.appendChild(meta3);
    drawer.appendChild(statsEl);
    drawer.appendChild(actions);
  }

  async function callToggleAdmin(userId, makeAdmin) {
      const r = await fetch('endpoints/admin/users/toggle_admin.php', { method: 'POST', headers: csrfHeaders({}), body: JSON.stringify({ user_id: userId, make_admin: makeAdmin }) });
    const d = await r.json();
    if (!d.success) alert(d.message || 'Failed to update admin flag');
    return d.success;
  }

  async function callToggleActive(userId, isActive) {
      const r = await fetch('endpoints/admin/users/toggle_active.php', { method: 'POST', headers: csrfHeaders({}), body: JSON.stringify({ user_id: userId, is_active: isActive }) });
    const d = await r.json();
    if (!d.success) alert(d.message || 'Failed to update active flag');
    return d.success;
  }

  async function callResetPassword(userId, newPassword) {
    const r = await fetch('endpoints/admin/users/reset_password.php', { method: 'POST', headers: csrfHeaders({}), body: JSON.stringify({ user_id: userId, new_password: newPassword || null }) });
    const d = await r.json();
    if (d.success) {
      if (d.data && d.data.temporary_password) {
        alert('Temporary password: ' + d.data.temporary_password);
      } else {
        alert('Password updated');
      }
    } else {
      alert(d.message || 'Failed to reset password');
    }
  }

  // Event bindings
  let searchDebounce;
  searchInput.addEventListener('input', () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => { state.q = searchInput.value.trim(); loadUsers(1); }, 300);
  });
  fAdmin.addEventListener('change', () => { state.filter.is_admin = fAdmin.checked ? true : null; loadUsers(1); });
  fActive.addEventListener('change', () => { state.filter.is_active = fActive.checked ? true : null; loadUsers(1); });
  fVerified.addEventListener('change', () => { state.filter.is_verified = fVerified.checked ? 1 : null; loadUsers(1); });
  prevBtn.addEventListener('click', () => { if (state.page > 1) loadUsers(state.page - 1); });
  nextBtn.addEventListener('click', () => { loadUsers(state.page + 1); });

  // Initial load
  loadUsers();
});
