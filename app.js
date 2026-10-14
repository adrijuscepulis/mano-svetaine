// ThrowHouse Šiauliai - Pagrindinis scenarijus

document.addEventListener('DOMContentLoaded', () => {
  initMobileMenu();
  initFaqAccordion();
  initBookingDefaults();
});

// 1. MOBILUSIS MENIU
function initMobileMenu() {
  const toggleBtn = document.getElementById('mobileToggle');
  const nav = document.getElementById('mainNav');
  const navLinks = document.querySelectorAll('.nav-link, .nav-cta');

  if (!toggleBtn || !nav) return;

  toggleBtn.addEventListener('click', () => {
    nav.classList.toggle('active');
  });

  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      nav.classList.remove('active');
    });
  });
}

// 2. DUK (FAQ) AKORDEONAS
function initFaqAccordion() {
  const faqItems = document.querySelectorAll('.faq-item');

  faqItems.forEach(item => {
    const questionBtn = item.querySelector('.faq-question');
    if (!questionBtn) return;

    questionBtn.addEventListener('click', () => {
      const isActive = item.classList.contains('active');

      // Uždaryti kitus atidarytus
      faqItems.forEach(otherItem => {
        if (otherItem !== item) {
          otherItem.classList.remove('active');
        }
      });

      // Perjungti esamą
      item.classList.toggle('active', !isActive);
    });
  });
}

// 3. REZERVACIJOS SKAIČIUOKLĖ IR NUSTATYMAI
function initBookingDefaults() {
  const dateInput = document.getElementById('bookingDate');
  if (dateInput) {
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    dateInput.value = today;
  }
  updateBookingCalculation();
}

function updateBookingCalculation() {
  const activityRadio = document.querySelector('input[name="activity"]:checked');
  const countSelect = document.getElementById('participantCount');
  const priceDisplay = document.getElementById('calcPrice');

  if (!activityRadio || !countSelect || !priceDisplay) return;

  const activity = activityRadio.value;
  const count = parseInt(countSelect.value, 10);
  let totalPrice = 0;

  if (activity === 'Pykčio kambarys') {
    if (count === 1) totalPrice = 35;
    else if (count === 2) totalPrice = 65;
    else if (count === 3) totalPrice = 90;
    else if (count === 4) totalPrice = 115;
    else if (count === 5) totalPrice = 135;
    else totalPrice = 160;
  } else if (activity === 'Kirvių mėtymas') {
    // 20 € asmeniui
    if (count >= 6) {
      totalPrice = count * 18; // nuolaida grupėms
    } else {
      totalPrice = count * 20;
    }
  } else {
    // KOMBO paketas (Pyktis + Kirviai)
    if (count === 1) totalPrice = 50;
    else if (count === 2) totalPrice = 90;
    else if (count === 3) totalPrice = 130;
    else if (count === 4) totalPrice = 165;
    else if (count >= 6) totalPrice = 220;
    else totalPrice = 195;
  }

  priceDisplay.textContent = totalPrice + ' €';
}

function selectActivityInBooking(activityKey) {
  const radios = document.querySelectorAll('input[name="activity"]');
  radios.forEach(radio => {
    if (activityKey === 'pykcio-kambarys' && radio.value.includes('Pykčio')) {
      radio.checked = true;
    } else if (activityKey === 'kirviu-metymas' && radio.value.includes('Kirvių')) {
      radio.checked = true;
    }
  });
  updateBookingCalculation();
}

function selectPackageInBooking(packageType) {
  const countSelect = document.getElementById('participantCount');
  const radios = document.querySelectorAll('input[name="activity"]');

  if (packageType === 'solo') {
    if (countSelect) countSelect.value = '1';
    radios[0].checked = true;
  } else if (packageType === 'duo') {
    if (countSelect) countSelect.value = '2';
    radios[0].checked = true;
  } else if (packageType === 'vip') {
    if (countSelect) countSelect.value = '6';
    radios[2].checked = true; // Kombo
  }

  updateBookingCalculation();
}

function handleBookingSubmit(event) {
  event.preventDefault();

  const activity = document.querySelector('input[name="activity"]:checked').value;
  const count = document.getElementById('participantCount').value;
  const date = document.getElementById('bookingDate').value;
  const time = document.getElementById('bookingTime').value;
  const name = document.getElementById('customerName').value;
  const phone = document.getElementById('customerPhone').value;
  const email = document.getElementById('customerEmail').value;
  const payMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
  const price = document.getElementById('calcPrice').textContent;

  const form = document.getElementById('bookingForm');
  const successBox = document.getElementById('bookingSuccess');
  const successDetails = document.getElementById('successDetails');

  if (form && successBox && successDetails) {
    form.style.display = 'none';
    successBox.style.display = 'block';

    const payText = payMethod === 'online' ? 'Mokėjimas internetu (Banklink / Kortelė)' : 'Atsiskaitymas atvykus vietoje';

    successDetails.innerHTML = `
      <strong>Užsakymo suvestinė:</strong><br>
      👤 Užsakovas: <strong>${name}</strong> (${phone})<br>
      🎯 Pramoga: <strong>${activity}</strong> (${count} asm.)<br>
      📅 Data ir laikas: <strong>${date} d., ${time} val.</strong><br>
      💰 Preliminari suma: <strong>${price}</strong><br>
      💳 Apmokėjimas: <em>${payText}</em><br><br>
      <em>Detalus patvirtinimas su instrukcija išsiųstas į <strong>${email}</strong>. Lauksime jūsų adresu Pramonės g. 15, Šiauliai!</em>
    `;

    successBox.scrollIntoView({ behavior: 'smooth' });
  }
}

function resetBookingForm() {
  const form = document.getElementById('bookingForm');
  const successBox = document.getElementById('bookingSuccess');
  if (form && successBox) {
    form.reset();
    form.style.display = 'block';
    successBox.style.display = 'none';
    initBookingDefaults();
  }
}
