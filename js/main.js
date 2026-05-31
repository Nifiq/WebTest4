'use strict';

/* ═══════════════════════════════════════════════════════════════════
   WebTest2 — main.js
   ✅ Cookie-сохранение всех полей формы (при обновлении не сбрасываются)
   ✅ Inline-ошибка email (маленький текст под полем, не браузерный попап)
   ✅ Пол: клик по radio-кружку рядом с надписью (в одну строку)
   ✅ Языки: вертикальный список радио-кнопок (как в WebServer5)
   ✅ Капча остаётся в DOM, не учитывается при отправке
   ✅ Чекбокс согласия — обязателен
   ✅ Дата — сохраняется в куки
   ✅ Вкладки, карусель отзывов, burger, dropdown, FAQ
═══════════════════════════════════════════════════════════════════ */

// ── Языки программирования (id совпадает с WebServer5) ────────────
const LANGUAGES = [
  { id: 1,  name: 'Pascal' },
  { id: 2,  name: 'C' },
  { id: 3,  name: 'C++' },
  { id: 4,  name: 'JavaScript' },
  { id: 5,  name: 'PHP' },
  { id: 6,  name: 'Python' },
  { id: 7,  name: 'Java' },
  { id: 8,  name: 'Haskell' },
  { id: 9,  name: 'Clojure' },
  { id: 10, name: 'Prolog' },
  { id: 11, name: 'Scala' },
  { id: 12, name: 'Go' },
];

// ── Cookie helpers ─────────────────────────────────────────────────
const Cookie = {
  set(name, value, days = 30) {
    const exp = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)};expires=${exp};path=/;SameSite=Lax`;
  },
  get(name) {
    const key = encodeURIComponent(name) + '=';
    for (const part of document.cookie.split('; ')) {
      if (part.startsWith(key)) return decodeURIComponent(part.slice(key.length));
    }
    return null;
  },
  remove(name) {
    document.cookie = `${encodeURIComponent(name)}=;expires=Thu,01 Jan 1970 00:00:00 GMT;path=/`;
  }
};

const DRAFT_KEY = 'wt2_draft';

document.addEventListener('DOMContentLoaded', () => {

  // ── Burger ──────────────────────────────────────────────────────
  const burger = document.querySelector('.burger');
  const nav    = document.getElementById('main-navigation');
  if (burger && nav) {
    burger.addEventListener('click', () => {
      const open = burger.getAttribute('aria-expanded') === 'true';
      burger.setAttribute('aria-expanded', String(!open));
      nav.classList.toggle('nav--open', !open);
    });
  }

  // ── Dropdown ────────────────────────────────────────────────────
  document.querySelectorAll('.nav__item--dropdown').forEach(item => {
    item.addEventListener('click', e => { e.stopPropagation(); item.classList.toggle('open'); });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.nav__item--dropdown.open').forEach(el => el.classList.remove('open'));
  });

  // ── Reviews carousel ────────────────────────────────────────────
  const cards = document.querySelectorAll('.review-card');
  if (cards.length) {
    let cur = 0;
    const show = i => cards.forEach((c, j) => { c.style.display = j === i ? '' : 'none'; });
    show(0);
    document.querySelectorAll('.review-prev').forEach(b => b.addEventListener('click', () => { cur = (cur - 1 + cards.length) % cards.length; show(cur); }));
    document.querySelectorAll('.review-next').forEach(b => b.addEventListener('click', () => { cur = (cur + 1) % cards.length; show(cur); }));
  }

  // ── FAQ ─────────────────────────────────────────────────────────
  document.querySelectorAll('.faq-item:not(.faq-item--highlight)').forEach(item => {
    item.style.cursor = 'pointer';
    item.addEventListener('click', () => item.classList.toggle('open'));
  });

  // ── Вкладки ─────────────────────────────────────────────────────
  const tabs   = document.querySelectorAll('.form-tab');
  const panels = document.querySelectorAll('.tab-panel');

  function switchTab(targetPanel) {
    tabs.forEach(t => {
      const active = t.dataset.tab === targetPanel;
      t.classList.toggle('form-tab--active', active);
      t.setAttribute('aria-selected', String(active));
    });
    panels.forEach(p => {
      const active = p.dataset.panel === targetPanel;
      p.hidden = !active;
    });
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.dataset.tab));
  });

  // ═══════════════════════════════════════════════════════════════
  //  ФОРМА ЗАЯВКИ
  // ═══════════════════════════════════════════════════════════════
  const form = document.getElementById('support-form');
  if (!form) return;

  // ── Пол: рендерим radio-кружки в одну строку ──────────────────
  initGenderSelector('#gender-selector', 'gender');
  initGenderSelector('#edit-gender-selector', 'gender');

  // ── Языки: вертикальный список radio ──────────────────────────
  renderLangList('#lang-chips', 'preferred_lang_id');
  renderLangList('#edit-lang-chips', 'preferred_lang_id');

  // ── Восстановление черновика из куки ──────────────────────────
  const savedRaw = Cookie.get(DRAFT_KEY);
  let draft = {};
  if (savedRaw) {
    try { draft = JSON.parse(savedRaw); } catch (_) {}
  }
  restoreFormDraft(form, draft);

  // ── Сохранение в куку при каждом изменении ────────────────────
  form.addEventListener('input',  () => saveFormDraft(form));
  form.addEventListener('change', () => saveFormDraft(form));

  // ── Валидация email инлайном ───────────────────────────────────
  const emailInput = form.querySelector('[name="email"]');
  if (emailInput) {
    emailInput.addEventListener('blur', () => {
      const v = emailInput.value.trim();
      if (!v) return clearFieldError(emailInput);
      if (!isValidEmail(v)) showFieldError(emailInput, 'Введите корректный email — только латинские буквы, например: name@domain.ru');
      else clearFieldError(emailInput);
    });
    emailInput.addEventListener('input', () => {
      if (isValidEmail(emailInput.value.trim())) clearFieldError(emailInput);
    });
  }

  // ── Submit ─────────────────────────────────────────────────────
  form.addEventListener('submit', e => {
    clearAllErrors(form);
    let ok = true;

    const name = form.querySelector('[name="name"]');
    if (name && name.value.trim().length < 2) {
      showFieldError(name, 'Введите имя (минимум 2 символа)');
      ok = false;
    }

    if (emailInput) {
      const v = emailInput.value.trim();
      if (!v || !isValidEmail(v)) {
        showFieldError(emailInput, 'Введите корректный email — только латинские буквы, например: name@domain.ru');
        ok = false;
      }
    }

    const consent = form.querySelector('[name="consent"]');
    if (consent && !consent.checked) {
      showFieldError(consent, 'Необходимо дать согласие на обработку персональных данных');
      ok = false;
    }

    // Капча — НЕ УЧИТЫВАЕМ при отправке (остаётся в DOM визуально)

    if (!ok) {
      e.preventDefault();
      const firstErr = form.querySelector('.field-err-msg');
      if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });

  // ── Успех из куки (после редиректа PHP) ───────────────────────
  if (Cookie.get('form_success')) {
    const msg = form.closest('.tab-panel')?.querySelector('.webform-message');
    if (msg) {
      msg.textContent = '✅ Ваша заявка принята! Мы свяжемся с вами в ближайшее время.';
      msg.hidden = false;
    }
    Cookie.remove('form_success');
    Cookie.remove(DRAFT_KEY);
  }

  // ── Ошибки из куки (PHP redirect back) ────────────────────────
  const errRaw = Cookie.get('form_errors');
  const oldRaw = Cookie.get('form_old');
  if (errRaw) {
    try {
      const errs = JSON.parse(errRaw);
      const oldVals = oldRaw ? JSON.parse(oldRaw) : {};
      // восстановить old values
      Object.entries(oldVals).forEach(([k, v]) => {
        const el = form.querySelector(`[name="${k}"]`);
        if (el && el.type !== 'checkbox' && el.type !== 'radio') el.value = v;
      });
      // показать ошибки
      Object.entries(errs).forEach(([field, msg]) => {
        const el = form.querySelector(`[name="${field}"]`);
        if (el) showFieldError(el, msg);
      });
    } catch (_) {}
    Cookie.remove('form_errors');
    Cookie.remove('form_old');
  }

  // ── Логин пользователя ────────────────────────────────────────
  const loginForm = document.getElementById('user-login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async e => {
      e.preventDefault();
      const status = document.getElementById('login-status');
      const login  = loginForm.querySelector('[name="login"]').value.trim();
      const pw     = loginForm.querySelector('[name="password"]').value.trim();
      if (!login || !pw) {
        showStatus(status, 'error', 'Введите логин и пароль');
        return;
      }
      try {
        const res  = await fetch('backend/login.php', { method: 'POST', body: new FormData(loginForm) });
        const data = await res.json();
        if (data.ok) {
          showStatus(status, 'success', 'Вы вошли. Загружаем данные...');
          // заполняем форму данными пользователя
          if (data.fields) {
            fillFormFromData(form, data.fields);
            saveFormDraft(form);
          }
          document.getElementById('logout-btn')?.removeAttribute('hidden');
          loginForm.reset();
        } else {
          showStatus(status, 'error', data.message || 'Неверный логин или пароль');
        }
      } catch (_) {
        showStatus(status, 'error', 'Ошибка соединения');
      }
    });
  }

  const logoutBtn = document.getElementById('logout-btn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      await fetch('backend/logout.php', { method: 'POST' });
      Cookie.remove(DRAFT_KEY);
      location.reload();
    });
  }

  // ── Модальное окно редактирования ─────────────────────────────
  const modal         = document.getElementById('edit-modal');
  const modalClose    = document.getElementById('edit-modal-close');
  const modalCancel   = document.getElementById('edit-modal-cancel');
  const modalBackdrop = document.getElementById('edit-modal-backdrop');

  function openModal() { if (modal) { modal.hidden = false; document.body.style.overflow = 'hidden'; } }
  function closeModal() { if (modal) { modal.hidden = true; document.body.style.overflow = ''; } }

  if (modalClose)    modalClose.addEventListener('click', closeModal);
  if (modalCancel)   modalCancel.addEventListener('click', closeModal);
  if (modalBackdrop) modalBackdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  // Кнопка "Редактировать" в панели пользователя (если есть)
  document.querySelectorAll('[data-action="edit-my"]').forEach(btn => {
    btn.addEventListener('click', () => openModal());
  });

  // Submit модала
  const modalForm = document.getElementById('edit-modal-form');
  if (modalForm) {
    modalForm.addEventListener('submit', async e => {
      e.preventDefault();
      const msg = document.getElementById('edit-modal-message');
      try {
        const res  = await fetch('backend/update.php', { method: 'POST', body: new FormData(modalForm) });
        const data = await res.json();
        if (data.ok) {
          showStatus(msg, 'success', '✅ Данные обновлены');
          setTimeout(closeModal, 1200);
        } else {
          showStatus(msg, 'error', data.message || 'Ошибка сохранения');
        }
      } catch (_) {
        showStatus(msg, 'error', 'Ошибка соединения');
      }
    });
  }
});

// ═══════════════════════════════════════════════════════════════════
//  ХЕЛПЕРЫ
// ═══════════════════════════════════════════════════════════════════

/** Валидация email — только латиница */
function isValidEmail(v) {
  return /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(v);
}

/** Показать inline-ошибку под полем */
function showFieldError(el, msg) {
  el.classList.add('field-invalid');
  // удалить старую
  const existing = el.parentElement.querySelector('.field-err-msg');
  if (existing) existing.remove();
  const div = document.createElement('div');
  div.className = 'field-err-msg';
  div.textContent = msg;
  el.after(div);
}

function clearFieldError(el) {
  el.classList.remove('field-invalid');
  el.parentElement.querySelector('.field-err-msg')?.remove();
}

function clearAllErrors(form) {
  form.querySelectorAll('.field-invalid').forEach(el => el.classList.remove('field-invalid'));
  form.querySelectorAll('.field-err-msg').forEach(el => el.remove());
}

function showStatus(el, type, msg) {
  if (!el) return;
  el.className = `login-panel__status status--${type}`;
  el.textContent = msg;
  el.hidden = false;
}

// ─── Пол: рендер radio в одну строку ─────────────────────────────
/**
 * Заменяет кнопки .gender-btn на правильные radio <label> с кружком.
 * Кружок — слева от надписи, оба в одной строке, клик по кружку или надписи.
 */
function initGenderSelector(selectorStr, inputName) {
  const wrap = document.querySelector(selectorStr);
  if (!wrap) return;
  const hidden = wrap.querySelector(`input[name="${inputName}"]`);
  // Уже ли инициализирован?
  if (wrap.querySelector('input[type="radio"]')) return;

  // Убираем старые кнопки
  wrap.querySelectorAll('.gender-btn, .lang-chips-loading').forEach(el => el.remove());

  const genders = [
    { value: 'male',   label: 'Мужской' },
    { value: 'female', label: 'Женский' },
  ];

  wrap.style.display = 'flex';
  wrap.style.gap = '18px';
  wrap.style.alignItems = 'center';
  wrap.style.flexWrap = 'wrap';

  genders.forEach(g => {
    const uid = `gender_${selectorStr.replace(/[^a-z]/gi,'')}_${g.value}`;
    const label = document.createElement('label');
    label.className = 'gender-radio-label';
    label.style.cssText = 'display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;white-space:nowrap;';
    label.htmlFor = uid;

    const radio = document.createElement('input');
    radio.type  = 'radio';
    radio.name  = `gender_visual_${selectorStr.replace(/[^a-z]/gi,'')}`;
    radio.value = g.value;
    radio.id    = uid;
    radio.style.cssText = 'width:16px;height:16px;cursor:pointer;accent-color:#27ae60;flex-shrink:0;';

    radio.addEventListener('change', () => {
      if (hidden) hidden.value = g.value;
      // сохранить в черновик
      const parentForm = wrap.closest('form');
      if (parentForm) saveFormDraft(parentForm);
    });

    const span = document.createElement('span');
    span.textContent = g.label;

    label.appendChild(radio);
    label.appendChild(span);
    wrap.insertBefore(label, hidden);
  });

  // Восстановить значение из hidden
  if (hidden && hidden.value) {
    const r = wrap.querySelector(`input[value="${hidden.value}"]`);
    if (r) r.checked = true;
  }
}

// ─── Языки: вертикальный список radio ────────────────────────────
/**
 * Рендерит список языков как вертикальные radio-кнопки (как в WebServer5 select[multiple] → radio).
 */
function renderLangList(selectorStr, hiddenName) {
  const wrap = document.querySelector(selectorStr);
  if (!wrap) return;
  const hidden = wrap.closest('.field-group')?.querySelector(`input[name="${hiddenName}"]`)
               || wrap.parentElement.querySelector(`input[name="${hiddenName}"]`);

  // Очистить
  wrap.innerHTML = '';
  wrap.style.cssText = 'display:flex;flex-direction:column;gap:6px;max-height:220px;overflow-y:auto;padding:4px 0;';

  const prefix = selectorStr.replace(/[^a-z]/gi, '');

  LANGUAGES.forEach(lang => {
    const uid = `lang_${prefix}_${lang.id}`;
    const label = document.createElement('label');
    label.className = 'lang-radio-label';
    label.style.cssText = 'display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;padding:3px 0;';
    label.htmlFor = uid;

    const radio = document.createElement('input');
    radio.type  = 'radio';
    radio.name  = `lang_visual_${prefix}`;
    radio.value = String(lang.id);
    radio.id    = uid;
    radio.style.cssText = 'width:15px;height:15px;cursor:pointer;accent-color:#27ae60;flex-shrink:0;';

    radio.addEventListener('change', () => {
      if (hidden) hidden.value = lang.id;
      const parentForm = wrap.closest('form');
      if (parentForm) saveFormDraft(parentForm);
    });

    const span = document.createElement('span');
    span.textContent = lang.name;

    label.appendChild(radio);
    label.appendChild(span);
    wrap.appendChild(label);
  });

  // Восстановить значение
  if (hidden && hidden.value) {
    const r = wrap.querySelector(`input[value="${hidden.value}"]`);
    if (r) r.checked = true;
  }
}

// ─── Cookie-черновик ─────────────────────────────────────────────
function saveFormDraft(form) {
  const draft = {};
  new FormData(form).forEach((v, k) => { draft[k] = v; });
  // добавить визуальные radio (gender, lang)
  form.querySelectorAll('input[type="radio"]:checked').forEach(r => {
    if (r.name.startsWith('gender_visual_')) {
      draft['gender'] = r.value;
    }
    if (r.name.startsWith('lang_visual_')) {
      draft['preferred_lang_id'] = r.value;
    }
  });
  draft['consent'] = form.querySelector('[name="consent"]')?.checked ? '1' : '0';
  Cookie.set(DRAFT_KEY, JSON.stringify(draft));
}

function restoreFormDraft(form, draft) {
  if (!draft || !Object.keys(draft).length) return;

  Object.entries(draft).forEach(([k, v]) => {
    const el = form.querySelector(`[name="${k}"]`);
    if (!el) return;
    if (el.type === 'checkbox') { el.checked = v === '1' || v === 'on'; return; }
    if (el.type !== 'radio')    { el.value = v; }
  });

  // Восстановить пол
  if (draft.gender) {
    const gWrap = form.querySelector('#gender-selector');
    if (gWrap) {
      const r = gWrap.querySelector(`input[value="${draft.gender}"]`);
      if (r) r.checked = true;
    }
    const hidG = form.querySelector('[name="gender"]');
    if (hidG) hidG.value = draft.gender;
  }

  // Восстановить язык
  if (draft.preferred_lang_id) {
    const lWrap = form.querySelector('#lang-chips');
    if (lWrap) {
      const r = lWrap.querySelector(`input[value="${draft.preferred_lang_id}"]`);
      if (r) r.checked = true;
    }
    const hidL = form.closest('.field-group')?.querySelector('[name="preferred_lang_id"]')
               || form.querySelector('[name="preferred_lang_id"]');
    if (hidL) hidL.value = draft.preferred_lang_id;
  }
}

function fillFormFromData(form, fields) {
  Object.entries(fields).forEach(([k, v]) => {
    const el = form.querySelector(`[name="${k}"]`);
    if (!el) return;
    if (el.type === 'checkbox') { el.checked = v == 1; return; }
    el.value = v;
  });
  if (fields.gender) {
    const r = form.querySelector(`#gender-selector input[value="${fields.gender}"]`);
    if (r) r.checked = true;
    const h = form.querySelector('[name="gender"]');
    if (h) h.value = fields.gender;
  }
  if (fields.preferred_lang_id) {
    const r = form.querySelector(`#lang-chips input[value="${fields.preferred_lang_id}"]`);
    if (r) r.checked = true;
    const h = form.querySelector('[name="preferred_lang_id"]');
    if (h) h.value = fields.preferred_lang_id;
  }
}
