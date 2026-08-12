<?php
/**
 * staff/lead-new.php — lead creation form (staff/admin with create_customers permission).
 * Vars: $districts, $flash.
 */
$districts = $data['districts'] ?? [];
$flash = $data['flash'] ?? null;
?>
<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1>Create New Lead</h1>
        <div class="sub">Add a new lead to FieldRoutes CRM.</div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/staff/customers">Back to Customers</a>
      </div>
    </div>

    <?php if ($flash): ?>
    <div class="flash <?= !empty($flash['success']) ? 'success' : 'error' ?>">
      <?php if (!empty($flash['success'])): ?>
        <?= $view->e($flash['success']) ?>
      <?php elseif (!empty($flash['error'])): ?>
        <?= $view->e($flash['error']) ?>
      <?php elseif (!empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $field => $msgs): ?>
          <div><?= $view->e(ucfirst($field)) ?>: <?= $view->e(implode(', ', $msgs)) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="panel" style="max-width:800px">
      <form method="post" action="/staff/leads">
        <?= $view->csrf() ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div>
            <label for="firstName" style="color:var(--cream)">First Name *</label>
            <input type="text" id="firstName" name="firstName" required maxlength="100"
              style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
          </div>
          <div>
            <label for="lastName" style="color:var(--cream)">Last Name *</label>
            <input type="text" id="lastName" name="lastName" required maxlength="100"
              style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
          </div>
        </div>

        <div style="margin-bottom:1rem">
          <label for="companyName" style="color:var(--cream)">Company Name (optional)</label>
          <input type="text" id="companyName" name="companyName" maxlength="200"
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div>
            <label for="email" style="color:var(--cream)">Email</label>
            <input type="email" id="email" name="email" maxlength="254"
              style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
          </div>
          <div>
            <label for="phone" style="color:var(--cream)">Phone *</label>
            <input type="tel" id="phone" name="phone" required maxlength="20"
              style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
          </div>
        </div>

        <div style="margin-bottom:1rem">
          <label for="address" style="color:var(--cream)">Street Address</label>
          <input type="text" id="address" name="address" maxlength="200"
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div>
            <label for="city" style="color:var(--cream)">City</label>
            <input type="text" id="city" name="city" maxlength="100"
              style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
          </div>
          <div>
            <label for="state" style="color:var(--cream)">State</label>
            <input type="text" id="state" name="state" maxlength="50"
              style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
          </div>
          <div>
            <label for="zip" style="color:var(--cream)">ZIP Code</label>
            <input type="text" id="zip" name="zip" maxlength="20"
              style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
          </div>
        </div>

        <div style="margin-bottom:1rem">
          <label for="district" style="color:var(--cream)">District *</label>
          <select id="district" name="district" required
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
            <option value="">Select District</option>
            <?php foreach ($districts as $d): ?>
            <option value="<?= $view->e($d['code']) ?>">
              <?= $view->e(ucfirst($d['code'])) ?> District
            </option>
            <?php endforeach; ?>
          </select>
          <div style="font-size:.75rem;color:var(--khaki);margin-top:.3rem">
            Select the FieldRoutes district where this lead should be created.
          </div>
        </div>

        <div style="margin-bottom:1rem">
          <label for="source" style="color:var(--cream)">Lead Source</label>
          <select id="source" name="source"
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
            <option value="Manual Entry">Manual Entry</option>
            <option value="Website">Website</option>
            <option value="Phone Call">Phone Call</option>
            <option value="Referral">Referral</option>
            <option value="Facebook">Facebook</option>
            <option value="Google">Google</option>
            <option value="Yelp">Yelp</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div style="margin-bottom:1rem">
          <label for="notes" style="color:var(--cream)">Notes</label>
          <textarea id="notes" name="notes" rows="4"
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem;resize:vertical"></textarea>
        </div>

        <button type="submit" class="btn" style="background:var(--orange);color:var(--olive-950);padding:.5rem 1.2rem;border:none">
          Create Lead
        </button>
      </form>
    </div>

    <div class="panel" style="margin-top:1.6rem">
      <h3>Lead Creation Information</h3>
      <div style="margin-top:.8rem;font-size:.85rem;color:var(--khaki)">
        <p>This form creates a new lead directly in the FieldRoutes CRM system. The lead will be:</p>
        <ul style="margin-left:1.5rem;margin-top:.4rem;line-height:1.6">
          <li>Created in the selected FieldRoutes district</li>
          <li>Automatically synced to the local customer database</li>
          <li>Available for follow-up and conversion to customers</li>
          <li>Accessible in both the FieldRoutes dashboard and this system</li>
        </ul>
        <p style="margin-top:.8rem"><strong>District Selection:</strong></p>
        <ul style="margin-left:1.5rem;margin-top:.4rem;line-height:1.6">
          <li><strong>WA District:</strong> Washington, Idaho, Oregon operations</li>
          <li><strong>AZ District:</strong> Arizona operations</li>
        </ul>
      </div>
    </div>
  </div>
</div>