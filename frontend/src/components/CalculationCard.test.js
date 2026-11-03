import { expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CalculationCard from './CalculationCard.vue'

const trip = { origin: 'Milano', destination: 'Roma', transport_mode: 'rail', weight_kg: 20, emissions_kg_co2e: 0.409435932, distance_km: 666.83 }

it('presenta la stima con formato italiano e il contesto del peso trasportato', () => {
  const wrapper = mount(CalculationCard, { props: { calculation: trip } })
  expect(wrapper.find('.calculation-total strong').text()).toBe('0,409436')
  expect(wrapper.text()).toContain('Ferrovia')
  expect(wrapper.text()).toContain('20 kg')
  expect(wrapper.find('.calculation-scope').text()).toContain('non dell’intero passeggero')
  wrapper.unmount()
})

it('conserva uno zero valido ed omette i campi opzionali assenti', () => {
  const wrapper = mount(CalculationCard, { props: { calculation: { ...trip, emissions_kg_co2e: 0, distance_km: null } } })
  expect(wrapper.find('.calculation-total strong').text()).toBe('0')
  expect(wrapper.text()).not.toContain('Distanza stimata')
  expect(wrapper.text()).not.toContain('Metodo')
  wrapper.unmount()
})
