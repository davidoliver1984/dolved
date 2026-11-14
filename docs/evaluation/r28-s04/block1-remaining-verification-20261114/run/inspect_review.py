import json,sys
from pathlib import Path
P=Path(__file__).resolve().parent
rows=json.loads((P/'review-inputs.json').read_text())
for r in rows:
 if sys.argv[1:] and r['item'] not in sys.argv[1:]: continue
 plan=r['plan'] or {}; raw=json.loads((P/'cases'/f"{r['item']}.json").read_text())
 def receipts(x,path=''):
  out=[]
  if isinstance(x,dict):
   for k,v in x.items():
    if k in ('validation','interpretation_check','validation_attempts'):
     if k=='interpretation_check' and isinstance(v,dict): out.append({path+'.'+k:{a:b for a,b in v.items() if a!='reviewed_plan'}})
     elif k=='validation': out.append({path+'.'+k:v})
     else: out+=receipts(v,path+'.'+k)
    elif k!='reviewed_plan': out+=receipts(v,path+'.'+k)
  elif isinstance(x,list):
   for i,v in enumerate(x): out+=receipts(v,path+'.'+str(i))
  return out
 print(json.dumps({'item':r['item'],'plan':plan,'generation_needs':r['generation_needs'],'checks':receipts(raw.get('usage',{})),'generation_receipts':receipts(raw.get('generation_usage')),'expected':{k:v for k,v in (r['expected'] or {}).items() if k!='baseline'}},ensure_ascii=False))
