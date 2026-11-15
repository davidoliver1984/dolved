import json
import urllib.request
from pathlib import Path
HERE=Path(__file__).resolve().parent
scopes=json.loads((HERE/'corpus.json').read_text())['scopes']
out={}
for name,s in scopes.items():
    corpus={'key':'workspace_corpus_generation_id','match':{'value':s['generation']}}
    workspace={'key':'workspace_id','match':{'value':s['workspace']}}
    def count(filter):
        request=urllib.request.Request('http://qdrant:6333/collections/'+s['dense']['collection_name']+'/points/count',
          data=json.dumps({'filter':filter,'exact':True}).encode(),headers={'Content-Type':'application/json'})
        return json.load(urllib.request.urlopen(request,timeout=10))['result']['count']
    out[name]={'workspace':s['workspace'],'generation':s['generation'],
        'active_points':count({'must':[corpus,workspace]}),'foreign_points_in_generation':count({'must':[corpus],'must_not':[workspace]})}
    assert out[name]['active_points']==s['active_count'] and out[name]['foreign_points_in_generation']==0
(HERE/'index-counts.json').write_text(json.dumps(out,indent=2)+'\n')
print(json.dumps(out))
