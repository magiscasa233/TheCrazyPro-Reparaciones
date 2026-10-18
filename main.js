/* =====================================================================
   CrazyPro-Reparaciones — JavaScript mínimo e indispensable.
   Tema, menú móvil, botón de WhatsApp guiado, filtros del catálogo,
   aparición al hacer scroll y acciones asíncronas del panel (+1 aliados).
   ===================================================================== */
(function () {
  'use strict';

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- Tema claro / oscuro ---------------------------------- */
  var botonTema = $('#tema-toggle');
  if (botonTema) {
    botonTema.addEventListener('click', function () {
      var actual = document.documentElement.getAttribute('data-tema') === 'claro' ? 'claro' : 'oscuro';
      var nuevo  = actual === 'claro' ? 'oscuro' : 'claro';
      document.documentElement.setAttribute('data-tema', nuevo);
      try { localStorage.setItem('cp_tema', nuevo); } catch (e) {}
      document.cookie = 'cp_tema=' + nuevo + ';path=/;max-age=31536000;samesite=lax';

      // Si hay sesión iniciada, guarda la preferencia en el perfil.
      if (document.body.dataset.logueado === '1') {
        var fd = new FormData();
        fd.append('tema', nuevo);
        fd.append('_csrf', document.body.dataset.csrf || '');
        fetch(document.body.dataset.base + '/cliente/cliente.php?view=tema', {
          method: 'POST', body: fd, credentials: 'same-origin'
        }).catch(function () {});
      }
    });
  }

  /* ---------- Menú móvil (sitio público) ----------------------------- */
  var toggle = $('.nav-toggle:not(#panel-toggle)');
  var nav = $('#nav-principal');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var abierto = nav.classList.toggle('abierto');
      toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    nav.addEventListener('click', function (ev) {
      if (ev.target.tagName === 'A') {
        nav.classList.remove('abierto');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------- Menú móvil (panel de administrador) --------------------- */
  var panelToggle = $('#panel-toggle');
  var panelLateral = $('#panel-lateral');
  if (panelToggle && panelLateral) {
    panelToggle.addEventListener('click', function () {
      var abierto = panelLateral.classList.toggle('abierto');
      panelToggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    panelLateral.addEventListener('click', function (ev) {
      if (ev.target.tagName === 'A') {
        panelLateral.classList.remove('abierto');
        panelToggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------- Botón de WhatsApp con mensaje armado ------------------- */
  var waAbrir  = $('#wa-abrir');
  var waPanel  = $('#wa-panel');
  var waCerrar = $('#wa-cerrar');

  if (waAbrir && waPanel) {
    waAbrir.addEventListener('click', function () {
      var visible = !waPanel.hasAttribute('hidden');
      if (visible) { waPanel.setAttribute('hidden', ''); } else { waPanel.removeAttribute('hidden'); }
      waAbrir.setAttribute('aria-expanded', visible ? 'false' : 'true');
      if (!visible) { var eq = $('#wa-equipo'); if (eq) { eq.focus(); } }
    });
  }
  if (waCerrar && waPanel && waAbrir) {
    waCerrar.addEventListener('click', function () {
      waPanel.setAttribute('hidden', '');
      waAbrir.setAttribute('aria-expanded', 'false');
      waAbrir.focus();
    });
  }
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && waPanel && !waPanel.hasAttribute('hidden')) {
      waPanel.setAttribute('hidden', '');
      if (waAbrir) { waAbrir.setAttribute('aria-expanded', 'false'); }
    }
  });

  function armarMensajeWa() {
    var equipo   = ($('#wa-equipo') || {}).value || '';
    var problema = ($('#wa-problema') || {}).value || '';
    var pregunta = ($('#wa-pregunta') || {}).value || '';
    var texto = '¡Hola! ';

    if (equipo.trim()) {
      texto += 'Tengo un ' + equipo.trim();
      texto += problema.trim() ? ' y ' + problema.trim() + ', ' : ', ';
    } else if (problema.trim()) {
      texto += problema.trim() + ', ';
    }
    texto += pregunta;

    var vista = $('#wa-vista');
    if (vista) { vista.textContent = texto; }

    var enlace = $('#wa-enviar');
    if (enlace) {
      enlace.href = 'https://wa.me/' + (window.CP_WA_NUMERO || '') + '?text=' + encodeURIComponent(texto);
    }
  }

  ['#wa-equipo', '#wa-problema', '#wa-pregunta'].forEach(function (sel) {
    var el = $(sel);
    if (el) { el.addEventListener('input', armarMensajeWa); el.addEventListener('change', armarMensajeWa); }
  });
  if ($('#wa-vista')) { armarMensajeWa(); }

  /* ---------- Filtro de categorías del catálogo ---------------------- */
  var tabs = $$('.cat-tab');
  if (tabs.length) {
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var cat = tab.dataset.cat;
        tabs.forEach(function (t) { t.classList.toggle('activa', t === tab); });
        $$('.servicio').forEach(function (card) {
          var coincide = (cat === 'todos' || card.dataset.cat === cat);
          card.classList.toggle('oculto', !coincide);
        });
      });
    });
  }

  /* ---------- Aparición al hacer scroll ------------------------------ */
  var aparecen = $$('.aparece');
  if (aparecen.length && 'IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('visible');
          obs.unobserve(en.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    aparecen.forEach(function (el) { obs.observe(el); });
  } else {
    aparecen.forEach(function (el) { el.classList.add('visible'); });
  }

  /* ---------- Panel admin: "+1" a un aliado sin recargar -------------- */
  $$('[data-accion="sumar-referido"]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      var id = boton.dataset.aliado;
      var fd = new FormData();
      fd.append('_csrf', document.body.dataset.csrf || '');
      fd.append('aliado_id', id);

      boton.disabled = true;
      fetch(document.body.dataset.base + '/admin/admin.php?view=aliados&accion=sumar_ajax', {
        method: 'POST', body: fd, credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.ok) { throw new Error((data && data.error) || 'Error'); }
          var cont = document.querySelector('[data-total-referidos="' + id + '"]');
          var deuda = document.querySelector('[data-deuda="' + id + '"]');
          if (cont)  { cont.textContent = data.total; }
          if (deuda) { deuda.textContent = data.deuda; }
          boton.classList.add('ok');
        })
        .catch(function () { alert('No se pudo registrar el referido. Recarga la página e inténtalo otra vez.'); })
        .finally(function () { boton.disabled = false; });
    });
  });

  /* ---------- Confirmación en acciones destructivas ------------------ */
  $$('[data-confirmar]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.dataset.confirmar)) { ev.preventDefault(); }
    });
  });

  /* ---------- Folio en mayúsculas mientras se escribe ----------------- */
  $$('[data-folio]').forEach(function (input) {
    input.addEventListener('input', function () {
      input.value = input.value.toUpperCase().replace(/\s+/g, '');
    });
  });
})();
