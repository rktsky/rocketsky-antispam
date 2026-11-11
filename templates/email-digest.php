<?php
/**
 * Renders the aggregate daily email with inline styles and no remote resources.
 *
 * The sender prepares and localizes every value before including this read-only
 * template. Counts describe rejected checks, not confirmed spam or accuracy.
 * Presentation tables and a conditional fixed-width wrapper retain a readable
 * baseline in Outlook; clients supporting max-width receive a fluid layout.
 *
 * @package RktskyAntispam
 * @since 0.5.4
 * @var array{language:string,direction:string,site_name:string,title:string,date:string,calendar:string,count:string,count_label:string,note:string,activity_label:string,activity_url:string,settings_label:string,settings_url:string,footer:string} $email Localized plain text, document language/direction and authenticated administration URLs. No message contents or visitor details.
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $email['language'] ); ?>" dir="<?php echo esc_attr( $email['direction'] ); ?>">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="format-detection" content="telephone=no,address=no,email=no,date=no" />
	<title><?php echo esc_html( $email['title'] ); ?></title>
</head>
<body style="width:100%; margin:0; padding:0; background-color:#f1eff4; color:#17142d; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:1.5; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
	<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#f1eff4" style="width:100%; border-collapse:collapse; background-color:#f1eff4; mso-table-lspace:0pt; mso-table-rspace:0pt;">
		<tr>
			<td align="center" style="padding:24px 12px;">
				<!-- Outlook's Word renderer needs a width attribute outside the fluid card. -->
				<!--[if mso]>
				<table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" align="center"><tr><td>
				<![endif]-->
				<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" bgcolor="#fffefa" style="width:100%; max-width:600px; table-layout:fixed; margin:0 auto; border:1px solid #d7d4df; border-spacing:0; border-collapse:separate; border-radius:12px; background-color:#fffefa; mso-table-lspace:0pt; mso-table-rspace:0pt;">
					<tr>
						<td bgcolor="#17142d" style="padding:22px 24px; padding-right:4%; padding-left:4%; border-top:4px solid #d4ff52; border-radius:11px 11px 0 0; background-color:#17142d; color:#fffefa; font-family:Arial,Helvetica,sans-serif;">
							<p style="margin:0; color:#fffefa; font-size:20px; font-weight:700; line-height:28px;">Rocketsky Antispam</p>
						</td>
					</tr>
					<tr>
						<td align="<?php echo esc_attr( 'rtl' === $email['direction'] ? 'right' : 'left' ); ?>" style="padding:28px 24px 24px; padding-right:4%; padding-left:4%; font-family:Arial,Helvetica,sans-serif;">
							<h1 style="margin:0 0 12px; color:#17142d; font-size:22px; font-weight:700; line-height:30px; word-wrap:break-word; overflow-wrap:anywhere;"><?php echo esc_html( $email['title'] ); ?></h1>
							<p style="margin:0 0 16px; color:#50349b; font-size:16px; font-weight:700; line-height:24px; word-wrap:break-word; overflow-wrap:anywhere;"><?php echo esc_html( $email['site_name'] ); ?></p>
							<p style="margin:0 0 24px; color:#17142d; font-size:16px; line-height:24px;">
								<?php echo esc_html( $email['date'] ); ?><br />
								<span style="color:#625d70; font-size:14px; line-height:22px;"><?php echo esc_html( $email['calendar'] ); ?></span>
							</p>
							<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#f5f3f8" style="width:100%; border:1px solid #ded8e9; border-spacing:0; border-collapse:separate; border-radius:8px; background-color:#f5f3f8; mso-table-lspace:0pt; mso-table-rspace:0pt;">
								<tr>
									<td style="padding:20px; font-family:Arial,Helvetica,sans-serif;">
										<p style="margin:0 0 8px; color:#50349b; font-size:44px; font-weight:700; line-height:52px; word-wrap:break-word; overflow-wrap:anywhere; word-break:break-all;"><?php echo esc_html( $email['count'] ); ?></p>
										<p style="margin:0; color:#17142d; font-size:16px; font-weight:700; line-height:24px;"><?php echo esc_html( $email['count_label'] ); ?></p>
									</td>
								</tr>
							</table>
							<p style="margin:14px 0 24px; color:#625d70; font-size:14px; line-height:22px;"><?php echo esc_html( $email['note'] ); ?></p>
							<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate; mso-table-lspace:0pt; mso-table-rspace:0pt;">
								<tr>
									<td align="center" bgcolor="#50349b" style="padding:0; mso-padding-alt:14px 22px; border:1px solid #50349b; border-radius:6px; background-color:#50349b; font-family:Arial,Helvetica,sans-serif;">
										<a href="<?php echo esc_url( $email['activity_url'] ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block; padding:14px 22px; color:#fffefa; font-size:16px; font-weight:700; line-height:24px; text-decoration:none; word-wrap:break-word; overflow-wrap:anywhere;"><?php echo esc_html( $email['activity_label'] ); ?></a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td align="<?php echo esc_attr( 'rtl' === $email['direction'] ? 'right' : 'left' ); ?>" bgcolor="#f5f3f8" style="padding:20px 24px; padding-right:4%; padding-left:4%; border-top:1px solid #d7d4df; border-radius:0 0 11px 11px; background-color:#f5f3f8; font-family:Arial,Helvetica,sans-serif;">
							<p style="margin:0 0 10px; color:#625d70; font-size:14px; line-height:22px;"><?php echo esc_html( $email['footer'] ); ?></p>
							<p style="margin:0; font-size:14px; line-height:22px;"><a href="<?php echo esc_url( $email['settings_url'] ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block; padding:11px 0; color:#50349b; font-size:14px; line-height:22px; text-decoration:underline;"><?php echo esc_html( $email['settings_label'] ); ?></a></p>
						</td>
					</tr>
				</table>
				<!--[if mso]>
				</td></tr></table>
				<![endif]-->
			</td>
		</tr>
	</table>
</body>
</html>
