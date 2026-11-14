<?php
/*
 * Page footer shared by index.php and submit.php: copyright, the Support
 * AMSAT button, deploy info and the Acknowledgments / API / Changelog
 * links. Expects $appCommitSha and $appDeployedAt from config.php.
 */
?>
<hr>
<br>
<center>
&copy; Radio Amateur Satellite Corporation (AMSAT)
<div class="paypal_button" style="margin-top:10px;">
<a href="https://www.amsat.org/donations/amsat-general-fund-donations/" style="display:inline-block;padding:6px 12px;background-color:#648fff;color:#ffffff;text-decoration:none;border-radius:4px;font-size:16px;">Support AMSAT</a>
</div>
<p style="font-size:12px;color:#888;"><?php if ($appCommitSha !== null && $appDeployedAt !== null): ?>Last updated <?php echo htmlspecialchars(date('m/d/Y H:i', strtotime($appDeployedAt))); ?> UTC (commit <?php echo htmlspecialchars(substr($appCommitSha, 0, 7)); ?>) &middot; <?php endif; ?><a href="https://github.com/AMSAT-NA/satellite-status/blob/main/ACKNOWLEDGMENTS.md" target="_blank" rel="noopener" style="color:#888;">Acknowledgments</a> &middot; <a href="/api/v1/overview.php" style="color:#888;">API</a> &middot; <a href="https://github.com/AMSAT-NA/satellite-status/blob/main/CHANGELOG" target="_blank" rel="noopener" style="color:#888;">Changelog</a></p>
<br>
