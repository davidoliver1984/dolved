// Uses installed Chromium only. No downloads, saved browser state or provider access.
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, writeFile, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
const output = process.argv[2];
if (!output) throw Error('Fresh evidence directory required');
await mkdir(output, {recursive: false});
const profile = await mkdtemp(path.join(tmpdir(), 'dolved-stop3-browser-'));
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
  '--headless=new', `--user-data-dir=${profile}`, '--remote-debugging-port=0',
  '--disable-extensions', '--disable-background-networking', '--disable-sync', '--no-first-run',
  '--no-default-browser-check', '--disable-component-update', '--disable-default-apps',
  '--proxy-server=http://127.0.0.1:9', '--proxy-bypass-list=127.0.0.1;localhost;[::1]',
  '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1, EXCLUDE localhost', 'about:blank',
], {stdio: 'ignore'});
const wait = ms => new Promise(r => setTimeout(r, ms));
const checkDeadline=()=>{if(Date.now()/1000>=Number(process.env.CONFIRMATION_DEADLINE_EPOCH??Infinity))throw Error('Overall deadline exhausted');};
const until = async fn => { for (let i=0;i<300;i++){checkDeadline(); const v=await fn(); if(v)return v; await wait(100); } throw Error('Bounded browser wait expired'); };
let socket; const requests=[]; const errors=[]; const records=[]; const events=[];
try {
  const port = await until(async()=>{try{return (await readFile(path.join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0]}catch{return null}});
  const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, {method:'PUT'})).json();
  socket = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j});
  let serial=0;let loaded=0;const pending=new Map();
  const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}))});
  socket.onmessage=({data})=>{const m=JSON.parse(data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result);}
    if(m.method==='Page.loadEventFired') loaded++;
    if(m.method==='Network.requestWillBeSent') requests.push({url:m.params.request.url,method:m.params.request.method});
    if(m.method==='Network.eventSourceMessageReceived') events.push({name:m.params.eventName,data:m.params.data});
    if(m.method==='Runtime.exceptionThrown') errors.push(m.params.exceptionDetails.text);
    if(m.method==='Fetch.requestPaused') {const u=new URL(m.params.request.url); const allowed=['127.0.0.1','localhost','[::1]'].includes(u.hostname);
      if(!allowed) errors.push(`non-loopback request: ${u.href}`);
      void send(allowed?'Fetch.continueRequest':'Fetch.failRequest', {requestId:m.params.requestId,...(allowed?{}:{errorReason:'BlockedByClient'})});}
  };
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  await send('Emulation.setDeviceMetricsOverride',{width:1280,height:1400,deviceScaleFactor:1,mobile:false});
  await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});
  const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
  const api=async (endpoint,method='GET',body=null)=>evaluate(`(async()=>{
    const base='http://127.0.0.1:38431';
    await fetch(base+'/sanctum/csrf-cookie',{credentials:'include'});
    const token=document.cookie.split('; ').find(x=>x.startsWith('XSRF-TOKEN='))?.slice(11);
    const r=await fetch(base+${JSON.stringify(endpoint)},{method:${JSON.stringify(method)},credentials:'include',headers:{Accept:'application/json','Content-Type':'application/json',...(token?{'X-XSRF-TOKEN':decodeURIComponent(token)}:{})},body:${JSON.stringify(body===null?null:JSON.stringify(body))}});
    return {status:r.status,body:r.status===204?null:await r.json()};})()`);
  const fixtures=process.argv[3] ? [JSON.parse(await readFile(process.argv[3],'utf8'))] : JSON.parse(await readFile('/private/tmp/stop3-fixtures.json','utf8'));
  for(const fixture of fixtures){
    await send('Page.navigate',{url:`http://127.0.0.1:38430${fixture.web_path ?? `/?workspace=${fixture.workspace}`}`});
    await until(()=>evaluate('location.origin === "http://127.0.0.1:38430" && document.readyState === "complete"'));
    await api('/api/auth/logout','POST');
    const password=process.env.CURRENT_POLICY_CONFIRMATION_PASSWORD ?? ((!process.argv[3] || fixture.controlled===true) ? 'disposable-test-only' : null);
    if(!password)throw Error('Release-bound test/user credentials not supplied');
    const login=await api('/api/auth/login','POST',{email:fixture.email,password});
    if(login.status!==204 && login.status!==200)throw Error(`Login failed: ${login.status}`);
    const loadBefore=loaded; await send('Page.reload'); await until(()=>loaded>loadBefore);
    await until(()=>evaluate('!!document.querySelector("textarea")'));
    await evaluate(`document.querySelector('textarea').focus()`);
    await send('Input.insertText',{text:fixture.utterance});
    await wait(150);
    checkDeadline();
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    const conversation=await until(()=>evaluate('new URLSearchParams(location.search).get("conversation") || location.pathname.match(/conversations\\/([^/]+)/)?.[1]'));
    const backend=await until(async()=>{const r=await api(`/api/workspaces/${fixture.workspace}/conversations/${conversation}`);return ['completed','retrieval_no_answer','clarification_required','failed','cancelled'].includes(r.body?.data?.runs?.at(-1)?.status)?r:null;});
    const publicRun=backend.body.data.runs.at(-1);
    if(publicRun.assistant_message_id)await until(()=>evaluate('document.querySelectorAll("[data-canonical-answer]").length === 1'));
    const capture=()=>evaluate(`({text:document.querySelector('[data-canonical-answer]')?.textContent??null, count:document.querySelectorAll('[data-canonical-answer]').length,citations:[...document.querySelectorAll('[data-citation-id]')].map(x=>x.dataset.citationId),dom:document.body.innerHTML})`);
    const completed=await capture();
    const answer=publicRun.answer;
    const savedText=backend.body.data.messages.find(m=>m.id===publicRun.assistant_message_id)?.text??null;
    await writeFile(path.join(output,fixture.id+'-completed.json'),JSON.stringify({id:fixture.id,backend:backend.body,completed},null,2));
    if(completed.text!==savedText || (fixture.saved_text!==undefined && completed.text!==fixture.saved_text) || completed.count!==(publicRun.assistant_message_id?1:0) || JSON.stringify(completed.citations)!==JSON.stringify((answer?.citations??[]).map(c=>c.id)))throw Error(`Parity failed: ${fixture.id}`);
    await evaluate(`document.querySelector('[data-citation-id] button')?.click()`);
    const screenshot=await send('Page.captureScreenshot',{format:'png'});
    await writeFile(path.join(output,fixture.id+'.png'),Buffer.from(screenshot.data,'base64'));
    const reloadBefore=loaded; await send('Page.reload'); await until(()=>loaded>reloadBefore);
    if(publicRun.assistant_message_id)await until(()=>evaluate('document.querySelectorAll("[data-canonical-answer]").length === 1'));
    const reload=await capture();
    if(reload.text!==completed.text || JSON.stringify(reload.citations)!==JSON.stringify(completed.citations))throw Error(`Reload mismatch: ${fixture.id}`);
    records.push({id:fixture.id,backend:backend.body,completed,reload,normalization:'none; DOM textContent and ordered citation UUIDs'});
    await writeFile(path.join(output,'records.json'),JSON.stringify(records,null,2));
  }
  if(errors.length)throw Error(errors.join('; '));
} catch (error) {
  errors.push(String(error));
  throw error;
} finally {
  await writeFile(path.join(output,'events.json'),JSON.stringify(events,null,2));
  await writeFile(path.join(output,'network.json'),JSON.stringify({requests,errors,temporary_profile:profile,temporary_profile_removed:false},null,2));
  socket?.close(); chrome.kill('SIGTERM');
  await new Promise(r=>{if(chrome.exitCode!==null)r();else chrome.once('exit',r)});
  await rm(profile,{recursive:true,maxRetries:20,retryDelay:200});
  await writeFile(path.join(output,'network.json'),JSON.stringify({requests,errors,temporary_profile_removed:true},null,2));
}
