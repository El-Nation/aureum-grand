<?php
/**
 * Root index.php — redirects to the public-facing homepage.
 * Visiting the bare domain (e.g. https://yourdomain.com/) lands here
 * and forwards to /public/index.php.
 */
header('Location: public/index.php');
exit;
