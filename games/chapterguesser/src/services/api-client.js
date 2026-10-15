export class ApiError extends Error {
  constructor(message, kind = 'server') {
    super(message);
    this.kind = kind;
  }
}

export function networkMessage(error) {
  if (error?.kind === 'offline') return 'You are offline. Reconnect and retry; no result is confirmed yet.';
  if (error?.kind === 'network') return 'Connection interrupted. Retry to check the server.';
  return error?.message || 'The server could not complete the request. Please retry.';
}

export function createApiClient({ baseURI = globalThis.document?.baseURI, fetchImpl = globalThis.fetch,
  online = () => globalThis.navigator?.onLine !== false } = {}) {
  return {
    async call(action, body, query = {}) {
      const url = new URL('api/index.php', baseURI);
      url.searchParams.set('action', action);
      for (const [key, value] of Object.entries(query)) url.searchParams.set(key, value);
      let response;
      try {
        response = await fetchImpl(url, {
          method: body === undefined ? 'GET' : 'POST', credentials: 'same-origin', cache: 'no-store',
          ...(body === undefined ? {} : { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        });
      } catch {
        throw new ApiError('Connection unavailable.', online() ? 'network' : 'offline');
      }
      let data;
      try { data = await response.json(); } catch { throw new ApiError('The server returned an unreadable response.'); }
      if (!response.ok || data.error) throw new ApiError(data.error || `Server error (${response.status}).`);
      return data;
    }
  };
}
