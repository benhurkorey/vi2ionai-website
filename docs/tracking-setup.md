# Vi2ionai Fleet — Analytics & Conversion Tracking Setup

All tracking code is **already deployed** — you just need to paste your IDs to activate it. No developer needed.

---

## Quick Activation (3 steps)

### Step 1 — Get your IDs

| Platform | Where to find it |
|---|---|
| **Google Analytics 4** | [analytics.google.com](https://analytics.google.com) → Admin → Data Streams → Web stream → Measurement ID (starts with `G-`) |
| **Meta Pixel** | [business.facebook.com](https://business.facebook.com) → Events Manager → Pixels → Your pixel ID (16-digit number) |
| **Google Search Console** | [search.google.com/search-console](https://search.google.com/search-console) → Add property → HTML tag → copy the `content=` value only |

### Step 2 — Edit the VPS config file

SSH into the VPS and edit the `.env.php`:

```bash
ssh -i ~/.ssh/vi2ion_fleet root@72.62.192.223
nano /var/www/vi2ionai/includes/.env.php
```

Add your IDs to the three analytics lines:

```php
define('GA4_MEASUREMENT_ID', 'G-XXXXXXXXXX');    // ← your GA4 ID
define('META_PIXEL_ID',       '1234567890123456'); // ← your Meta Pixel ID
define('GSC_VERIFICATION',    'abc123xyz...');     // ← GSC content= value
```

### Step 3 — Activate on homepage (static HTML)

Open `index.html`, find this block near the top:

```javascript
window.VI2_CFG = {
  GA4_ID:   '',   // TODO → replace with 'G-XXXXXXXXXX'
  PIXEL_ID: '',   // TODO → replace with '1234567890123456'
  GSC_CODE: '',   // TODO → replace with Google Search Console HTML tag content= value
};
```

Replace the empty strings with your real IDs, commit, and push — the GitHub Action will deploy automatically.

---

## Events Already Tracked

All events fire to both GA4 and Meta Pixel when IDs are configured.

### Homepage (`index.html`)

| Event Name | Trigger | GA4 Category | Meta Pixel Event |
|---|---|---|---|
| `cta_click` | Click any "Get a Free Quote" or "landing-page" link | `engagement` | `Lead` |
| `phone_click` | Click any `tel:` link | `engagement` | `Contact` |
| `form_submit` | Homepage contact form submitted successfully | `conversion` | `Lead` |
| `scroll_depth` | Page scrolled to 25%, 50%, 75%, 100% | `engagement` | `CustomEvent` |

### Lead Form (`landing-page.php`)

| Event Name | Trigger | GA4 Category | Meta Pixel Event |
|---|---|---|---|
| `generate_lead` | Enquiry form submitted successfully | `conversion` | `Lead` + `CompleteRegistration` |

---

## GA4 Conversion Goals to Set Up

After activating GA4, mark these events as **conversions** in GA4 → Admin → Conversions:

1. `form_submit` — homepage enquiry
2. `generate_lead` — landing page enquiry
3. `phone_click` — phone call intent

**Recommended GA4 goals:**
- New Conversion: `form_submit`
- New Conversion: `generate_lead`
- New Conversion: `phone_click`

---

## Meta Pixel Standard Events Used

| Standard Event | When it fires |
|---|---|
| `PageView` | Every page load (automatic) |
| `Lead` | Form submitted OR "Get a Quote" CTA clicked |
| `Contact` | Phone number clicked |
| `CompleteRegistration` | Landing page form submitted |
| `CustomEvent` | Scroll depth milestones |

**Recommended Meta Pixel custom conversions:**
- `Lead` event = "Fleet Enquiry Lead"
- `CompleteRegistration` = "Landing Page Conversion"

---

## Google Search Console Setup

1. Add property: `https://vi2ionai.com.au`
2. Choose "HTML tag" verification method
3. Copy only the `content=` value (not the full tag)
4. Paste into `.env.php` and `index.html` VI2_CFG as above
5. Click "Verify" in Search Console

**After verification:**
- Submit sitemap: `https://vi2ionai.com.au/sitemap.xml`
- Check Core Web Vitals report after 28 days

---

## Custom Audiences for Meta Ads

Once the pixel is active, create these audiences in Meta:

| Audience | Rules |
|---|---|
| **Website Visitors (30d)** | Visited vi2ionai.com.au (any page) |
| **High Intent (7d)** | Visited `/landing-page.php` OR `form_submit` event |
| **Industry Interest** | Visited any `/transport-\|construction-\|mining-\|refrigerated-\|trades-` page |
| **Lookalike** | Based on "High Intent" source audience, 1% AU |

---

## GA4 Audiences for Google Ads

| Audience | Condition |
|---|---|
| **All Website Visitors** | Session start, any page |
| **Quote Page Visitors** | Page path contains `landing-page` |
| **Industry Page Visitors** | Page path contains `fleet-tracking` |
| **Converted Leads** | Event: `generate_lead` or `form_submit` |

---

## UTM Tracking for Campaigns

Use these UTM parameters on all paid and outbound links:

```
https://vi2ionai.com.au/landing-page.php
  ?utm_source=google
  &utm_medium=cpc
  &utm_campaign=fleet-gps-au
  &utm_term=fleet+gps+tracking+australia
  &utm_content=search-ad-v1
```

Standard sources: `google`, `facebook`, `linkedin`, `email`, `referral`

---

## Files Modified in This Phase

| File | Change |
|---|---|
| `index.html` | Analytics config block + event tracking layer |
| `landing-page.php` | `analytics.php` include + conversion tracking on success |
| `includes/analytics.php` | **NEW** — PHP analytics head fragment for all PHP pages |
| `includes/env.example.php` | Added GA4, Meta Pixel, GSC constants |
| `docs/tracking-setup.md` | **NEW** — this file |
