<script setup>
import { nextTick, ref, watch } from 'vue'
import ChatMessage from './ChatMessage.vue'
import Composer from './Composer.vue'
import ExampleCards from './ExampleCards.vue'
import AssistantIcon from './icons/AssistantIcon.vue'
import PlusIcon from './icons/PlusIcon.vue'

const props = defineProps({ chat: { type: Object, required: true } })
const messageList = ref(null)
watch(() => [props.chat.visibleMessages, props.chat.isLoading, props.chat.isSending], async () => {
  await nextTick()
  if (messageList.value) messageList.value.scrollTop = messageList.value.scrollHeight
}, { flush: 'post' })
</script>

<template>
  <section class="chat-card" aria-labelledby="chat-title">
    <header class="chat-header">
      <div class="assistant-avatar" aria-hidden="true"><AssistantIcon /></div>
      <div class="chat-heading"><h2 id="chat-title">Il tuo prossimo orizzonte</h2><p>L’assistente di Orizon</p></div>
      <button type="button" class="reset-button" :disabled="chat.isBusy || (!chat.hasConversation && chat.isConnected)"
        @click="chat.newConversation" title="Inizia una nuova conversazione"><PlusIcon /><span>Nuova chat</span></button>
    </header>

    <div ref="messageList" class="conversation" role="log" aria-label="Conversazione con Orizon"
      aria-live="polite" aria-relevant="additions text" tabindex="0">
      <div class="conversation-date">IL TUO VIAGGIO, UNA CONVERSAZIONE ALLA VOLTA</div>
      <p v-if="chat.isLoading" class="activity-message" role="status">Recupero della conversazione…</p>
      <template v-else>
        <ChatMessage v-for="message in chat.visibleMessages" :key="message.id" :message="message" />
        <p v-if="chat.isSending" class="activity-message" role="status"><span class="typing-dots" aria-hidden="true"><i></i><i></i><i></i></span> L’assistente sta elaborando il messaggio…</p>
        <div v-if="chat.isConnected && !chat.hasConversation" class="essentials" aria-label="Informazioni utili per la stima">
          <p>Ci basteranno quattro informazioni</p>
          <div class="essential-tags"><span>01 · Origine</span><span>02 · Destinazione</span><span>03 · Mezzo</span><span>04 · Peso</span></div>
        </div>
      </template>
    </div>

    <ExampleCards v-if="chat.isConnected && !chat.hasConversation && !chat.isLoading" :disabled="chat.isBusy" @select="chat.useExample" />
    <div v-if="chat.errorMessage" class="chat-error" role="alert"><p>{{ chat.errorMessage }}</p>
      <button v-if="!chat.isConnected" type="button" :disabled="chat.isBusy" @click="chat.loadConversation()">Riprova collegamento</button>
    </div>
    <Composer v-model="chat.draft" :disabled="chat.isBusy || !chat.isConnected" :can-send="chat.canSend"
      :max-length="chat.maxMessageLength" @send="chat.sendMessage" />
    <p class="chat-footnote">Stime indicative del peso trasportato · Fonte dei calcoli: EcoFreight</p>
  </section>
</template>
