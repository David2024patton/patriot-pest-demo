/**
 * Cost Report Dashboard - Receipt Renderer
 * Patriot Pest Control - Tactical Theme
 * CSS-animated threat meters, count-up numbers, bugfield canvas, grain overlay
 * No Canvas charts. No AI references.
 */

class CostReceiptRenderer {
  constructor() {
    this.colors = [
      '#f4772e', '#ff8c3b', '#c8b98c', '#8fa05e',
      '#5c6f3a', '#334024', '#26301c', '#1c2415'
    ];
    this.startTime = performance.now();
  }

  formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  }

  formatCompact(value) {
    if (value >= 1000) return '$' + (value / 1000).toFixed(0) + 'k';
    return '$' + value;
  }

  async loadData() {
    try {
      const response = await fetch('./data/pricing.json');
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return await response.json();
    } catch (error) {
      console.error('[CostReceipt] Load failed:', error);
      return null;
    }
  }

  /* Bugfield canvas - ambient insect particles from scrollytelling */
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
      bugs.forEach((b, i) => {
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

  /* Count-up animation for numbers */
  animateCountUp(element, target, duration) {
    if (!element) return;
    const start = performance.now();
    const initial = 0;

    function update(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.min(target, Math.max(0, Math.round(initial + (target - initial) * eased)));
      element.textContent = '$' + current.toLocaleString();
      if (progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
  }

  /* Set receipt date */
  setReceiptDate() {
    const el = document.getElementById('receipt-date');
    if (!el) return;
    const now = new Date();
    el.textContent = now.toISOString().split('T')[0].replace(/-/g, '.');
  }

  /* Animate summary card values */
  renderSummaryCards(data) {
    const min = data.agency_range.min;
    const max = data.agency_range.max;
    const minEl = document.getElementById('val-min');
    const maxEl = document.getElementById('val-max');

    if (minEl) this.animateCountUp(minEl, min, 1200);
    if (maxEl) this.animateCountUp(maxEl, max, 1400);
  }

  /* Build receipt line items with animated threat meters */
  renderReceiptItems(data) {
    const container = document.getElementById('receipt-items');
    if (!container) return;

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
            '<span class="range-label">RANGE</span>',
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

    /* Animate meter fills after DOM insertion */
    requestAnimationFrame(() => {
      const fills = container.querySelectorAll('.meter-fill');
      fills.forEach(f => {
        const lvl = f.getAttribute('data-lvl');
        if (lvl) f.style.width = lvl + '%';
      });
    });
  }

  escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  /* Subtotals and grand total */
  renderTotals(data) {
    const totalMin = data.breakdown.reduce((s, i) => s + i.min, 0);
    const totalMax = data.breakdown.reduce((s, i) => s + i.max, 0);

    const subLow = document.getElementById('subtotal-low');
    const subHigh = document.getElementById('subtotal-high');
    const grand = document.getElementById('grand-total');

    if (subLow) this.animateCountUp(subLow, totalMin, 1600);
    if (subHigh) this.animateCountUp(subHigh, totalMax, 1800);

    /* Grand total: display the full range after subtotals animate */
    setTimeout(() => {
      if (grand) grand.textContent = this.formatCurrency(totalMin) + ' \u2014 ' + this.formatCurrency(totalMax);
    }, 2000);
  }

  /* Timeline */
  renderTimeline(data) {
    const el = document.getElementById('receipt-timeline');
    if (el && data.timeline && data.timeline.agency) {
      el.textContent = data.timeline.agency;
    }
  }

  /* Pricing factors */
  renderFactors(data) {
    const container = document.getElementById('factors-list');
    if (!container || !data.factors) return;
    container.innerHTML = data.factors.map(f => '<li>' + this.escapeHtml(f) + '</li>').join('');
  }

  async init() {
    /* Start bugfield immediately */
    this.initBugfield();

    /* Set receipt date */
    this.setReceiptDate();

    /* Load data */
    const data = await this.loadData();
    if (!data) return;

    /* Render all sections */
    this.renderSummaryCards(data);
    this.renderReceiptItems(data);
    this.renderTotals(data);
    this.renderTimeline(data);
    this.renderFactors(data);

    const renderTime = performance.now() - this.startTime;
    console.log('[CostReceipt] Render: ' + renderTime.toFixed(2) + 'ms');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const renderer = new CostReceiptRenderer();
  renderer.init();
});
