"""Send four explicitly authorized EFB diagnostic emails; no settings changes."""
import getpass
import paramiko

code = r'''<?php
define('DISABLE_WP_CRON', true);
define('WP_USE_THEMES', false);
require '/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress/wp-load.php';
if (!class_exists('EmsfbEmailHandler')) {
    require ABSPATH . 'wp-content/plugins/easy-form-builder/includes/class-email-handler.php';
}
$logs = get_option('efb_email_log', []);
if (!is_array($logs) || count($logs) > 196) {
    fwrite(STDERR, "Aborted: avoid replacing or trimming existing EFB logs.\n");
    exit(1);
}
$settings = get_setting_Emsfb('decoded');
if (!is_object($settings) || !isset($settings->femail) || !is_email($settings->femail)) {
    fwrite(STDERR, "Aborted: no valid EFB configured sender.\n");
    exit(1);
}
$run = 'EFB-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
echo 'RUN ' . $run . "\n";
$handler = new EmsfbEmailHandler();
$failures = [];
add_action('wp_mail_failed', function($error) use (&$failures) {
    $failures[] = ['code' => $error->get_error_code(), 'message' => $error->get_error_message()];
});
foreach (['hi@whitestudio.team', 'hasan.tafreshi@gmail.com'] as $recipient) {
    foreach (['A-current-form-sender', 'B-configured-EFB-sender'] as $variant) {
        $to = $variant[0] === 'A' ? $recipient : [$recipient, '', $settings->femail];
        $subject = 'EFB delivery test [' . $run . '] ' . $variant;
        $body = '<p>Authorized email delivery diagnostic for Easy Form Builder.</p>'
            . '<p>Run: ' . esc_html($run) . '</p><p>Variant: ' . esc_html($variant) . '</p>'
            . '<p>Please compare receipt of variants A and B, including the Spam folder.</p>';
        $result = $handler->send_email_state_new($to, $subject, [$run, $body], false,
            'newMessage', home_url(), $settings);
        global $phpmailer;
        echo json_encode([
            'recipient' => $recipient, 'variant' => $variant,
            'handoff_result' => $result, 'mailer' => $phpmailer->Mailer ?? null,
            'final_from' => $phpmailer->From ?? null,
            'final_envelope_sender' => $phpmailer->Sender ?? null,
            'message_id' => isset($phpmailer) ? $phpmailer->getLastMessageID() : null,
            'errors' => $failures,
        ], JSON_UNESCAPED_SLASHES) . "\n";
    }
}
echo "Completed: four EFB calls; no settings changed.\n";
'''

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('45.82.189.77', username='hiwhites',
               password=getpass.getpass('SSH password: '), timeout=15,
               look_for_keys=False, allow_agent=False)
try:
    stdin, stdout, stderr = client.exec_command('/opt/plesk/php/8.4/bin/php', timeout=90)
    stdin.write(code)
    stdin.flush()
    stdin.channel.shutdown_write()
    print(stdout.read().decode('utf-8'))
    print(stderr.read().decode('utf-8'))
    raise SystemExit(stdout.channel.recv_exit_status())
finally:
    client.close()
