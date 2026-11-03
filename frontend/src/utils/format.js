const numberFormatter = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 6 })
export const formatNumber = value => numberFormatter.format(value)
export const modeLabels = { road: 'Su strada (merci)', rail: 'Ferrovia', sea: 'Mare', air: 'Aereo' }
