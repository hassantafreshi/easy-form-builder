/* Probe: can a generic Intl-backed engine serve calendars we do not implement
 * (hebrew, buddhist, japanese, roc, indian, coptic, ethiopic, ethioaa, chinese)?
 * Checks that (year, month, day) parts from Intl are unique per day (so a
 * search-based toJdn is well-defined) and reports month-count / era quirks. */
const E=require('./engines.js');
const cals=['hebrew','buddhist','japanese','roc','indian','coptic','ethiopic','ethioaa','chinese','dangi'];
const from=E.gregorianToJdn(1990,1,1),to=E.gregorianToJdn(2060,12,31);
const res={};
for(const cal of cals){
  const f=new Intl.DateTimeFormat('en-u-ca-'+cal+'-nu-latn',{timeZone:'UTC',era:'short',year:'numeric',month:'numeric',day:'numeric'});
  const seen=new Map();let dup=0;const months=new Set();let sampleParts=null;const eras=new Set();
  for(let j=from;j<=to;j++){const p=f.formatToParts(new Date((j-2440588)*864e5));if(!sampleParts)sampleParts=p.filter(x=>x.type!=='literal').map(x=>x.type+'='+x.value).join(' ');
    const g=t=>(p.find(x=>x.type===t)||{}).value;const key=[g('era'),g('year')||g('relatedYear'),g('month'),g('day')].join('|');months.add(g('month'));eras.add(g('era'));if(seen.has(key))dup++;else seen.set(key,j);}
  const t0=Date.now();res[cal]={duplicate_keys:dup,distinct_month_tokens:[...months].slice(0,15).join(','),eras:[...eras].join(','),sample:sampleParts};
}
console.log(JSON.stringify(res,null,1));
