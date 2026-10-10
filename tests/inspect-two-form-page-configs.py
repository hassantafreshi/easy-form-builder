import json,re
from pathlib import Path
for path in Path(__file__).parent.glob('remote-page-*.html'):
    html=path.read_text(encoding='utf-8')
    match=re.search(r'var ajax_object_efm\s*=\s*',html)
    obj,_=json.JSONDecoder().raw_decode(html[match.end():])
    for form in obj.get('ajax_value_forms',[]):
        raw=form.get('form_structer','')
        fields=json.loads(raw)
        meta=fields[0]
        selected={key:meta.get(key) for key in ['formName','smsnoti','email_noti_type','email_to','trackingCode','notification_rules','webhook_rules']}
        print(path.name,'FORM',form.get('id'),'META',json.dumps(selected,ensure_ascii=True))
        print('UPLOAD FIELDS',sum(f.get('type') in ['file','dadfile','audio_recorder','video_recorder'] for f in fields),'FIELDS',len(fields))
