import getpass,shlex,paramiko
base='/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress'
c=paramiko.SSHClient();c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('45.82.189.77',username='hiwhites',password=getpass.getpass('SSH password: '),look_for_keys=False,allow_agent=False,timeout=20)
try:
    query="SELECT form_id,COUNT(*) AS sms_config_rows,MAX(LENGTH(admin_numbers)) AS admin_numbers_bytes FROM pqmB4d8_emsfb_sms_contact WHERE form_id IN (2,3,5) GROUP BY form_id"
    cmd='/usr/local/bin/wp --path='+shlex.quote(base)+' --skip-plugins --skip-themes db query '+shlex.quote(query)
    _,out,err=c.exec_command(cmd,timeout=30);print('SMS option:',out.read().decode());err.read()
    query="SELECT option_name,option_value FROM pqmB4d8_options WHERE option_name='active_plugins'"
    cmd='/usr/local/bin/wp --path='+shlex.quote(base)+' --skip-plugins --skip-themes db query '+shlex.quote(query)
    _,out,err=c.exec_command(cmd,timeout=30);print('Active plugins:',out.read().decode());err.read()
    path=base+'/wp-content/plugins/easy-form-builder/includes/functions.php'
    cmd="awk '/public function sms_ready_for_send_efb/{p=1;n=0} p&&n++<15{print}' "+shlex.quote(path)
    _,out,err=c.exec_command(cmd,timeout=30);print(out.read().decode());err.read()
finally:c.close()
