export class MammatusWebSocket {
  #url;
  #socket = null;
  #pending = new Map();
  #handlers = new Map();

  constructor(url) {
    this.#url = url;
  }

  on(event, handler) {
    if (!this.#handlers.has(event)) {
      this.#handlers.set(event, []);
    }
    this.#handlers.get(event).push(handler);
  }

  #emit(event, ...args) {
    for (const handler of this.#handlers.get(event) ?? []) {
      handler(...args);
    }
  }

  connect() {
    return new Promise((resolve, reject) => {
      this.#socket = new WebSocket(this.#url);
      this.#socket.addEventListener('open', () => {
        this.#emit('open');
        resolve();
      });
      this.#socket.addEventListener('error', () => {
        this.#emit('error', new Error('WebSocket error'));
        reject(new Error('WebSocket error'));
      });
      this.#socket.addEventListener('message', (event) => {
        let msg;
        try {
          msg = JSON.parse(String(event.data));
        } catch {
          return;
        }
        if (msg.op === 'evt') {
          this.#emit('evt', msg.c, msg.d);
          return;
        }
        if (msg.op === 'res' && this.#pending.has(msg.i)) {
          this.#pending.get(msg.i).resolve(msg.r);
          this.#pending.delete(msg.i);
          return;
        }
        if (msg.op === 'err' && msg.i !== undefined && this.#pending.has(msg.i)) {
          this.#pending.get(msg.i).reject(new Error(`${msg.e}: ${msg.msg}`));
          this.#pending.delete(msg.i);
        }
      });
    });
  }

  subscribe(channel) {
    this.#send({ op: 'sub', c: channel });
  }

  unsubscribe(channel) {
    this.#send({ op: 'unsub', c: channel });
  }

  rpc(method, params = {}) {
    const i = crypto.randomUUID();
    return new Promise((resolve, reject) => {
      this.#pending.set(i, { resolve, reject });
      this.#send({ op: 'rpc', i, m: method, p: params });
    });
  }

  #send(payload) {
    if (this.#socket?.readyState === WebSocket.OPEN) {
      this.#socket.send(JSON.stringify(payload));
    }
  }
}
