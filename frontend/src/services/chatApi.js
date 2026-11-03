const API_URL = import.meta.env.VITE_API_URL || '/api/chat.php'
const STORAGE_KEY = 'orizon.conversation.v1'
export const REQUEST_TIMEOUT_MS = 175000
let inMemoryToken = null

export class ChatError extends Error {
  constructor(message, code = 'NETWORK') {
    super(message)
    this.code = code
  }
}

function readToken() {
  let token = inMemoryToken
  try {
    token = localStorage.getItem(STORAGE_KEY) || inMemoryToken
  } catch { /* Usiamo la memoria se lo storage del browser non è disponibile. */ }
  if (token !== null && !isToken(token)) {
    clearToken()
    return null
  }
  return token
}

function isToken(token) {
  return typeof token === 'string' && /^[a-f0-9]{64}$/.test(token)
}

function saveToken(token) {
  if (!isToken(token)) throw new ChatError('Il server ha restituito una conversazione non valida.', 'PROTOCOL')
  inMemoryToken = token
  try { localStorage.setItem(STORAGE_KEY, token) } catch { /* La chat corrente continua anche senza storage del browser. */ }
}

function clearToken() {
  inMemoryToken = null
  try { localStorage.removeItem(STORAGE_KEY) } catch { /* Storage non disponibile. */ }
}

async function request(action, payload = null, token = null) {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS)
  try {
    const url = new URL(API_URL, window.location.origin)
    const headers = { Accept: 'application/json' }
    if (token) headers.Authorization = `Bearer ${token}`
    if (payload) headers['Content-Type'] = 'application/json'
    else url.searchParams.set('action', action)
    const response = await fetch(url, {
      method: payload ? 'POST' : 'GET',
      headers,
      body: payload ? JSON.stringify({ action, ...payload }) : undefined,
      cache: 'no-store',
      signal: controller.signal,
    })
    if (!response.headers.get('content-type')?.includes('application/json')) {
      throw new ChatError('Il backend PHP non risponde con JSON. Controlla che WAMP sia avviato e riavvia Vite dopo aver copiato vite.config.js.', 'BACKEND_URL')
    }
    let data
    try {
      data = await response.json()
    } catch {
      throw new ChatError('Il server ha restituito JSON non valido. Riprova tra poco.', 'PROTOCOL')
    }
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
      throw new ChatError('La risposta della chat non è valida. Riprova tra poco.', 'PROTOCOL')
    }
    if (!response.ok || data.ok !== true) {
      throw new ChatError(data.error?.message || 'Il server non ha completato la richiesta.', data.error?.code || 'SERVER')
    }
    if (!Array.isArray(data.messages) || data.messages.some((message) => !message || !['user', 'assistant'].includes(message.role) || typeof message.content !== 'string')
      || !Number.isSafeInteger(data.limits?.message_characters) || data.limits.message_characters <= 0) {
      throw new ChatError('La risposta della chat non è valida. Riprova tra poco.', 'PROTOCOL')
    }
    return data
  } catch (error) {
    if (error instanceof ChatError) throw error
    if (error.name === 'AbortError') {
      throw new ChatError('La risposta sta impiegando troppo tempo. Puoi riprovare lo stesso invio o ricaricare per recuperare i messaggi salvati.', 'TIMEOUT')
    }
    throw new ChatError('Non riesco a raggiungere il server. Controlla WAMP e la connessione, poi riprova.', 'NETWORK')
  } finally {
    clearTimeout(timer)
  }
}

export async function createConversation() {
  const data = await request('start', {})
  saveToken(data.conversation_token)
  return data
}

export async function restoreConversation() {
  const token = readToken()
  if (token) {
    try { return await request('history', null, token) } catch (error) {
      if (!['CONVERSATION_TOKEN', 'CONVERSATION_NOT_FOUND'].includes(error.code)) throw error
      clearToken()
    }
  }
  return createConversation()
}

export function sendChatMessage(message, requestId) {
  const token = readToken()
  if (!token) return Promise.reject(new ChatError('La conversazione non è riconosciuta. Premi Nuova chat.', 'CONVERSATION_TOKEN'))
  return request('send', { message, request_id: requestId }, token)
}
