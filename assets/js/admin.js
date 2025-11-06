(function(){
  const state = {
    members: [],
    admins: [],
    transactions: [],
    boardEntries: [],
    currentPage: 1,
    perPage: 10,
    txFilter: 'all',
    txSearch: ''
  };

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  function formatEuro(value){
    const number = Number(value || 0);
    return '€ ' + number.toFixed(2);
  }

  function animateCounter(el, start, end, duration, formatter = v => v){
    if(!el) return;
    const t0 = performance.now();
    function step(t){
      const progress = Math.min((t - t0) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 4);
      const current = start + (end - start) * eased;
      el.textContent = formatter(current);
      if(progress < 1){
        requestAnimationFrame(step);
      }
    }
    requestAnimationFrame(step);
  }

  function escapeHtml(str){
    const map = {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"};
    return (str || '').replace(/[&<>"']/g, c => map[c]);
  }

  async function fetchJson(url, options){
    const res = await fetch(url, options);
    if(!res.ok){
      throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
  }

  async function fetchMembers(){
    const [memberRes, adminRes] = await Promise.all([
      fetchJson('/api/get_members.php'),
      fetchJson('/api/get_admins.php')
    ]);
    const adminNames = Array.isArray(adminRes) ? adminRes.map(entry => entry.member_name) : [];
    const members = Array.isArray(memberRes) ? memberRes : [];
    state.members = members.map(member => ({ ...member, is_admin: adminNames.includes(member.name) }));
    state.admins = adminNames;
    return state.members;
  }

  async function fetchBalance(){
    const data = await fetchJson('/api/get_balance.php');
    return Array.isArray(data) ? data : [];
  }

  async function fetchShifts(){
    const data = await fetchJson('/api/get_shifts.php');
    return Array.isArray(data) ? data : [];
  }

  async function fetchTransactions(limit = 200, member = ''){
    const url = member
      ? `/api/get_transactions.php?name=${encodeURIComponent(member)}&limit=${limit}`
      : `/api/get_all_transactions.php?limit=${limit}`;
    const data = await fetchJson(url);
    state.transactions = Array.isArray(data) ? data : [];
    state.currentPage = 1;
    return state.transactions;
  }

  async function fetchBoard(){
    const data = await fetchJson('/api/get_admin_board.php');
    state.boardEntries = Array.isArray(data) ? data : [];
    return state.boardEntries;
  }

  function refreshCashBalance(){
    const balanceTarget = $('#cashPageBalance');
    if(!balanceTarget){ return; }
    fetchBalance()
      .then(balances => {
        const totalBalance = balances.reduce((sum, entry) => sum + (parseFloat(entry.balance || 0) || 0), 0);
        const previous = parseFloat(balanceTarget.dataset.value || '0') || 0;
        animateCounter(balanceTarget, previous, totalBalance, 800, value => formatEuro(value));
        balanceTarget.dataset.value = totalBalance;
      })
      .catch(() => {
        balanceTarget.textContent = '–';
      });
  }

  function updateMembersChart(members){
    const canvas = $('#membersChart');
    if(!canvas || typeof Chart === 'undefined'){ return; }
    const active = members.filter(m => m.status !== 'inactive').length;
    const inactive = members.length - active;
    if(canvas._chartInstance){ canvas._chartInstance.destroy(); }
    canvas._chartInstance = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: ['Aktiv', 'Inaktiv'],
        datasets: [{
          data: [active, inactive],
          backgroundColor: ['rgba(244,63,94,0.8)', 'rgba(148,163,184,0.45)'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        cutout: '70%',
        plugins: { legend: { labels: { color: '#fff' } } }
      }
    });
  }

  function updateBalanceChart(entries){
    const canvas = $('#balanceChart');
    if(!canvas || typeof Chart === 'undefined'){ return; }
    if(canvas._chartInstance){ canvas._chartInstance.destroy(); }
    const labels = entries.map(row => row.name || row.member || 'Mitglied');
    const values = entries.map(row => Number(row.balance || row.total || 0));
    canvas._chartInstance = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Saldo',
          data: values,
          backgroundColor: 'rgba(244,63,94,0.45)',
          borderRadius: 12
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            labels: { color: '#fff' }
          }
        },
        scales: {
          x: {
            ticks: { color: '#e2e8f0' },
            grid: { color: 'rgba(255,255,255,0.08)' }
          },
          y: {
            ticks: { color: '#e2e8f0' },
            grid: { color: 'rgba(255,255,255,0.08)' }
          }
        }
      }
    });
  }

  function renderTransactions(){
    const tbody = $('#transactionsTable');
    if(!tbody){ return; }

    let list = state.transactions.slice();
    if(state.txFilter === 'einzahlung'){ list = list.filter(t => t.type === 'Einzahlung'); }
    if(state.txFilter === 'auszahlung'){ list = list.filter(t => t.type === 'Auszahlung'); }
    if(state.txFilter === 'schaden'){ list = list.filter(t => t.type === 'Schaden'); }
    if(state.txFilter === 'gruppenaktion'){ list = list.filter(t => t.type === 'Gruppenaktion'); }
    if(state.txFilter === 'heute'){
      const today = new Date().toISOString().split('T')[0];
      list = list.filter(t => (t.date || '').startsWith(today));
    }
    if(state.txSearch){
      list = list.filter(t => (t.name || '').toLowerCase().includes(state.txSearch) || (t.note || t.reason || '').toLowerCase().includes(state.txSearch));
    }

    const total = list.length;
    const perPage = state.perPage;
    const startIndex = (state.currentPage - 1) * perPage;
    const pageItems = list.slice(startIndex, startIndex + perPage);

    const totalEl = $('#totalTransactions');
    const pageInfo = $('#pageInfo');
    const prevBtn = $('#btnPrev');
    const nextBtn = $('#btnNext');

    if(totalEl){ totalEl.textContent = total.toString(); }
    if(pageInfo){
      const from = Math.min(total, startIndex + 1);
      const to = Math.min(total, startIndex + pageItems.length);
      pageInfo.textContent = total === 0 ? '0–0' : `${from}–${to}`;
    }

    if(prevBtn){ prevBtn.disabled = state.currentPage === 1; }
    if(nextBtn){
      const totalPages = Math.ceil(total / perPage) || 1;
      nextBtn.disabled = state.currentPage >= totalPages;
    }

    tbody.innerHTML = '';
    if(pageItems.length === 0){
      tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-white/50">Keine Transaktionen</td></tr>';
      return;
    }

    pageItems.forEach(transaction => {
      const type = transaction.type || '';
      const badgeClasses = {
        'Einzahlung': 'bg-emerald-500/20 text-emerald-300',
        'Auszahlung': 'bg-red-500/20 text-red-300',
        'Gutschrift': 'bg-sky-500/20 text-sky-200',
        'Schaden': 'bg-orange-500/20 text-orange-200',
        'Gruppenaktion': 'bg-purple-500/20 text-purple-200'
      };
      const isPositive = type === 'Einzahlung';
      const isNeutral = type === 'Gutschrift';
      const amountClass = isPositive ? 'text-emerald-400' : (isNeutral ? 'text-sky-300' : 'text-red-400');
      const amountPrefix = isPositive ? '+' : (isNeutral ? '' : '-');

      const tr = document.createElement('tr');
      tr.className = 'border-b border-white/5 hover:bg-white/5 transition-colors';
      tr.innerHTML = `
        <td class="py-3 px-2">${escapeHtml(transaction.date || 'N/A')}</td>
        <td class="py-3 px-2">${escapeHtml(transaction.name || 'N/A')}</td>
        <td class="py-3 px-2">
          <span class="px-2 py-1 rounded-lg text-xs font-semibold ${badgeClasses[type] || 'bg-white/10 text-white/70'}">${escapeHtml(type)}</span>
        </td>
        <td class="py-3 px-2 ${amountClass}">${amountPrefix}${formatEuro(transaction.amount || transaction.balance)}</td>
        <td class="py-3 px-2 text-white/70">${escapeHtml(transaction.note || transaction.reason || '–')}</td>
        <td class="py-3 px-2 text-right">
          <button class="px-2 py-1 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-200 text-xs" data-tx-id="${transaction.id}">🗑️</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  function populateTransactionFilter(){
    const select = $('#transactionFilter');
    if(!select){ return; }
    const existing = new Set();
    $$('option', select).forEach(opt => existing.add(opt.value));
    state.transactions
      .map(t => t.name)
      .filter(Boolean)
      .filter(name => !existing.has(name))
      .sort()
      .forEach(name => {
        const option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        select.appendChild(option);
      });
  }

  function populateMemberSelects(){
    const selects = [$('#transactionMember'), $('#transactionModal select[name="name"]')].filter(Boolean);
    if(selects.length === 0){ return; }
    selects.forEach(select => {
      const current = select.value;
      select.innerHTML = '<option value="">Bitte wählen...</option>';
      state.members.forEach(member => {
        const option = document.createElement('option');
        option.value = member.name;
        option.textContent = member.name;
        if(current && current === member.name){ option.selected = true; }
        select.appendChild(option);
      });
    });
  }

  function handleTypeChange(selectEl, memberSelect){
    if(!selectEl || !memberSelect){ return; }
    const isGroup = selectEl.value === 'Gruppenaktion';
    if(isGroup){
      memberSelect.disabled = true;
      memberSelect.removeAttribute('required');
      memberSelect.classList.add('opacity-60');
    }else{
      memberSelect.disabled = false;
      memberSelect.setAttribute('required', 'required');
      memberSelect.classList.remove('opacity-60');
    }
  }

  function registerTypeWatcher(selectSelector, memberSelector){
    const select = $(selectSelector);
    const memberSelect = $(memberSelector);
    if(select){
      select.addEventListener('change', () => handleTypeChange(select, memberSelect));
      handleTypeChange(select, memberSelect);
    }
  }

  function initParticles(){
    const canvas = $('#particles');
    if(!canvas){ return; }
    const ctx = canvas.getContext('2d');
    const dpr = Math.max(1, window.devicePixelRatio || 1);
    let width = window.innerWidth;
    let height = window.innerHeight;

    function resize(){
      width = window.innerWidth;
      height = window.innerHeight;
      canvas.width = width * dpr;
      canvas.height = height * dpr;
      canvas.style.width = width + 'px';
      canvas.style.height = height + 'px';
      ctx.scale(dpr, dpr);
    }

    resize();

    const particleCount = Math.round((width * height) / 90000);
    const particles = Array.from({ length: particleCount }, () => ({
      x: Math.random() * width,
      y: Math.random() * height,
      vx: (Math.random() - 0.5) * 0.3,
      vy: (Math.random() - 0.5) * 0.3,
      r: 1 + Math.random() * 2
    }));

    function draw(){
      ctx.clearRect(0, 0, width, height);
      particles.forEach(p => {
        p.x += p.vx;
        p.y += p.vy;
        if(p.x < 0) p.x = width;
        if(p.x > width) p.x = 0;
        if(p.y < 0) p.y = height;
        if(p.y > height) p.y = 0;
        ctx.beginPath();
        ctx.fillStyle = 'rgba(255,255,255,0.06)';
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
      });

      for(let i = 0; i < particles.length; i++){
        for(let j = i + 1; j < particles.length; j++){
          const a = particles[i];
          const b = particles[j];
          const dx = a.x - b.x;
          const dy = a.y - b.y;
          const distance = dx * dx + dy * dy;
          if(distance < 9000){
            const alpha = 0.03 * (1 - distance / 9000);
            ctx.strokeStyle = `rgba(140,160,255,${alpha})`;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();
          }
        }
      }

      requestAnimationFrame(draw);
    }

    draw();
    window.addEventListener('resize', resize);
  }

  function initNavToggle(){
    const toggle = $('#adminNavToggle');
    const nav = $('.admin-nav');
    if(!toggle || !nav){ return; }
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('admin-nav--open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    nav.addEventListener('click', event => {
      if(event.target.closest('a')){
        nav.classList.remove('admin-nav--open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function initDashboard(){
    if(document.body.dataset.adminPage !== 'dashboard'){ return; }

    Promise.all([fetchMembers(), fetchBalance(), fetchShifts()])
      .then(([members, balances, shifts]) => {
        const memberCount = members.length;
        animateCounter($('#anzMembers'), 0, memberCount, 800, v => Math.floor(v));
        updateMembersChart(members);

        const totalBalance = balances.reduce((sum, entry) => sum + (parseFloat(entry.balance || 0) || 0), 0);
        animateCounter($('#kassenstand'), 0, totalBalance, 1200, v => formatEuro(v));
        updateBalanceChart(balances);

        const today = new Date().toISOString().split('T')[0];
        const todaysShifts = shifts.filter(shift => (shift.shift_date || '').startsWith(today));
        animateCounter($('#shiftsToday'), 0, todaysShifts.length, 800, v => Math.floor(v));
      })
      .catch(() => {
        const errorTarget = $('#dashboardActivity');
        if(errorTarget){
          errorTarget.innerHTML = '<div class="text-red-400 text-sm">Fehler beim Laden der Kennzahlen.</div>';
        }
      });

    loadDashboardActivity();

    if(window.gsap && window.ScrollTrigger){
      gsap.registerPlugin(ScrollTrigger);
      gsap.utils.toArray('.glass-strong, .stat-card').forEach((el, index) => {
        gsap.from(el, {
          scrollTrigger: { trigger: el, start: 'top 90%', once: true },
          y: 30,
          opacity: 0,
          duration: 0.6,
          delay: index * 0.05,
          ease: 'power3.out'
        });
      });
    }
  }

  function loadDashboardActivity(){
    const container = $('#dashboardActivity');
    if(!container){ return; }
    Promise.all([
      fetchTransactions(5),
      fetchBoard()
    ]).then(([transactions, boardEntries]) => {
      const latestTransactions = transactions.slice(0, 5);
      const latestBoard = boardEntries.slice(0, 3);
      const parts = [];
      if(latestTransactions.length){
        const items = latestTransactions.map(tx => `
          <li class="flex items-start justify-between gap-4">
            <span>
              <strong>${escapeHtml(tx.name || 'Mitglied')}</strong>
              <span class="text-white/50">${escapeHtml(tx.type || '')}</span>
            </span>
            <span class="font-mono ${tx.type === 'Einzahlung' ? 'text-emerald-400' : 'text-red-400'}">${tx.type === 'Einzahlung' ? '+' : '-'}${formatEuro(tx.amount || 0)}</span>
          </li>
        `).join('');
        parts.push(`<section><h3 class="text-sm uppercase tracking-[0.25em] text-white/50 mb-2">Transaktionen</h3><ul class="space-y-2">${items}</ul></section>`);
      }
      if(latestBoard.length){
        const dateFmt = new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' });
        const items = latestBoard.map(entry => `
          <li class="flex items-start justify-between gap-4">
            <div>
              <strong>${escapeHtml(entry.title)}</strong>
              <div class="text-white/50 text-xs">${escapeHtml(entry.created_by || '')}</div>
            </div>
            <time class="text-white/40 text-xs">${dateFmt.format(new Date(entry.created_at))}</time>
          </li>
        `).join('');
        parts.push(`<section><h3 class="text-sm uppercase tracking-[0.25em] text-white/50 mb-2">Board</h3><ul class="space-y-2">${items}</ul></section>`);
      }
      container.innerHTML = parts.length ? parts.join('<div class="h-px bg-white/10 my-3"></div>') : '<div class="text-white/50">Keine Aktivitäten vorhanden.</div>';
    }).catch(() => {
      container.innerHTML = '<div class="text-red-400 text-sm">Aktivitäten konnten nicht geladen werden.</div>';
    });
  }

  function initTransactionsPage(){
    if(document.body.dataset.adminPage !== 'kasse'){ return; }

    fetchMembers()
      .then(populateMemberSelects)
      .catch(() => {});

    loadTransactions();

    refreshCashBalance();

    const filter = $('#transactionFilter');
    if(filter){
      filter.addEventListener('change', () => {
        fetchTransactions(200, filter.value || '')
          .then(() => {
            populateTransactionFilter();
            renderTransactions();
          })
          .catch(() => alert('Fehler beim Laden der Transaktionen'));
      });
    }

    const search = $('#searchTransactions');
    if(search){
      let timeout;
      search.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
          state.txSearch = search.value.trim().toLowerCase();
          renderTransactions();
        }, 250);
      });
    }

    const refreshBtn = $('#refreshTransactions');
    if(refreshBtn){
      refreshBtn.addEventListener('click', () => {
        loadTransactions();
        refreshCashBalance();
      });
    }

    const quickRefresh = $('#quickRefresh');
    if(quickRefresh){
      quickRefresh.addEventListener('click', () => {
        loadTransactions();
        refreshCashBalance();
      });
    }

    $$('.txchip').forEach(button => {
      button.addEventListener('click', () => {
        $$('.txchip').forEach(b => b.classList.remove('bg-white/30', 'text-white'));
        button.classList.add('bg-white/30', 'text-white');
        state.txFilter = button.dataset.q || 'all';
        renderTransactions();
      });
    });

    const btnPrev = $('#btnPrev');
    if(btnPrev){
      btnPrev.addEventListener('click', () => {
        if(state.currentPage > 1){
          state.currentPage -= 1;
          renderTransactions();
        }
      });
    }

    const btnNext = $('#btnNext');
    if(btnNext){
      btnNext.addEventListener('click', () => {
        const totalPages = Math.ceil(state.transactions.length / state.perPage) || 1;
        if(state.currentPage < totalPages){
          state.currentPage += 1;
          renderTransactions();
        }
      });
    }

    const btnModal = $('#btnOpenTxModal');
    if(btnModal){ btnModal.addEventListener('click', openTransactionModal); }

    const modal = $('#transactionModal');
    if(modal){
      modal.addEventListener('click', event => {
        if(event.target === modal){ closeTransactionModal(); }
      });
    }

    $$('.js-close-transaction-modal').forEach(button => {
      button.addEventListener('click', closeTransactionModal);
    });

    document.addEventListener('keydown', event => {
      if(event.key === 'Escape'){ closeTransactionModal(); }
    });

    const tbody = $('#transactionsTable');
    if(tbody){
      tbody.addEventListener('click', event => {
        const button = event.target.closest('button[data-tx-id]');
        if(!button){ return; }
        const id = button.getAttribute('data-tx-id');
        if(confirm('Transaktion wirklich löschen?')){
          deleteTransaction(id);
        }
      });
    }

    const inlineForm = $('#transactionFormInline');
    if(inlineForm){ inlineForm.addEventListener('submit', onTransactionSubmit(inlineForm)); }

    const modalForm = $('#transactionFormModal');
    if(modalForm){ modalForm.addEventListener('submit', onTransactionSubmit(modalForm)); }

    registerTypeWatcher('#transactionTypeInline', '#transactionMember');
    registerTypeWatcher('#transactionTypeModal', '#transactionModal select[name="name"]');
  }

  function loadTransactions(){
    fetchTransactions()
      .then(() => {
        populateTransactionFilter();
        renderTransactions();
      })
      .catch(() => {
        const tbody = $('#transactionsTable');
        if(tbody){
          tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-red-400">Fehler beim Laden</td></tr>';
        }
      });
  }

  function onTransactionSubmit(form){
    return async event => {
      event.preventDefault();
      const formData = new FormData(form);
      const type = formData.get('type');
      const isGroup = type === 'Gruppenaktion';
      if(isGroup){
        formData.set('name', 'ALL');
      }
      try{
        const response = await fetch('/api/add_transaction.php', {
          method: 'POST',
          body: new URLSearchParams(formData)
        });
        const result = await response.json();
        const messageTarget = form.querySelector('p[id$="Msg"]');
        if(result.status === 'ok'){
          if(messageTarget){
            messageTarget.textContent = 'Gespeichert ✅';
            messageTarget.className = 'text-sm text-emerald-400 text-center';
          }
          form.reset();
          populateMemberSelects();
          loadTransactions();
          refreshCashBalance();
        }else{
          if(messageTarget){
            messageTarget.textContent = result.error || 'Fehler beim Speichern';
            messageTarget.className = 'text-sm text-red-400 text-center';
          }
        }
      }catch(err){
        alert('Fehler beim Speichern der Transaktion');
      }
    };
  }

  function deleteTransaction(id){
    fetch('/api/delete_transaction.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ id })
    })
      .then(res => res.json())
      .then(result => {
        if(result.status === 'ok'){
          loadTransactions();
          refreshCashBalance();
        }else{
          alert(result.error || 'Fehler beim Löschen');
        }
      })
      .catch(() => alert('Fehler beim Löschen der Transaktion'));
  }

  function openTransactionModal(){
    const modal = $('#transactionModal');
    if(!modal){ return; }
    modal.classList.remove('hidden');
    modal.classList.add('backdrop-blur-sm');
  }

  function closeTransactionModal(){
    const modal = $('#transactionModal');
    if(!modal){ return; }
    modal.classList.add('hidden');
    modal.classList.remove('backdrop-blur-sm');
  }

  function initShiftsPage(){
    if(document.body.dataset.adminPage !== 'schichten'){ return; }

    const reloadBtn = $('#reloadShifts');
    if(reloadBtn){ reloadBtn.addEventListener('click', loadShifts); }
    loadShifts();
  }

  function loadShifts(){
    fetchShifts()
      .then(shifts => {
        const tbody = $('#shiftsRows');
        if(!tbody){ return; }
        tbody.innerHTML = '';
        if(shifts.length === 0){
          tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-white/50">Keine Schichten gefunden</td></tr>';
          return;
        }
        const today = new Date().toISOString().split('T')[0];
        shifts.filter(shift => (shift.shift_date || '').startsWith(today)).forEach(shift => {
          const row = document.createElement('tr');
          row.className = 'border-b border-white/5 hover:bg-white/5 transition-colors';
          row.innerHTML = `
            <td class="py-3 px-2">${escapeHtml(shift.member_name || shift.name || 'N/A')}</td>
            <td class="py-3 px-2">${escapeHtml(shift.shift_date || 'N/A')}</td>
            <td class="py-3 px-2">${escapeHtml(shift.shift_start || '—')}</td>
            <td class="py-3 px-2">${escapeHtml(shift.shift_end || '—')}</td>
            <td class="py-3 px-2 text-right">
              <button data-del-shift="${shift.id}" class="px-2 py-1 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-200 text-xs">🗑️</button>
            </td>
          `;
          tbody.appendChild(row);
        });

        tbody.addEventListener('click', event => {
          const button = event.target.closest('button[data-del-shift]');
          if(!button){ return; }
          if(!confirm('Schicht wirklich löschen?')){ return; }
          const id = button.getAttribute('data-del-shift');
          fetch('/api/delete_shift.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id })
          })
            .then(res => res.json())
            .then(result => {
              if(result.status === 'ok'){
                loadShifts();
              }else{
                alert(result.error || 'Fehler beim Löschen');
              }
            })
            .catch(() => alert('Fehler beim Löschen der Schicht'));
        }, { once: true });
      })
      .catch(() => {
        const tbody = $('#shiftsRows');
        if(tbody){
          tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-red-400">Fehler beim Laden</td></tr>';
        }
      });
  }

  function initBoardPage(){
    if(document.body.dataset.adminPage !== 'board'){ return; }
    loadBoard();
    const reloadBtn = $('#reloadBoard');
    if(reloadBtn){ reloadBtn.addEventListener('click', loadBoard); }
    const form = $('#boardForm');
    if(form){ form.addEventListener('submit', submitBoardEntry); }
    const list = $('#boardList');
    if(list){
      list.addEventListener('click', event => {
        const button = event.target.closest('button[data-del-board]');
        if(!button){ return; }
        if(!confirm('Eintrag wirklich löschen?')){ return; }
        const id = button.getAttribute('data-del-board');
        fetch('/api/admin_board_delete.php', {
          method: 'POST',
          body: new URLSearchParams({ id })
        })
          .then(res => res.json())
          .then(result => {
            if(result.status === 'ok'){
              loadBoard();
            }else{
              alert(result.error || 'Fehler beim Löschen');
            }
          })
          .catch(() => alert('Fehler beim Löschen'));
      });
    }
  }

  function loadBoard(){
    fetchBoard()
      .then(entries => {
        const list = $('#boardList');
        if(!list){ return; }
        if(entries.length === 0){
          list.innerHTML = '<div class="text-white/50 text-sm">Noch keine Einträge vorhanden.</div>';
          return;
        }
        const dateFmt = new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' });
        list.innerHTML = '';
        entries.forEach(entry => {
          const card = document.createElement('div');
          const badgeClass = entry.type === 'event' ? 'bg-rose-500/20 text-rose-200' : 'bg-sky-500/20 text-sky-200';
          const badgeLabel = entry.type === 'event' ? 'Event' : 'Ankündigung';
          const schedule = entry.scheduled_for ? dateFmt.format(new Date(entry.scheduled_for)) : null;
          card.className = 'glass rounded-2xl p-6 space-y-4 relative';
          card.innerHTML = `
            <div class="flex items-start justify-between gap-4">
              <span class="px-2 py-1 rounded-lg text-xs font-semibold ${badgeClass}">${badgeLabel}</span>
              <button data-del-board="${entry.id}" class="px-2 py-1 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-200 text-xs">🗑️</button>
            </div>
            <div>
              <h3 class="text-lg font-semibold">${escapeHtml(entry.title)}</h3>
              ${schedule ? `<div class="text-sm text-white/60 mt-1">${schedule}</div>` : ''}
            </div>
            <p class="text-sm text-white/70 whitespace-pre-line">${escapeHtml(entry.content || '')}</p>
            <div class="text-xs text-white/40 flex justify-between">
              <span>von ${escapeHtml(entry.created_by || 'System')}</span>
              <span>${dateFmt.format(new Date(entry.created_at))}</span>
            </div>
          `;
          list.appendChild(card);
        });
      })
      .catch(() => {
        const list = $('#boardList');
        if(list){
          list.innerHTML = '<div class="text-red-400 text-sm">Fehler beim Laden des Boards.</div>';
        }
      });
  }

  function submitBoardEntry(event){
    event.preventDefault();
    const form = event.currentTarget;
    const msg = $('#boardFormMsg');
    const formData = new FormData(form);
    fetch('/api/admin_board_create.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(result => {
        if(result.status === 'ok'){
          if(msg){
            msg.textContent = 'Gespeichert ✅';
            msg.className = 'text-sm text-emerald-400 text-center';
          }
          form.reset();
          loadBoard();
        }else{
          if(msg){
            msg.textContent = result.error || 'Fehler beim Speichern';
            msg.className = 'text-sm text-red-400 text-center';
          }
        }
      })
      .catch(() => {
        if(msg){
          msg.textContent = 'Fehler beim Speichern';
          msg.className = 'text-sm text-red-400 text-center';
        }
      });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initParticles();
    initNavToggle();
    initDashboard();
    initTransactionsPage();
    initShiftsPage();
    initBoardPage();
  });
})();
