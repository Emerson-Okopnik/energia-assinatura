<script setup>
// Página "Faturar Usina" — PÁGINA ÚNICA da usina, sem abas (blueprint §3).
// Sempre acessada com :usinaId (a rota /faturar sem id redireciona para /usinas).
// Coluna única rolável: Hero → stat-cards → Apuração → Histórico → Expectativa.
// O frontend NÃO calcula nada: lê preview/projeção do backend (faturamentoApi.js).
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Chart as ChartJS,
  Title, Tooltip, Legend, LineElement, PointElement,
  BarElement, CategoryScale, LinearScale,
} from 'chart.js'
import { Bar } from 'vue-chartjs'

import swal from '../utils/swal.js'
import { formatKwh, formatNumero, formatReais } from '../utils/formatters.js'
import * as api from '../services/faturamentoApi.js'

import SectionCard from '../components/base/SectionCard.vue'
import BaseButton from '../components/base/BaseButton.vue'
import BaseBadge from '../components/base/BaseBadge.vue'
import DataTable from '../components/base/DataTable.vue'
import StatValue from '../components/base/StatValue.vue'
import CompetenciaSelector from '../components/faturamento/CompetenciaSelector.vue'
import PreviewPanel from '../components/faturamento/PreviewPanel.vue'
import AuditoriaAccordion from '../components/faturamento/AuditoriaAccordion.vue'
import LancamentoReadonly from '../components/faturamento/LancamentoReadonly.vue'
import ApurarMesModal from '../components/faturamento/ApurarMesModal.vue'

ChartJS.register(
  Title, Tooltip, Legend, BarElement, LineElement,
  PointElement, CategoryScale, LinearScale
)

const route = useRoute()
const router = useRouter()

// ---------------------------------------------------------------- constantes
const MESES = {
  janeiro: 'Janeiro', fevereiro: 'Fevereiro', marco: 'Março', abril: 'Abril',
  maio: 'Maio', junho: 'Junho', julho: 'Julho', agosto: 'Agosto',
  setembro: 'Setembro', outubro: 'Outubro', novembro: 'Novembro', dezembro: 'Dezembro',
}
const CHAVES_MESES = Object.keys(MESES)
const MIN_ANO = 2024
const anoAtual = new Date().getFullYear()
const maxAno = anoAtual + 1
const chaveMesAtual = CHAVES_MESES[new Date().getMonth()]

// ---------------------------------------------------------------- estado
const usina = ref(null)
const carregandoPagina = ref(false)
const erroPagina = ref('')

const ano = ref(anoAtual)
const mes = ref(chaveMesAtual)

const dadosFaturamentoAnual = ref(null)
const dadosGeracaoRealMensal = ref({})
const ultimoRevertivel = ref(null)
const historicoEstorno = ref([])
const inputsSalvos = ref({})
const projecaoAnual = ref([])
const carregandoAno = ref(false)
const erroAno = ref('')

// Modal de apuração (blueprint §4): toda a entrada de dados, preview e auditoria
// editável saiu para o ApurarMesModal. A view só dispara a abertura na competência
// certa e recarrega o ano ao confirmar.
const apuracaoModalAberto = ref(false)
const apuracaoMesAlvo = ref(null)

const isRevertendo = ref(false)

// Auditoria READ-ONLY do histórico: preview relido por mês a partir dos inputs salvos.
const previewHistorico = reactive({}) // { [chaveMes]: { dados, loading, error } }

// ---------------------------------------------------------------- derivados
const usinaIdRota = computed(() => (route.params.usinaId ? Number(route.params.usinaId) : null))
const mesIndex = computed(() => CHAVES_MESES.indexOf(mes.value) + 1)
const competenciaLabel = computed(() => `${MESES[mes.value] ?? ''}/${ano.value}`)

const fioB = computed(() => {
  const valor = parseFloat(usina.value?.comercializacao?.fio_b)
  return Number.isFinite(valor) && valor > 0 ? valor : 0.13
})
const percentualLei = computed(() => {
  const valor = parseFloat(usina.value?.comercializacao?.percentual_lei)
  return Number.isFinite(valor) && valor > 0 ? valor : 45
})
const reservaTotal = computed(() =>
  dadosFaturamentoAnual.value?.valor_acumulado_reserva?.total ?? null
)

// ----- Hero: subtítulo e chips de contrato
const nomeCliente = computed(() => usina.value?.cliente?.nome ?? 'Usina')
const subtituloUsina = computed(() => {
  if (!usina.value) return ''
  const partes = []
  if (usina.value.uc) partes.push(`UC ${usina.value.uc}`)
  const cidade = usina.value.cliente?.endereco?.cidade
  const uf = usina.value.cliente?.endereco?.estado
  if (cidade && uf) partes.push(`${cidade}/${uf}`)
  else if (cidade || uf) partes.push(cidade || uf)
  const cia = usina.value.comercializacao?.cia_energia
  if (cia) partes.push(cia)
  return partes.join(' · ')
})

const mediaGeracao = computed(() => usina.value?.dado_geracao?.media ?? null)
const menorGeracao = computed(() => Number(usina.value?.dado_geracao?.menor_geracao) || 0)
const tarifaContrato = computed(() => usina.value?.comercializacao?.valor_kwh ?? null)

const chipsContrato = computed(() => [
  { label: 'Fio B', valor: usina.value ? formatReais(fioB.value) : '—' },
  { label: 'Lei 14.300', valor: usina.value ? `${formatNumero(percentualLei.value, 0)}%` : '—' },
  { label: 'Média geração', valor: mediaGeracao.value == null ? '—' : formatKwh(mediaGeracao.value) },
  { label: 'Menor geração', valor: menorGeracao.value > 0 ? formatKwh(menorGeracao.value) : '—' },
  { label: 'Tarifa', valor: tarifaContrato.value == null ? '—' : formatReais(tarifaContrato.value) },
])

function temDadosMes(chaveMes) {
  const geracao = Number(dadosGeracaoRealMensal.value?.[chaveMes] || 0)
  const faturamento = Number(dadosFaturamentoAnual.value?.faturamento_usina?.[chaveMes] || 0)
  const reserva = Number(dadosFaturamentoAnual.value?.valor_acumulado_reserva?.[chaveMes] || 0)
  const creditado = Number(dadosFaturamentoAnual.value?.creditos_distribuidos?.[chaveMes] || 0)
  return geracao > 0 || faturamento > 0 || reserva > 0 || creditado > 0
}

const estadosPorMes = computed(() => {
  const estados = {}
  for (const chave of CHAVES_MESES) {
    estados[chave] = temDadosMes(chave) ? 'faturado' : 'pendente'
  }
  return estados
})

// ----- Stat-cards (1ª dobra)
const mesesFaturados = computed(() => CHAVES_MESES.filter((c) => temDadosMes(c)).length)

const creditosNoAno = computed(() => {
  const dist = dadosFaturamentoAnual.value?.creditos_distribuidos || {}
  return CHAVES_MESES.reduce((total, c) => total + (Number(dist[c]) || 0), 0)
})

const ultimoFaturamento = computed(() => {
  const fatur = dadosFaturamentoAnual.value?.faturamento_usina || {}
  for (let i = CHAVES_MESES.length - 1; i >= 0; i -= 1) {
    const chave = CHAVES_MESES[i]
    if (temDadosMes(chave)) {
      return {
        valor: Number(fatur[chave]) || 0,
        competencia: `${MESES[chave]}/${ano.value}`,
      }
    }
  }
  return null
})

const mesFaturado = computed(() => temDadosMes(mes.value))

// Parâmetros de auditoria: rede/desconto vêm do preview do mês (quando houver).
function montarParametros(preview = null) {
  return {
    tarifa: usina.value?.comercializacao?.valor_kwh ?? null,
    mediaKwh: usina.value?.dado_geracao?.media ?? null,
    menorGeracaoKwh: usina.value?.dado_geracao?.menor_geracao ?? null,
    fioB: fioB.value,
    percentualLei: percentualLei.value,
    rede: preview?.geracao?.rede ?? null,
    descontoRedeKwh: preview?.geracao?.desconto_rede_kwh ?? null,
  }
}

const parametrosAuditoria = computed(() => montarParametros())

function numeroOuNulo(valor) {
  if (valor === null || valor === undefined || valor === '') return null
  const numero = Number(valor)
  return Number.isFinite(numero) ? numero : null
}

const registroLancamento = computed(() =>
  historicoEstorno.value.find(
    (h) => Number(h.ano) === ano.value && h.mes_nome === mes.value && !h.revertido_em
  ) ?? null
)

const dadosLancamento = computed(() => ({
  geracaoKwh: numeroOuNulo(dadosGeracaoRealMensal.value?.[mes.value]),
  consumoKwh: numeroOuNulo(inputsSalvos.value?.[mes.value]?.consumo),
  faturaReais: numeroOuNulo(inputsSalvos.value?.[mes.value]?.fatura_energia),
  valorFinalReais: numeroOuNulo(dadosFaturamentoAnual.value?.faturamento_usina?.[mes.value]),
  lancadoEm: registroLancamento.value?.created_at ?? null,
  lancadoPor: registroLancamento.value?.lancado_por ?? null,
}))

const mesRevertivel = computed(
  () =>
    Boolean(ultimoRevertivel.value) &&
    ultimoRevertivel.value.mes_nome === mes.value &&
    Number(ultimoRevertivel.value.ano) === ano.value
)

// ---------------------------------------------------------------- expectativa
const colunasExpectativa = [
  { key: 'mes', label: 'Mês' },
  { key: 'geracao', label: 'Geração', numeric: true },
  { key: 'media', label: 'Média', numeric: true },
  { key: 'fixo', label: 'Fixo', numeric: true },
  { key: 'injetado', label: 'Injetado', numeric: true },
  { key: 'creditado', label: 'Creditado', numeric: true },
  { key: 'cuo', label: 'CUO', numeric: true },
  { key: 'valorFinal', label: 'Valor final', numeric: true },
]

function ucfirst(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

const linhasExpectativa = computed(() =>
  projecaoAnual.value.map((linha) => ({
    chave: linha.mes_nome,
    mes: ucfirst(linha.mes_nome),
    geracao: formatKwh(linha.geracao_kwh),
    geracaoEhMenor: Number(linha.geracao_kwh) === menorGeracao.value && menorGeracao.value > 0,
    ehMesCorrente: linha.mes_nome === mes.value,
    media: formatKwh(linha.media_kwh),
    fixo: formatReais(linha.fixo_reais),
    injetado: formatReais(linha.injetado_reais),
    creditado: formatReais(linha.creditado_reais),
    cuo: formatReais(linha.cuo_reais),
    valorFinal: formatReais(linha.valor_final_reais),
  }))
)

// Cards-resumo da Expectativa: total projetado, melhor e pior mês.
const resumoExpectativa = computed(() => {
  const p = projecaoAnual.value
  if (!p.length) return null
  let total = 0
  let melhor = null
  let pior = null
  for (const l of p) {
    const v = Number(l.valor_final_reais) || 0
    total += v
    if (!melhor || v > melhor.valor) melhor = { mes: ucfirst(l.mes_nome), valor: v }
    if (!pior || v < pior.valor) pior = { mes: ucfirst(l.mes_nome), valor: v }
  }
  return { total, melhor, pior }
})

// Gráfico responsivo na paleta do design system (F9).
// Fonte de dados: projecaoAnual (expectativa anual), não o preview do mês.
const chartData = computed(() => {
  const p = projecaoAnual.value
  if (!p.length) return null
  return {
    labels: p.map((l) => ucfirst(l.mes_nome)),
    datasets: [
      { type: 'bar', label: 'Fixo', data: p.map((l) => Number(l.fixo_reais) || 0), backgroundColor: '#F9B566', stack: 'montagem', order: 2 },
      { type: 'bar', label: 'Injetado', data: p.map((l) => Number(l.injetado_reais) || 0), backgroundColor: '#F39325', stack: 'montagem', order: 3 },
      { type: 'bar', label: 'Creditado', data: p.map((l) => Number(l.creditado_reais) || 0), backgroundColor: '#5FB53A', stack: 'montagem', order: 4 },
      { type: 'bar', label: 'CUO', data: p.map((l) => Number(l.cuo_reais) || 0), backgroundColor: '#C53B2F', stack: 'montagem', order: 5 },
      { type: 'line', label: 'Valor final a receber', data: p.map((l) => Number(l.valor_final_reais) || 0), borderColor: '#3D3D3D', borderWidth: 2, fill: false, pointRadius: 3, tension: 0.3, order: 1 },
    ],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: { legend: { position: 'top', labels: { font: { family: 'Nunito' } } } },
  scales: { y: { stacked: true }, x: { stacked: true } },
}

// ---------------------------------------------------------------- histórico
const colunasHistorico = [
  { key: 'detalhes', label: 'Detalhes', align: 'center' },
  { key: 'mes', label: 'Mês' },
  { key: 'geracao', label: 'Geração', numeric: true },
  { key: 'guardado', label: 'Reserva Acumulada (kWh)', numeric: true },
  { key: 'creditado', label: 'Creditado', numeric: true },
  { key: 'pago', label: 'Valor pago', numeric: true },
  { key: 'estado', label: 'Estado' },
  { key: 'acao', label: 'Ação', align: 'right' },
]

// Expansão por linha (F8): auditoria READ-ONLY com a MESMA composição do preview
// (4 termos / geração líquida / FIFO / expiração) relida dos inputs salvos.
const mesesExpandidos = ref(new Set())

// Relê o preview do mês a partir dos inputs persistidos (modo leitura).
async function carregarPreviewHistorico(chave) {
  if (!usina.value?.usi_id) return
  const salvo = inputsSalvos.value?.[chave] || {}
  const mesIdx = CHAVES_MESES.indexOf(chave) + 1
  previewHistorico[chave] = { dados: null, loading: true, error: '' }
  try {
    const dados = await api.obterPreview(usina.value.usi_id, ano.value, mesIdx, {
      geracaoBrutaKwh: numeroOuNulo(dadosGeracaoRealMensal.value?.[chave]),
      faturaEnergia: numeroOuNulo(salvo.fatura_energia),
      adicionalCuo: null,
      consumo: numeroOuNulo(salvo.consumo),
    })
    previewHistorico[chave] = { dados, loading: false, error: '' }
  } catch (error) {
    previewHistorico[chave] = {
      dados: null,
      loading: false,
      error: 'Não foi possível carregar o detalhamento. Tente de novo.',
    }
  }
}

function alternarDetalhes(chave) {
  const novo = new Set(mesesExpandidos.value)
  if (novo.has(chave)) {
    novo.delete(chave)
  } else {
    novo.add(chave)
    // Carrega sob demanda (uma vez) ao abrir a linha.
    if (!previewHistorico[chave]) carregarPreviewHistorico(chave)
  }
  mesesExpandidos.value = novo
}

const linhasHistorico = computed(() =>
  CHAVES_MESES.filter((chave) => temDadosMes(chave)).map((chave) => {
    const inputs = inputsSalvos.value?.[chave] || {}
    const consumoSalvo = numeroOuNulo(inputs.consumo)
    const faturaSalva = numeroOuNulo(inputs.fatura_energia)
    return {
      chave,
      mes: MESES[chave],
      geracao: formatKwh(dadosGeracaoRealMensal.value?.[chave]),
      guardado: formatKwh(dadosFaturamentoAnual.value?.valor_acumulado_reserva?.[chave]),
      creditado: formatReais(dadosFaturamentoAnual.value?.creditos_distribuidos?.[chave]),
      pago: formatReais(dadosFaturamentoAnual.value?.faturamento_usina?.[chave]),
      consumoInput: consumoSalvo !== null ? formatKwh(consumoSalvo) : '—',
      faturaInput: faturaSalva !== null ? formatReais(faturaSalva) : '—',
      _detalhes: mesesExpandidos.value.has(chave),
      revertivel:
        Boolean(ultimoRevertivel.value) &&
        ultimoRevertivel.value.mes_nome === chave &&
        Number(ultimoRevertivel.value.ano) === ano.value,
    }
  })
)

function formatarDataHora(isoString) {
  if (!isoString) return '—'
  const data = new Date(isoString)
  if (Number.isNaN(data.getTime())) return String(isoString)
  return data.toLocaleString('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'America/Sao_Paulo',
  })
}

const colunasTrilha = [
  { key: 'competencia', label: 'Competência' },
  { key: 'lancadoPor', label: 'Lançado por' },
  { key: 'lancadoEm', label: 'Lançado em' },
  { key: 'revertidoPor', label: 'Revertido por' },
  { key: 'revertidoEm', label: 'Revertido em' },
]

const linhasTrilha = computed(() =>
  historicoEstorno.value.map((h) => ({
    competencia: `${ucfirst(h.mes_nome)}/${h.ano}`,
    lancadoPor: h.lancado_por || '—',
    lancadoEm: formatarDataHora(h.created_at),
    revertidoPor: h.revertido_por || '—',
    revertidoEm: h.revertido_em ? formatarDataHora(h.revertido_em) : '—',
  }))
)

// ---------------------------------------------------------------- carregamento
async function carregarAno() {
  if (!usina.value?.usi_id) return
  carregandoAno.value = true
  erroAno.value = ''
  mesesExpandidos.value = new Set()
  // Limpa a auditoria read-only em cache ao trocar ano/usina.
  for (const k of Object.keys(previewHistorico)) delete previewHistorico[k]
  try {
    const usiId = usina.value.usi_id
    const [faturamento, geracao, revertivel, historico, inputs, projecao] = await Promise.all([
      api.obterFaturamentoAnual(usiId, ano.value),
      api.obterGeracaoReal(usiId, ano.value),
      api.obterUltimoRevertivel(usiId),
      api.obterHistoricoEstorno(usiId),
      api.obterInputsSalvos(usiId, ano.value),
      api.obterProjecao(usiId, ano.value),
    ])
    dadosFaturamentoAnual.value = faturamento
    dadosGeracaoRealMensal.value = geracao
    ultimoRevertivel.value = revertivel
    historicoEstorno.value = historico
    inputsSalvos.value = inputs
    projecaoAnual.value = projecao
  } catch (error) {
    dadosFaturamentoAnual.value = null
    dadosGeracaoRealMensal.value = {}
    ultimoRevertivel.value = null
    historicoEstorno.value = []
    inputsSalvos.value = {}
    projecaoAnual.value = []
    erroAno.value = 'Não foi possível carregar os dados do ano. Verifique a conexão e tente de novo.'
  } finally {
    carregandoAno.value = false
  }
}

async function carregarUsina(usiId) {
  carregandoPagina.value = true
  erroPagina.value = ''
  try {
    usina.value = await api.obterUsina(usiId)
    await carregarAno()
  } catch (error) {
    usina.value = null
    erroPagina.value = 'Não foi possível carregar a usina. Verifique a conexão e tente de novo.'
  } finally {
    carregandoPagina.value = false
  }
}

// ---------------------------------------------------------------- rota (F5)
function navegar(usiId, novoAno = ano.value, novoMes = mes.value) {
  router.push(`/faturar/${usiId}/${novoAno}/${novoMes}`)
}

function trocarUsina() {
  router.push('/usinas')
}

watch(
  () => route.params,
  async (params, anteriores) => {
    if (route.name !== 'faturar') return
    const idParam = params.usinaId ? Number(params.usinaId) : null
    // Sem :usinaId a rota redireciona para /usinas (beforeEnter); nada a fazer aqui.
    if (!idParam) return

    const anoParam = Number(params.ano)
    const mesParam = String(params.mes || '')
    const anoValido = Number.isInteger(anoParam) && anoParam >= MIN_ANO && anoParam <= maxAno
    const mesValido = CHAVES_MESES.includes(mesParam)

    if (!anoValido || !mesValido) {
      router.replace(
        `/faturar/${idParam}/${anoValido ? anoParam : anoAtual}/${mesValido ? mesParam : chaveMesAtual}`
      )
      return
    }

    const anoMudou = ano.value !== anoParam || anteriores?.ano !== params.ano
    ano.value = anoParam
    mes.value = mesParam
    // Trocar de competência fecha qualquer modal de apuração aberto.
    apuracaoModalAberto.value = false

    if (!usina.value || Number(usina.value.usi_id) !== idParam) {
      await carregarUsina(idParam)
    } else if (anoMudou && anteriores) {
      await carregarAno()
    }
  },
  { immediate: true }
)

// ---------------------------------------------------------------- apuração (modal)
// Mês alvo do modal: por padrão a competência selecionada; "Apurar" por linha do
// histórico abre o modal já na competência daquela linha.
const apuracaoMes = computed(() => apuracaoMesAlvo.value ?? mes.value)
const apuracaoMesFaturado = computed(() => temDadosMes(apuracaoMes.value))

const apuracaoGeracaoCadastrada = computed(() =>
  numeroOuNulo(dadosGeracaoRealMensal.value?.[apuracaoMes.value])
)
const apuracaoInputsSalvos = computed(() => inputsSalvos.value?.[apuracaoMes.value] ?? null)

// Abre o modal na competência selecionada (botão do card Apuração / refaturar).
function abrirApuracao() {
  apuracaoMesAlvo.value = mes.value
  apuracaoModalAberto.value = true
}

function fecharApuracao() {
  apuracaoModalAberto.value = false
  apuracaoMesAlvo.value = null
}

// Confirmado no modal: recarrega o ano e fecha.
async function aoConfirmarApuracao() {
  apuracaoModalAberto.value = false
  apuracaoMesAlvo.value = null
  await carregarAno()
}

// ---------------------------------------------------------------- ações
async function confirmarEstorno(chaveMes) {
  const label = `${MESES[chaveMes]}/${ano.value}`
  const resultado = await swal.fire({
    icon: 'warning',
    title: `Reverter ${label}?`,
    html:
      `O lançamento de <strong>${label}</strong> será desfeito:<br>` +
      'o valor faturado, o crédito distribuído e a reserva do mês voltam ao estado anterior ' +
      'e o PDF gerado é descartado.<br>Depois da reversão o mês pode ser faturado novamente.',
    showCancelButton: true,
    confirmButtonText: 'Sim, reverter',
    cancelButtonText: 'Cancelar',
  })
  if (!resultado.isConfirmed) return

  const mesIdx = CHAVES_MESES.indexOf(chaveMes) + 1
  isRevertendo.value = true
  try {
    await api.estornarMes(usina.value.usi_id, ano.value, mesIdx)
    swal.fire({
      icon: 'success',
      title: 'Lançamento revertido',
      text: `${label} voltou a ficar pendente e pode ser faturado novamente.`,
    })
    await carregarAno()
  } catch (error) {
    const mensagem = error.response?.data?.error || 'Não foi possível reverter o lançamento.'
    swal.fire({ icon: 'error', title: 'Erro ao reverter', text: mensagem })
  } finally {
    isRevertendo.value = false
  }
}

// PDF do mês JÁ faturado (card LancamentoReadonly): usa os inputs persistidos.
async function baixarPdf() {
  const resultado = await swal.fire({
    title: `Baixar PDF — ${competenciaLabel.value}`,
    input: 'textarea',
    inputLabel: 'Observações (opcional)',
    inputPlaceholder: 'Texto exibido no PDF da fatura',
    showCancelButton: true,
    confirmButtonText: 'Gerar PDF',
    cancelButtonText: 'Cancelar',
  })
  if (!resultado.isConfirmed) return
  const observacoes = resultado.value || ''
  const faturaSalva = numeroOuNulo(inputsSalvos.value?.[mes.value]?.fatura_energia)

  try {
    swal.fire({
      title: 'Gerando PDF...',
      html: 'Aguarde enquanto preparamos o documento.',
      allowOutsideClick: false,
      didOpen: () => swal.showLoading(),
    })

    const blob = await api.gerarPdfUsina(usina.value.usi_id, {
      observacoes,
      mes: mesIndex.value,
      ano: ano.value,
      fatura: faturaSalva ?? 0,
      adicionalCuo: null,
    })

    swal.close()

    const url = window.URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `fatura ${usina.value.cliente?.nome} - ${usina.value.usi_id}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    swal.close()
    swal.fire({
      icon: 'error',
      title: 'Erro ao gerar PDF',
      text: 'Não foi possível gerar o PDF. Tente de novo.',
    })
  }
}
</script>

<template>
  <div class="workspace">
    <!-- Erro/carregamento da página inteira (usina não resolvida) -->
    <div v-if="erroPagina" class="workspace__erro" role="alert">
      <p>{{ erroPagina }}</p>
      <BaseButton variant="secondary" size="sm" @click="carregarUsina(usinaIdRota)">
        Tentar de novo
      </BaseButton>
    </div>
    <p v-else-if="carregandoPagina && !usina" class="workspace__carregando">Carregando usina…</p>

    <template v-else-if="usina">
      <!-- ============ 1) Hero da usina ============ -->
      <header class="hero">
        <div class="hero__identidade">
          <span class="hero__eyebrow">Usina parceira</span>
          <h1 class="hero__nome">{{ nomeCliente }}</h1>
          <p class="hero__subtitulo">{{ subtituloUsina }}</p>
        </div>

        <div class="hero__lado">
          <dl class="hero__chips">
            <div v-for="chip in chipsContrato" :key="chip.label" class="hero__chip">
              <dt class="hero__chip-label">{{ chip.label }}</dt>
              <dd class="hero__chip-valor">{{ chip.valor }}</dd>
            </div>
          </dl>
          <BaseButton variant="ghost" size="sm" @click="trocarUsina">
            <svg
              width="18" height="18" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
              stroke-linejoin="round" aria-hidden="true"
            >
              <path d="M16 3h5v5" />
              <path d="M21 3 9 15" />
              <path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6" />
            </svg>
            Trocar usina
          </BaseButton>
        </div>
      </header>

      <!-- ============ 2) Faixa de stat-cards ============ -->
      <div class="stat-grid">
        <div class="stat-grid__card stat-grid__card--destaque">
          <StatValue
            label="Reserva total acumulada"
            :loading="carregandoAno"
            :value="reservaTotal == null ? '—' : formatKwh(reservaTotal)"
          />
        </div>
        <div class="stat-grid__card">
          <StatValue
            label="Último faturamento"
            :loading="carregandoAno"
            :value="ultimoFaturamento ? formatReais(ultimoFaturamento.valor) : '—'"
            :hint="ultimoFaturamento ? ultimoFaturamento.competencia : 'Nenhum lançamento no ano'"
          />
        </div>
        <div class="stat-grid__card">
          <StatValue
            label="Créditos distribuídos no ano"
            :loading="carregandoAno"
            :value="formatReais(creditosNoAno)"
          />
        </div>
        <div class="stat-grid__card">
          <StatValue
            label="Meses faturados"
            :loading="carregandoAno"
            :value="`${mesesFaturados} / 12`"
          />
        </div>
      </div>

      <!-- ============ 3) Card de Apuração ============ -->
      <SectionCard eyebrow="Apuração" :title="`Apuração de ${competenciaLabel}`">
        <CompetenciaSelector
          :ano="ano"
          :mes="mes"
          :estados-por-mes="estadosPorMes"
          :min-ano="MIN_ANO"
          @update:ano="(novoAno) => navegar(usina.usi_id, novoAno, mes)"
          @update:mes="(novoMes) => navegar(usina.usi_id, ano, novoMes)"
        />

        <div v-if="erroAno" class="workspace__erro workspace__erro--inline" role="alert">
          <p>{{ erroAno }}</p>
          <BaseButton variant="secondary" size="sm" @click="carregarAno">Tentar de novo</BaseButton>
        </div>

        <p v-else-if="carregandoAno" class="workspace__carregando">Carregando dados do mês…</p>

        <template v-else>
          <!-- Estado JÁ FATURADO (leitura): resumo + refaturar abre o modal. -->
          <LancamentoReadonly
            v-if="mesFaturado"
            class="apuracao__lancamento"
            :dados="dadosLancamento"
            :competencia-label="competenciaLabel"
            :revertivel="mesRevertivel"
            @refaturar="abrirApuracao"
            @reverter="confirmarEstorno(mes)"
            @pdf="baixarPdf"
          />

          <!-- Estado PENDENTE: a entrada de dados acontece no modal de apuração. -->
          <div v-else class="apuracao__pendente">
            <p class="apuracao__pendente-texto">
              {{ competenciaLabel }} ainda não foi faturado. Abra a conferência para
              informar geração, consumo e fatura, ver a simulação e confirmar.
            </p>
            <BaseButton variant="primary" glow @click="abrirApuracao">
              Apurar {{ competenciaLabel }}
            </BaseButton>
          </div>
        </template>
      </SectionCard>

      <!-- ============ 4) Card Histórico ============ -->
      <SectionCard eyebrow="Histórico" :title="`Lançamentos de ${ano}`">
        <p class="workspace__explicacao">
          Geração do mês (bruta, em kWh). A competência segue a seleção de ano acima.
        </p>

        <p v-if="carregandoAno" class="workspace__carregando">Carregando lançamentos…</p>
        <template v-else>
          <DataTable :columns="colunasHistorico" :rows="linhasHistorico">
            <template #cell-detalhes="{ row }">
              <button
                type="button"
                class="historico__detalhes-toggle"
                :aria-expanded="row._detalhes ? 'true' : 'false'"
                :aria-controls="`detalhes-${row.chave}`"
                :aria-label="`Detalhes do lançamento de ${row.mes}`"
                @click="alternarDetalhes(row.chave)"
              >
                <svg
                  class="historico__chevron"
                  :class="{ 'historico__chevron--aberto': row._detalhes }"
                  width="18" height="18" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                  stroke-linejoin="round" aria-hidden="true"
                >
                  <polyline points="9 6 15 12 9 18" />
                </svg>
              </button>
            </template>
            <!-- Auditoria do mês em modo LEITURA (blueprint §5): inputs salvos +
                 a MESMA composição do preview (4 termos / FIFO / expiração)
                 relida via obterPreview(inputsSalvos). -->
            <template #row-details="{ row }">
              <div :id="`detalhes-${row.chave}`" class="historico__detalhes">
                <p class="historico__detalhes-titulo">Inputs do lançamento de {{ row.mes }}/{{ ano }}</p>
                <dl class="historico__detalhes-pares">
                  <div class="historico__detalhes-par">
                    <dt>Geração bruta</dt>
                    <dd>{{ row.geracao }}</dd>
                  </div>
                  <div class="historico__detalhes-par">
                    <dt>Consumo da usina</dt>
                    <dd>{{ row.consumoInput }}</dd>
                  </div>
                  <div class="historico__detalhes-par">
                    <dt>Fatura de energia</dt>
                    <dd>{{ row.faturaInput }}</dd>
                  </div>
                </dl>

                <PreviewPanel
                  class="historico__detalhes-preview"
                  salvo
                  :preview="previewHistorico[row.chave]?.dados ?? null"
                  :loading="previewHistorico[row.chave]?.loading ?? false"
                  :error="previewHistorico[row.chave]?.error ?? ''"
                  @retry="carregarPreviewHistorico(row.chave)"
                />

                <AuditoriaAccordion
                  class="historico__detalhes-auditoria"
                  :preview="previewHistorico[row.chave]?.dados ?? null"
                  :parametros="montarParametros(previewHistorico[row.chave]?.dados ?? null)"
                />
              </div>
            </template>
            <template #cell-estado>
              <BaseBadge variant="success" dot>Faturado</BaseBadge>
            </template>
            <template #cell-acao="{ row }">
              <BaseButton
                v-if="row.revertivel"
                variant="danger-soft"
                size="sm"
                :loading="isRevertendo"
                @click="confirmarEstorno(row.chave)"
              >
                Reverter
              </BaseButton>
              <span
                v-else
                class="historico__acao-bloqueada"
                title="Apenas o último lançamento pode ser revertido"
                aria-label="Bloqueado: apenas o último lançamento pode ser revertido"
              >
                <svg
                  width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                  stroke-linejoin="round" aria-hidden="true"
                >
                  <rect x="3" y="11" width="18" height="11" rx="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
              </span>
            </template>
            <template #empty>Nenhum lançamento registrado em {{ ano }}.</template>
          </DataTable>

          <details v-if="linhasTrilha.length" class="historico__trilha">
            <summary class="historico__trilha-summary">
              <svg
                class="historico__trilha-chevron"
                width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                stroke-linejoin="round" aria-hidden="true"
              >
                <polyline points="9 6 15 12 9 18" />
              </svg>
              Trilha de auditoria (lançamentos e reversões)
            </summary>
            <div class="historico__trilha-conteudo">
              <DataTable :columns="colunasTrilha" :rows="linhasTrilha" />
            </div>
          </details>
        </template>
      </SectionCard>

      <!-- ============ 5) Card Expectativa anual ============ -->
      <SectionCard eyebrow="Expectativa" :title="`Expectativa anual de ${ano}`">
        <p class="workspace__explicacao">
          Previsão baseada na <strong>geração projetada</strong> da usina. O CUO considera
          apenas o Fio B — a fatura real de cada mês entra na apuração mensal.
        </p>

        <p v-if="carregandoAno" class="workspace__carregando">Carregando projeção…</p>
        <template v-else>
          <div v-if="resumoExpectativa" class="expectativa__resumo">
            <StatValue
              label="Total projetado no ano"
              :value="formatReais(resumoExpectativa.total)"
            />
            <StatValue
              v-if="resumoExpectativa.melhor"
              label="Melhor mês"
              tone="success"
              :value="formatReais(resumoExpectativa.melhor.valor)"
              :hint="resumoExpectativa.melhor.mes"
            />
            <StatValue
              v-if="resumoExpectativa.pior"
              label="Pior mês"
              tone="danger"
              :value="formatReais(resumoExpectativa.pior.valor)"
              :hint="resumoExpectativa.pior.mes"
            />
          </div>

          <div class="expectativa__grid">
            <div class="expectativa__tabela">
              <DataTable class="expectativa__data-table" :columns="colunasExpectativa" :rows="linhasExpectativa">
                <template #cell-mes="{ row, value }">
                  <span
                    class="expectativa__mes"
                    :class="{ 'expectativa__mes--corrente': row.ehMesCorrente }"
                  >{{ value }}</span>
                </template>
                <template #cell-geracao="{ row, value }">
                  <span :class="{ 'expectativa__menor': row.geracaoEhMenor }">{{ value }}</span>
                </template>
                <template #empty>Sem projeção disponível para {{ ano }}.</template>
              </DataTable>
            </div>

            <div v-if="chartData" class="expectativa__grafico">
              <Bar :data="chartData" :options="chartOptions" />
            </div>
          </div>
        </template>
      </SectionCard>
    </template>

    <!-- Modal de Apuração (blueprint §4): inputs → preview → auditoria →
         confirmar/refaturar/PDF. Abre na competência selecionada ou na linha
         pendente; ao confirmar recarrega o ano e fecha. -->
    <ApurarMesModal
      v-if="usina"
      :aberto="apuracaoModalAberto"
      :usina="usina"
      :ano="ano"
      :mes="apuracaoMes"
      :geracao-cadastrada="apuracaoGeracaoCadastrada"
      :inputs-salvos="apuracaoInputsSalvos"
      :modo-refaturar="apuracaoMesFaturado"
      :parametros-auditoria="parametrosAuditoria"
      @confirmado="aoConfirmarApuracao"
      @fechar="fecharApuracao"
    />
  </div>
</template>

<style scoped>
.workspace {
  max-width: var(--max-w-app);
  margin: 0 auto;
  padding: var(--space-6) var(--space-5) var(--space-10);
  display: flex;
  flex-direction: column;
  gap: var(--space-7);
  font-family: var(--font-body);
  color: var(--color-ink);
}

/* ---------- Estados de carga/erro ---------- */
.workspace__carregando {
  margin: 0;
  padding: var(--space-4) 0;
  font-size: var(--fs-sm);
  color: var(--color-slate);
}

.workspace__erro {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
  background: var(--color-danger-soft);
  border-radius: var(--radius-md);
}

.workspace__erro--inline {
  margin-top: var(--space-5);
}

.workspace__erro p {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--color-danger);
}

.workspace__explicacao {
  margin: 0 0 var(--space-4);
  font-size: var(--fs-sm);
  color: var(--color-graphite);
}

/* ---------- 1) Hero ---------- */
.hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-5) var(--space-6);
  background: var(--color-paper);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-sm);
  padding: var(--space-6);
}

.hero__identidade {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.hero__eyebrow {
  font-family: var(--font-body);
  font-size: var(--fs-eyebrow);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--color-primary-deep);
}

.hero__nome {
  margin: 0;
  font-family: var(--font-display);
  font-size: var(--fs-h3, 24px);
  font-weight: 800;
  line-height: var(--lh-snug);
  letter-spacing: -0.01em;
  color: var(--color-ink);
}

.hero__subtitulo {
  margin: 0;
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--fs-sm);
  color: var(--color-slate);
}

.hero__lado {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--space-3);
}

.hero__chips {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--space-2);
  margin: 0;
}

.hero__chip {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--space-1) var(--space-3);
  background: var(--color-linen);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  min-width: 96px;
}

.hero__chip-label {
  font-family: var(--font-body);
  font-size: 11px;
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--color-slate);
  white-space: nowrap;
}

.hero__chip-valor {
  margin: 0;
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
  white-space: nowrap;
}

/* ---------- 2) Stat-cards ---------- */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--space-5);
}

.stat-grid__card {
  background: var(--color-paper);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--space-5);
}

/* Único card colorido da tela (grad-sun com parcimônia). */
.stat-grid__card--destaque {
  background: var(--grad-sun);
}

.stat-grid__card--destaque :deep(.stat-value__label),
.stat-grid__card--destaque :deep(.stat-value__valor) {
  color: var(--color-ink);
}

/* ---------- 3) Apuração ---------- */
.apuracao__lancamento {
  margin-top: var(--space-5);
}

.apuracao__pendente {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-4);
  margin-top: var(--space-5);
  padding: var(--space-5);
  background: var(--color-linen);
  border-radius: var(--radius-lg);
}

.apuracao__pendente-texto {
  margin: 0;
  max-width: 52ch;
  font-size: var(--fs-sm);
  color: var(--color-graphite);
}

/* ---------- 4) Histórico ---------- */
.historico__detalhes-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  border-radius: var(--radius-sm);
  color: var(--color-slate);
  cursor: pointer;
}

.historico__detalhes-toggle:hover {
  color: var(--color-primary-deep);
}

.historico__detalhes-toggle:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.historico__chevron {
  transition: transform var(--dur-hover) var(--ease-standard);
}

.historico__chevron--aberto {
  transform: rotate(90deg);
}

.historico__detalhes {
  padding: var(--space-4);
  background: var(--color-paper);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.historico__detalhes-titulo {
  margin: 0 0 var(--space-3);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-graphite);
}

.historico__detalhes-pares {
  margin: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--space-4);
}

.historico__detalhes-par {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.historico__detalhes-par dt {
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-slate);
}

.historico__detalhes-par dd {
  margin: 0;
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--fs-body);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
}

.historico__acao-bloqueada {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  color: var(--color-smoke);
  cursor: help;
}

.historico__trilha {
  margin-top: var(--space-5);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
}

.historico__trilha-summary {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
  cursor: pointer;
  list-style: none;
  border-radius: var(--radius-md);
}

.historico__trilha-summary::-webkit-details-marker {
  display: none;
}

.historico__trilha-summary:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.historico__trilha-chevron {
  flex: none;
  color: var(--color-slate);
  transition: transform var(--dur-hover) var(--ease-standard);
}

.historico__trilha[open] > .historico__trilha-summary .historico__trilha-chevron {
  transform: rotate(90deg);
}

.historico__trilha-conteudo {
  padding: 0 var(--space-4) var(--space-4);
}

/* ---------- 5) Expectativa ---------- */
.expectativa__resumo {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-5);
  margin-bottom: var(--space-6);
  padding-bottom: var(--space-5);
  border-bottom: 1px solid var(--color-mist);
}

/* Tabela de 12 meses × 8 colunas e gráfico empilhados em largura total:
   lado a lado, a tabela não cabia em 50% e gerava scroll horizontal. */
.expectativa__grid {
  display: flex;
  flex-direction: column;
  gap: var(--space-7);
  align-items: stretch;
}

.expectativa__tabela {
  min-width: 0;
}

/* Tabela densa (12×8): colunas regulares para os números formarem uma grade
   alinhada (sem vãos irregulares), em vez de cada coluna esticar sozinha. */
.expectativa__data-table :deep(.data-table) {
  table-layout: fixed;
}

.expectativa__data-table :deep(thead th),
.expectativa__data-table :deep(tbody td) {
  padding-left: var(--space-2);
  padding-right: var(--space-2);
  font-size: var(--fs-xs);
}

/* 1ª coluna (Mês) estreita; as 7 numéricas dividem o resto igualmente. */
.expectativa__data-table :deep(thead th:first-child),
.expectativa__data-table :deep(tbody td:first-child) {
  width: 11%;
}

.expectativa__data-table :deep(.num) {
  font-size: var(--fs-xs);
}

.expectativa__grafico {
  width: 100%;
  height: 360px;
}

.expectativa__mes--corrente {
  display: inline-block;
  font-weight: var(--fw-bold);
  border-left: 2px solid var(--color-primary);
  padding-left: var(--space-2);
  margin-left: calc(-1 * var(--space-2));
}

.expectativa__menor {
  color: var(--color-danger);
  font-weight: var(--fw-bold);
}

@media (max-width: 640px) {
  /* Em telas estreitas o gráfico fica mais baixo para não espremer as barras. */
  .expectativa__grafico {
    height: 300px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .historico__chevron,
  .historico__trilha-chevron {
    transition: none;
  }
}
</style>
