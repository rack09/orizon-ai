<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { createConversation, restoreConversation, sendChatMessage } from './services/chatApi'

const MAX_MESSAGE_LENGTH = 1200
const draft = ref('')
const messageList = ref(null)
const messageInput = ref(null)
let nextMessageId = 1
const isLoading = ref(true)
const isSending = ref(false)
const isConnected = ref(false)
const errorMessage = ref('')
const pendingText = ref('')
let lastAttempt = null
const modeLabels = { road: 'Su strada (merci)', rail: 'Ferrovia', sea: 'Mare', air: 'Aereo' }

function welcomeMessage() {
  return {
    id: nextMessageId++,
    role: 'assistant',
    content: 'Ciao! Sono l’assistente di Orizon.\n\nDescrivi il tuo tragitto: da dove parti, dove vuoi arrivare, il mezzo di trasporto e il peso dei bagagli o del carico in kg.',
  }
}

const messages = ref([welcomeMessage()])
const hasConversation = computed(() => messages.value.some((message) => message.role === 'user'))
const isBusy = computed(() => isLoading.value || isSending.value)
const canSend = computed(() => isConnected.value && !isBusy.value && draft.value.trim().length > 0 && draft.value.length <= MAX_MESSAGE_LENGTH)
const connectionLabel = computed(() => isLoading.value ? 'Collegamento…' : isConnected.value ? 'Chat pronta' : 'Da collegare')
const visibleMessages = computed(() => isSending.value && pendingText.value ? [...messages.value, { id: 'pending-user', role: 'user', content: pendingText.value }] : messages.value)

function formatNumber(value) {
  return new Intl.NumberFormat('it-IT', { maximumFractionDigits: 6 }).format(value)
}

const examples = [
  {
    label: 'Un tragitto in treno',
    route: 'Milano → Roma',
    mode: 'rail',
    message: 'Vorrei stimare le emissioni del trasporto di 20 kg di bagagli da Milano, Italia, a Roma, Italia, in treno.',
  },
  {
    label: 'Una partenza in aereo',
    route: 'Roma → Madrid',
    mode: 'air',
    message: 'Vorrei stimare le emissioni del trasporto di 15 kg di bagagli da Roma, Italia, a Madrid, Spagna, in aereo.',
  },
  {
    label: 'Un percorso su strada',
    route: 'Torino → Lione',
    mode: 'road',
    message: 'Vorrei stimare le emissioni del trasporto di 30 kg di carico da Torino, Italia, a Lione, Francia, su strada.',
  },
]

function resizeInput() {
  const input = messageInput.value
  if (!input) return
  input.style.height = 'auto'
  input.style.height = `${Math.min(input.scrollHeight, 144)}px`
}

async function scrollToLatestMessage() {
  await nextTick()
  if (messageList.value) {
    messageList.value.scrollTop = messageList.value.scrollHeight
  }
}

async function useExample(example) {
  draft.value = example.message
  await nextTick()
  resizeInput()
  messageInput.value?.focus()
}

async function sendMessage() {
  if (!canSend.value) return
  const text = draft.value.trim()
  if (!lastAttempt || lastAttempt.text !== text) {
    lastAttempt = { text, requestId: crypto.randomUUID() }
  }
  isSending.value = true
  pendingText.value = text
  errorMessage.value = ''
  await scrollToLatestMessage()
  try {
    const data = await sendChatMessage(text, lastAttempt.requestId)
    messages.value = data.messages
    draft.value = ''
    lastAttempt = null
  } catch (error) {
    errorMessage.value = error.message
    // Il testo e requestId restano disponibili: un retry non duplica un turno già salvato.
  } finally {
    isSending.value = false
    pendingText.value = ''
    await nextTick()
    resizeInput()
    await scrollToLatestMessage()
    messageInput.value?.focus()
  }
}

function handleKeydown(event) {
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault()
    sendMessage()
  }
}

async function newConversation() {
  if (isBusy.value) return
  await loadConversation(true)
}

async function loadConversation(startNew = false) {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const data = await (startNew ? createConversation() : restoreConversation())
    messages.value = data.messages
    isConnected.value = true
    if (startNew) {
      draft.value = ''
      lastAttempt = null
    }
  } catch (error) {
    isConnected.value = false
    errorMessage.value = error.message
  } finally {
    isLoading.value = false
    await nextTick()
    resizeInput()
    await scrollToLatestMessage()
  }
}

onMounted(() => loadConversation())
</script>

<template>
  <div class="page-shell">
    <header class="site-header">
      <a class="brand" href="./" aria-label="Orizon, pagina iniziale">
        <svg class="brand-mark" viewBox="0 0 48 48" fill="none" aria-hidden="true">
          <circle cx="24" cy="24" r="18" stroke="currentColor" stroke-width="2" />
          <path d="M5 29h38M14 29a10 10 0 0 1 20 0" stroke="currentColor" stroke-width="2" />
          <circle cx="35" cy="11" r="4" fill="var(--accent)" />
        </svg>
        <span>orizon<span class="brand-dot">.</span></span>
      </a>
      <p class="header-caption">NUOVI ORIZZONTI. SCELTE CONSAPEVOLI.</p>
      <span class="preview-badge" role="status"><span aria-hidden="true"></span> {{ connectionLabel }}</span>
    </header>

    <main class="main-layout">
      <section class="intro" aria-labelledby="page-title">
        <p class="eyebrow"><span></span> IL VIAGGIO COMINCIA DA QUI</p>
        <h1 id="page-title">Esplora di più.<br />Lascia<br /><em>meno tracce.</em></h1>
        <p class="intro-description">Ogni viaggio ha un impatto. Comprenderlo è il primo passo per partire con più consapevolezza.</p>

        <div class="journey-illustration" aria-hidden="true">
          <svg viewBox="0 0 330 148" fill="none">
            <path d="M17 118C59 118 31 48 89 48S108 112 163 104 199 30 258 30h44" stroke="#b6c7b7" stroke-width="1.6" stroke-dasharray="4 7" />
            <path d="M10 127c34-18 54-18 82 0s50 18 76 0 50-18 76 0 48 16 73 0" stroke="#d7dfd2" stroke-width="1.5" />
            <circle cx="17" cy="118" r="5" fill="#244c3d" />
            <circle cx="302" cy="30" r="6" fill="#e5ad69" />
            <circle cx="302" cy="30" r="15" stroke="#e5ad69" stroke-opacity=".4" />
            <path d="m159 88 9-27 6 10 12 4-27 13Z" fill="#244c3d" />
            <path d="m159 88 15-17" stroke="#fffdf7" stroke-width="1.4" />
            <path d="M79 24V9m-7 8h14" stroke="#c8975e" stroke-width="1.5" />
          </svg>
          <span>Una scelta alla volta, verso nuovi orizzonti.</span>
        </div>

        <aside class="scope-note" aria-label="Come leggere le stime">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 4c-8-1-14 3-14 9a6 6 0 0 0 6 6c6 0 9-7 8-15Z" stroke="currentColor" stroke-width="1.5" /><path d="m5 20 9-10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
          <div><h2>Un dato, con il giusto contesto.</h2><p>Le stime riguardano il trasporto del peso indicato, secondo i fattori EcoFreight. Non rappresentano l’intera impronta del passeggero.</p></div>
        </aside>
      </section>

      <section class="chat-card" aria-labelledby="chat-title">
        <header class="chat-header">
          <div class="assistant-avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 16h16M7 16a5 5 0 0 1 10 0M12 3v3M4 8l2 2m14-2-2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /><path d="M7 20h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
          </div>
          <div class="chat-heading"><h2 id="chat-title">Il tuo prossimo orizzonte</h2><p>L’assistente di Orizon</p></div>
          <button type="button" class="reset-button" :disabled="isBusy || (!hasConversation && isConnected)" @click="newConversation" title="Inizia una nuova conversazione">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>
            <span>Nuova chat</span>
          </button>
        </header>

        <div ref="messageList" class="conversation" role="log" aria-label="Conversazione con Orizon" aria-live="polite" aria-relevant="additions text" tabindex="0">
          <div class="conversation-date">IL TUO VIAGGIO, UNA CONVERSAZIONE ALLA VOLTA</div>
          <div v-for="message in visibleMessages" :key="message.id" class="message-row" :class="`message-row--${message.role}`">
              <span v-if="message.role === 'assistant'" class="message-avatar" aria-hidden="true">o.</span>
              <div class="message-content">
                <span class="message-author">{{ message.role === 'user' ? 'Tu' : message.response_kind === 'fallback' ? 'Orizon · riepilogo automatico' : 'Orizon' }}</span>
                <p class="message-bubble">{{ message.content }}</p>
                <section v-for="(calculation, index) in message.calculations || []" :key="`${message.id}-calculation-${index}`" class="calculation-card" aria-label="Risultato del calcolo EcoFreight">
                  <p class="calculation-source">STIMA ECOFREIGHT · SINGOLA TRATTA</p>
                  <p class="calculation-total"><strong>{{ formatNumber(calculation.emissions_kg_co2e) }}</strong><span>kg CO₂e</span></p>
                  <dl class="calculation-details">
                    <div><dt>Origine</dt><dd>{{ calculation.origin }}</dd></div>
                    <div><dt>Destinazione</dt><dd>{{ calculation.destination }}</dd></div>
                    <div><dt>Mezzo</dt><dd>{{ modeLabels[calculation.transport_mode] }}</dd></div>
                    <div><dt>Peso trasportato</dt><dd>{{ formatNumber(calculation.weight_kg) }} kg</dd></div>
                    <div v-if="calculation.distance_km !== null"><dt>Distanza stimata</dt><dd>{{ formatNumber(calculation.distance_km) }} km</dd></div>
                    <div v-if="calculation.methodology"><dt>Metodo</dt><dd>{{ calculation.methodology }}</dd></div>
                  </dl>
                  <p class="calculation-scope">Emissioni del peso trasportato secondo fattori merci; non dell’intero passeggero. Fonte: EcoFreight.</p>
                </section>
              </div>
          </div>

          <p v-if="isLoading" class="activity-message" role="status">Recupero della conversazione…</p>
          <p v-if="isSending" class="activity-message" role="status"><span class="typing-dots" aria-hidden="true"><i></i><i></i><i></i></span> L’assistente sta elaborando il messaggio…</p>

          <div v-if="!hasConversation" class="essentials" aria-label="Informazioni utili per la stima">
            <p>Ci basteranno quattro informazioni</p>
            <div class="essential-tags"><span>01 · Origine</span><span>02 · Destinazione</span><span>03 · Mezzo</span><span>04 · Peso</span></div>
          </div>
        </div>

        <div v-if="!hasConversation" class="examples-section">
          <p class="examples-label">NON SAI DA DOVE INIZIARE? PROVA UN ESEMPIO</p>
          <div class="example-grid">
            <button v-for="example in examples" :key="example.mode" class="example-card" type="button" @click="useExample(example)">
              <svg v-if="example.mode === 'rail'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="6" y="3" width="12" height="15" rx="3" stroke="currentColor" stroke-width="1.5" /><path d="M6 10h12M12 3v7M8 21l2-3m6 3-2-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /><circle cx="9" cy="14" r="1" fill="currentColor" /><circle cx="15" cy="14" r="1" fill="currentColor" /></svg>
              <svg v-else-if="example.mode === 'air'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m21 3-6 18-4-8-8-4 18-6Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /><path d="m11 13 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
              <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 3 3 21M16 3l5 18M12 4v3m0 4v3m0 4v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
              <span class="example-route">{{ example.route }}</span><span class="example-label">{{ example.label }}</span>
            </button>
          </div>
        </div>

        <div v-if="errorMessage" class="chat-error" role="alert"><p>{{ errorMessage }}</p><button v-if="!isConnected" type="button" :disabled="isBusy" @click="loadConversation()">Riprova collegamento</button></div>

        <form class="composer" @submit.prevent="sendMessage">
          <div class="composer-field">
            <label for="travel-message" class="sr-only">Descrivi il tragitto, il mezzo e il peso trasportato</label>
            <textarea id="travel-message" ref="messageInput" v-model="draft" :disabled="isBusy || !isConnected" :maxlength="MAX_MESSAGE_LENGTH" rows="2" placeholder="Da dove partiamo? Raccontami il tuo viaggio…" aria-describedby="composer-help" @keydown="handleKeydown" @input="resizeInput"></textarea>
            <button class="send-button" type="submit" :disabled="!canSend" aria-label="Invia messaggio" title="Invia messaggio"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 19V5m-6 6 6-6 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
          </div>
          <div class="composer-help" id="composer-help"><span>Invio per inviare · Maiusc + Invio per andare a capo</span><span>{{ draft.length }}/{{ MAX_MESSAGE_LENGTH }}</span></div>
        </form>
        <p class="chat-footnote">Stime indicative del peso trasportato · Fonte dei calcoli: EcoFreight</p>
      </section>
    </main>

    <footer class="site-footer"><span>ORIZON · VIAGGIARE CON CONSAPEVOLEZZA</span><span>Un progetto di Melvin Rosales</span></footer>
  </div>
</template>
