export const HEARTBEAT_CHANNEL = '%%MAMMATUS_WEBSOCKET_HEARTBEAT_CHANNEL%%';

export const Operation = Object.freeze({
  CALL: '%%MAMMATUS_WEBSOCKET_OPERATION_CALL%%',
  RESULT: '%%MAMMATUS_WEBSOCKET_OPERATION_RESULT%%',
  ERROR: '%%MAMMATUS_WEBSOCKET_OPERATION_ERROR%%',
  SUBSCRIBE: '%%MAMMATUS_WEBSOCKET_OPERATION_SUBSCRIBE%%',
  UNSUBSCRIBE: '%%MAMMATUS_WEBSOCKET_OPERATION_UNSUBSCRIBE%%',
  PUBLISH: '%%MAMMATUS_WEBSOCKET_OPERATION_PUBLISH%%',
});

export const WireProperties = Object.freeze({
  OPERATION: '%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_OPERATION%%',
  ID: '%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_ID%%',
  METHOD: '%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_METHOD%%',
  CHANNEL: '%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_CHANNEL%%',
  PAYLOAD: '%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_PAYLOAD%%',
  RESULT: '%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_RESULT%%',
  ERROR: '%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_ERROR%%',
  DATA: '%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_DATA%%',
  MESSAGE: '%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_MESSAGE%%',
});

export class MammatusWebSocket {
  #url;
  #socket = null;
  #pending = new Map();
  #handlers = new Map();
  #channels = new Set();
  #reconnectEnabled = true;
  #reconnectTimer = null;
  #reconnectAttempt = 0;
  #hasConnected = false;

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
      this.#reconnectEnabled = true;
      this.#openSocket(
        () => {
          resolve();
        },
        (error) => {
          reject(error);
        },
      );
    });
  }

  disconnect() {
    this.#reconnectEnabled = false;
    this.#clearReconnectTimer();
    this.#rejectPending(new Error('connection closed'));
    if (this.#socket !== null) {
      this.#socket.close();
      this.#socket = null;
    }
  }

  subscribe(channel) {
    this.#channels.add(channel);
    this.#sendSubscribe(channel);
  }

  unsubscribe(channel) {
    this.#channels.delete(channel);
    this.#send({
      [WireProperties.OPERATION]: Operation.UNSUBSCRIBE,
      [WireProperties.CHANNEL]: channel,
    });
  }

  rpc(method, params = {}) {
    const id = crypto.randomUUID();
    return new Promise((resolve, reject) => {
      this.#pending.set(id, { resolve, reject });
      this.#send({
        [WireProperties.OPERATION]: Operation.CALL,
        [WireProperties.ID]: id,
        [WireProperties.METHOD]: method,
        [WireProperties.PAYLOAD]: params,
      });
    });
  }

  #openSocket(onFirstOpen, onFirstError) {
    this.#socket = new WebSocket(this.#url);
    this.#socket.addEventListener('open', () => {
      this.#reconnectAttempt = 0;
      this.#clearReconnectTimer();
      this.#resubscribeAll();
      if (!this.#hasConnected) {
        this.#hasConnected = true;
        this.#emit('open');
        onFirstOpen();
        return;
      }
      this.#emit('reconnect');
    });
    this.#socket.addEventListener('error', () => {
      const error = new Error('WebSocket error');
      this.#emit('error', error);
      if (!this.#hasConnected) {
        onFirstError(error);
      }
    });
    this.#socket.addEventListener('close', () => {
      this.#socket = null;
      this.#rejectPending(new Error('connection lost'));
      this.#emit('close');
      if (this.#reconnectEnabled) {
        this.#scheduleReconnect(onFirstOpen, onFirstError);
      }
    });
    this.#socket.addEventListener('message', (event) => {
      let msg;
      try {
        msg = JSON.parse(String(event.data));
      } catch {
        return;
      }
      const op = msg[WireProperties.OPERATION] ?? msg.op;
      if (op === Operation.PUBLISH) {
        this.#emit('evt', msg[WireProperties.CHANNEL], msg[WireProperties.DATA]);
        return;
      }
      if (op === Operation.RESULT && this.#pending.has(msg[WireProperties.ID])) {
        this.#pending.get(msg[WireProperties.ID]).resolve(msg[WireProperties.RESULT]);
        this.#pending.delete(msg[WireProperties.ID]);
        return;
      }
      if (
        op === Operation.ERROR
        && msg[WireProperties.ID] !== undefined
        && this.#pending.has(msg[WireProperties.ID])
      ) {
        this.#pending
          .get(msg[WireProperties.ID])
          .reject(new Error(`${msg[WireProperties.ERROR]}: ${msg[WireProperties.MESSAGE]}`));
        this.#pending.delete(msg[WireProperties.ID]);
      }
    });
  }

  #resubscribeAll() {
    this.#sendSubscribe(HEARTBEAT_CHANNEL);
    for (const channel of this.#channels) {
      this.#sendSubscribe(channel);
    }
  }

  #sendSubscribe(channel) {
    this.#send({
      [WireProperties.OPERATION]: Operation.SUBSCRIBE,
      [WireProperties.CHANNEL]: channel,
    });
  }

  #scheduleReconnect(onFirstOpen, onFirstError) {
    if (this.#reconnectTimer !== null) {
      return;
    }
    const delayMs = Math.min(1000 * 2 ** this.#reconnectAttempt, 30000);
    this.#reconnectAttempt += 1;
    this.#reconnectTimer = setTimeout(() => {
      this.#reconnectTimer = null;
      if (!this.#reconnectEnabled) {
        return;
      }
      this.#openSocket(onFirstOpen, onFirstError);
    }, delayMs);
  }

  #clearReconnectTimer() {
    if (this.#reconnectTimer === null) {
      return;
    }
    clearTimeout(this.#reconnectTimer);
    this.#reconnectTimer = null;
  }

  #rejectPending(error) {
    for (const { reject } of this.#pending.values()) {
      reject(error);
    }
    this.#pending.clear();
  }

  #send(payload) {
    if (this.#socket?.readyState === WebSocket.OPEN) {
      this.#socket.send(JSON.stringify(payload));
    }
  }
}
