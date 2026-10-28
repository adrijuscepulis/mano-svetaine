// ThrowHouse Šiauliai - Dinaminis svetainės nustatymų sinchronizavimas
(function() {
  window.SITE_SETTINGS = null;

  window.getWorkingHoursForDate = function(d, settings) {
    if (!d) d = new Date();
    if (typeof d === 'string') {
      d = new Date(d.includes('T') ? d : d + 'T00:00:00');
    }
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const iso = `${yyyy}-${mm}-${dd}`;
    const dayOfWeek = String(d.getDay());

    const s = settings || window.SITE_SETTINGS || {};
    const sched = s.schedule || null;

    if (sched && sched.custom_dates && sched.custom_dates[iso]) {
      const cd = sched.custom_dates[iso];
      return {
        enabled: cd.enabled !== false,
        start: Number(cd.start ?? 16),
        end: Number(cd.end ?? 22),
        is_custom: true
      };
    }

    if (sched && sched.days && sched.days[dayOfWeek]) {
      const sd = sched.days[dayOfWeek];
      return {
        enabled: sd.enabled !== false,
        start: Number(sd.start ?? 16),
        end: Number(sd.end ?? 22),
        is_custom: false
      };
    }

    if (dayOfWeek === '0') return { enabled: true, start: 9, end: 20, is_custom: false };
    if (dayOfWeek === '5' || dayOfWeek === '6') return { enabled: true, start: 9, end: 24, is_custom: false };
    return { enabled: true, start: 16, end: 22, is_custom: false };
  };

  async function loadSettings() {
    try {
      let res = await fetch('settings.php?v=' + Date.now(), { cache: 'no-store' });
      if (!res || !res.ok) {
        res = await fetch('nustatymai.json?v=' + Date.now(), { cache: 'no-store' });
      }
      if (!res || !res.ok) return;
      const data = await res.json();
      window.SITE_SETTINGS = data;
      applySettings(data);
    } catch (e) {
      console.warn('THROW_HOUSE: Nepavyko užkrauti nustatymų failo, naudojami standartiniai.', e);
    }
  }

  let promoIntervalId = null;

  function isPromoActive(pr) {
    if (!pr || !pr.enabled) return false;
    const endTs = new Date(pr.end_time).getTime();
    return !isNaN(endTs) && endTs > Date.now();
  }

  function applySettings(s) {
    if (!s) return;

    // 1. ŽAIBIŠKA AKCIJA ARBA STANDARTINIS BANERIS
    applyPromo(s.promo, s.prices, s.banner);

    // 2. KONTAKTAI IR NUORODOS
    applyContacts(s.contacts);

    // 3. DARBO LAIKAS
    applyHours(s.hours);

    // 4. D.U.K. (FAQ)
    applyFaq(s.faq);

    // Jei puslapyje yra rezervacijos skaičiuoklė, perskaičiuoti
    if (typeof calcTotal === 'function') {
      try { calcTotal(); } catch (err) {}
    }

    // Jei kalendoriuje pasirinkta data, atnaujiname laikus pagal grafiką
    if (typeof populateTimeSlots === 'function' && typeof selectedDate !== 'undefined' && selectedDate) {
      try { populateTimeSlots(selectedDate); } catch(err) {}
    }
    if (typeof renderCalendar === 'function') {
      try { renderCalendar(); } catch(err) {}
    }
  }

  function applyPromo(promo, prices, banner) {
    if (promoIntervalId) {
      clearInterval(promoIntervalId);
      promoIntervalId = null;
    }

    const promoNotice = document.getElementById('promoNoticeBox');
    const actSmallRage = document.getElementById('actPriceRage');
    const actSmallAxe = document.getElementById('actPriceAxe');
    const actSmallCombo = document.getElementById('actPriceCombo');

    const active = isPromoActive(promo);

    if (!active) {
      if (promoNotice) promoNotice.style.display = 'none';
      if (actSmallRage) actSmallRage.textContent = `${prices?.rage_price || 30} € (30 indų)`;
      if (actSmallAxe) actSmallAxe.textContent = `${prices?.axe_price || 30} € (2 tak.)`;
      if (actSmallCombo) actSmallCombo.textContent = `${prices?.combo_price || 50} €`;
      applyStandardPrices(prices);
      applyBanner(banner);
      return;
    }

    const discount = Number(promo.discount) || 5;
    const endTs = new Date(promo.end_time).getTime();

    // 1. Informacinė lentelė virš rezervacijos formos
    if (promoNotice) {
      promoNotice.style.display = 'block';
      const titleEl = promoNotice.querySelector('.promo-notice-title');
      const descEl = promoNotice.querySelector('.promo-notice-desc');
      if (titleEl) titleEl.textContent = promo.title || 'Visoms sesijoms ir dovanų kuponams taikoma 5 € nuolaida!';
      if (descEl) descEl.textContent = promo.subtitle || 'Nuolaida jau automatiškai pritaikyta žemiau esančioms kainoms.';
    }

    // 2. Kainos pasirinkimo mygtukuose formoje
    const rageBase = Number(prices?.rage_price || 30);
    const axeBase = Number(prices?.axe_price || 30);
    const comboBase = Number(prices?.combo_price || 50);

    if (actSmallRage) actSmallRage.innerHTML = `<del style="opacity:0.6;">${rageBase} €</del> <strong style="color:#22c55e;">${rageBase - discount} €</strong> (30 indų)`;
    if (actSmallAxe) actSmallAxe.innerHTML = `<del style="opacity:0.6;">${axeBase} €</del> <strong style="color:#22c55e;">${axeBase - discount} €</strong> (2 tak.)`;
    if (actSmallCombo) actSmallCombo.innerHTML = `<del style="opacity:0.6;">${comboBase} €</del> <strong style="color:#22c55e;">${comboBase - discount} €</strong>`;

    // 3. Kainoraščio kortelės (#kainos)
    const rEl = document.getElementById('priceCardRageCost');
    if (rEl) rEl.innerHTML = `<del style="font-size:1.3rem;opacity:0.5;margin-right:6px;">${rageBase} €</del> ${rageBase - discount} <span>€</span> <span style="font-size:0.75rem;background:#22c55e;color:#052e16;font-weight:800;padding:2px 7px;border-radius:999px;vertical-align:middle;margin-left:6px;">-${discount} € AKCIJA</span>`;

    const aEl = document.getElementById('priceCardAxeCost');
    if (aEl) aEl.innerHTML = `<del style="font-size:1.3rem;opacity:0.5;margin-right:6px;">${axeBase} €</del> ${axeBase - discount} <span>€</span> <span style="font-size:0.75rem;background:#22c55e;color:#052e16;font-weight:800;padding:2px 7px;border-radius:999px;vertical-align:middle;margin-left:6px;">-${discount} € AKCIJA</span>`;

    const cEl = document.getElementById('priceCardComboCost');
    if (cEl) cEl.innerHTML = `<del style="font-size:1.3rem;opacity:0.5;margin-right:6px;">${comboBase} €</del> ${comboBase - discount} <span>€</span> <span style="font-size:0.75rem;background:#22c55e;color:#052e16;font-weight:800;padding:2px 7px;border-radius:999px;vertical-align:middle;margin-left:6px;">-${discount} € AKCIJA</span>`;

    if (prices?.pricing_subtitle) {
      document.querySelectorAll('[data-cms="pricing_subtitle"]').forEach(el => el.textContent = prices.pricing_subtitle);
    }

    const rageBtn = document.getElementById('btnBookRage');
    if (rageBtn) rageBtn.textContent = `Rezervuoti (${rageBase - discount} € 🔥)`;
    const axeBtn = document.getElementById('btnBookAxe');
    if (axeBtn) axeBtn.textContent = `Rezervuoti (${axeBase - discount} € 🔥)`;
    const comboBtn = document.getElementById('btnBookCombo');
    if (comboBtn) comboBtn.textContent = `Rezervuoti KOMBO (${comboBase - discount} € 🔥)`;

    // 3.1. Pramogų kortelės (#pramogos)
    const actRagePrice = document.getElementById('promoActRagePrice');
    if (actRagePrice) {
      actRagePrice.innerHTML = `
        Kaina:
        <div style="display: flex; align-items: baseline; gap: 6px; margin: 3px 0;">
          <del style="opacity: 0.55; font-size: 1.15rem; text-decoration: line-through; color: var(--text-gray);">${rageBase} €</del>
          <strong style="display: inline-block; color: #22c55e; font-size: 1.65rem; font-family: var(--font-head); line-height: 1;">${rageBase - discount} €</strong>
          <span style="font-size: 0.72rem; background: #22c55e; color: #052e16; font-weight: 800; padding: 2px 6px; border-radius: 4px; vertical-align: middle;">-${discount} € AKCIJA</span>
        </div>
        <span class="capacity-badge">30 indų | Iki 4 asm.</span>
      `;
    }
    const actAxePrice = document.getElementById('promoActAxePrice');
    if (actAxePrice) {
      actAxePrice.innerHTML = `
        Kaina:
        <div style="display: flex; align-items: baseline; gap: 6px; margin: 3px 0;">
          <del style="opacity: 0.55; font-size: 1.15rem; text-decoration: line-through; color: var(--text-gray);">${axeBase} €</del>
          <strong style="display: inline-block; color: #22c55e; font-size: 1.65rem; font-family: var(--font-head); line-height: 1;">${axeBase - discount} €</strong>
          <span style="font-size: 0.72rem; background: #22c55e; color: #052e16; font-weight: 800; padding: 2px 6px; border-radius: 4px; vertical-align: middle;">-${discount} € AKCIJA</span>
        </div>
        <span class="capacity-badge">2 takeliai | Iki 4 asm.</span>
      `;
    }
    const actRageBtn = document.getElementById('btnActRage');
    if (actRageBtn) actRageBtn.textContent = `Rezervuoti (${rageBase - discount} € 🔥)`;
    const actAxeBtn = document.getElementById('btnActAxe');
    if (actAxeBtn) actAxeBtn.textContent = `Rezervuoti (${axeBase - discount} € 🔥)`;

    // 4. Kuponų sumos (#kuponai)
    if (!promo || promo.apply_to_coupons !== false) {
      const g30 = document.getElementById('giftCard30Price');
      if (g30) g30.innerHTML = `<del style="opacity:0.5;font-size:1.15rem;margin-right:6px;">30 €</del> <span style="color:#22c55e;font-weight:800;">25 €</span> <span style="font-size:0.75rem;background:#22c55e;color:#052e16;padding:2px 6px;border-radius:6px;font-weight:800;vertical-align:middle;margin-left:6px;">-5 €</span>`;
      const g50 = document.getElementById('giftCard50Price');
      if (g50) g50.innerHTML = `<del style="opacity:0.5;font-size:1.15rem;margin-right:6px;">50 €</del> <span style="color:#22c55e;font-weight:800;">45 €</span> <span style="font-size:0.75rem;background:#22c55e;color:#052e16;padding:2px 6px;border-radius:6px;font-weight:800;vertical-align:middle;margin-left:6px;">-5 €</span>`;
    }

    // 5. Viršutinis baneris su gyvu atgaliniu laikmačiu
    let bannerEl = document.getElementById('topAnnouncementBanner');
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
      background: linear-gradient(90deg, #ff6a00 0%, #ff8c00 50%, #ea580c 100%);
      color: #08050e;
      padding: 10px 16px;
      font-size: 0.92rem;
      font-weight: 800;
      text-align: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 4px 20px rgba(255, 106, 0, 0.4);
      transition: all 0.2s ease;
    `;

    function updateTick() {
      const remaining = endTs - Date.now();
      if (remaining <= 0) {
        if (promoIntervalId) clearInterval(promoIntervalId);
        promo.enabled = false;
        applyPromo(promo, prices, banner);
        if (typeof calcTotal === 'function') calcTotal();
        return;
      }

      const totalHours = Math.floor(remaining / (3600 * 1000));
      const mins = Math.floor((remaining % (3600 * 1000)) / (60 * 1000));
      const secs = Math.floor((remaining % (60 * 1000)) / 1000);

      const timerFormatted = `${totalHours} val. ${String(mins).padStart(2, '0')} min. ${String(secs).padStart(2, '0')} s.`;
      const timerShort = `${String(totalHours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;

      // Banerio turinys
      bannerEl.innerHTML = `
        <span style="display:inline-flex; align-items:center; gap:6px;">
          <span>🔥</span> <strong>ŽAIBIŠKA AKCIJA: -${discount} € VISOMS REZERVACIJOMS IR KUPONAMS!</strong>
        </span>
        <span style="background:#08050e; color:#ffaa00; padding:3px 10px; border-radius:6px; font-family:monospace; font-size:0.95rem; font-weight:800; border:1px solid rgba(255,255,255,0.2);">
          ⏳ Liko: ${timerShort}
        </span>
        <a href="#rezervacija" style="background:#08050e; color:#ffffff; padding:4px 14px; border-radius:6px; font-size:0.84rem; text-decoration:none; font-weight:800; display:inline-flex; align-items:center; gap:4px; border:1px solid rgba(255,255,255,0.25);">
          Rezervuoti su nuolaida ➔
        </a>
      `;

      // Informacinės lentelės laikmatis
      const formTimerEl = document.getElementById('promoCountdownTimer');
      if (formTimerEl) {
        formTimerEl.textContent = timerFormatted;
      }
    }

    updateTick();
    promoIntervalId = setInterval(updateTick, 1000);

    setTimeout(() => {
      const bannerH = bannerEl.offsetHeight;
      const header = document.querySelector('.header');
      if (header && bannerH) {
        header.style.top = bannerH + 'px';
      }
    }, 50);
  }

  function applyStandardPrices(p) {
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

    // Pramogų kortelės (#pramogos)
    const actRagePrice = document.getElementById('promoActRagePrice');
    if (actRagePrice) {
      actRagePrice.innerHTML = `Kaina: <strong>${p.rage_price} €</strong> <span class="capacity-badge">30 indų | Iki 4 asm.</span>`;
    }
    const actAxePrice = document.getElementById('promoActAxePrice');
    if (actAxePrice) {
      actAxePrice.innerHTML = `Kaina: <strong>${p.axe_price} €</strong> <span class="capacity-badge">2 takeliai | Iki 4 asm.</span>`;
    }
    const actRageBtn = document.getElementById('btnActRage');
    if (actRageBtn) actRageBtn.textContent = `Rezervuoti pyktį`;
    const actAxeBtn = document.getElementById('btnActAxe');
    if (actAxeBtn) actAxeBtn.textContent = `Rezervuoti taikinį`;

    // Kuponų sumos
    const g30 = document.getElementById('giftCard30Price');
    if (g30) g30.textContent = `${p.rage_price} €`;
    const g50 = document.getElementById('giftCard50Price');
    if (g50) g50.textContent = `${p.combo_price} €`;
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
    const summaryStr = `I-IV: ${h.workdays || '16:00-22:00'} | V-VI: 09:00-24:00 | VII: ${h.sunday || '09:00-20:00'}`;

    document.querySelectorAll('[data-cms="hours_summary"]').forEach(el => {
      el.innerHTML = `⏱️ <strong>Darbo laikas:</strong> ${h.workdays || 'I-IV: 16:00-22:00'} | V-VI: 09:00-24:00 | VII: ${h.sunday || '09:00-20:00'}`;
    });

    document.querySelectorAll('[data-cms="hours_workdays"]').forEach(el => el.textContent = h.workdays);
    document.querySelectorAll('[data-cms="hours_friday"]').forEach(el => el.textContent = h.friday);
    document.querySelectorAll('[data-cms="hours_saturday"]').forEach(el => el.textContent = h.saturday);
    document.querySelectorAll('[data-cms="hours_sunday"]').forEach(el => el.textContent = h.sunday);
    if (h.note) {
      document.querySelectorAll('[data-cms="hours_note"]').forEach(el => el.textContent = h.note);
    }
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
