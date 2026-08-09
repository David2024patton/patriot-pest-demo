/**
 * Agency Intel Board - 15-company recon comparison
 * Filters (search, tier, AI strength, service, published-only),
 * checkbox-driven two-up side-by-side compare. Mobile-first.
 * No forbidden references. No em dashes.
 */

class IntelBoard {
  constructor() {
    this.agencies = [];
    this.selected = [];
    this.SVC = [
      ['seo', 'SEO'], ['ppc', 'PPC'], ['social', 'Social'], ['content', 'Content'],
      ['cro', 'CRO'], ['email', 'Email'], ['web', 'Web Dev'], ['creative', 'Creative'],
      ['amazon', 'Amazon'], ['ai_geo', 'AI/GEO'], ['analytics', 'Analytics'], ['pr', 'PR']
    ];
  }

  esc(s) {
    const d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  fmtBand(a) {
    const f = (n) => '$' + Number(n).toLocaleString('en-US');
    return f(a.est_min) + '-' + f(a.est_max);
  }

  aiClass(ai) {
    if (ai === 'Very Strong' || ai === 'Strong') return 'ai-strong';
    if (ai === 'Moderate') return 'ai-mid';
    return 'ai-weak';
  }

  async load() {
    try {
      const r = await fetch('./data/agencies.json');
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const d = await r.json();
      this.agencies = d.agencies || [];
      this.meta = d.meta || {};
    } catch (e) {
      console.error('[IntelBoard] Load failed:', e);
      this.agencies = [];
    }
  }

  fillOptions() {
    const tiers = [...new Set(this.agencies.map(a => a.tier))];
    const ais = ['Very Strong', 'Strong', 'Moderate', 'Low', 'Weak'];
    const tSel = document.getElementById('intel-tier');
    const aSel = document.getElementById('intel-ai');
    const sSel = document.getElementById('intel-svc');
    tiers.forEach(t => { const o = document.createElement('option'); o.value = t; o.textContent = t; tSel.appendChild(o); });
    ais.forEach(t => { const o = document.createElement('option'); o.value = t; o.textContent = t; aSel.appendChild(o); });
    this.SVC.forEach(([k, label]) => { const o = document.createElement('option'); o.value = k; o.textContent = label; sSel.appendChild(o); });
  }

  filtered() {
    const q = (document.getElementById('intel-q').value || '').trim().toLowerCase();
    const tier = document.getElementById('intel-tier').value;
    const ai = document.getElementById('intel-ai').value;
    const svc = document.getElementById('intel-svc').value;
    const pub = document.getElementById('intel-published').checked;
    return this.agencies.filter(a => {
      if (q && !(a.name.toLowerCase().includes(q) || a.domain.includes(q))) return false;
      if (tier && a.tier !== tier) return false;
      if (ai && a.ai !== ai) return false;
      if (svc && !a.services[svc]) return false;
      if (pub && a.transparency !== 'Published') return false;
      return true;
    });
  }

  card(a) {
    const checked = this.selected.includes(a.id) ? ' checked' : '';
    const chips = this.SVC.filter(([k]) => a.services[k]).map(([, label]) =>
      '<span class="svc-chip">' + this.esc(label) + '</span>').join('');
    return '<article class="agency-card" data-id="' + this.esc(a.id) + '">' +
      '<div class="ac-top"><h3>' + this.esc(a.name) + '</h3>' +
      '<label class="ac-compare"><input type="checkbox" data-compare="' + this.esc(a.id) + '"' + checked + '><span>COMPARE</span></label></div>' +
      '<div class="ac-tier">' + this.esc(a.tier) + '</div>' +
      '<div class="ac-band">' + this.esc(this.fmtBand(a)) + '<span class="per">/MO EST.</span></div>' +
      '<div class="ac-badges">' +
        '<span class="badge ' + (a.transparency === 'Published' ? 'b-pub' : 'b-inf') + '">' + this.esc(a.transparency.toUpperCase()) + ' PRICING</span>' +
        '<span class="badge ' + this.aiClass(a.ai) + '">AI: ' + this.esc(a.ai.toUpperCase()) + '</span>' +
      '</div>' +
      '<div class="ac-svcs">' + chips + '</div>' +
      '<div class="ac-flag"><span>FLAGSHIP //</span> ' + this.esc(a.flagship) + '</div>' +
      '<div class="ac-edge"><span>PATRIOT EDGE //</span> ' + this.esc(a.patriot_edge) + '</div>' +
      '<div class="ac-meta">' + this.esc(a.hq) + ' · ' + this.esc(a.domain) + ' · ' + this.esc(a.pricing_model) + '</div>' +
    '</article>';
  }

  render() {
    const list = this.filtered();
    const grid = document.getElementById('intel-grid');
    grid.innerHTML = list.length
      ? list.map(a => this.card(a)).join('')
      : '<div class="intel-empty">No agencies match the current filters. Reset to redeploy.</div>';
    document.getElementById('intel-count').textContent =
      'SHOWING ' + list.length + ' / ' + this.agencies.length;
    grid.querySelectorAll('input[data-compare]').forEach(cb => {
      cb.addEventListener('change', () => this.onCompareToggle(cb));
    });
    this.updateCompareBar();
  }

  onCompareToggle(cb) {
    const id = cb.getAttribute('data-compare');
    if (cb.checked) {
      if (this.selected.length >= 2) {
        cb.checked = false;
        this.flashStatus('Two agencies max. Uncheck one first.');
        return;
      }
      this.selected.push(id);
    } else {
      this.selected = this.selected.filter(x => x !== id);
      document.getElementById('compare-wrap').hidden = true;
    }
    this.updateCompareBar();
  }

  updateCompareBar() {
    const bar = document.getElementById('compare-bar');
    const open = document.getElementById('compare-open');
    const status = document.getElementById('compare-status');
    bar.hidden = false;
    if (this.selected.length === 2) {
      const [a, b] = this.selected.map(id => this.agencies.find(x => x.id === id));
      status.textContent = 'LOCKED: ' + a.name + ' vs ' + b.name;
      open.disabled = false;
    } else if (this.selected.length === 1) {
      const a = this.agencies.find(x => x.id === this.selected[0]);
      status.textContent = a.name + ' locked. Pick one more.';
      open.disabled = true;
    } else {
      status.textContent = 'Select two agencies to compare.';
      open.disabled = true;
    }
  }

  flashStatus(msg) {
    const status = document.getElementById('compare-status');
    status.textContent = msg;
    setTimeout(() => this.updateCompareBar(), 1600);
  }

  svcCell(a, k) {
    return a.services[k] ? '<span class="cmp-yes">YES</span>' : '<span class="cmp-no">NO</span>';
  }

  renderCompare() {
    const [a, b] = this.selected.map(id => this.agencies.find(x => x.id === id));
    if (!a || !b) return;
    const rows = [];
    const row = (label, va, vb, cls) => rows.push(
      '<tr><th>' + this.esc(label) + '</th><td class="' + (cls || '') + '">' + va + '</td><td class="' + (cls || '') + '">' + vb + '</td></tr>');
    row('TIER', this.esc(a.tier), this.esc(b.tier));
    row('HQ', this.esc(a.hq), this.esc(b.hq));
    row('EST. MONTHLY BAND', this.esc(this.fmtBand(a)), this.esc(this.fmtBand(b)), 'cmp-band');
    row('PRICING MODEL', this.esc(a.pricing_model), this.esc(b.pricing_model));
    row('PRICE TRANSPARENCY', this.esc(a.transparency), this.esc(b.transparency));
    row('AI STRENGTH', this.esc(a.ai), this.esc(b.ai));
    row('AI ASSET', this.esc(a.ai_note), this.esc(b.ai_note));
    row('FLAGSHIP', this.esc(a.flagship), this.esc(b.flagship));
    this.SVC.forEach(([k, label]) => row(label.toUpperCase(), this.svcCell(a, k), this.svcCell(b, k)));
    row('PATRIOT EDGE', this.esc(a.patriot_edge), this.esc(b.patriot_edge), 'cmp-edge');
    const t = document.getElementById('compare-table');
    t.innerHTML = '<thead><tr><th>FIELD</th><th>' + this.esc(a.name) + '</th><th>' + this.esc(b.name) + '</th></tr></thead><tbody>' + rows.join('') + '</tbody>';
    const wrap = document.getElementById('compare-wrap');
    wrap.hidden = false;
    wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  bind() {
    ['intel-q', 'intel-tier', 'intel-ai', 'intel-svc'].forEach(id => {
      document.getElementById(id).addEventListener('input', () => this.render());
    });
    document.getElementById('intel-published').addEventListener('change', () => this.render());
    document.getElementById('intel-reset').addEventListener('click', () => {
      document.getElementById('intel-q').value = '';
      document.getElementById('intel-tier').value = '';
      document.getElementById('intel-ai').value = '';
      document.getElementById('intel-svc').value = '';
      document.getElementById('intel-published').checked = false;
      this.render();
    });
    document.getElementById('compare-clear').addEventListener('click', () => {
      this.selected = [];
      document.getElementById('compare-wrap').hidden = true;
      this.render();
    });
    document.getElementById('compare-open').addEventListener('click', () => this.renderCompare());
    document.getElementById('compare-close').addEventListener('click', () => {
      document.getElementById('compare-wrap').hidden = true;
    });
  }

  async init() {
    await this.load();
    if (!this.agencies.length) {
      document.getElementById('intel-grid').innerHTML =
        '<div class="intel-empty">Intel file unavailable. Reload to retry.</div>';
      return;
    }
    this.fillOptions();
    this.bind();
    this.render();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new IntelBoard().init();
});
