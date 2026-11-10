<?php
// pricing.php — RETIRED. The standalone pricing page was removed; 301 any
// visitors/bookmarks/search hits to the homepage so there are no dead links.
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
header('Location: ' . $base . '/', true, 301);
exit;
