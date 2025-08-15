(function(){
  const $ = sel => document.querySelector(sel);
  const api = (window.AltegoBooking && AltegoBooking.api) || {};
  const cfg = window.AltegoBooking || { recaptcha: { enabled:false }, otp:{enabled:false} };
  const offDays = (cfg.workhours && Array.isArray(cfg.workhours.offDays)) ? cfg.workhours.offDays : [];
  const weekdayISO = d => ((d.getDay() + 6) % 7) + 1; // 1..7, Пн..Вс


  const els = {
    service: $('#service-select'),
    staff:   $('#staff-select'),
    date:    $('#date-input'),
    grid:    $('#time-grid'),
    name:    $('#customer-name'),
    email:   $('#customer-email'),
    phone:   $('#customer-phone'),
    sendBtn: $('#send-code-btn'),
    vGroup:  $('#verification-group'),
    vInput:  $('#verification-code'),
    vBtn:    $('#verify-btn'),
    vOk:     $('#verification-success'),
    create:  $('#create-appointment-btn'),
    success: $('#appointment-success'),
    manageA: $('#manage-link-a'),
    recaptchaToken: $('#recaptcha-token'),
    otpBlock: $('#otp-block'),
    dp:      $('#dp-popover'),
  };

  let state = {
    service_id: 0,
    staff_id: 0,
    date_iso: '',     // YYYY-MM-DD
    slot: '',         // HH:mm
    otp_verified: !cfg.otp?.enabled,
  };

  // utils
  const pad = n => n < 10 ? '0'+n : ''+n;

  const toIso = ddmmyyyy => {
    const m = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(ddmmyyyy || '');
    if (!m) return '';
    return `${m[3]}-${m[2]}-${m[1]}`;
  };
  const toHuman = iso => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
    if (!m) return '';
    return `${m[3]}.${m[2]}.${m[1]}`;
  };

  const todayMidnight = () => {
    const d = new Date();
    d.setHours(0,0,0,0);
    return d;
  };

  const setDisabled = (el, val) => { if (el) el.disabled = !!val; };
  const clearGrid = () => { els.grid.innerHTML = ''; state.slot = ''; updateCreateDisabled(); };

  function updateCreateDisabled(){
    const ok = state.service_id && state.staff_id && state.date_iso && state.slot &&
        els.name.value.trim() && els.email.value.trim() && els.phone.value.trim() &&
        state.otp_verified;
    setDisabled(els.create, !ok);
  }

  // reCAPTCHA v3
  async function getRecaptchaToken() {
    try {
      if (!cfg.recaptcha?.enabled || !cfg.recaptcha?.siteKey || !window.grecaptcha) return '';
      await window.grecaptcha.ready();
      const token = await window.grecaptcha.execute(cfg.recaptcha.siteKey, {action:'submit'});
      if (els.recaptchaToken) els.recaptchaToken.value = token;
      return token;
    } catch { return ''; }
  }

  // load services
  async function loadServices(){
    try {
      const r = await fetch(api.services, { credentials:'same-origin' });
      const json = await r.json();
      const items = json.items || [];
      els.service.innerHTML = '<option value="">Select a service</option>' +
          items.map(s => `<option value="${s.id}" data-duration="${s.duration}" data-price="${s.price}">${escapeHtml(s.name)}</option>`).join('');
    } catch(e) {
      console.error('services load fail', e);
    }
  }

  // load staff for service
  async function loadStaff(service_id){
    setDisabled(els.staff, true);
    els.staff.innerHTML = '<option value="">Loading...</option>';
    try {
      const url = new URL(api.staff, location.origin);
      url.searchParams.set('service_id', service_id);
      const r = await fetch(url.toString(), { credentials:'same-origin' });
      const json = await r.json();
      const items = json.items || json || [];
      els.staff.innerHTML = '<option value="">Select staff member</option>' +
          items.map(st => `<option value="${st.id}">${escapeHtml(st.name)}</option>`).join('');
      setDisabled(els.staff, false);
    } catch(e) {
      console.error('staff load fail', e);
      els.staff.innerHTML = '<option value="">No staff</option>';
    }
  }

  // load slots
  async function loadSlots(){
    clearGrid();
    if (!state.service_id || !state.staff_id || !state.date_iso) return;
    try {
      const url = new URL(api.slots, location.origin);
      url.searchParams.set('service_id', state.service_id);
      url.searchParams.set('staff_id', state.staff_id);
      url.searchParams.set('date', state.date_iso);
      const r = await fetch(url.toString(), { credentials:'same-origin' });
      const json = await r.json();
      const slots = json.slots || [];
      if (!slots.length) {
        els.grid.innerHTML = '<div class="altego-error" style="grid-column:1/-1;text-align:center">No time slots</div>';
        return;
      }
      els.grid.innerHTML = slots.map(t => `<button type="button" class="time-slot" data-time="${t}">${t}</button>`).join('');
      els.grid.querySelectorAll('.time-slot').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          els.grid.querySelectorAll('.time-slot').forEach(b=>b.classList.remove('selected'));
          btn.classList.add('selected');
          state.slot = btn.dataset.time;
          updateCreateDisabled();
        });
      });
    } catch(e) {
      console.error('slots load fail', e);
      els.grid.innerHTML = '<div class="altego-error" style="grid-column:1/-1;text-align:center">Failed to load slots</div>';
    }
  }

  // OTP
  function toggleOtpUI(showGroup, ok){
    els.vGroup.style.display = showGroup ? '' : 'none';
    els.vOk.style.display = ok ? '' : 'none';
    state.otp_verified = !!ok;
    updateCreateDisabled();
  }
  async function sendOtp(){
    if (!cfg.otp?.enabled) return;
    const phone = els.phone.value.trim();
    const email = els.email.value.trim();
    if (!phone && !email) { alert('Enter phone or email'); return; }

    try {
      const recaptcha = await getRecaptchaToken();
      const r = await fetch(api.otp_send, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        credentials:'same-origin',
        body: JSON.stringify({ phone, email, recaptcha })
      });
      if (!r.ok) {
        const err = await r.json().catch(()=>({message:'Send failed'}));
        alert(err.message || 'Send failed');
        return;
      }
      toggleOtpUI(true, false);
      els.vInput.focus();
    } catch(e) {
      alert('Send failed');
    }
  }

  async function verifyOtp(){
    if (!cfg.otp?.enabled) return;
    const code = els.vInput.value.trim();
    const phone = els.phone.value.trim();
    const email = els.email.value.trim();
    if (!code) { alert('Enter code'); return; }
    try {
      const r = await fetch(api.otp_check, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        credentials:'same-origin',
        body: JSON.stringify({ phone, email, code })
      });
      if (!r.ok) {
        const err = await r.json().catch(()=>({message:'Verify failed'}));
        alert(err.message || 'Verify failed');
        return;
      }
      toggleOtpUI(false, true);
    } catch(e) {
      alert('Verify failed');
    }
  }


  // create appointment
  async function createAppointment(){
    setDisabled(els.create, true);
    try {
      const recaptcha = await getRecaptchaToken();
      const payload = {
        location_id: 1,
        staff_id: state.staff_id,
        service_id: state.service_id,
        date: state.date_iso,
        start: state.slot,
        recaptcha,
        client: {
          name: els.name.value.trim(),
          email: els.email.value.trim(),
          phone: els.phone.value.trim(),
        }
      };
      const r = await fetch(api.create, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        credentials:'same-origin',
        body: JSON.stringify(payload),
      });
      const json = await r.json().catch(()=>null);
      if (!r.ok) {
        const msg = (json && (json.message || json.code)) || 'Create failed';
        alert(msg);
        setDisabled(els.create, false);
        return;
      }
      els.success.style.display = '';
      if (json && json.manage_url) els.manageA.href = json.manage_url;
      els.create.classList.add('time-slot','disabled');
      els.create.textContent = 'Created';
    } catch(e) {
      console.error(e);
      alert('Create failed');
      setDisabled(els.create, false);
    }
  }

  // Datepicker
  const dp = {
    opened: false,
    view: null,   // Date, first day of current month view
    selected: null,
    open(){
      if (this.opened) return;
      this.opened = true;
      els.dp.classList.remove('altego-hidden');
      this.render();
      setTimeout(()=>document.addEventListener('click', onDocClick));
      document.addEventListener('keydown', onKey);
    },
    close(){
      if (!this.opened) return;
      this.opened = false;
      els.dp.classList.add('altego-hidden');
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onKey);
    },
    setMonth(delta){
      const d = this.view || new Date();
      const y = d.getFullYear(), m = d.getMonth();
      const n = new Date(y, m + delta, 1);
      this.view = new Date(n.getFullYear(), n.getMonth(), 1);
      this.render();
    },
    setSelected(d){
      this.selected = new Date(d.getFullYear(), d.getMonth(), d.getDate());
      const iso = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
      const human = `${pad(d.getDate())}.${pad(d.getMonth()+1)}.${d.getFullYear()}`;
      els.date.value = human;
      state.date_iso = iso;
      clearGrid();
      if (state.service_id && state.staff_id) loadSlots();
      updateCreateDisabled();
      this.close();
    },
    render(){
      // header date
      const today = todayMidnight();
      let base = this.view;
      if (!base) {
        if (state.date_iso) {
          const [yy,mm,dd] = state.date_iso.split('-').map(x=>parseInt(x,10));
          base = new Date(yy, mm - 1, 1);
        } else {
          base = new Date();
          base.setDate(1);
        }
        this.view = new Date(base.getFullYear(), base.getMonth(), 1);
      }
      const y = base.getFullYear();
      const m = base.getMonth();

      const monthTitle = base.toLocaleDateString(undefined, { month:'long', year:'numeric' });

      // compute start Monday
      const first = new Date(y, m, 1);
      const offset = (first.getDay() + 6) % 7; // 0=Mon..6=Sun
      const start = new Date(first);
      start.setDate(first.getDate() - offset);

      // selected date
      let selISO = state.date_iso;
      // grid
      let html = '';
      html += `<div class="dp-head">
        <div class="dp-nav">
          <button type="button" class="dp-btn" data-nav="-1" aria-label="Prev">&lt;</button>
          <button type="button" class="dp-btn" data-nav="1" aria-label="Next">&gt;</button>
        </div>
        <div class="dp-title">${escapeHtml(monthTitle)}</div>
        <div style="width:70px"></div>
      </div>`;

      const dows = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
      html += '<div class="dp-week">'+ dows.map(s=>`<div class="dow">${s}</div>`).join('') +'</div>';

      html += '<div class="dp-grid">';
      let cur = new Date(start);
      for (let i=0;i<42;i++){
        const isOther = cur.getMonth() !== m;
        const isToday = cur.getTime() === today.getTime();
        const iso = `${cur.getFullYear()}-${pad(cur.getMonth()+1)}-${pad(cur.getDate())}`;
        const isSelected = selISO && iso === selISO;
        const isPast = cur.getTime() < today.getTime();
        const isOff = offDays.includes(weekdayISO(cur));
        const cls = [
          'dp-day',
          isOther ? 'other' : '',
          isToday ? 'today' : '',
          isSelected ? 'selected' : '',
          (isPast || isOff) ? 'disabled' : ''
        ].filter(Boolean).join(' ');
        html += `<button type="button" class="${cls}" data-date="${iso}" ${(isPast || isOff) ? 'disabled' : ''}>${cur.getDate()}</button>`;
        cur.setDate(cur.getDate() + 1);
      }
      html += '</div>';

      els.dp.innerHTML = html;

      els.dp.querySelectorAll('[data-nav]').forEach(b=>{
        b.addEventListener('click', ()=>{
          const delta = parseInt(b.getAttribute('data-nav'), 10) || 0;
          dp.setMonth(delta);
        });
      });
      els.dp.querySelectorAll('.dp-day:not(.disabled)').forEach(b=>{
        b.addEventListener('click', ()=>{
          const iso = b.getAttribute('data-date');
          const [yy,mm,dd] = iso.split('-').map(x=>parseInt(x,10));
          dp.setSelected(new Date(yy, mm - 1, dd));
        });
      });
    }
  };

  function onDocClick(e){
    const inside = e.target === els.date || e.composedPath().includes(els.dp);
    if (!inside) dp.close();
  }
  function onKey(e){
    if (e.key === 'Escape') dp.close();
  }

  // events
  els.service.addEventListener('change', ()=>{
    state.service_id = parseInt(els.service.value || '0', 10) || 0;
    els.staff.innerHTML = '<option value="">Select staff member</option>';
    setDisabled(els.staff, !state.service_id);
    state.staff_id = 0;
    clearGrid();
    if (state.service_id) loadStaff(state.service_id);
    updateCreateDisabled();
  });

  els.staff.addEventListener('change', ()=>{
    state.staff_id = parseInt(els.staff.value || '0', 10) || 0;
    clearGrid();
    if (state.staff_id && state.date_iso) loadSlots();
    updateCreateDisabled();
  });

  // открытие календаря
  els.date.addEventListener('click', ()=> dp.open());
  els.date.addEventListener('focus', ()=> dp.open());

  // если кто-то все же печатает вручную
  els.date.addEventListener('input', ()=>{
    const iso = toIso(els.date.value.trim());
    state.date_iso = iso;
    clearGrid();
    if (iso && state.service_id && state.staff_id) loadSlots();
    updateCreateDisabled();
  });

  ['keyup','change'].forEach(ev=>{
    els.name.addEventListener(ev, updateCreateDisabled);
    els.email.addEventListener(ev, updateCreateDisabled);
    els.phone.addEventListener(ev, updateCreateDisabled);
  });

  if (cfg.otp?.enabled) {
    els.sendBtn.addEventListener('click', sendOtp);
    els.vBtn.addEventListener('click', verifyOtp);
    els.otpBlock.style.display = '';
  } else {
    els.otpBlock.style.display = '';
    els.vGroup.style.display = 'none';
    els.vOk.style.display = 'none';
  }

  els.create.addEventListener('click', createAppointment);

  // init: today
  const today = new Date();
  els.date.value = `${pad(today.getDate())}.${pad(today.getMonth()+1)}.${today.getFullYear()}`;
  state.date_iso = toIso(els.date.value);

  // preload
  loadServices().then(updateCreateDisabled);

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
})();
