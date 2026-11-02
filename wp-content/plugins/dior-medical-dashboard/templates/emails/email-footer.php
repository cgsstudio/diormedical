<?php
/**
 * Shared Dior Medical email footer.
 * Expected: $site_name, $site_url.
 */
if (!defined('ABSPATH')) { exit; }
$site_name = isset($site_name) ? $site_name : (get_bloginfo('name') ?: 'Dior Medical');
$site_url = isset($site_url) ? $site_url : home_url('/');
?>
</td></tr>
<tr><td style="padding:20px 30px;background:#f7f9fc;border-top:1px solid #e8edf3;font-size:11px;line-height:1.6;color:#64748b">
<strong><?php echo esc_html($site_name); ?></strong><br>
This is an automated message about your secure healthcare portal. Please sign in to your dashboard for assistance.<br>
<a href="<?php echo esc_url($site_url); ?>" style="color:#007eb1;text-decoration:none">Visit website</a>
</td></tr></table></td></tr></table></body></html>
