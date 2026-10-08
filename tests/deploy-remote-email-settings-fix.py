"""Apply only reviewed EFB email hunks, preserving remote originals in EFB."""
import datetime
import getpass
import hashlib
from pathlib import Path
import shlex
import paramiko

root = '/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress/wp-content/plugins/easy-form-builder'
local = Path(__file__).resolve().parent.parent
run = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d-%H%M%S')
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('45.82.189.77', username='hiwhites', password=getpass.getpass('SSH password: '),
               timeout=15, look_for_keys=False, allow_agent=False)

def unique_replace(text, old, new):
    if text.count(old) != 1:
        raise RuntimeError('Expected exactly one matching hunk')
    return text.replace(old, new, 1)

try:
    with client.open_sftp() as sftp:
        files = {}
        for name in ['class-Emsfb-public.php', 'class-email-handler.php']:
            with sftp.open(root + '/includes/' + name, 'rb') as stream:
                files[name] = stream.read()
        original = files['class-Emsfb-public.php'].decode('utf-8')
        source = (local / 'includes/class-Emsfb-public.php').read_text(encoding='utf-8')
        start = original.index("\t\t$settings_source = 'object-cache';")
        end = original.index("\t\t$this->efb_trace('submit.settings'", start)
        changed = original[:start] + '\t\t$plugin_settings = $this->get_submission_settings_efb($settings_source);\n\n' + original[end:]
        start = changed.index('\t\tif (is_string($r))', start)
        end = changed.index("\t\tif (isset($plugin_settings['emailSupporter']))", start)
        changed = changed[:start] + changed[end:]
        helper_start = source.index('\t/** Read canonical decoded settings;')
        helper_end = source.index('\t  public function get_form_public_efb(', helper_start)
        changed = unique_replace(changed, '\t  public function get_form_public_efb(', source[helper_start:helper_end] + '\t  public function get_form_public_efb(')
        changed = unique_replace(changed, "isset($setttting['femail']) && is_email($plugin_settings['femail'])", "isset($plugin_settings['femail']) && is_email($plugin_settings['femail'])")
        changed = unique_replace(changed, '$email_recipients[2] = $plugin_settings->femail ;', "$email_recipients[2] = $plugin_settings['femail'];")
        updates = {'class-Emsfb-public.php': changed.encode('utf-8')}
        original_handler = files['class-email-handler.php'].decode('utf-8')
        local_handler = (local / 'includes/class-email-handler.php').read_text(encoding='utf-8')
        start = local_handler.index('        $configured_from =')
        end = local_handler.index('\n\n', start)
        old = '        $from = get_bloginfo(\'name\') . " <no-reply@" . $server_name . ">";'
        updates['class-email-handler.php'] = unique_replace(original_handler, old, local_handler[start:end]).encode('utf-8')
        backup_root = root + '/.efb-email-fix-backups'
        try:
            sftp.stat(backup_root)
        except FileNotFoundError:
            sftp.mkdir(backup_root, 0o700)
        backup = backup_root + '/' + run
        sftp.mkdir(backup, 0o700)
        print('BACKUP', backup)
        staged = []
        for name, payload in updates.items():
            with sftp.open(backup + '/' + name, 'wb') as stream:
                stream.write(files[name])
            with sftp.open(backup + '/' + name, 'rb') as stream:
                assert stream.read() == files[name], 'Backup verification failed'
            target = root + '/includes/' + name
            temp = target + '.efb-fix-' + run + '.php'
            with sftp.open(temp, 'wb') as stream:
                stream.write(payload)
            sftp.chmod(temp, sftp.stat(target).st_mode & 0o777)
            stdin, stdout, stderr = client.exec_command('/opt/plesk/php/8.4/bin/php -l ' + shlex.quote(temp), timeout=20)
            output = stdout.read().decode() + stderr.read().decode()
            print(output.strip())
            assert stdout.channel.recv_exit_status() == 0, 'Remote syntax validation failed'
            staged.append((name, temp, target))
        for name, temp, target in staged:
            with sftp.open(target, 'rb') as stream:
                assert stream.read() == files[name], 'Remote file changed during review'
            sftp.posix_rename(temp, target)
            with sftp.open(target, 'rb') as stream:
                assert stream.read() == updates[name], 'Deployed content mismatch'
            print('DEPLOYED', name, 'SHA256', hashlib.sha256(updates[name]).hexdigest())
        print('Only two EFB files patched. Originals preserved; no settings changed.')
finally:
    client.close()
