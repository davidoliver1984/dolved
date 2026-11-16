import test from 'node:test';
import assert from 'node:assert/strict';
import {completionDeadline, assertBeforeDeadline, settledPresentation} from './browser-deadline.mjs';
test('completion bound is original submission plus backend duration and 15 seconds, capped overall',()=>{
  assert.equal(completionDeadline(1000,900,2_000_000),916000);
  assert.equal(completionDeadline(1000,900,50000),50000);
  assert.equal(completionDeadline(1000,900,2_000_000,10000),25000);
  assert.equal(completionDeadline(1000,900,2_000_000),completionDeadline(1000,900,2_000_000));
});
test('deadline includes last valid instant but expiry is not extended by recovery',()=>{
  const deadline=completionDeadline(0,900,1_000_000);
  assert.doesNotThrow(()=>assertBeforeDeadline(40_000,deadline));
  assert.doesNotThrow(()=>assertBeforeDeadline(deadline-1,deadline));
  assert.throws(()=>assertBeforeDeadline(deadline,deadline));
  assert.throws(()=>assertBeforeDeadline(deadline+1,deadline));
  assert.throws(()=>completionDeadline(0,undefined,1_000_000));
});
test('matching text alone does not establish settled presentation',()=>{
  const run={id:'r',status:'completed',assistant_message_id:'m'};
  const state={runId:'r',pending:'false',progress:'',announcement:'Answer complete.',text:'Saved',count:1,citations:['c']};
  assert.equal(settledPresentation(state,run,'Saved',['c']),true);
  for(const change of [{pending:'true'},{progress:'Sending your question…'},{announcement:''},{runId:'other'},{citations:[]},{text:'Saved without qualification'},{count:2}])assert.equal(settledPresentation({...state,...change},run,'Saved',['c']),false);
});
