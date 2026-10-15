/* RG PMS - JS helpers + Sidebar toggle + Theme toggle + Translate */

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.alert-dismissible').forEach(function (el) {
    setTimeout(function () {
      var close = el.querySelector('.btn-close');
      if (close) close.click();
    }, 6000);
  });

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.confirm(form.dataset.confirm)) ev.preventDefault();
    });
  });

  var markAllBtn = document.getElementById('markAllRead');
  if (markAllBtn) {
    markAllBtn.addEventListener('click', function () {
      fetch(markAllBtn.dataset.url, { method: 'POST', headers: { 'X-Requested-With': 'fetch' } })
        .then(function () { window.location.reload(); });
    });
  }

  // ---- SIDEBAR TOGGLE (mobile) ----
  var sidebarToggle   = document.getElementById('sidebarToggle');
  var sidebarBackdrop = document.getElementById('sidebarBackdrop');

  function closeSidebar() { document.body.classList.remove('sidebar-open'); }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
      document.body.classList.toggle('sidebar-open');
    });
  }
  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeSidebar);
  }
  document.querySelectorAll('.app-sidebar .nav-link').forEach(function (link) {
    if (link.hasAttribute('data-bs-toggle')) return;
    link.addEventListener('click', function () {
      if (window.innerWidth < 992) closeSidebar();
    });
  });

  // ---- THEME TOGGLE + BUSINESS FACTS ----
  var themeBtn  = document.getElementById('themeToggle');
  var toast     = document.getElementById('factToast');
  var toastText = document.getElementById('factToastText');

  var businessFacts = [
    "The RG PMS was Developed by Quantix Solutions",
    "RG Trade and Projects was established in May 2025.",
    "We specialise in construction services, civil engineering, and the supply of construction materials.",
    "Our team has four dedicated professionals - two Project Managers (one specialising in civil engineering), a Quantity Surveyor, and an Administrator.",
    "Every project includes progress meetings with the client present.",
    "Programme and financial reports are issued to clients on a monthly basis.",
    "Our commercial dashboards track project budgets and spend-to-date in real time.",
    "When delays or material shortages arise, we assess impact on programme, cost, and deliverables before escalating to the client.",
    "Baseline programmes and cashflows are generated at the start of every project to track progress and expenditure.",
    "We implement mitigation measures such as sourcing alternative materials, adjusting programmes, and resequencing works.",
    "The Quantix team members are Reabetswe Ubane, Mapaseka Lerutla, Harmony Mashau, Botlhale Nthithe and Alex Fon Nche",
    "Cost reports, activity schedules, and material tracking registers are core to our project controls.",
    "Meeting minutes and commercial dashboards form part of every monthly client pack."
  ];

  function showFact() {
    if (!toast || !toastText) return;
    var fact = businessFacts[Math.floor(Math.random() * businessFacts.length)];
    toastText.textContent = fact;
    toast.classList.add('show');
    clearTimeout(window.__factTimer);
    window.__factTimer = setTimeout(function () {
      toast.classList.remove('show');
    }, 6500);
  }

  // Note: initial theme class is already applied by the inline script in <head>.
  // We only handle the CLICK here (with animation + fact toast).
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var html = document.documentElement;
      var isDark = html.classList.contains('theme-dark');
      var next = isDark ? 'light' : 'dark';

      // Enable transitions only during the toggle
      html.classList.add('theme-animating');

      html.classList.toggle('theme-dark', next === 'dark');
      try { localStorage.setItem('rgpms_theme', next); } catch(e) {}

      // Show random business fact
      showFact();

      // Remove transition class after animation ends
      setTimeout(function () {
        html.classList.remove('theme-animating');
      }, 400);
    });
  }

  // ---- GOOGLE TRANSLATE (SA languages) ----
  window.googleTranslateElementInit = function() {
    var el = document.getElementById('google_translate_element');
    if (!el) return;
    new google.translate.TranslateElement({
      pageLanguage: 'en',
      includedLanguages: 'af,en,zu,xh,st,tn,nso,ts,ss,ve,nr',
      layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
      autoDisplay: false
    }, 'google_translate_element');
  };
});