import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const TOKEN = 'a'.repeat(64)
const NEW_TOKEN = 'b'.repeat(64)
const STORAGE_KEY = 'orizon.conversation.v1'
const messages = [{ id: '1', role: 'assistant', content: 'Messaggio dal server.' }]
const conversation = token => ({ ok: true, conversation_token: token, messages, limits: { message_characters: 1200 } })
const response = (body, status = 200, type = 'application/json') => new Response(typeof body === 'string' ? body : JSON.stringify(body), { status, headers: { 'Content-Type': type } })
let api
let fetchMock

beforeEach(async () => {
  vi.resetModules()
  localStorage.clear()
  fetchMock = vi.fn()
  vi.stubGlobal('fetch', fetchMock)
  api = await import('./chatApi')
})
afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('token e ripristino della conversazione', () => {
  it('salva un token valido e lo usa nell’header del successivo invio', async () => {
    fetchMock.mockResolvedValueOnce(response(conversation(TOKEN))).mockResolvedValueOnce(response(conversation(TOKEN)))
    await api.createConversation()
    expect(localStorage.getItem(STORAGE_KEY)).toBe(TOKEN)
    await api.sendChatMessage('20 kg', 'request-id-12345678')
    const options = fetchMock.mock.calls[1][1]
    expect(options.headers.Authorization).toBe(`Bearer ${TOKEN}`)
    expect(JSON.parse(options.body)).toEqual({ action: 'send', message: '20 kg', request_id: 'request-id-12345678' })
  })

  it.each(['not-a-token', '', 'A'.repeat(64), null])('rifiuta il token restituito non valido: %s', async token => {
    fetchMock.mockResolvedValue(response(conversation(token)))
    await expect(api.createConversation()).rejects.toMatchObject({ code: 'PROTOCOL' })
    expect(localStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('ripristina la cronologia con un token valido già salvato', async () => {
    localStorage.setItem(STORAGE_KEY, TOKEN)
    fetchMock.mockResolvedValue(response(conversation(TOKEN)))
    await expect(api.restoreConversation()).resolves.toMatchObject({ messages })
    expect(fetchMock.mock.calls[0][0].searchParams.get('action')).toBe('history')
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBe(`Bearer ${TOKEN}`)
  })

  it('scarta il token locale malformato prima di inviare una richiesta di cronologia', async () => {
    localStorage.setItem(STORAGE_KEY, 'invalid')
    fetchMock.mockResolvedValue(response(conversation(NEW_TOKEN)))
    await api.restoreConversation()
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(JSON.parse(fetchMock.mock.calls[0][1].body).action).toBe('start')
    expect(localStorage.getItem(STORAGE_KEY)).toBe(NEW_TOKEN)
  })

  it.each(['CONVERSATION_TOKEN', 'CONVERSATION_NOT_FOUND'])('crea una nuova chat solo se il token è scaduto: %s', async code => {
    localStorage.setItem(STORAGE_KEY, TOKEN)
    fetchMock.mockResolvedValueOnce(response({ ok: false, error: { code } }, 401))
      .mockResolvedValueOnce(response(conversation(NEW_TOKEN)))
    await api.restoreConversation()
    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(localStorage.getItem(STORAGE_KEY)).toBe(NEW_TOKEN)
  })

  it('mantiene il token e propaga un errore di servizio durante il ripristino', async () => {
    localStorage.setItem(STORAGE_KEY, TOKEN)
    fetchMock.mockResolvedValue(response({ ok: false, error: { code: 'DATABASE_ERROR', message: 'Riprova.' } }, 503))
    await expect(api.restoreConversation()).rejects.toMatchObject({ code: 'DATABASE_ERROR', message: 'Riprova.' })
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(localStorage.getItem(STORAGE_KEY)).toBe(TOKEN)
  })

  it('continua a funzionare in memoria quando localStorage non è disponibile', async () => {
    vi.stubGlobal('localStorage', { getItem() { throw Error('blocked') }, setItem() { throw Error('blocked') }, removeItem() { throw Error('blocked') } })
    fetchMock.mockResolvedValueOnce(response(conversation(TOKEN))).mockResolvedValueOnce(response(conversation(TOKEN)))
    await api.createConversation()
    await api.sendChatMessage('Ciao', 'request-id-12345678')
    expect(fetchMock.mock.calls[1][1].headers.Authorization).toBe(`Bearer ${TOKEN}`)
  })

  it('non invia messaggi senza un token valido', async () => {
    await expect(api.sendChatMessage('Ciao', 'request-id-12345678')).rejects.toMatchObject({ code: 'CONVERSATION_TOKEN' })
    expect(fetchMock).not.toHaveBeenCalled()
  })
})

describe('errori e contratto HTTP', () => {
  it('riconosce una risposta HTML al posto del backend JSON', async () => {
    fetchMock.mockResolvedValue(response('<html>Not found</html>', 404, 'text/html'))
    await expect(api.createConversation()).rejects.toMatchObject({ code: 'BACKEND_URL' })
  })

  it('riconosce JSON troncato senza confonderlo con un errore di rete', async () => {
    fetchMock.mockResolvedValue(response('{broken'))
    await expect(api.createConversation()).rejects.toMatchObject({ code: 'PROTOCOL' })
  })

  it.each([null, [], { ok: true }, { ...conversation(TOKEN), messages: [null] },
    { ...conversation(TOKEN), messages: [{ role: 'system', content: 'invalid' }] },
    { ...conversation(TOKEN), limits: { message_characters: 0 } }])('rifiuta una risposta JSON incompatibile: %j', async body => {
    fetchMock.mockResolvedValue(response(body))
    await expect(api.createConversation()).rejects.toMatchObject({ code: 'PROTOCOL' })
  })

  it('propaga codice e messaggio di un errore applicativo', async () => {
    fetchMock.mockResolvedValue(response({ ok: false, error: { code: 'RATE_LIMIT', message: 'Quota raggiunta.' } }, 429))
    await expect(api.createConversation()).rejects.toMatchObject({ code: 'RATE_LIMIT', message: 'Quota raggiunta.' })
  })

  it('mappa AbortError a TIMEOUT e libera il timer', async () => {
    vi.useFakeTimers()
    fetchMock.mockImplementation((_url, { signal }) => new Promise((_resolve, reject) => {
      signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), { once: true })
    }))
    const check = expect(api.createConversation()).rejects.toMatchObject({ code: 'TIMEOUT' })
    await vi.advanceTimersByTimeAsync(api.REQUEST_TIMEOUT_MS)
    await check
    expect(vi.getTimerCount()).toBe(0)
  })

  it('segnala una reale indisponibilità di rete', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
    await expect(api.createConversation()).rejects.toMatchObject({ code: 'NETWORK' })
  })
})
