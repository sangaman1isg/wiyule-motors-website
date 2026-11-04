<?php
/**
 * includes/analytics.php — Google Analytics 4 (GA4) tracking snippet
 * ------------------------------------------------------------------
 * Include this inside the <head> of every page you want to track:
 *     <?php include __DIR__ . '/includes/analytics.php'; ?>
 * (adjust the path depending on the page's folder — see notes below)
 *
 * SETUP: replace G-XXXXXXXXXX below with your real Measurement ID
 * from https://analytics.google.com (Admin → Data Streams → Web).
 *
 * Tracking is automatically DISABLED on localhost so your own testing
 * doesn't pollute the real visitor stats.
 */

$GA_MEASUREMENT_ID = 'G-HMM03HL39K';   // <-- PASTE YOUR REAL ID HERE

// Don't track local development / testing
$ga_host = $_SERVER['HTTP_HOST'] ?? '';
$ga_is_local = (strpos($ga_host, 'localhost') !== false || strpos($ga_host, '127.0.0.1') !== false);

if ($GA_MEASUREMENT_ID !== 'G-HMM03HL39K' && !$ga_is_local):
?>
<!-- Google Analytics (GA4) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($GA_MEASUREMENT_ID) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= htmlspecialchars($GA_MEASUREMENT_ID) ?>');
</script>
<!-- End Google Analytics -->
<?php endif; ?>
