document.addEventListener('DOMContentLoaded', function(){
  const h = document.getElementById('health-status');
  if (!h) return;
  const refreshBtn = document.getElementById('refreshHealth');
  const logsBtn = document.getElementById('viewLogs');
  const logsView = document.getElementById('logsView');

  function csrfHeaders(o={}){ if (window.csrfToken) o['X-CSRF-Token']=window.csrfToken; return o; }

  async function loadHealth(){
    try {
      const res = await fetch('endpoints/health/status.php', { headers: csrfHeaders({}) });
      const d = await res.json();
      if (!d.success) throw new Error(d.message||'Failed');
      const c = d.data.health;
      h.innerHTML = '';
      const ul = document.createElement('ul');
      ul.style.listStyle='none'; ul.style.padding='0'; ul.style.margin='0';
      function li(k,v){ const e=document.createElement('li'); e.textContent = `${k}: ${v}`; return e; }
      ul.appendChild(li('PHP', c.php_version));
      ul.appendChild(li('App', c.app_version || 'n/a'));
      ul.appendChild(li('DB', c.db_ok ? 'ok' : 'error'));
      if (typeof c.users !== 'undefined') ul.appendChild(li('Users', c.users));
      if (typeof c.disk_free_mb !== 'undefined') ul.appendChild(li('Disk free (MB)', c.disk_free_mb));
      if (c.last_update_next_payment) ul.appendChild(li('Next payment job', c.last_update_next_payment));
      h.appendChild(ul);
    } catch (e) { h.textContent = 'Failed to load health.'; }
  }

  async function loadLogs(){
    try{
      const res = await fetch('endpoints/logs/view.php?lines=200', { headers: csrfHeaders({}) });
      const d = await res.json();
      if (d.success){ logsView.textContent = d.data.log || ''; logsView.style.display='block'; }
      else { alert(d.message||'Failed to load logs'); }
    }catch(e){ alert('Failed to load logs'); }
  }

  refreshBtn && refreshBtn.addEventListener('click', loadHealth);
  logsBtn && logsBtn.addEventListener('click', loadLogs);
  loadHealth();
});

