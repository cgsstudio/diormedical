<?php
/**
 * Shared footer for Dior Medical HTML emails.
 *
 * Expected optional variable: $site_url.
 * Closes the outer container and document opened by email-header.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_footer_url = isset( $site_url ) && $site_url ? $site_url : home_url( '/' );
?>
                <tr>
                    <td style="padding:18px 28px 22px;background-color:#f8fafc;color:#71839c;font-size:11px;line-height:17px;">
                        <div style="font-weight:700;">DIOR MEDICAL Telehealth Practice · Secure Digital Health Platform</div>
                        <div style="padding-top:7px;">This is an automated notification regarding your healthcare portal activity. Please do not reply directly to this automated email. For assistance, sign in to your dashboard.</div>
                        <div style="padding-top:7px;">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> DIOR MEDICAL. All rights reserved. · <a href="<?php echo esc_url( $email_footer_url ); ?>" style="color:#2865a5;text-decoration:none;">Visit Website</a></div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
