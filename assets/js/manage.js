(function(){
    // клики с маленькой анимацией
    document.querySelectorAll('.action-button, .bottom-action').forEach(btn=>{
        btn.addEventListener('click', function(){
            this.style.transform = 'scale(0.97)';
            setTimeout(()=>{ this.style.transform = 'scale(1)'; }, 150);
        });
    });

    // контакты
    document.querySelectorAll('.contact-item').forEach(item=>{
        item.addEventListener('click', function(){
            const type = this.getAttribute('data-type');
            const val = this.getAttribute('data-value') || '';
            if (!type || !val) return;
            if (type === 'tel') window.location.href = 'tel:'+val;
            if (type === 'web') window.open(val, '_blank', 'noopener');
            if (type === 'tg')  window.open(val, '_blank', 'noopener');
            if (type === 'addr') {
                const url = 'https://www.google.com/maps/dir/?api=1&destination='+encodeURIComponent(val);
                window.open(url, '_blank', 'noopener');
            }
        });
    });

    // карта
    const holder = document.getElementById('bm-map');
    const addr = window.AltegoManage && AltegoManage.address ? AltegoManage.address : '';
    if (holder && addr && window.L) {
        fetch('https://nominatim.openstreetmap.org/search?format=json&q='+encodeURIComponent(addr), { headers:{'Accept':'application/json'} })
            .then(r=>r.json())
            .then(list=>{
                if (!list || !list[0]) return;
                const lat = parseFloat(list[0].lat), lon = parseFloat(list[0].lon);
                const map = L.map('bm-map', { zoomControl:false }).setView([lat, lon], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19, attribution: '&copy; OpenStreetMap'
                }).addTo(map);
                L.marker([lat, lon]).addTo(map).bindPopup(AltegoManage.markerTitle || 'Location');
            }).catch(()=>{});
    }
})();
