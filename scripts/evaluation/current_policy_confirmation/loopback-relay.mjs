// Test transport only: preserves raw HTTP/SSE; containers remain on an internal network.
import net from 'node:net';
import {spawn} from 'node:child_process';
for (const [port,container,command] of [
  [38430,'dolved-current-policy-checks-approved-web-1',['node','-e','const s=require("net").connect(38430,"127.0.0.1");process.stdin.pipe(s);s.pipe(process.stdout);s.on("close",()=>process.exit(0));']],
  [38431,'dolved-current-policy-checks-approved-api-1',['php','-r', '$s=stream_socket_client("tcp://127.0.0.1:38431"); stream_set_blocking(STDIN,false); stream_set_blocking($s,false); while(!feof($s)){ $r=[STDIN,$s]; $w=null;$e=null; if(stream_select($r,$w,$e,30)===0)break; foreach($r as $v){$d=fread($v,65536); if($d!==false&&$d!=="")fwrite($v===STDIN?$s:STDOUT,$d);} }']],
]) {
  net.createServer(socket=>{
    const child=spawn('docker',['exec','-i',container,...command],{stdio:['pipe','pipe','ignore']});
    socket.pipe(child.stdin); child.stdout.pipe(socket);
    socket.on('error',()=>child.kill()); socket.on('close',()=>child.kill());
    child.stdin.on('error',()=>socket.destroy()); child.on('exit',()=>socket.end());
  }).listen(port,'127.0.0.1');
}
