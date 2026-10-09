<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

use Mammatus\Http\Server\Attributes\HttpMethod;
use Mammatus\Http\Server\Attributes\Route;
use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\WebSocket\ClientAsset;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;

use const WyriHaximus\Constants\HTTPStatusCodes\OK;

#[Vhost('frontend')]
#[Route(HttpMethod::GET, '/demo/websocket')]
final class DemoWebSocketPageHandler
{
    public function handle(): ResponseInterface
    {
        $clientPath = ClientAsset::PATH;
        $html       = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>WebSocket demo</title></head>
<body>
<h1>WebSocket demo</h1>
<p>Open the browser console. This page exercises RPC <code>ping</code> and channel <code>demo-events</code>.</p>
<script>
const wsUrl = (location.protocol === 'https:' ? 'wss:' : 'ws:') + '//' + location.host + '/';
</script>
<script type="module">
import { MammatusWebSocket, HEARTBEAT_CHANNEL } from '{$clientPath}';
const client = new MammatusWebSocket(wsUrl);
await client.connect();
client.subscribe('demo-events');
client.on('evt', (channel, data) => {
  if (channel === HEARTBEAT_CHANNEL) {
    console.debug('heartbeat (optional)', data);
    return;
  }
  console.log('evt', channel, data);
});
const result = await client.rpc('ping', { message: 'hello' });
console.log('rpc ping', result);
</script>
<hr>
<h2>Native WebSocket (same ops)</h2>
<pre id="native-log"></pre>
<script>
const log = (line) => { document.getElementById('native-log').textContent += line + '\\n'; };
const pending = new Map();
const socket = new WebSocket(wsUrl);
socket.addEventListener('open', () => {
  socket.send(JSON.stringify({ op: 'sub', c: 'demo-events' }));
  const i = crypto.randomUUID();
  pending.set(i, { resolve: (r) => log('rpc res ' + JSON.stringify(r)), reject: (e) => log(String(e)) });
  socket.send(JSON.stringify({ op: 'rpc', i, m: 'ping', p: { message: 'native' } }));
});
socket.addEventListener('message', (event) => {
  const msg = JSON.parse(event.data);
  if (msg.op === 'evt') log('evt ' + msg.c + ' ' + JSON.stringify(msg.d));
  if (msg.op === 'res' && pending.has(msg.i)) { pending.get(msg.i).resolve(msg.r); pending.delete(msg.i); }
  if (msg.op === 'err' && msg.i !== undefined && pending.has(msg.i)) { pending.get(msg.i).reject(new Error(msg.msg)); pending.delete(msg.i); }
});
</script>
</body>
</html>
HTML;

        return new Response(OK, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }
}
