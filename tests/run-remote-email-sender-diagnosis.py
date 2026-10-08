"""Read-only EFB diagnosis. Password is prompted and never saved."""
import getpass
from pathlib import Path
import paramiko

root = '/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress/wp-content/plugins/easy-form-builder'
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('45.82.189.77', username='hiwhites',
               password=getpass.getpass('SSH password: '), timeout=15,
               look_for_keys=False, allow_agent=False)
try:
    with client.open_sftp() as sftp:
        with sftp.open(root + '/includes/class-Emsfb-public.php') as source:
            lines = source.read().decode('utf-8').splitlines()
        for index, line in enumerate(lines):
            if "isset($setttting['femail'])" in line:
                print('LIVE EFB sender assignment, line', index + 1)
                print('\n'.join(lines[index:index + 3]))
    code = Path(__file__).with_name('test-email-sender-diagnosis.php').read_text(encoding='utf-8')
    code = code.replace("require dirname(__DIR__) . '/includes/class-email-handler.php';",
                        "require '" + root + "/includes/class-email-handler.php';")
    stdin, stdout, stderr = client.exec_command('/opt/plesk/php/8.4/bin/php', timeout=30)
    stdin.write(code)
    stdin.flush()
    stdin.channel.shutdown_write()
    print(stdout.read().decode('utf-8'))
    print(stderr.read().decode('utf-8'))
    raise SystemExit(stdout.channel.recv_exit_status())
finally:
    client.close()
