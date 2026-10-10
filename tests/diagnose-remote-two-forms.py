"""Read-only diagnosis of EFB traces and the two reported form pages."""
import getpass, json, re, shlex
from pathlib import Path
import paramiko, requests
base = '/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress'
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('45.82.189.77', username='hiwhites', password=getpass.getpass('SSH password: '), timeout=20, look_for_keys=False, allow_agent=False)
try:
    with client.open_sftp() as sftp:
        folder = base+'/wp-content/uploads/emsfb-email-trace'
        for attr in sftp.listdir_attr(folder):
            if not attr.filename.endswith('.log'): continue
            with sftp.open(folder+'/'+attr.filename, 'rb') as stream:
                stream.seek(max(0,attr.st_size-250000)); text=stream.read().decode('utf-8',errors='replace')
            records=[]
            for line in text.splitlines():
                try:
                    record=json.loads(line[line.index('{'):]); records.append(record)
                except (ValueError,KeyError): pass
            ids={r['req'] for r in records if r.get('stage')=='submit.settings' and r.get('data',{}).get('form_id') in [2,3,5]}
            for r in records:
                if r.get('req') in ids and r.get('stage')!='env': print(json.dumps(r,ensure_ascii=True))
        for path in [base+'/wp-content/debug.log', '/var/www/vhosts/system/ong-grandcoeur.org/logs/error_log', '/var/www/vhosts/system/ong-grandcoeur.org/logs/proxy_error_log']:
            try:
                attr=sftp.stat(path)
                with sftp.open(path,'rb') as stream:
                    stream.seek(max(0,attr.st_size-400000)); lines=stream.read().decode('utf-8',errors='replace').splitlines()
                selected=[line for line in lines if 'easy-form-builder' in line or 'Uncaught' in line or 'Fatal error' in line]
                print('ERROR LOG',path, '\n'.join(selected[-35:]))
            except OSError as error: print('ERROR LOG unavailable',path,str(error))
        for name in ['includes/class-Emsfb-public.php','includes/class-email-handler.php']:
            with sftp.open(base+'/wp-content/plugins/easy-form-builder/'+name,'rb') as stream: contents=stream.read()
            dest=Path(__file__).parent / ('remote-two-forms-'+Path(name).name+'.txt')
            dest.write_bytes(contents)
            print('Remote source snapshot',dest.name,'bytes',len(contents))
        print('Remote helper exists', end=' ')
        try: print(sftp.stat(base+'/wp-content/plugins/easy-form-builder/includes/json-efb.php').st_size)
        except OSError: print(False)
    queries=['SELECT form_id,form_name,form_type,LENGTH(form_structer) AS structure_bytes FROM pqmB4d8_emsfb_form ORDER BY form_id', 'SELECT form_id,COUNT(*) AS entries,MAX(date) AS newest FROM pqmB4d8_emsfb_msg_ GROUP BY form_id']
    for query in queries:
        cmd='/usr/local/bin/wp --path='+shlex.quote(base)+' --skip-plugins --skip-themes db query '+shlex.quote(query)
        _, out, err=client.exec_command(cmd,timeout=30)
        print(out.read().decode('utf-8')); err.read()
finally: client.close()
for slug in ['patenschaftformular-2','anmeldeformular-freiwilliger-oder-praktikantin-2']:
    response=requests.get('https://www.ong-grandcoeur.org/'+slug+'/',timeout=40)
    Path(__file__).with_name('remote-page-'+slug+'.html').write_text(response.text,encoding='utf-8')
    print('PAGE',slug,response.status_code,'EFB scripts',re.findall(r'<script[^>]+src=["\']([^"\']*efb[^"\']*)',response.text)[:12])
