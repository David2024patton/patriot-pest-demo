/**
 * Cost Report Dashboard - Marketing Cost Explainer
 * Patriot Pest Control - Tactical Theme
 * Chart.js charts, CSS-animated threat meters, count-up numbers,
 * bugfield canvas, grain overlay. No forbidden references. No em dashes.
 */

class CostExplainerRenderer {
  constructor() {
    this.colors = [
      '#f4772e', '#ff8c3b', '#c8b98c', '#8fa05e',
      '#5c6f3a', '#334024', '#26301c', '#1c2415'
    ];
    this.startTime = performance.now();
  }

  cssVar(name, fallback) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }

  formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  }

  escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  async loadData() {
    try {
      const response = await fetch('./data/pricing.json');
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return await response.json();
    } catch (error) {
      console.error('[CostExplainer] Load failed:', error);
      return null;
    }
  }

  /* Hero crosshair - tactical targeting reticle */
  initCrosshair() {
    const hero = document.getElementById('cost-hero');
    if (!hero || !window.matchMedia('(pointer:fine)').matches) return;
    const xhv = document.getElementById('xh-v'), xhh = document.getElementById('xh-h'), xhr = document.getElementById('xh-ring');
    if (xhv) {
      hero.addEventListener('mousemove', (e) => {
        const r = hero.getBoundingClientRect(), x = e.clientX - r.left, y = e.clientY - r.top;
        xhv.style.left = x + 'px'; xhh.style.top = y + 'px'; xhr.style.left = x + 'px'; xhr.style.top = y + 'px';
        hero.classList.add('aim');
      });
      hero.addEventListener('mouseleave', () => hero.classList.remove('aim'));
    }
  }

  /* Bugfield canvas - ambient insect particles */
  initBugfield() {
    const canvas = document.getElementById('bugfield');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let bugs = [];
    const BUG_COUNT = 28;

    function resize() {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    for (let i = 0; i < BUG_COUNT; i++) {
      bugs.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        vx: (Math.random() - 0.5) * 0.4,
        vy: (Math.random() - 0.5) * 0.4,
        size: Math.random() * 2.2 + 0.8,
        opacity: Math.random() * 0.18 + 0.04
      });
    }

    function draw() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      bugs.forEach((b) => {
        b.x += b.vx;
        b.y += b.vy;
        if (b.x < 0) b.x = canvas.width;
        if (b.x > canvas.width) b.x = 0;
        if (b.y < 0) b.y = canvas.height;
        if (b.y > canvas.height) b.y = 0;

        ctx.beginPath();
        ctx.arc(b.x, b.y, b.size, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(200,185,140,' + b.opacity + ')';
        ctx.fill();
      });
      requestAnimationFrame(draw);
    }
    draw();
  }

  animateCountUp(element, target, duration) {
    if (!element) return;
    const start = performance.now();

    function update(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.min(target, Math.max(0, Math.round(target * eased)));
      element.textContent = '$' + current.toLocaleString();
      if (progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
  }

  setReceiptDate() {
    const el = document.getElementById('receipt-date');
    if (!el) return;
    el.textContent = new Date().toISOString().split('T')[0].replace(/-/g, '.');
  }

  renderHero(data) {
    const h = data.hero || {};
    const eyebrow = document.getElementById('hero-eyebrow');
    const title = document.getElementById('hero-title');
    const sub = document.getElementById('hero-sub');
    if (eyebrow && h.eyebrow) eyebrow.textContent = h.eyebrow;
    if (title && h.title_pre) {
      title.innerHTML = this.escapeHtml(h.title_pre) + '<em>' + this.escapeHtml(h.title_em || '') + '</em> ' + this.escapeHtml(h.title_post || '');
    }
    if (sub && h.sub) sub.textContent = h.sub;
  }

  renderThreeK(data) {
    const t = data.three_k || {};
    const set = (id, val) => { const el = document.getElementById(id); if (el && val) el.textContent = val; };
    set('three-k-answer', t.answer);
    set('fee-label', (t.agency_fee || {}).label);
    set('fee-range', (t.agency_fee || {}).range);
    set('fee-desc', (t.agency_fee || {}).desc);
    set('spend-label', (t.ad_spend || {}).label);
    set('spend-range', (t.ad_spend || {}).range);
    set('spend-desc', (t.ad_spend || {}).desc);
    set('three-k-sources', t.sources);
  }

  renderReceiptItems(data) {
    const container = document.getElementById('receipt-items');
    if (!container || !data.breakdown) return;

    const maxValue = Math.max(...data.breakdown.map(d => d.max));

    container.innerHTML = data.breakdown.map((item, index) => {
      const percent = ((item.max / maxValue) * 100).toFixed(0);
      const color = item.color || this.colors[index % this.colors.length];
      return [
        '<div class="receipt-item">',
          '<div class="ri-cat">' + this.escapeHtml(item.category) + '</div>',
          '<div class="ri-desc">' + this.escapeHtml(item.description) + '</div>',
          '<div class="ri-cost">',
            this.formatCurrency(item.min) + ' - ' + this.formatCurrency(item.max),
            '<span class="range-label">RANGE / MO</span>',
          '</div>',
          '<div class="ri-meter">',
            '<div class="meter-label"><span>THREAT</span><span>' + percent + '%</span></div>',
            '<div class="meter-bar">',
              '<div class="meter-fill" style="background:' + color + '" data-lvl="' + percent + '"></div>',
            '</div>',
          '</div>',
        '</div>'
      ].join('');
    }).join('');

    requestAnimationFrame(() => {
      const fills = container.querySelectorAll('.meter-fill');
      fills.forEach(f => {
        const lvl = f.getAttribute('data-lvl');
        if (lvl) f.style.width = lvl + '%';
      });
    });
  }

  renderTotals(data) {
    if (!data.breakdown) return;
    const totalMin = data.breakdown.reduce((s, i) => s + i.min, 0);
    const totalMax = data.breakdown.reduce((s, i) => s + i.max, 0);

    const subLow = document.getElementById('subtotal-low');
    const subHigh = document.getElementById('subtotal-high');
    const grand = document.getElementById('grand-total');

    if (subLow) this.animateCountUp(subLow, totalMin, 1600);
    if (subHigh) this.animateCountUp(subHigh, totalMax, 1800);

    setTimeout(() => {
      if (grand) grand.textContent = this.formatCurrency(totalMin) + ' - ' + this.formatCurrency(totalMax);
    }, 2000);

    const t = data.totals || {};
    const rl = document.getElementById('realistic-label');
    const rr = document.getElementById('realistic-range');
    if (rl && t.realistic_label) rl.textContent = t.realistic_label.toUpperCase();
    if (rr && t.realistic_min) rr.textContent = this.formatCurrency(t.realistic_min) + '-' + this.formatCurrency(t.realistic_max);
  }

  renderReceiptMeta(data) {
    const r = data.receipt || {};
    const set = (id, val) => { const el = document.getElementById(id); if (el && val) el.textContent = val; };
    set('receipt-number', r.report_number);
    set('receipt-classification', r.classification);
    const stamp = document.getElementById('receipt-stamp');
    if (stamp && r.stamp) stamp.innerHTML = r.stamp;
    const notes = document.getElementById('receipt-notes');
    if (notes && r.notes) {
      notes.innerHTML = r.notes.map(n => '<p><strong>INTEL:</strong> ' + this.escapeHtml(n) + '</p>').join('');
    }
  }

  /* CHART 1: stacked bars, fee vs spend at low and high end */
  renderStackedChart(data) {
    const canvas = document.getElementById('chart-stacked');
    if (!canvas || typeof Chart === 'undefined') return;
    const c = (data.charts || {}).stacked || {};
    const low = c.low || { fee: 0, spend: 0 };
    const high = c.high || { fee: 0, spend: 0 };

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: ['Low End', 'High End'],
        datasets: [
          {
            label: 'Ad Spend',
            data: [low.spend, high.spend],
            backgroundColor: this.cssVar('--chart-primary', '#f4772e'),
            stack: 's'
          },
          {
            label: 'Agency Fee',
            data: [low.fee, high.fee],
            backgroundColor: this.cssVar('--chart-tertiary', '#c8b98c'),
            stack: 's'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { stacked: true, ticks: { color: '#ece4cd', font: { family: "'IBM Plex Mono', monospace" } }, grid: { color: 'rgba(200,185,140,.12)' } },
          y: { stacked: true, ticks: { color: '#c8b98c', font: { family: "'IBM Plex Mono', monospace" }, callback: (v) => '$' + (v / 1000) + 'k' }, grid: { color: 'rgba(200,185,140,.12)' } }
        },
        plugins: {
          legend: { labels: { color: '#f5f1e4', font: { family: "'IBM Plex Mono', monospace", size: 11 } } },
          tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + this.formatCurrency(ctx.parsed.y) } }
        }
      }
    });
  }

  /* CHART 2: horizontal bars ranked by high end */
  renderRankChart(data) {
    const canvas = document.getElementById('chart-rank');
    if (!canvas || typeof Chart === 'undefined' || !data.breakdown) return;

    const ranked = [...data.breakdown].sort((a, b) => b.max - a.max);

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: ranked.map(r => r.category),
        datasets: [{
          label: 'High End / mo',
          data: ranked.map(r => r.max),
          backgroundColor: ranked.map((r, i) => r.color || this.colors[i % this.colors.length]),
          borderColor: 'rgba(236,228,205,.35)',
          borderWidth: 1
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { ticks: { color: '#c8b98c', font: { family: "'IBM Plex Mono', monospace" }, callback: (v) => '$' + (v / 1000) + 'k' }, grid: { color: 'rgba(200,185,140,.12)' } },
          y: { ticks: { color: '#ece4cd', font: { family: "'Barlow', system-ui, sans-serif", size: 11 } }, grid: { display: false } }
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (ctx) => 'High end: ' + this.formatCurrency(ctx.parsed.x) + '/mo' } }
        }
      }
    });
  }

  renderCompetitive(data) {
    const c = data.competitive || {};
    const allIn = document.getElementById('patriot-all-in');
    if (allIn && c.patriot_all_in) this.animateCountUp(allIn, c.patriot_all_in, 1400);
    const note = document.getElementById('patriot-note');
    if (note && c.patriot_note) note.textContent = c.patriot_note;

    const tbody = document.querySelector('#comp-table tbody');
    if (!tbody || !c.tiers) return;
    tbody.innerHTML = c.tiers.map(t => {
      return '<tr><td>' + this.escapeHtml(t.tier) + '</td><td>' +
        this.formatCurrency(t.min) + '-' + this.formatCurrency(t.max) + '</td><td>' +
        this.escapeHtml(t.covers) + '</td></tr>';
    }).join('') +
    '<tr class="patriot-row"><td>PATRIOT ALL-IN</td><td>' + this.formatCurrency(c.patriot_all_in || 0) + '</td><td>Management AND ad spend, one number</td></tr>';
  }

  renderLead(data) {
    const l = data.lead_response || {};
    const set = (id, val) => { const el = document.getElementById(id); if (el && val) el.textContent = val; };
    set('lead-headline', l.headline);
    set('lead-text', l.body);
    const cta = document.getElementById('lead-cta');
    if (cta) {
      if (l.cta) cta.textContent = l.cta;
      if (l.link) cta.href = l.link;
    }
  }

  renderSources(data) {
    const block = document.getElementById('source-block');
    if (!block || !data.sources) return;
    block.innerHTML = '<span class="sb-title">SOURCE FILE //</span>' +
      data.sources.map(s => this.escapeHtml(s)).join('<br>');
  }

  renderFooter(data) {
    const f = data.footer || {};
    const cta = document.getElementById('footer-cta');
    if (cta && f.cta_pre && f.phone) {
      cta.innerHTML = this.escapeHtml(f.cta_pre) + '<a href="' + this.escapeHtml(f.phone_href || 'tel:' + f.phone) + '">' + this.escapeHtml(f.phone) + '</a>';
    }
    const stamp = document.getElementById('footer-stamp');
    if (stamp && f.stamp) stamp.innerHTML = f.stamp;
  }

  async init() {
    this.initBugfield();
    this.initCrosshair();
    this.setReceiptDate();

    const data = await this.loadData();
    if (!data) return;

    this.renderHero(data);
    this.renderThreeK(data);
    this.renderReceiptItems(data);
    this.renderTotals(data);
    this.renderReceiptMeta(data);
    this.renderStackedChart(data);
    this.renderRankChart(data);
    this.renderCompetitive(data);
    this.renderLead(data);
    this.renderSources(data);
    this.renderFooter(data);

    const renderTime = performance.now() - this.startTime;
    console.log('[CostExplainer] Render: ' + renderTime.toFixed(2) + 'ms');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const renderer = new CostExplainerRenderer();
  renderer.init();
});
