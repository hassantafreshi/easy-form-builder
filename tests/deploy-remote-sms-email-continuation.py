"""Deploy only the reviewed EFB form SMS continuation block; retain originals."""
import datetime,getpass,hashlib,shlex
from pathlib import Path
import paramiko
root='/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress/wp-content/plugins/easy-form-builder'
local=Path(__file__).resolve().parents[1]
target=root+'/includes/class-Emsfb-public.php'
run=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d-%H%M%S')
c=paramiko.SSHClient();c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('45.82.189.77',username='hiwhites',password=getpass.getpass('SSH password: '),look_for_keys=False,allow_agent=False,timeout=20)
def block(source):
    start=source.index("if (isset($form_fields_array[0]['smsnoti'])",source.index('case "form":'))
    end=source.index('if ($should_send_email)',start)
    return start,end,source[start:end]
def test_source():
    code=(local/'tests/test-sms-failure-email-continuation.php').read_text(encoding='utf-8')
    return code.replace("__DIR__ . '/../includes/class-Emsfb-public.php'",repr(target))
def run_test():
    inp,out,err=c.exec_command('/opt/plesk/php/8.4/bin/php',timeout=30)
    inp.write(test_source());inp.flush();inp.channel.shutdown_write()
    output=out.read().decode();errors=err.read().decode();status=out.channel.recv_exit_status()
    print('Regression test status:',status,output,errors[:400]);return status
try:
    with c.open_sftp() as sftp:
        original=sftp.open(target,'rb').read()
        old=original.decode('utf-8');start,end,old_block=block(old)
        assert 'wp_send_json_success($response, 200)' in old_block,'Expected original abort block'
        assert run_test()!=0,'Old code must reproduce the failure'
        _,_,new_block=block((local/'includes/class-Emsfb-public.php').read_bytes().decode('utf-8'))
        assert 'submit.sms-failed' in new_block and 'wp_send_json_success' not in new_block
        changed=(old[:start]+new_block+old[end:]).encode('utf-8')
        backup_root=root+'/.efb-sms-email-fix-backups'
        try:sftp.stat(backup_root)
        except OSError:sftp.mkdir(backup_root)
        backup=backup_root+'/'+run;sftp.mkdir(backup)
        with sftp.open(backup+'/class-Emsfb-public.php','wb') as stream:stream.write(original)
        assert sftp.open(backup+'/class-Emsfb-public.php','rb').read()==original
        staged=backup+'/class-Emsfb-public.fixed.php'
        with sftp.open(staged,'wb') as stream:stream.write(changed)
        _,out,err=c.exec_command('/opt/plesk/php/8.4/bin/php -l '+shlex.quote(staged),timeout=30)
        print(out.read().decode(),err.read().decode());assert out.channel.recv_exit_status()==0
        assert sftp.open(target,'rb').read()==original,'Remote file changed during review'
        sftp.posix_rename(staged,target)
        assert sftp.open(target,'rb').read()==changed
        print('BACKUP:',backup)
        print('Installed sha256:',hashlib.sha256(changed).hexdigest())
    assert run_test()==0,'Deployed regression test failed'
    print('Only EFB form SMS failure handling changed; no settings or data deleted.')
finally:c.close()
