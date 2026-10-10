import { BOOKS } from '../config/books.js';
import { DEFAULT_CONFIG, validateName, validateSelection, validateServerConfig } from '../modes/rules.js';
import { Countdown } from '../timer/countdown.js';
import { networkMessage } from '../services/api-client.js';
import { globalRank } from '../services/leaderboards.js';
import { createRequestId } from '../services/request-id.js';

export class OnlineController {
  constructor(view, { api, now, setTimeoutImpl = (callback, ms) => globalThis.setTimeout(callback, ms),
    clearTimeoutImpl = handle => globalThis.clearTimeout(handle),
    requestId = createRequestId } = {}) {
    Object.assign(this, { view, api, setTimeoutImpl, clearTimeoutImpl, requestId });
    this.token = 0;
    this.boardToken = 0;
    this.configToken = 0;
    this.delays = new Set();
    this.model = { screen: 'menu', mode: 'normal', name: '', selectedBooks: BOOKS.map(book => book.id),
      config: { ...DEFAULT_CONFIG }, configStatus: 'loading', preview: {}, previewStatus: 'loading',
      boards: {}, boardMode: 'normal', boardStatus: 'idle', pending: false, snapshot: null, error: '' };
    this.timer = new Countdown({ now, setTimeoutImpl, clearTimeoutImpl,
      onTick: ms => this.view.updateTimer(ms), onExpire: () => this.expire() });
  }

  render() { this.view.render(this.model); }
  init() { this.render(); return Promise.all([this.loadConfig(), this.loadPreview()]); }
  async loadConfig() {
    const token = ++this.configToken;
    this.model.configStatus = 'loading';
    this.render();
    try {
      const config = await this.api.call('config');
      if (token !== this.configToken) return;
      if (!validateServerConfig(config)) throw new Error('Invalid or unsupported server rules. Retry configuration.');
      this.model.config = config;
      this.model.configStatus = 'ready';
    } catch (error) {
      if (token !== this.configToken) return;
      this.model.configStatus = 'error';
      this.model.configError = networkMessage(error);
    }
    this.render();
  }
  async loadPreview() {
    const token = ++this.boardToken;
    this.model.previewStatus = 'loading';
    this.render();
    try {
      const data = await this.api.call('preview');
      if (token !== this.boardToken) return;
      this.model.preview = data.boards;
      this.model.previewStatus = 'ready';
    } catch (error) {
      if (token !== this.boardToken) return;
      this.model.previewStatus = error.kind || 'error';
      this.model.previewError = networkMessage(error);
    }
    this.render();
  }
  setName(name) { this.model.name = name; }
  selectMode(mode) {
    if (this.model.screen !== 'menu') return;
    this.model.mode = mode;
    this.model.error = '';
    this.render();
  }
  selectBooks(ids) {
    this.model.selectedBooks = BOOKS.filter(book => ids.includes(book.id)).map(book => book.id);
    this.render();
  }
  cleanup() {
    this.timer.stop();
    for (const delay of this.delays) this.clearTimeoutImpl(delay);
    this.delays.clear();
    this.model.pending = false;
  }
  menu() {
    const runId = this.model.snapshot?.runId;
    const incomplete = this.model.snapshot?.state !== 'completed';
    ++this.token;
    ++this.boardToken;
    this.cleanup();
    this.model.screen = 'menu';
    this.model.snapshot = null;
    this.model.provisional = null;
    this.model.rank = null;
    this.model.error = '';
    this.render();
    if (runId && incomplete) this.api.call('abandon', { runId }).catch(() => {});
    this.loadConfig();
    this.loadPreview();
  }
  async start() {
    if (this.model.pending) return;
    const validated = validateName(this.model.name);
    const error = validated.error || validateSelection(this.model.mode, this.model.selectedBooks);
    if (error) { this.model.error = error; this.render(); return; }
    if (this.model.configStatus !== 'ready') {
      this.model.error = 'Fetch the server rules before starting.';
      this.render();
      return;
    }
    const previousRun = this.model.snapshot;
    const token = ++this.token;
    ++this.boardToken;
    this.cleanup();
    if (previousRun?.runId && previousRun.state !== 'completed') {
      this.api.call('abandon', { runId: previousRun.runId }).catch(() => {});
    }
    this.model.snapshot = null;
    this.model.provisional = null;
    this.model.rank = null;
    this.model.rankStatus = 'idle';
    this.model.name = validated.name;
    this.model.screen = 'loading';
    this.model.pending = true;
    this.model.error = '';
    this.render();
    try {
      const body = { name: validated.name, mode: this.model.mode };
      if (body.mode === 'specific') body.selectedBooks = [...this.model.selectedBooks];
      const ready = await this.api.call('create', body);
      if (token !== this.token) {
        this.api.call('abandon', { runId: ready.runId }).catch(() => {});
        return;
      }
      this.model.snapshot = ready;
      this.render();
      const snapshot = await this.api.call('start', { runId: ready.runId });
      if (token !== this.token) return;
      this.apply(snapshot);
    } catch (error) {
      if (token !== this.token) return;
      this.model.pending = false;
      this.model.screen = 'error';
      this.model.error = networkMessage(error);
      this.retryAction = () => this.model.snapshot ? this.resume() : this.start();
      this.render();
    }
  }
  apply(snapshot, feedback = true) {
    this.model.snapshot = snapshot;
    this.model.pending = false;
    this.model.error = '';
    if (snapshot.state === 'completed' && feedback && snapshot.feedback?.type === 'book' && !snapshot.feedback.correct) {
      this.cleanup();
      this.model.screen = 'quiz';
      this.model.pending = true;
      this.render();
      this.schedule(() => this.apply(snapshot, false), 900);
      return;
    }
    if (snapshot.state === 'completed') {
      this.cleanup();
      this.model.screen = 'result';
      this.render();
      if (snapshot.result?.accepted === true) this.refreshRank();
      return;
    }
    this.model.screen = 'quiz';
    if (feedback && snapshot.feedback) {
      this.model.pending = true;
      this.render();
      if (snapshot.mode === 'timed') this.timer.sync(snapshot.remainingMs);
      const delay = snapshot.feedback.type === 'book' ? 900 : snapshot.feedback.correct ? 1200 : 2200;
      if (this.model.screen !== 'quiz') return;
      this.schedule(() => {
        if (snapshot.state === 'answer-feedback') this.next();
        else { this.model.pending = false; this.render(); }
      }, delay);
      return;
    }
    this.render();
    if (snapshot.mode === 'timed') this.timer.sync(snapshot.remainingMs);
  }
  schedule(callback, ms) {
    const token = this.token;
    const handle = this.setTimeoutImpl(() => {
      this.delays.delete(handle);
      if (token === this.token && this.model.screen === 'quiz') callback();
    }, ms);
    this.delays.add(handle);
  }
  async answer(type, answerId) {
    if (this.model.pending || this.model.screen !== 'quiz') return;
    if (this.timer.expired()) { this.expire(); return; }
    const snapshot = this.model.snapshot;
    if (snapshot.state !== `${type}-selection`) return;
    if (type === 'book' && !BOOKS.some(book => book.id === answerId)) return;
    if (type === 'chapter' && !snapshot.question?.chapterChoices?.some(choice => choice.id === answerId)) return;
    await this.runCall('answer', { type, answerId, requestId: this.requestId() });
  }
  async runCall(action, extra = {}) {
    const token = this.token;
    const runId = this.model.snapshot.runId;
    this.model.pending = true;
    this.render();
    try {
      const snapshot = await this.api.call(action, { runId, ...extra });
      if (token !== this.token || this.model.screen === 'result') return;
      this.apply(snapshot);
    } catch {
      if (token !== this.token || this.model.screen === 'result') return;
      await this.resume();
    }
  }
  next() {
    if (this.timer.expired()) { this.expire(); return; }
    return this.runCall('next');
  }
  async resume() {
    const token = this.token;
    this.model.pending = true;
    this.render();
    try {
      let snapshot = await this.api.call('state', { runId: this.model.snapshot.runId });
      if (token !== this.token) return;
      if (snapshot.state === 'ready') snapshot = await this.api.call('start', { runId: snapshot.runId });
      if (token !== this.token) return;
      this.apply(snapshot, snapshot.state === 'answer-feedback');
    } catch (error) {
      if (token !== this.token) return;
      this.cleanup();
      this.model.screen = 'error';
      this.model.error = `${networkMessage(error)} The answer may have reached the server. Check run state to continue.`;
      this.retryAction = () => this.resume();
      this.render();
    }
  }
  expire() {
    if (this.model.screen !== 'quiz') return;
    ++this.token;
    this.cleanup();
    this.model.screen = 'result';
    this.model.provisional = { score: this.model.snapshot.score, reason: 'time-expired',
      elapsedMs: this.model.snapshot.duration * 1000, accepted: false };
    this.model.error = 'Time expired. Confirming with the server…';
    this.render();
    this.confirmFinish();
  }
  async confirmFinish() {
    if (this.model.pending) return;
    const token = this.token;
    const runId = this.model.snapshot?.runId;
    if (!runId) return;
    this.model.pending = true;
    this.render();
    try {
      let snapshot;
      try { snapshot = await this.api.call('finish', { runId }); }
      catch { snapshot = await this.api.call('state', { runId }); }
      if (token !== this.token) return;
      this.model.pending = false;
      if (snapshot.state === 'completed') this.apply(snapshot);
      else {
        this.model.error = 'The server has not confirmed expiry. Check state / retry confirmation.';
        this.render();
      }
    } catch (error) {
      if (token !== this.token) return;
      this.model.pending = false;
      this.model.error = networkMessage(error);
      this.render();
    }
  }
  async refreshRank() {
    const token = this.token;
    const snapshot = this.model.snapshot;
    this.model.rank = null;
    this.model.rankStatus = 'loading';
    this.render();
    try {
      const data = await this.api.call('leaderboard', undefined, { mode: snapshot.mode, limit: 100 });
      if (token !== this.token) return;
      this.model.boards[snapshot.mode] = data.entries;
      this.model.rank = globalRank(data.entries, snapshot.result);
      const entryId = snapshot.result.id ?? snapshot.result.entryId;
      this.model.rankAtAcceptance = this.model.rank !== null
        && !data.entries.some(entry => entryId != null && String(entry.id) === String(entryId));
      this.model.rankStatus = 'ready';
    } catch (error) {
      if (token !== this.token) return;
      this.model.rankStatus = 'error';
      this.model.rankError = networkMessage(error);
    }
    this.render();
  }
  async leaderboard(mode = this.model.mode) {
    if (this.model.screen === 'quiz' || this.model.screen === 'loading') return;
    const token = ++this.boardToken;
    this.model.screen = 'leaderboard';
    this.model.boardMode = mode;
    this.model.boardStatus = 'loading';
    this.render();
    try {
      const data = await this.api.call('leaderboard', undefined, { mode, limit: 100 });
      if (token !== this.boardToken) return;
      this.model.boards[mode] = data.entries;
      this.model.boardStatus = 'ready';
    } catch (error) {
      if (token !== this.boardToken) return;
      this.model.boardStatus = error.kind || 'error';
      this.model.boardError = networkMessage(error);
    }
    this.render();
  }
  retry() { return this.retryAction?.(); }
}
