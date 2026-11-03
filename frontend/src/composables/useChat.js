import { computed, onMounted, reactive, ref } from 'vue'
import { createConversation, restoreConversation, sendChatMessage } from '../services/chatApi'

export function useChat() {
  const draft = ref('')
  // La sola fonte dei messaggi, incluso il benvenuto, è il backend.
  const messages = ref([])
  const maxMessageLength = ref(null)
  const isLoading = ref(true)
  const isSending = ref(false)
  const isConnected = ref(false)
  const errorMessage = ref('')
  const pendingText = ref('')
  let lastAttempt = null

  const hasConversation = computed(() => messages.value.some(message => message.role === 'user'))
  const isBusy = computed(() => isLoading.value || isSending.value)
  const canSend = computed(() => isConnected.value && !isBusy.value && draft.value.trim().length > 0 && draft.value.length <= maxMessageLength.value)
  const connectionLabel = computed(() => isLoading.value ? 'Collegamento…' : isConnected.value ? 'Chat pronta' : 'Da collegare')
  const visibleMessages = computed(() => isSending.value && pendingText.value
    ? [...messages.value, { id: 'pending-user', role: 'user', content: pendingText.value }]
    : messages.value)

  function applyConversation(data) {
    messages.value = data.messages
    maxMessageLength.value = data.limits.message_characters
  }

  function useExample(example) {
    if (!isBusy.value && isConnected.value) draft.value = example.message
  }

  async function sendMessage() {
    if (!canSend.value) return
    const text = draft.value.trim()
    if (!lastAttempt || lastAttempt.text !== text) lastAttempt = { text, requestId: crypto.randomUUID() }
    isSending.value = true
    pendingText.value = text
    errorMessage.value = ''
    try {
      applyConversation(await sendChatMessage(text, lastAttempt.requestId))
      draft.value = ''
      lastAttempt = null
    } catch (error) {
      errorMessage.value = error.message
      if (['CONVERSATION_TOKEN', 'CONVERSATION_NOT_FOUND'].includes(error.code)) isConnected.value = false
      // Il testo e requestId restano disponibili per un retry senza duplicati.
    } finally {
      isSending.value = false
      pendingText.value = ''
    }
  }

  async function loadConversation(startNew = false) {
    isLoading.value = true
    errorMessage.value = ''
    try {
      applyConversation(await (startNew ? createConversation() : restoreConversation()))
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
    }
  }

  async function newConversation() {
    if (!isBusy.value) await loadConversation(true)
  }

  onMounted(() => loadConversation())
  return reactive({ draft, messages, maxMessageLength, isLoading, isSending, isConnected,
    errorMessage, hasConversation, isBusy, canSend, connectionLabel, visibleMessages,
    useExample, sendMessage, loadConversation, newConversation })
}
