export class Countdown {
  constructor({ now = () => performance.now(), setTimeoutImpl = (callback, ms) => globalThis.setTimeout(callback, ms),
    clearTimeoutImpl = handle => globalThis.clearTimeout(handle), onTick = () => {}, onExpire = () => {} } = {}) {
    Object.assign(this, { now, setTimeoutImpl, clearTimeoutImpl, onTick, onExpire });
    this.active = false;
    this.generation = 0;
  }

  sync(remainingMs) {
    this.stop();
    this.active = true;
    this.deadline = this.now() + Math.max(0, Number(remainingMs) || 0);
    const generation = this.generation;
    const tick = () => {
      if (!this.active || generation !== this.generation) return;
      const remaining = this.remaining();
      this.onTick(remaining);
      if (remaining <= 0) {
        this.stop();
        this.onExpire();
      } else this.handle = this.setTimeoutImpl(tick, Math.min(200, remaining));
    };
    tick();
  }

  remaining() { return Math.max(0, this.deadline - this.now()); }
  expired() { return this.active && this.remaining() <= 0; }
  stop() {
    this.active = false;
    this.generation += 1;
    this.clearTimeoutImpl(this.handle);
    this.handle = null;
  }
}
