import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import App from './App.vue'
import { createConversation, restoreConversation, sendChatMessage } from './services/chatApi'

vi.mock('./services/chatApi', () => ({ createConversation: vi.fn(), restoreConversation: vi.fn(), sendChatMessage: vi.fn() }))
const welcome = { id: '1', role: 'assistant', content: 'Benvenuto aggiornato soltanto nel backend.' }
const data = messages => ({ ok: true, messages, limits: { message_characters: 1200 } })
let wrapper
beforeEach(() => {
  restoreConversation.mockResolvedValue(data([welcome]))
  createConversation.mockResolvedValue(data([welcome]))
})
afterEach(() => wrapper?.unmount())

it('mostra solo il caricamento, poi il benvenuto ricevuto dal backend', async () => {
  let resolve
  restoreConversation.mockReturnValue(new Promise(done => { resolve = done }))
  wrapper = mount(App)
  expect(wrapper.findAll('.message-bubble')).toHaveLength(0)
  expect(wrapper.find('[role="log"]').text()).toContain('Recupero della conversazione')
  expect(wrapper.findAll('.example-card')).toHaveLength(0)
  resolve(data([welcome]))
  await flushPromises()
  expect(wrapper.findAll('.message-bubble').map(node => node.text())).toEqual([welcome.content])
  expect(wrapper.find('textarea').attributes('maxlength')).toBe('1200')
})

it('offre tre esempi che compilano il messaggio senza inviarlo automaticamente', async () => {
  wrapper = mount(App)
  await flushPromises()
  const buttons = wrapper.findAll('.example-card')
  expect(buttons).toHaveLength(3)
  await buttons[1].trigger('click')
  expect(wrapper.find('textarea').element.value).toContain('Madrid')
  expect(wrapper.find('textarea').element.value).toContain('15 kg')
  expect(sendChatMessage).not.toHaveBeenCalled()
})

it('conserva testo e requestId dopo un errore e non duplica i messaggi al retry', async () => {
  sendChatMessage.mockRejectedValueOnce(new Error('Riprova tra poco.'))
    .mockResolvedValueOnce(data([welcome, { id: '2', role: 'user', content: '20 kg' }, { id: '3', role: 'assistant', content: 'Ecco la stima.' }]))
  wrapper = mount(App)
  await flushPromises()
  await wrapper.find('textarea').setValue('20 kg')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(wrapper.find('[role="alert"]').text()).toContain('Riprova tra poco.')
  expect(wrapper.find('textarea').element.value).toBe('20 kg')
  expect(wrapper.findAll('.message-row--user')).toHaveLength(0)
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(sendChatMessage.mock.calls[1]).toEqual(sendChatMessage.mock.calls[0])
  expect(wrapper.findAll('.message-row--user')).toHaveLength(1)
  expect(wrapper.find('textarea').element.value).toBe('')
  expect(wrapper.find('[role="alert"]').exists()).toBe(false)
})

it('permette di riprovare il collegamento senza inventare messaggi di benvenuto', async () => {
  restoreConversation.mockRejectedValueOnce(new Error('Backend non disponibile.')).mockResolvedValueOnce(data([welcome]))
  wrapper = mount(App)
  await flushPromises()
  expect(wrapper.findAll('.message-bubble')).toHaveLength(0)
  expect(wrapper.find('textarea').element.disabled).toBe(true)
  await wrapper.find('.chat-error button').trigger('click')
  await flushPromises()
  expect(wrapper.find('.message-bubble').text()).toBe(welcome.content)
  expect(wrapper.find('textarea').element.disabled).toBe(false)
})

it('rispetta il limite di caratteri comunicato dal server', async () => {
  restoreConversation.mockResolvedValue({ ...data([welcome]), limits: { message_characters: 4 } })
  wrapper = mount(App)
  await flushPromises()
  expect(wrapper.find('textarea').attributes('maxlength')).toBe('4')
  await wrapper.find('textarea').setValue('12345')
  expect(wrapper.find('.send-button').element.disabled).toBe(true)
})

it('consente di recuperare una conversazione scaduta mantenendo la bozza', async () => {
  sendChatMessage.mockRejectedValueOnce(Object.assign(new Error('Conversazione scaduta.'), { code: 'CONVERSATION_NOT_FOUND' }))
  wrapper = mount(App)
  await flushPromises()
  await wrapper.find('textarea').setValue('20 kg')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  expect(wrapper.find('.chat-error button').exists()).toBe(true)
  await wrapper.find('.chat-error button').trigger('click')
  await flushPromises()
  expect(wrapper.find('textarea').element.value).toBe('20 kg')
  expect(wrapper.find('.send-button').element.disabled).toBe(false)
})
