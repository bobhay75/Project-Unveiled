(() => {
  'use strict';
  if (window.__PROJECT_UNVEILED_TRACKER__) return;
  window.__PROJECT_UNVEILED_TRACKER__ = true;

  const prepareSupportPrompt = () => {
    const prompts = Array.from(document.querySelectorAll('.pu-support-float'));
    if (!prompts.length) return;
    const isTimeline = /\/book\/timeline\.html$/i.test(window.location.pathname);
    const isSupportPage = /support-right-hand\.html$/i.test(window.location.pathname);
    if (isTimeline || isSupportPage) {
      prompts.forEach((prompt) => prompt.remove());
      return;
    }
    prompts.forEach((prompt) => {
      prompt.style.setProperty('left', '15px', 'important');
      prompt.style.setProperty('right', 'auto', 'important');
      prompt.style.opacity = '0';
      prompt.style.pointerEvents = 'none';
      prompt.style.transform = 'translateY(10px)';
      prompt.style.transition = 'opacity .25s ease, transform .25s ease';
      prompt.setAttribute('aria-hidden', 'true');
    });
    let revealed = false;
    const reveal = () => {
      if (revealed) return;
      revealed = true;
      prompts.forEach((prompt) => {
        prompt.style.opacity = '1';
        prompt.style.pointerEvents = 'auto';
        prompt.style.transform = 'translateY(0)';
        prompt.removeAttribute('aria-hidden');
      });
    };
    const checkDepth = () => {
      const total = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      if (window.scrollY / total >= 0.55) reveal();
    };
    window.addEventListener('scroll', checkDepth, { passive: true });
    window.setTimeout(reveal, 45000);
  };

  const preparePublicReviewLinks = () => {
    const reviewsHref = '/book/reviews.html';
    const addLink = (container, className = '') => {
      if (!container || container.querySelector(`a[href="${reviewsHref}"]`)) return;
      const link = document.createElement('a');
      link.href = reviewsHref;
      link.textContent = 'Reviews';
      link.setAttribute('data-pu-event', 'reviews_page_click');
      if (className) link.className = className;
      container.appendChild(link);
    };

    document.querySelectorAll('.nav-links').forEach((nav) => addLink(nav));
    document.querySelectorAll('.footer-links').forEach((footer) => addLink(footer));
    document.querySelectorAll('.pu-support-footer').forEach((footer) => addLink(footer));

    const progress = document.querySelector('[role="progressbar"][aria-valuenow]');
    if (progress) {
      const value = Math.max(0, Math.min(100, Number(progress.getAttribute('aria-valuenow')) || 0));
      const fill = progress.querySelector('.progress-fill');
      if (fill) fill.style.width = `${value}%`;
    }

    if (/\/book\/updates\.html$/i.test(window.location.pathname)) {
      document.querySelectorAll('.roadmap-card').forEach((card) => {
        const heading = card.querySelector('h3');
        if (!heading || !/Reader Discussion and Response/i.test(heading.textContent || '')) return;
        const status = card.querySelector('.roadmap-status');
        const copy = card.querySelector('p');
        if (status) status.textContent = 'Live';
        if (copy) {
          copy.textContent = 'The public Reader Reviews & Scholarly Review desk is live, with verified attribution, explicit publication permission, expert-credential checks, and a separate path for factual corrections and stronger evidence.';
        }
        if (!card.querySelector('a[href="/book/reviews.html"]')) {
          const link = document.createElement('a');
          link.href = '/book/reviews.html';
          link.textContent = 'Open the Review Desk →';
          link.setAttribute('data-pu-event', 'reviews_page_click');
          link.style.display = 'inline-block';
          link.style.marginTop = '14px';
          link.style.color = '#f3d47d';
          link.style.fontWeight = '800';
          card.appendChild(link);
        }
      });
    }
  };

  const prepareUi = () => {
    prepareSupportPrompt();
    preparePublicReviewLinks();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', prepareUi, { once: true });
  } else {
    prepareUi();
  }

  const dnt = navigator.doNotTrack === '1' || window.doNotTrack === '1';
  const gpc = navigator.globalPrivacyControl === true;
  if (dnt || gpc) return;

  const endpoint = '/project-unveiled-analytics/collect.php';
  const allowedCampaignKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
  const maxText = 300;

  const clean = (value, limit = maxText) => String(value || '').replace(/[\u0000-\u001f\u007f]/g, '').slice(0, limit);

  let sessionId = '';
  try {
    sessionId = sessionStorage.getItem('pu_reading_session') || '';
    if (!sessionId) {
      if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        sessionId = window.crypto.randomUUID().replace(/-/g, '');
      } else {
        sessionId = Date.now().toString(36) + Math.random().toString(36).slice(2, 16);
      }
      sessionStorage.setItem('pu_reading_session', sessionId);
    }
  } catch (_) {
    sessionId = Date.now().toString(36) + Math.random().toString(36).slice(2, 16);
  }

  let campaign = {};
  try {
    const params = new URLSearchParams(window.location.search);
    let found = false;
    for (const key of allowedCampaignKeys) {
      const value = params.get(key);
      if (value) {
        campaign[key.replace('utm_', '')] = clean(value, 120);
        found = true;
      }
    }
    if (found) {
      sessionStorage.setItem('pu_campaign', JSON.stringify(campaign));
    } else {
      const saved = sessionStorage.getItem('pu_campaign');
      if (saved) campaign = JSON.parse(saved) || {};
    }
  } catch (_) {
    campaign = {};
  }

  const chapterFromPath = (path) => {
    const match = String(path || '').match(/chapter-(\d{2})\.html/i);
    return match ? Number(match[1]) : 0;
  };

  const referrer = (() => {
    try {
      if (!document.referrer) return '';
      const url = new URL(document.referrer);
      return clean(url.hostname + url.pathname, 240);
    } catch (_) {
      return '';
    }
  })();

  const send = (eventName, details = {}) => {
    const payload = {
      event: clean(eventName, 60),
      path: clean(window.location.pathname, 240),
      title: clean(document.title, 240),
      session: clean(sessionId, 80),
      chapter: chapterFromPath(window.location.pathname),
      referrer,
      source: clean(campaign.source || '', 120),
      medium: clean(campaign.medium || '', 120),
      campaign: clean(campaign.campaign || '', 120),
      content: clean(campaign.content || '', 120),
      term: clean(campaign.term || '', 120),
      target: clean(details.target || '', 300),
      label: clean(details.label || '', 180)
    };

    const body = JSON.stringify(payload);
    try {
      if (navigator.sendBeacon) {
        const blob = new Blob([body], { type: 'application/json' });
        if (navigator.sendBeacon(endpoint, blob)) return;
      }
    } catch (_) {}

    try {
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body,
        credentials: 'same-origin',
        keepalive: true,
        cache: 'no-store'
      }).catch(() => {});
    } catch (_) {}
  };

  window.projectUnveiledTrack = send;

  const recordPageview = () => send('pageview');
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', recordPageview, { once: true });
  } else {
    recordPageview();
  }

  window.setTimeout(() => send('engaged_30s'), 30000);
  const recordedDepths = new Set();
  const recordDepth = () => {
    const total = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    const percent = Math.round((window.scrollY / total) * 100);
    for (const mark of [25, 50, 75, 90]) {
      if (percent >= mark && !recordedDepths.has(mark)) {
        recordedDepths.add(mark);
        send(`scroll_${mark}`);
      }
    }
  };
  window.addEventListener('scroll', recordDepth, { passive: true });

  document.addEventListener('click', (event) => {
    const element = event.target instanceof Element ? event.target.closest('a,button') : null;
    if (!element) return;

    const href = element instanceof HTMLAnchorElement ? (element.getAttribute('href') || '') : '';
    const absoluteHref = element instanceof HTMLAnchorElement ? element.href : '';
    const text = clean(element.textContent || element.getAttribute('aria-label') || '', 180).trim();
    const explicit = clean(element.getAttribute('data-pu-event') || '', 60);
    const currentChapter = chapterFromPath(window.location.pathname);
    const targetChapter = chapterFromPath(href);

    let eventName = explicit;
    if (!eventName && /paypal\.me\/Bobsome1975/i.test(absoluteHref || href)) eventName = 'paypal_click';
    if (!eventName && /support-right-hand\.html/i.test(href)) eventName = 'support_page_click';
    if (!eventName && /share/i.test(text)) eventName = 'share_click';
    if (!eventName && element.matches('.rail-item,.dot,#heroPrev,#heroNext,#bottomPrev,#bottomNext')) eventName = 'timeline_event';
    if (!eventName && currentChapter && targetChapter === currentChapter + 1) eventName = 'chapter_next';
    if (!eventName && !currentChapter && targetChapter === 1) eventName = 'chapter_start';
    if (!eventName && currentChapter === 13 && /\/book\/read\/?(?:#.*)?$/i.test(href)) eventName = 'book_complete';
    if (!eventName) return;

    send(eventName, { target: absoluteHref || href, label: text });
  }, true);

  const chapterSearch = document.getElementById('chapter-search');
  if (chapterSearch) {
    let searchRecorded = false;
    chapterSearch.addEventListener('input', () => {
      if (!searchRecorded && chapterSearch.value.trim().length >= 2) {
        searchRecorded = true;
        send('search_use', { label: chapterSearch.value.trim() });
      }
    });
  }
})();
