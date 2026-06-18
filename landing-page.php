<?php
/**
 * Vi2ionai Fleet — Lead Capture Landing Page
 * POSTs to /api/contact.php via fetch; no Google Forms dependency.
 */

// Load env config (provides GA4_MEASUREMENT_ID, META_PIXEL_ID, GSC_VERIFICATION)
$_env = __DIR__ . '/includes/.env.php';
if (file_exists($_env)) { require_once $_env; }

$title       = 'Get a Free Fleet GPS Quote — Vi2ionai Australia';
$description = 'Talk to an Australian fleet specialist. Enterprise GPS tracking, AI dash cams and fleet management from $25/vehicle/month. No contracts. Fast response.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= htmlspecialchars($title) ?></title>
<meta name="description" content="<?= htmlspecialchars($description) ?>"/>
<meta name="robots" content="index, follow"/>
<meta property="og:title" content="<?= htmlspecialchars($title) ?>"/>
<meta property="og:description" content="<?= htmlspecialchars($description) ?>"/>
<meta property="og:type" content="website"/>
<meta property="og:url" content="https://vi2ionai.com.au/landing-page.php"/>
<meta property="og:locale" content="en_AU"/>
<link rel="canonical" href="https://vi2ionai.com.au/landing-page.php"/>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='16' fill='%230a0a0a'/><path d='M16 6C12 6 9 9 9 13c0 7 7 15 7 15s7-8 7-15c0-4-3-7-7-7z' fill='%23fff'/><circle cx='16' cy='13' r='3.5' fill='%230a0a0a'/></svg>"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<?php require_once __DIR__ . '/includes/analytics.php'; ?>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --black:#0a0a0a;--white:#fff;
  --blue:#1d4ed8;--blue-dark:#1e40af;
  --text:#0f172a;--text-muted:#64748b;
  --border:#e2e8f0;--bg:#f8fafc;
  --radius:10px;--radius-lg:16px;
  --shadow-lg:0 10px 40px rgba(0,0,0,.14);
}
body{font-family:'Manrope',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;}
a{text-decoration:none;color:inherit;}

/* ── Nav ── */
nav{background:var(--black);position:sticky;top:0;z-index:100;}
.nav-inner{max-width:1100px;margin:0 auto;padding:0 24px;height:62px;display:flex;align-items:center;justify-content:space-between;}
.nav-logo{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.2rem;font-weight:800;color:#fff;letter-spacing:-.02em;}
.nav-logo span{color:#555;font-weight:500;}
.nav-cta{background:#fff;color:#000;border:none;padding:8px 18px;border-radius:6px;font-family:'Manrope',sans-serif;font-size:.82rem;font-weight:700;cursor:pointer;transition:opacity .15s;}
.nav-cta:hover{opacity:.85;}

/* ── Hero ── */
.hero{text-align:center;padding:60px 24px 36px;background:var(--white);}
.hero-eyebrow{display:inline-block;background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;border-radius:20px;padding:4px 14px;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:18px;}
.hero h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:clamp(1.9rem,4vw,2.9rem);font-weight:800;letter-spacing:-.03em;line-height:1.12;margin-bottom:14px;}
.hero p{font-size:.97rem;color:var(--text-muted);max-width:500px;margin:0 auto;line-height:1.65;}

/* ── Trust bar ── */
.trust-bar{background:#f8fafc;border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:14px 24px;display:flex;align-items:center;justify-content:center;gap:32px;flex-wrap:wrap;}
.trust-item{display:flex;align-items:center;gap:7px;font-size:.78rem;font-weight:600;color:#475569;}
.trust-item svg{color:#16a34a;}

/* ── Layout ── */
.page-body{flex:1;display:flex;justify-content:center;padding:48px 24px 64px;}
.form-card{background:var(--black);border-radius:var(--radius-lg);padding:40px 36px;width:100%;max-width:580px;box-shadow:var(--shadow-lg);}
.form-card-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.25rem;font-weight:800;color:#fff;margin-bottom:6px;letter-spacing:-.02em;}
.form-card-sub{font-size:.82rem;color:#64748b;margin-bottom:28px;}

.field{margin-bottom:16px;}
.field label{display:block;font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-weight:700;}
.field input,
.field select,
.field textarea{width:100%;background:#111;border:1px solid rgba(255,255,255,.1);border-radius:var(--radius);padding:11px 14px;font-family:'Manrope',sans-serif;font-size:.88rem;color:#f1f5f9;outline:none;transition:border-color .15s;}
.field input::placeholder,.field textarea::placeholder{color:#475569;}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#3b82f6;background:#0d1117;}
.field select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;cursor:pointer;}
.field select option{background:#111;}
.field textarea{resize:vertical;min-height:90px;line-height:1.5;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

.submit-btn{width:100%;background:#fff;color:#000;border:none;padding:14px;border-radius:var(--radius);font-family:'Manrope',sans-serif;font-size:.95rem;font-weight:800;cursor:pointer;margin-top:8px;transition:opacity .15s,transform .1s;display:flex;align-items:center;justify-content:center;gap:8px;letter-spacing:-.01em;}
.submit-btn:hover{opacity:.9;transform:translateY(-1px);}
.submit-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}

.alert{border-radius:var(--radius);padding:14px 16px;margin-bottom:20px;font-size:.85rem;line-height:1.5;}
.alert-error{background:#450a0a;border:1px solid #7f1d1d;color:#fca5a5;}
.alert-success{background:#052e16;border:1px solid #14532d;color:#86efac;text-align:center;padding:36px 24px;}
.alert-success h2{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.3rem;font-weight:800;color:#fff;margin-bottom:10px;}
.alert-success p{color:#94a3b8;font-size:.88rem;line-height:1.6;}

/* ── Footer ── */
footer{background:var(--black);color:#555;text-align:center;padding:24px;font-size:.78rem;}
footer a{color:#444;}footer a:hover{color:#888;}

@media(max-width:500px){
  .form-card{padding:28px 18px;}
  .form-row{grid-template-columns:1fr;}
  .trust-bar{gap:16px;}
  .hero{padding:44px 16px 28px;}
}
</style>
</head>
<body>

<nav>
  <div class="nav-inner">
    <a href="/" class="nav-logo">VI2IONAI <span>Fleet</span></a>
    <a href="/" class="nav-cta">← View Products</a>
  </div>
</nav>

<div class="hero">
  <div class="hero-eyebrow">Australian Fleet Specialists</div>
  <h1>Fleet GPS Tracking<br>Built for Australian Business</h1>
  <p>Fill in your details and a Vi2ionai fleet specialist will get back to you within one business day.</p>
</div>

<div class="trust-bar">
  <span class="trust-item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    No lock-in contracts
  </span>
  <span class="trust-item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    Free demo available
  </span>
  <span class="trust-item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    AU-based support
  </span>
  <span class="trust-item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    1–200+ vehicle fleets
  </span>
</div>

<div class="page-body">
  <div class="form-card">
    <div class="form-card-title">Request a Free Consultation</div>
    <div class="form-card-sub">We'll prepare a tailored quote for your fleet.</div>

    <div id="form-alert"></div>

    <form id="enquiry-form">
      <div class="form-row">
        <div class="field">
          <label>First Name *</label>
          <input type="text" name="first_name" placeholder="Sarah" required autocomplete="given-name"/>
        </div>
        <div class="field">
          <label>Last Name *</label>
          <input type="text" name="last_name" placeholder="Thompson" required autocomplete="family-name"/>
        </div>
      </div>
      <div class="field">
        <label>Company Email *</label>
        <input type="email" name="email" placeholder="sarah@company.com.au" required autocomplete="email"/>
      </div>
      <div class="field">
        <label>Company Name</label>
        <input type="text" name="company" placeholder="Acme Transport Pty Ltd" autocomplete="organization"/>
      </div>
      <div class="field">
        <label>Fleet Size *</label>
        <select name="fleet_size" required>
          <option value="">Select fleet size…</option>
          <option value="1–10 vehicles">1–10 vehicles</option>
          <option value="11–50 vehicles">11–50 vehicles</option>
          <option value="51–200 vehicles">51–200 vehicles</option>
          <option value="200+ vehicles">200+ vehicles</option>
        </select>
      </div>
      <div class="field">
        <label>How can we help?</label>
        <textarea name="message" placeholder="Tell us about your fleet needs, current pain points, or products you're interested in…"></textarea>
      </div>
      <button type="submit" class="submit-btn" id="submit-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Send Enquiry
      </button>
    </form>
  </div>
</div>

<footer>
  <div>0422 55 7927 &middot; info@vi2ionai.com.au &middot; 10 Brown Street, NSW 2067</div>
  <div style="margin-top:6px">&copy; <?= date('Y') ?> Vi2ionai Pty Ltd &middot; <a href="/terms.html">Terms &amp; Conditions</a></div>
</footer>

<script>
document.getElementById('enquiry-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn   = document.getElementById('submit-btn');
  const alert = document.getElementById('form-alert');
  const data  = Object.fromEntries(new FormData(this));

  btn.disabled   = true;
  btn.textContent = 'Sending…';
  alert.innerHTML = '';

  try {
    const res  = await fetch('/api/contact.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const json = await res.json();

    if (json.success) {
      document.getElementById('enquiry-form').style.display = 'none';
      alert.innerHTML = `
        <div class="alert alert-success">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="2" style="display:block;margin:0 auto 14px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <h2>Enquiry Sent!</h2>
          <p>Thanks ${data.first_name || ''}! We've received your enquiry and will get back to you within 1 business day.</p>
        </div>`;
      // ── Conversion tracking ──
      try { if(typeof gtag!=='undefined') gtag('event','generate_lead',{form_id:'landing_page_enquiry',fleet_size:data.fleet_size||''}); } catch(_){}
      try { if(typeof fbq!=='undefined'){ fbq('track','Lead',{content_name:'landing_page_enquiry',content_category:'fleet_gps'}); fbq('track','CompleteRegistration'); } } catch(_){}
    } else {
      const msg = Array.isArray(json.errors)
        ? '<ul>' + json.errors.map(e => `<li>${e}</li>`).join('') + '</ul>'
        : (json.message || 'Something went wrong. Please try again.');
      alert.innerHTML = `<div class="alert alert-error">${msg}</div>`;
      btn.disabled   = false;
      btn.innerHTML  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Enquiry';
    }
  } catch {
    alert.innerHTML = '<div class="alert alert-error">Network error. Please check your connection and try again.</div>';
    btn.disabled   = false;
    btn.textContent = 'Send Enquiry';
  }
});
</script>
</body>
</html>
