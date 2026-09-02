package view

// pageSignup — website account signup. Public marketing surface: no login
// required, form writes a customer record with source='website' for
// marketing attribution. Fires the generate_lead conversion event on success.
const pageSignup = `<section class="block">
  <div class="wrap" style="max-width:720px">
    <div class="eyebrow">ACCOUNT // SIGN UP</div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(2rem,6vw,3rem);margin:.4rem 0 .8rem">Create your <em>account.</em></h1>
    <p class="lead">Sign up online to request service, track visits, and get member pricing. No card required.</p>
  </div>
</section>

<section class="block alt">
  <div class="wrap" style="max-width:720px">
    <div class="form-panel">
      {{if .Success}}<div class="notice success">{{.Success}}</div>
        {{if eq .AnalyticsEvent "generate_lead"}}
        <script>
          gtag('event', 'generate_lead', {
            'event_category': 'conversion',
            'event_label': 'Account Signup'
          });
        </script>
        {{end}}
      {{end}}
      {{if .Errors}}<div class="notice error">
        <strong>Please fix the following:</strong>
        <ul>{{range .Errors}}<li>{{.}}</li>{{end}}</ul>
      </div>{{end}}

      <form method="post" action="/signup" class="form-stack" novalidate>
        {{raw .Csrf}}
        <div class="field">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" required value="{{.OldName}}">
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="{{.OldEmail}}">
        </div>
        <div class="field">
          <label for="phone">Phone <span style="color:var(--khaki)">(optional)</span></label>
          <input type="tel" id="phone" name="phone" value="{{.OldPhone}}">
        </div>
        <div class="split" style="gap:.8rem">
          <div class="field">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="{{.OldCity}}">
          </div>
          <div class="field">
            <label for="state">State</label>
            <input type="text" id="state" name="state" maxlength="2" placeholder="WA" value="{{.OldState}}">
          </div>
        </div>
        <div class="field">
          <label for="zip">ZIP <span style="color:var(--khaki)">(optional)</span></label>
          <input type="text" id="zip" name="zip" maxlength="10" value="{{.OldZip}}">
        </div>
        <button type="submit" style="min-height:48px;background:var(--orange);color:var(--ink);border:0;padding:0 1.6rem;font-family:var(--display);text-transform:uppercase;cursor:pointer">Sign Up</button>
        <p style="color:var(--khaki);font-size:.8rem">Already have an account? <a href="/login" style="color:var(--orange)">Log in</a> (staff &amp; existing customers).</p>
      </form>
    </div>
  </div>
</section>`

func init() {
	RegisterPageTemplate("signup", pageSignup)
}