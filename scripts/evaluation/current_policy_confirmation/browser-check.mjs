// Uses installed Chromium only. No downloads, saved browser state or provider access.
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, writeFile, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import {completionDeadline, assertBeforeDeadline, settledPresentation} from './browser-deadline.mjs';
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
const overallDeadline=Number(process.env.CONFIRMATION_DEADLINE_EPOCH)*1000;
let deadline=Math.min(Date.now()+120000,overallDeadline);
const checkDeadline=()=>{if(!Number.isFinite(deadline))throw Error('Overall deadline must be bound');assertBeforeDeadline(Date.now(),deadline);};
let observeWait=async()=>{};
const until = async fn => { while(true){checkDeadline();await observeWait();const v=await fn();checkDeadline();if(v)return v;await wait(Math.min(250,Math.max(0,deadline-Date.now())));} };
let socket; const requests=[]; const errors=[]; const records=[]; const events=[];
let lastBackend=null;let lastBrowser=null;let timing=null;let suppressSse=false;
try {
  const port = await until(async()=>{try{return (await readFile(path.join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0]}catch{return null}});
  const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, {method:'PUT'})).json();
  socket = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j});
  let serial=0;let loaded=0;const pending=new Map();
  const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser operation deadline expired'));},Math.max(1,deadline-Date.now()));pending.set(id,{resolve:(v)=>{clearTimeout(timer);resolve(v)},reject:(e)=>{clearTimeout(timer);reject(e)}});socket.send(JSON.stringify({id,method,params}))});
  socket.onmessage=({data})=>{const m=JSON.parse(data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);if(p)m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result);}
    if(m.method==='Page.loadEventFired') loaded++;
    if(m.method==='Network.requestWillBeSent') requests.push({url:m.params.request.url,method:m.params.request.method});
    if(m.method==='Network.eventSourceMessageReceived') events.push({name:m.params.eventName,data:m.params.data});
    if(m.method==='Runtime.exceptionThrown') errors.push((m.params.exceptionDetails.exception?.description??m.params.exceptionDetails.text).slice(0,2000));
    if(m.method==='Fetch.requestPaused') {const u=new URL(m.params.request.url); const allowed=['127.0.0.1','localhost','[::1]'].includes(u.hostname);
      if(allowed && suppressSse && u.pathname.endsWith('/events')) {
        events.push({name:'controlled_transport_drop',data:'SSE response ended without terminal event'});
        // Preserve the real credentialed loopback CORS boundary. Without these
        // headers this models a fatal CORS error, not a missed final event.
        void send('Fetch.fulfillRequest',{requestId:m.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/event-stream'},{name:'Access-Control-Allow-Origin',value:'http://127.0.0.1:38430'},{name:'Access-Control-Allow-Credentials',value:'true'}],body:Buffer.from(': controlled stream ended without its final event\n\n').toString('base64')});return;
      }
      if(!allowed) errors.push(`non-loopback request: ${u.href}`);
      void send(allowed?'Fetch.continueRequest':'Fetch.failRequest', {requestId:m.params.requestId,...(allowed?{}:{errorReason:'BlockedByClient'})});}
  };
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  await send('Emulation.setDeviceMetricsOverride',{width:1280,height:1400,deviceScaleFactor:1,mobile:false});
  await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});
  const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
  const capture=()=>evaluate(`({url:location.href,text:document.querySelector('[data-canonical-answer]')?.textContent??null,count:document.querySelectorAll('[data-canonical-answer]').length,citations:[...document.querySelectorAll('[data-citation-id]')].map(x=>x.dataset.citationId),runId:document.querySelector('[data-completion-run]')?.dataset.completionRun,path:document.querySelector('[data-completion-path]')?.dataset.completionPath,pending:document.querySelector('[data-chat-pending]')?.dataset.chatPending,progress:document.querySelector('[data-chat-progress]')?.dataset.chatProgress,announcement:document.querySelector('[data-chat-announcement]')?.textContent,dom:document.body?.innerHTML??null})`);
  observeWait=async()=>{lastBrowser=await capture();};
  const api=async (endpoint,method='GET',body=null)=>evaluate(`(async()=>{
    const base='http://127.0.0.1:38431';
    await fetch(base+'/sanctum/csrf-cookie',{credentials:'include'});
    const token=document.cookie.split('; ').find(x=>x.startsWith('XSRF-TOKEN='))?.slice(11);
    const r=await fetch(base+${JSON.stringify(endpoint)},{method:${JSON.stringify(method)},credentials:'include',headers:{Accept:'application/json','Content-Type':'application/json',...(token?{'X-XSRF-TOKEN':decodeURIComponent(token)}:{})},body:${JSON.stringify(body===null?null:JSON.stringify(body))}});
    return {status:r.status,body:r.status===204?null:await r.json()};})()`);
  const fixtures=process.argv[3] ? [JSON.parse(await readFile(process.argv[3],'utf8'))] : JSON.parse(await readFile('/private/tmp/stop3-fixtures.json','utf8'));
  for(const fixture of fixtures){
    suppressSse=fixture.controlled===true && fixture.suppress_sse===true;
    deadline=Math.min(Date.now()+120000,overallDeadline);
    await send('Page.navigate',{url:'http://127.0.0.1:38430/login'});
    await until(()=>evaluate('location.origin === "http://127.0.0.1:38430" && document.readyState === "complete"'));
    await api('/api/auth/logout','POST');
    const password=process.env.CURRENT_POLICY_CONFIRMATION_PASSWORD ?? ((!process.argv[3] || fixture.controlled===true) ? 'disposable-test-only' : null);
    if(!password)throw Error('Release-bound test/user credentials not supplied');
    const login=await api('/api/auth/login','POST',{email:fixture.email,password});
    if(login.status!==204 && login.status!==200)throw Error(`Login failed: ${login.status}`);
    if(!fixture.web_path)throw Error('Actual application route must be release-bound');
    const loadBefore=loaded; await send('Page.navigate',{url:`http://127.0.0.1:38430${fixture.web_path}`}); await until(()=>loaded>loadBefore);
    // SSR markup is not proof of hydration. Wait for the component's real
    // initial persisted read, then for React to acknowledge the typed draft.
    await until(()=>evaluate('!!document.querySelector("textarea") && !!document.querySelector("[aria-labelledby=chat-heading] [aria-busy=false]")'));
    await evaluate(`document.querySelector('textarea').focus()`);
    await send('Input.insertText',{text:fixture.utterance});
    await until(()=>evaluate('document.querySelector("textarea")?.form?.querySelector("button[type=submit]")?.disabled === false'));
    checkDeadline();
    const submittedAtMs=Date.now();
    deadline=completionDeadline(submittedAtMs,fixture.run_timeout_seconds,overallDeadline);
    timing={submitted_at_ms:submittedAtMs,backend_max_seconds:fixture.run_timeout_seconds,delivery_allowance_ms:15000,overall_deadline_ms:overallDeadline,completion_deadline_ms:deadline};
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    const conversation=await until(()=>evaluate('new URLSearchParams(location.search).get("conversation") || location.pathname.match(/conversations\\/([^/]+)/)?.[1]'));
    const backend=await until(async()=>{lastBrowser=await capture();const r=await api(`/api/workspaces/${fixture.workspace}/conversations/${conversation}`);lastBackend=r;return ['completed','retrieval_no_answer','clarification_required','failed','cancelled'].includes(r.body?.data?.runs?.at(-1)?.status)?r:null;});
    const publicRun=backend.body.data.runs.at(-1);
    if(['failed','cancelled'].includes(publicRun.status))throw Error(`Recorded terminal failure: ${publicRun.status}`);
    const answer=publicRun.answer;
    const savedText=backend.body.data.messages.find(m=>m.id===publicRun.assistant_message_id)?.text??null;
    const completed=await until(async()=>{lastBrowser=await capture();return settledPresentation(lastBrowser,publicRun,savedText,(answer?.citations??[]).map(c=>c.id))?lastBrowser:null;});
    timing.settled_at_ms=Date.now();
    await writeFile(path.join(output,fixture.id+'-completed.json'),JSON.stringify({id:fixture.id,backend:backend.body,completed},null,2));
    if(completed.text!==savedText || (fixture.saved_text!==undefined && completed.text!==fixture.saved_text) || completed.count!==(publicRun.assistant_message_id?1:0) || JSON.stringify(completed.citations)!==JSON.stringify((answer?.citations??[]).map(c=>c.id)))throw Error(`Parity failed: ${fixture.id}`);
    await evaluate(`document.querySelector('[data-citation-id] button')?.click()`);
    const screenshot=await send('Page.captureScreenshot',{format:'png'});
    await writeFile(path.join(output,fixture.id+'.png'),Buffer.from(screenshot.data,'base64'));
    const reloadBefore=loaded; await send('Page.reload'); await until(()=>loaded>reloadBefore);
    const reload=await until(async()=>{lastBrowser=await capture();return settledPresentation(lastBrowser,publicRun,savedText,(answer?.citations??[]).map(c=>c.id))?lastBrowser:null;});
    if(reload.text!==completed.text || JSON.stringify(reload.citations)!==JSON.stringify(completed.citations))throw Error(`Reload mismatch: ${fixture.id}`);
    records.push({id:fixture.id,backend:backend.body,completed,reload,timing,completion_path:completed.path,normalization:'none; DOM textContent and ordered citation UUIDs'});
    await writeFile(path.join(output,'records.json'),JSON.stringify(records,null,2));
  }
  if(errors.length)throw Error(errors.join('; '));
} catch (error) {
  errors.push(String(error));
  await writeFile(path.join(output,'halt.json'),JSON.stringify({error:String(error),timing,deadline_ms:deadline,observed_at_ms:Date.now(),backend:lastBackend,backend_unavailable_reason:lastBackend===null?'No persisted run query completed':null,browser:lastBrowser,events},null,2));
  throw error;
} finally {
  await writeFile(path.join(output,'events.json'),JSON.stringify(events,null,2));
  await writeFile(path.join(output,'network.json'),JSON.stringify({requests,errors,temporary_profile:profile,temporary_profile_removed:false},null,2));
  socket?.close(); chrome.kill('SIGTERM');
  await new Promise(r=>{if(chrome.exitCode!==null)r();else chrome.once('exit',r)});
  await rm(profile,{recursive:true,maxRetries:20,retryDelay:200});
  await writeFile(path.join(output,'network.json'),JSON.stringify({requests,errors,temporary_profile_removed:true},null,2));
}
