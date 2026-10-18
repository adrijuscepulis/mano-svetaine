// ThrowHouse Šiauliai - Dinaminis svetainės nustatymų sinchronizavimas
(function() {
  window.SITE_SETTINGS = null;

  async function loadSettings() {
    try {
      const res = await fetch('nustatymai.json?v=' + Date.now(), { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      window.SITE_SETTINGS = data;
      applySettings(data);
    } catch (e) {
      console.warn('THROW_HOUSE: Nepavyko užkrauti nustatymų failo, naudojami standartiniai.', e);
    }
  }

  function applySettings(s) {
    if (!s) return;

    // 1. PRANEŠIMŲ JUOSTA (TOP BANNER)
    applyBanner(s.banner);

    // 2. KONTAKTAI IR NUORODOS
    applyContacts(s.contacts);

    // 3. DARBO LAIKAS
    applyHours(s.hours);

    // 4. PRAMOGŲ KAINOS
    applyPrices(s.prices);

    // 5. D.U.K. (FAQ)
    applyFaq(s.faq);

    // Jei puslapyje yra rezervacijos skaičiuoklė, perskaičiuoti
    if (typeof calcTotal === 'function') {
      try { calcTotal(); } catch (err) {}
    }
  }

  function applyBanner(b) {
    let bannerEl = document.getElementById('topAnnouncementBanner');
    if (!b || !b.enabled) {
      if (bannerEl) bannerEl.style.display = 'none';
      document.documentElement.style.removeProperty('--banner-height');
      const header = document.querySelector('.header');
      if (header) header.style.top = '0px';
      return;
    }

    if (!bannerEl) {
      bannerEl = document.createElement('div');
      bannerEl.id = 'topAnnouncementBanner';
      document.body.prepend(bannerEl);
    }

    bannerEl.style.cssText = `
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
      background: ${b.bg_color || '#ff6a00'};
      color: ${b.text_color || '#08050e'};
      padding: 9px 16px;
      font-size: 0.92rem;
      font-weight: 700;
      text-align: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 4px 15px rgba(0,0,0,0.3);
      transition: all 0.2s ease;
    `;

    let html = `<span>${b.text || ''}</span>`;
    if (b.btn_text && b.btn_link) {
      html += `<a href="${b.btn_link}" style="background: #08050e; color: #ffffff; padding: 4px 14px; border-radius: 6px; font-size: 0.82rem; text-decoration: none; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(255,255,255,0.2);">${b.btn_text}</a>`;
    }
    bannerEl.innerHTML = html;

    // Jei header yra fiksuotas, pastumiame jį žemyn tiek, koks banerio aukštis
    setTimeout(() => {
      const bannerH = bannerEl.offsetHeight;
      const header = document.querySelector('.header');
      if (header && bannerH) {
        header.style.top = bannerH + 'px';
      }
    }, 50);
  }

  function applyContacts(c) {
    if (!c) return;

    // Telefonas
    if (c.phone) {
      document.querySelectorAll('[data-cms="phone"]').forEach(el => el.textContent = c.phone);
      document.querySelectorAll('a[href^="tel:"]').forEach(a => a.href = 'tel:' + (c.phone_raw || c.phone.replace(/[^0-9+]/g, '')));
    }

    // El. paštas
    if (c.email) {
      document.querySelectorAll('[data-cms="email"]').forEach(el => el.textContent = c.email);
      document.querySelectorAll('a[href^="mailto:"]').forEach(a => a.href = 'mailto:' + c.email);
    }

    // Adresas
    if (c.address) {
      document.querySelectorAll('[data-cms="address"]').forEach(el => el.textContent = c.address);
    }

    // Google Maps & Waze
    if (c.google_maps_url) {
      document.querySelectorAll('a[href*="google.com/maps"]').forEach(a => a.href = c.google_maps_url);
    }
    if (c.waze_url) {
      document.querySelectorAll('a[href*="waze.com"]').forEach(a => a.href = c.waze_url);
    }

    // Socialiniai tinklai
    if (c.instagram_url) {
      document.querySelectorAll('a[href*="instagram.com"]').forEach(a => a.href = c.instagram_url);
    }
    if (c.facebook_url) {
      document.querySelectorAll('a[href*="facebook.com"]').forEach(a => a.href = c.facebook_url);
    }
    if (c.tiktok_url) {
      document.querySelectorAll('a[href*="tiktok.com"]').forEach(a => a.href = c.tiktok_url);
    }
  }

  function applyHours(h) {
    if (!h) return;

    // Bendra darbo laiko eilutė
    const summaryStr = `V-VI: 09:00-24:00 | VII: ${h.sunday || '09:00-20:00'} | ${h.workdays || 'I-IV: Nedirbame (Jau greitai!)'}`;

    document.querySelectorAll('[data-cms="hours_summary"]').forEach(el => {
      el.innerHTML = `⏱️ <strong>Darbo laikas:</strong> V-VI: 09:00-24:00 | VII: ${h.sunday || '09:00-20:00'} | ${h.workdays || 'I-IV: Nedirbame'} <span style="color:#fbbf24; font-weight:700;">(Jau greitai!)</span>`;
    });

    document.querySelectorAll('[data-cms="hours_workdays"]').forEach(el => el.textContent = h.workdays);
    document.querySelectorAll('[data-cms="hours_friday"]').forEach(el => el.textContent = h.friday);
    document.querySelectorAll('[data-cms="hours_saturday"]').forEach(el => el.textContent = h.saturday);
    document.querySelectorAll('[data-cms="hours_sunday"]').forEach(el => el.textContent = h.sunday);
    if (h.note) {
      document.querySelectorAll('[data-cms="hours_note"]').forEach(el => el.textContent = h.note);
    }
  }

  function applyPrices(p) {
    if (!p) return;

    // Kainų kortelės
    const rEl = document.getElementById('priceCardRageCost');
    if (rEl) rEl.innerHTML = `${p.rage_price} <span>€</span>`;
    const aEl = document.getElementById('priceCardAxeCost');
    if (aEl) aEl.innerHTML = `${p.axe_price} <span>€</span>`;
    const cEl = document.getElementById('priceCardComboCost');
    if (cEl) cEl.innerHTML = `${p.combo_price} <span>€</span>`;

    // Paantraštė
    if (p.pricing_subtitle) {
      document.querySelectorAll('[data-cms="pricing_subtitle"]').forEach(el => el.textContent = p.pricing_subtitle);
    }

    // Mygtukai su kainomis
    const rageBtn = document.getElementById('btnBookRage');
    if (rageBtn) rageBtn.textContent = `Rezervuoti (${p.rage_price} €)`;
    const axeBtn = document.getElementById('btnBookAxe');
    if (axeBtn) axeBtn.textContent = `Rezervuoti (${p.axe_price} €)`;
    const comboBtn = document.getElementById('btnBookCombo');
    if (comboBtn) comboBtn.textContent = `Rezervuoti KOMBO (${p.combo_price} €)`;

    // Kuponų sumos
    const g30 = document.getElementById('giftCard30Price');
    if (g30) g30.textContent = `${p.rage_price} €`;
    const g50 = document.getElementById('giftCard50Price');
    if (g50) g50.textContent = `${p.combo_price} €`;
  }

  function applyFaq(faqList) {
    if (!Array.isArray(faqList) || faqList.length === 0) return;
    const faqContainer = document.getElementById('faqContainer');
    if (!faqContainer) return;

    let html = '';
    faqList.forEach(item => {
      html += `
        <div class="faq-item">
          <div class="faq-q">
            <span>${escapeHtml(item.q)}</span>
            <div class="faq-arrow">▼</div>
          </div>
          <div class="faq-a">
            <p>${escapeHtml(item.a)}</p>
          </div>
        </div>
      `;
    });
    faqContainer.innerHTML = html;

    // Prisegti akordeono paspaudimus
    faqContainer.querySelectorAll('.faq-item').forEach(item => {
      const q = item.querySelector('.faq-q');
      if (q) {
        q.addEventListener('click', () => {
          const isOpen = item.classList.contains('active');
          faqContainer.querySelectorAll('.faq-item').forEach(other => other.classList.remove('active'));
          if (!isOpen) item.classList.add('active');
        });
      }
    });
  }

  function escapeHtml(str) {
    if (!str) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return str.replace(/[&<>"']/g, m => map[m]);
  }

  // Paleidimas kai paruoštas DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadSettings);
  } else {
    loadSettings();
  }
})();
