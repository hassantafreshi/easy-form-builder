"""Read EFB-only trace and table metadata, without changing the remote site."""
import getpass
import shlex
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('45.82.189.77', username='hiwhites', password=getpass.getpass('SSH password: '),
               timeout=15, look_for_keys=False, allow_agent=False)
base = '/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress'
try:
    with client.open_sftp() as sftp:
        folder = base + '/wp-content/uploads/emsfb-email-trace'
        for item in sftp.listdir_attr(folder):
            if item.filename.endswith('.log'):
                print('TRACE', item.filename, 'bytes', item.st_size)
                with sftp.open(folder + '/' + item.filename) as stream:
                    stream.seek(max(0, item.st_size - 70000))
                    for line in stream.read().decode('utf-8', errors='replace').splitlines()[-8:]:
                        if '"stage":"env"' not in line:
                            print(line[:5000])
    for query in ['DESCRIBE pqmB4d8_emsfb_form', 'DESCRIBE pqmB4d8_emsfb_msg_']:
        command = '/usr/local/bin/wp --path=' + shlex.quote(base) + ' --skip-plugins --skip-themes db query ' + shlex.quote(query)
        stdin, stdout, stderr = client.exec_command(command, timeout=30)
        print(stdout.read().decode('utf-8')[:6000])
        print(stderr.read().decode('utf-8')[:300])
finally:
    client.close()
