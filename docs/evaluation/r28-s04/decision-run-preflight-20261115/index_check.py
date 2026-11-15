"""Read-only Qdrant identity/count checks; never performs vector search."""
import json
import urllib.request
from pathlib import Path
HERE=Path(__file__).resolve().parent
identity=json.loads((HERE/'verification/index-identities.json').read_text())
collection=identity['dense']['collection_name']
def request(path,body=None):
    req=urllib.request.Request('http://qdrant:6333/'+path,data=None if body is None else json.dumps(body).encode(),headers={'Content-Type':'application/json'})
    return json.load(urllib.request.urlopen(req,timeout=10))['result']
info=request('collections/'+collection)
workspace='5323cd6b-8b7a-40f9-98b0-9413c75f643e'
scope={'must':[{'key':'workspace_id','match':{'value':workspace}},{'key':'workspace_corpus_generation_id','match':{'value':identity['corpus']['public_id']}}]}
count=request('collections/'+collection+'/points/count',{'filter':scope,'exact':True})
foreign=request('collections/'+collection+'/points/count',{'filter':{'must':[scope['must'][1]],'must_not':[scope['must'][0]]},'exact':True})
points=[]; offset=None
while True:
    body={'filter':scope,'limit':250,'with_vector':False,'with_payload':['chunk_id','workspace_id','workspace_corpus_generation_id']}
    if offset is not None:body['offset']=offset
    page=request('collections/'+collection+'/points/scroll',body)
    points.extend(page['points']); offset=page.get('next_page_offset')
    if offset is None:break
chunks=sorted(p['payload']['chunk_id'] for p in points)
out={'collection':collection,'status':info['status'],'vector_config':info['config']['params']['vectors'],
     'sparse_config':info['config']['params'].get('sparse_vectors'),'scoped_points':count['count'],
     'foreign_points_in_active_generation':foreign['count'],'chunk_identity_set_matches_database':chunks==identity['assigned_chunk_ids'],
     'read_only':True,'provider_calls':0}
(HERE/'verification/qdrant.json').write_text(json.dumps(out,indent=2)+'\n')
assert count['count']==986 and foreign['count']==0 and out['chunk_identity_set_matches_database'],out
print(json.dumps(out))
