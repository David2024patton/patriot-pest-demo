<?php
/**
 * templates/partials/egg.php - THE BEACON (ORDER 1) hidden Easter egg.
 * Rendered by layouts/main.php ONLY when the egg_enabled site setting is on.
 * Strings are Hamilton locked, zero em dashes. The jackpot payline is ONE
 * contiguous text run in the h2 (blink + $25 spans live inside it) so
 * textContent equals the locked sentence verbatim (Rockwell build note).
 */
?>
<button id="egg" class="egg" data-corner="br" aria-label="Hidden mission cache. 0 of 5 signals acquired.">
  <svg viewBox="0 0 44 44" aria-hidden="true"><path class="star" d="M22 5 L26.5 17.5 L39 22 L26.5 26.5 L22 39 L17.5 26.5 L5 22 L17.5 17.5 Z"/><circle class="dot" cx="22" cy="22" r="3.6"/></svg>
  <span class="egg-ring" aria-hidden="true"></span>
</button>

<div id="toasts" aria-live="polite"></div>

<div id="egg-modal" role="dialog" aria-modal="true" aria-labelledby="egg-modal-title">
  <div class="modal-frame"><i aria-hidden="true"></i>
    <div class="rays" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
    <div class="burst" aria-hidden="true"></div>
    <div class="tag">// OPERATION CACHE // JACKPOT</div>
    <h2 id="egg-modal-title">JACKPOT HIT. <span class="blink">CACHE UNLOCKED.</span> <span class="amt">$25</span> mission reward secured.</h2>
    <div class="machine">
      <div class="marquee">MISSION REWARD <b>//</b> $25</div>
      <div class="reels" id="reels" aria-label="Jackpot reels"></div>
    </div>
    <div class="code-drop" id="code-drop">PATRIOT25</div>
    <p class="pay">$25 off any plan at checkout. Single use. 90 day validity.</p>
    <button class="btn" id="egg-continue">CONTINUE HUNT</button>
  </div>
</div>
