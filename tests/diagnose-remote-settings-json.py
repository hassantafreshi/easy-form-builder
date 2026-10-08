"""Read stored EFB JSON and reproduce the form parser without modifying data."""
import getpass
import json
import shlex
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('45.82.189.77', username='hiwhites', password=getpass.getpass('SSH password: '),
               timeout=15, look_for_keys=False, allow_agent=False)
base = '/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress'
try:
    with client.open_sftp() as sftp:
        for filename, needle, span in [('class-Emsfb-public.php', "$settings_source = 'object-cache'", 38),
                                       ('class-Emsfb.php', 'public static function get_setting_Emsfb', 125)]:
            with sftp.open(base + '/wp-content/plugins/easy-form-builder/includes/' + filename) as source:
                lines = source.read().decode('utf-8').splitlines()
            for index, line in enumerate(lines):
                if needle in line:
                    print(filename, 'line', index + 1)
                    print('\n'.join(lines[index:index + span]))
                    break
    query = 'SELECT setting FROM pqmB4d8_emsfb_setting ORDER BY id DESC LIMIT 1'
    cmd = '/usr/local/bin/wp --path=' + shlex.quote(base) + ' --skip-plugins --skip-themes db query ' + shlex.quote(query) + ' --batch --raw --skip-column-names'
    stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
    raw = stdout.read().decode('utf-8').strip()
    print('Stored settings length:', len(raw))
    for label, text in [('original JSON', raw), ('form parser removes all backslashes', raw.replace('\\', ''))]:
        try:
            obj = json.loads(text)
            print(label, 'VALID', 'type', type(obj).__name__)
            if isinstance(obj, dict):
                print('Sending enabled:', obj.get('smtp'), 'sender:', obj.get('femail'))
                print('Configured recipient matches authorized addresses:', obj.get('emailSupporter') in ['hi@whitestudio.team', 'hasan.tafreshi@gmail.com'])
        except json.JSONDecodeError as error:
            print(label, 'INVALID', error.msg, 'position', error.pos)
    print(stderr.read().decode('utf-8')[:300])
finally:
    client.close()
