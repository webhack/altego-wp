(function(){
    const el = document.getElementById('altego-admin-app');
    if(!el) return;

    // util
    async function j(url, opts={}){
        const o = Object.assign({headers:{'Content-Type':'application/json','X-WP-Nonce':AltegoAdmin.nonce}, credentials:'same-origin'}, opts);
        const r = await fetch(url, o);
        const ct = r.headers.get('content-type')||'';
        const body = ct.includes('application/json') ? await r.json() : await r.text();
        if(!r.ok){ throw new Error(body && body.message ? body.message : r.statusText); }
        return body;
    }
    const fmt = (d)=>d.toISOString().slice(0,10);
    function firstDayOfMonth(d){ return new Date(d.getFullYear(), d.getMonth(), 1); }
    function lastDayOfMonth(d){ return new Date(d.getFullYear(), d.getMonth()+1, 0); }
    function addDays(d,n){ const x = new Date(d); x.setDate(x.getDate()+n); return x; }

    // state
    let current = new Date();
    let services = [];
    let staff = [];
    let appts = []; // current month cache
    let daySelected = null;

    // mount
    el.innerHTML = `
    <div class="ag-card" id="left">
      <div class="ag-head">
        <div>
          <button class="ag-b" id="prev">&lt;</button>
          <button class="ag-b" id="today">Today</button>
          <button class="ag-b" id="next">&gt;</button>
        </div>
        <strong id="title"></strong>
      </div>
      <div class="ag-cal" id="cal"></div>
    </div>
    <div id="right">
      <div class="ag-card">
        <h3 class="ag-h">Filters</h3>
        <div class="ag-filters">
          <select id="f_service" class="ag-select"><option value="">Service</option></select>
          <select id="f_staff" class="ag-select"><option value="">Staff</option></select>
          <select id="f_status" class="ag-select">
            <option value="">Status</option>
            <option value="confirmed">confirmed</option>
            <option value="new">new</option>
            <option value="canceled">canceled</option>
            <option value="completed">completed</option>
            <option value="no_show">no_show</option>
          </select>
          <input id="f_from" class="ag-input" type="date">
          <input id="f_to" class="ag-input" type="date">
          <button id="f_apply" class="ag-b primary">Apply</button>
        </div>
        <table class="ag-table" id="tbl">
          <thead><tr>
            <th>Date</th><th>Time</th><th>Service</th><th>Staff</th><th>Client</th><th>Phone</th><th>Status</th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  `;

    const $ = (id)=>document.getElementById(id);
    const cal = $('cal');
    const title = $('title');

    $('prev').onclick = ()=>{ current = new Date(current.getFullYear(), current.getMonth()-1, 1); loadMonth(); };
    $('next').onclick = ()=>{ current = new Date(current.getFullYear(), current.getMonth()+1, 1); loadMonth(); };
    $('today').onclick = ()=>{ current = new Date(); loadMonth(); };

    $('f_apply').onclick = ()=>{ loadList(); };

    // load catalogs
    Promise.all([
        j(`${AltegoAdmin.rest}/services`),
        j(`${AltegoAdmin.rest}/staff`)
    ]).then(([srv, st])=>{
        services = srv.items || [];
        staff = st.items || [];
        fillSelect($('f_service'), services, 'Service');
        fillSelect($('f_staff'), staff, 'Staff');
        loadMonth();
    }).catch(err=>{
        console.error(err);
        loadMonth();
    });

    function fillSelect(sel, items, placeholder){
        const v0 = sel.value;
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        items.forEach(i=>{
            const o = document.createElement('option');
            o.value = i.id;
            o.textContent = i.name;
            sel.appendChild(o);
        });
        if(v0) sel.value = v0;
    }

    async function loadMonth(){
        const start = firstDayOfMonth(current);
        const end = lastDayOfMonth(current);
        title.textContent = start.toLocaleString(undefined, {month:'long', year:'numeric'});
        daySelected = null;

        const df = fmt(start);
        const dt = fmt(end);
        const data = await j(`${AltegoAdmin.rest}/appointments?date_from=${df}&date_to=${dt}`);
        appts = data.items || [];

        renderCalendar(start, end);
        $('f_from').value = df;
        $('f_to').value = dt;
        loadList();
    }
// Monday of the week for a given date in local time
    function startOfWeekMonday(date){
        const y = date.getFullYear();
        const m = date.getMonth();
        const d = date.getDate();
        const local = new Date(y, m, d);
        const wd = local.getDay() === 0 ? 7 : local.getDay(); // Sun becomes 7
        return new Date(y, m, d - (wd - 1)); // always Monday
    }



    function renderCalendar(firstOfMonth){
        const weekDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        const cal = document.getElementById('cal');
        cal.innerHTML = weekDays.map(d=>`<div class="hd">${d}</div>`).join('');

        const gridStart = startOfWeekMonday(firstOfMonth); // must be Mon 2025-07-28 for Aug 2025
        const todayStr  = fmt(gridStart.constructor === Date ? new Date() : new Date());
        const viewMonth = firstOfMonth.getMonth();

        const cells = [];
        const baseY = gridStart.getFullYear();
        const baseM = gridStart.getMonth();
        const baseD = gridStart.getDate();

        for (let i = 0; i < 42; i++) {
            const day = new Date(baseY, baseM, baseD + i); // no incremental mutation
            const dstr = fmt(day);
            const isToday = dstr === fmt(new Date());
            const isOtherMonth = day.getMonth() !== viewMonth;
            const count = (appts || []).filter(a => a.date === dstr).length;

            cells.push(`
      <div class="cell ${isToday ? 'today' : ''} ${isOtherMonth ? 'other' : ''}" data-date="${dstr}" title="${dstr}">
        <span class="d">${day.getDate()}</span>
        <span class="cnt">${count ? count + ' appt' : ''}</span>
      </div>
    `);
        }

        cal.insertAdjacentHTML('beforeend', cells.join(''));
        [...cal.querySelectorAll('.cell')].forEach(cell=>{
            cell.addEventListener('click', ()=>{
                const date = cell.dataset.date;
                document.getElementById('f_from').value = date;
                document.getElementById('f_to').value = date;
                loadList();
            });
        });
    }



    async function loadList(){
        const p = new URLSearchParams();
        const fsvc = $('f_service').value;
        const fstf = $('f_staff').value;
        const fst = $('f_status').value;
        const df = $('f_from').value;
        const dt = $('f_to').value;

        if (fsvc) p.set('service_id', fsvc);
        if (fstf) p.set('staff_id', fstf);
        if (fst) p.set('status', fst);
        if (df) p.set('date_from', df);
        if (dt) p.set('date_to', dt);

        const data = await j(`${AltegoAdmin.rest}/appointments?`+p.toString());
        const items = data.items || [];
        const tb = $('tbl').querySelector('tbody');
        tb.innerHTML = items.map(r=>`
  <tr class="ag-rowlink" data-id="${r.id}">
    <td>${r.date}</td>
    <td>${r.start}–${r.end}</td>
    <td>${esc(r.service_name||'')}</td>
    <td>${esc(r.staff_name||'')}</td>
    <td>${esc(r.client_name||'')}</td>
    <td>${esc(r.client_phone||'')}</td>
    <td>${r.status}</td>
  </tr>
`).join('');
// навесим обработчик
        tb.querySelectorAll('tr.ag-rowlink').forEach(tr=>{
            tr.addEventListener('click', ()=> openEditor(parseInt(tr.dataset.id,10)));
        });

    }

    el.insertAdjacentHTML('beforeend', `
  <div class="ag-modal-back" id="ag_edit_back">
    <div class="ag-modal">
      <h3 class="h">Edit appointment</h3>
      <div class="ag-form">
        <label><span>Date</span><input type="date" id="ed_date"></label>
        <label><span>Start</span><input type="time" id="ed_start"></label>

        <label><span>Service</span><select id="ed_service"></select></label>
        <label><span>Staff</span><select id="ed_staff"></select></label>

        <label><span>Status</span>
          <select id="ed_status">
            <option value="new">new</option>
            <option value="confirmed">confirmed</option>
            <option value="canceled">canceled</option>
            <option value="completed">completed</option>
            <option value="no_show">no_show</option>
          </select>
        </label>
        <div></div>

        <label><span>Client name</span><input type="text" id="ed_client_name"></label>
        <label><span>Client phone</span><input type="text" id="ed_client_phone"></label>
        <label class="full"><span>Client email</span><input type="email" id="ed_client_email"></label>
        <label class="full"><span>Notes</span><textarea id="ed_notes" rows="3"></textarea></label>
      </div>
      <div class="actions">
        <button class="ag-b" id="ed_cancel">Close</button>
        <button class="ag-b primary" id="ed_save">Save</button>
      </div>
    </div>
  </div>
`);

    const back = document.getElementById('ag_edit_back');
    const ed = {
        date:  document.getElementById('ed_date'),
        start: document.getElementById('ed_start'),
        service: document.getElementById('ed_service'),
        staff: document.getElementById('ed_staff'),
        status: document.getElementById('ed_status'),
        cname: document.getElementById('ed_client_name'),
        cphone: document.getElementById('ed_client_phone'),
        cemail: document.getElementById('ed_client_email'),
        notes: document.getElementById('ed_notes'),
    };
    document.getElementById('ed_cancel').onclick = ()=> back.style.display = 'none';

    async function openEditor(id){
        try{
            const row = await j(`${AltegoAdmin.rest}/appointments/${id}`);
            // каталоги
            fillSelect(ed.service, services, 'Service');
            // staff по услуге
            const st = await j(`${AltegoAdmin.rest}/staff?service_id=${row.service_id}`);
            fillSelect(ed.staff, st.items || [], 'Staff');

            ed.date.value = row.date;
            ed.start.value = row.start;
            ed.service.value = row.service_id;
            ed.staff.value = row.staff_id;
            ed.status.value = row.status;
            ed.cname.value = row.client_name || '';
            ed.cphone.value = row.client_phone || '';
            ed.cemail.value = row.client_email || '';
            ed.notes.value = row.notes || '';

            // при смене услуги обновим список сотрудников
            ed.service.onchange = async ()=>{
                const st2 = await j(`${AltegoAdmin.rest}/staff?service_id=${ed.service.value}`);
                fillSelect(ed.staff, st2.items || [], 'Staff');
            };

            back.dataset.id = String(id);
            back.style.display = 'flex';
        }catch(e){
            alert('Failed to load: '+ e.message);
        }
    }

    document.getElementById('ed_save').onclick = async ()=>{
        const id = parseInt(back.dataset.id,10);
        const payload = {
            date: ed.date.value,
            start: ed.start.value,
            service_id: parseInt(ed.service.value || '0',10),
            staff_id: parseInt(ed.staff.value || '0',10),
            status: ed.status.value,
            notes: ed.notes.value || '',
            client: {
                name: ed.cname.value || '',
                phone: ed.cphone.value || '',
                email: ed.cemail.value || ''
            }
        };
        try{
            await j(`${AltegoAdmin.rest}/appointments/${id}`, {
                method: 'PUT',
                body: JSON.stringify(payload)
            });
            back.style.display = 'none';
            // перезагрузим список и календарь
            loadMonth();
        }catch(e){
            alert('Save failed: '+ e.message);
        }
    };


    function esc(s){ return String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
})();
