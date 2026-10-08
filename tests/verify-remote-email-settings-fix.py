"""Verify deployed EFB loader and send notifications only to authorized recipients."""
import getpass
from pathlib import Path
import paramiko

root = '/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress/wp-content/plugins/easy-form-builder'
code = r'''<?php
define('DISABLE_WP_CRON', true);
define('WP_USE_THEMES', false);
require '/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress/wp-load.php';
function efb_verify_fix($label, $ok) {
    if (!$ok) { throw new RuntimeException($label); }
    echo 'PASS: ' . $label . "\n";
}
$class = new ReflectionClass('Emsfb\\_Public');
$public = $class->newInstanceWithoutConstructor();
$method = $class->getMethod('get_submission_settings_efb');
$method->setAccessible(true);
// Replace only the old invalid EFB cache value; never delete cache/data.
wp_cache_set('emsfb_settings', null, 'emsfb', 3600);
$origin = '';
$args = [&$origin];
$settings = $method->invokeArgs($public, $args);
efb_verify_fix('NULL cache recovered using canonical storage', $origin === 'storage' && is_array($settings));
efb_verify_fix('Real configured sending switch stays enabled', emsfb_is_email_sending_enabled_efb($settings));
efb_verify_fix('Template and submission use identical settings', json_decode(wp_json_encode($public->setting), true) === $settings);
$second = $method->invokeArgs($public, $args);
efb_verify_fix('Warm cache keeps the same settings', $origin === 'object-cache' && $second === $settings);
$before = get_option('efb_email_log', []);
efb_verify_fix('Existing logs will not be trimmed', is_array($before) && count($before) <= 198);
$run = 'EFB-FIX-' . gmdate('Ymd-His');
$public->efbFunction = get_efbFunction();
$public->lanText = $public->efbFunction->text_efb([
    'msgdml', 'thankFillForm', 'trackNo', 'vmgs', 'WeRecivedUrM', 'thankRegistering',
    'welcome', 'thankSubscribing', 'thankDonePoll', 'newMessageReceived', 'youRecivedNewMessage'
]);
$mail_calls = [];
add_filter('wp_mail', function($args) use (&$mail_calls) {
    $mail_calls[] = ['to' => $args['to'], 'headers' => $args['headers']];
    return $args;
});
// Use the actual public form notification wrapper, rather than calling wp_mail.
$public->send_email_Emsfb_([['hi@whitestudio.team'], ['hasan.tafreshi@gmail.com']],
    $run, false, ['newMessage', 'notiToUserFormFilled', 'msg'], '',
    '<p>Authorized verification of the repaired EFB form notification path.</p>',
    'EFB form path fixed [' . $run . ']');
$after = get_option('efb_email_log', []);
$new = array_slice($after, count($before));
efb_verify_fix('Both form notifications reached wp_mail', count($mail_calls) === 2 && count($new) === 2);
foreach ($new as $entry) {
    efb_verify_fix('Notification accepted: ' . $entry['to'], $entry['success'] === true);
}
global $phpmailer;
efb_verify_fix('Configured EFB sender used for notifications', $phpmailer->From === $settings['femail']);
echo json_encode(['run' => $run, 'sender' => $phpmailer->From, 'calls' => $mail_calls], JSON_UNESCAPED_SLASHES) . "\n";
echo "No form entries created; no data deleted; no mail/server settings changed.\n";
'''.replace("Emsfb\\\\_Public", "Emsfb\\_Public")

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('45.82.189.77', username='hiwhites', password=getpass.getpass('SSH password: '),
               timeout=15, look_for_keys=False, allow_agent=False)
try:
    scripts = []
    for name in ['test-submission-settings-email.php', 'test-email-sender-diagnosis.php']:
        test = Path(__file__).with_name(name).read_text(encoding='utf-8')
        test = test.replace("dirname(__DIR__) . '/includes/", "'" + root + "/includes/")
        scripts.append((name, test))
    scripts.append(('live form notification path', code))
    for label, php in scripts:
        print('TEST', label)
        stdin, stdout, stderr = client.exec_command('/opt/plesk/php/8.4/bin/php', timeout=60)
        stdin.write(php)
        stdin.flush()
        stdin.channel.shutdown_write()
        print(stdout.read().decode('utf-8'))
        print(stderr.read().decode('utf-8'))
        if stdout.channel.recv_exit_status() != 0:
            raise RuntimeError('Verification failed: ' + label)
finally:
    client.close()
