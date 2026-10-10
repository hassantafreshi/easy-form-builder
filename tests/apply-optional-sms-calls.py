from pathlib import Path
import re
p=Path(__file__).resolve().parents[1]/'includes/class-Emsfb-public.php'
raw=p.read_bytes()
old=b'$this->efbFunction->sms_ready_for_send_efb('
# The final occurrence is the actual provider call in the new safe adapter.
assert raw.count(old)==7
raw=raw.replace(old,b'$this->send_optional_sms_efb(',6)
old_block=b"""\t\t\t\t\tif($smsSendResult !== true) {
\t\t\t\t\t\t$m =  $this->lanText['msgSndBut'];
\t\t\t\t\t\t$m = sprintf($m,  '<b>'.$this->lanText['smsWPN'] .'<b>' , '' );
\t\t\t\t\t\t$response = ['success' => false, 'm' => $m];
\t\t\t\t\t\twp_send_json_success($response, 200);
\t\t\t\t\t}""".replace(b'\n',b'\r\n')
pattern=rb'\r?\n'.join(re.escape(line) for line in old_block.splitlines())
raw,count=re.subn(pattern,b'\t\t\t\t\t// SMS failures are traced by the adapter; continue to reply email.',raw)
assert count==1
p.write_bytes(raw)
