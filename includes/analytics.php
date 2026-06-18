<?php
/**
 * Vi2ionai Fleet — Analytics head fragment
 *
 * Include inside <head> on every PHP page.
 * Activates GA4, Meta Pixel and GSC verification automatically
 * when the corresponding ID/code is set in .env.php (server-only).
 *
 * Usage:   <?php require_once __DIR__ . '/analytics.php'; ?>
 *
 * IDs are defined in /var/www/vi2ionai/includes/.env.php (never committed).
 * See includes/env.example.php for the constant names.
 */

$_vi2_ga4   = defined('GA4_MEASUREMENT_ID') ? trim(GA4_MEASUREMENT_ID)  : '';
$_vi2_pixel = defined('META_PIXEL_ID')       ? trim(META_PIXEL_ID)       : '';
$_vi2_gsc   = defined('GSC_VERIFICATION')    ? trim(GSC_VERIFICATION)    : '';

// Google Search Console verification meta tag
if ($_vi2_gsc !== ''): ?>
<meta name="google-site-verification" content="<?= htmlspecialchars($_vi2_gsc, ENT_QUOTES) ?>" />
<?php endif; ?>

<?php if ($_vi2_ga4 !== ''): ?>
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($_vi2_ga4, ENT_QUOTES) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?= htmlspecialchars($_vi2_ga4, ENT_QUOTES) ?>', { send_page_view: true });
</script>
<?php endif; ?>

<?php if ($_vi2_pixel !== ''): ?>
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init','<?= htmlspecialchars($_vi2_pixel, ENT_QUOTES) ?>');
fbq('track','PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt=""
  src="https://www.facebook.com/tr?id=<?= htmlspecialchars($_vi2_pixel, ENT_QUOTES) ?>&ev=PageView&noscript=1"/></noscript>
<?php endif; ?>
<?php
// Export flags as JSON for the event-tracking layer
$_vi2_json_config = json_encode([
    'ga4'   => $_vi2_ga4 !== '',
    'pixel' => $_vi2_pixel !== '',
]);
?>
<script>window.__VI2_ANALYTICS__ = <?= $_vi2_json_config ?>;</script>
