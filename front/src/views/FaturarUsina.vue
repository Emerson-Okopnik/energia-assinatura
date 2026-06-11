<script setup>
// Página "Faturar Usina" — workspace master-detail em 2 níveis.
// Nível 1: /faturar — seleção de usina.
// Nível 2: /faturar/:usinaId/:ano/:mes — apuração, expectativa anual e histórico.
// O frontend NÃO calcula nada: lê preview/projeção do backend (faturamentoApi.js).
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
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
import BaseField from '../components/base/BaseField.vue'
import NumberInput from '../components/base/NumberInput.vue'
import DataTable from '../components/base/DataTable.vue'
import UsinaCombobox from '../components/faturamento/UsinaCombobox.vue'
import ContextHeader from '../components/faturamento/ContextHeader.vue'
import CompetenciaSelector from '../components/faturamento/CompetenciaSelector.vue'
import PreviewPanel from '../components/faturamento/PreviewPanel.vue'
import AuditoriaAccordion from '../components/faturamento/AuditoriaAccordion.vue'
import ConfirmRefaturarDialog from '../components/faturamento/ConfirmRefaturarDialog.vue'
import LancamentoReadonly from '../components/faturamento/LancamentoReadonly.vue'

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
const usinas = ref([])
const carregandoUsinas = ref(false)
const erroUsinas = ref('')

// Estado da competência corrente por usina (nível 1): 'faturado' | 'pendente'.
// Limitação conhecida: GET /usina não traz dados do mês, então o estado é
// buscado em lote com os mesmos endpoints anuais, com concorrência limitada.
const estadosMesUsinas = ref({})
let estadosUsinasSeq = 0

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

// Formulário de apuração (Number|null — null = não preenchido, nunca 0 automático)
const mesGeracao = ref(null)
const consumoUsinaMes = ref(null)
const faturaEnergia = ref(null)
const adicionalCuo = ref(null)
const tocado = reactive({ consumo: false, fatura: false })

const previewMes = ref(null)
const previewLoading = ref(false)
const previewError = ref('')
let previewTimer = null
let previewSeq = 0

const aba = ref('apuracao')
const modoEdicao = ref(false)
const dialogRefaturarAberto = ref(false)
const isSalvando = ref(false)
const isRevertendo = ref(false)
const observacoes = ref('')

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

const mesFaturado = computed(() => temDadosMes(mes.value))
const mostrarFormulario = computed(() => !mesFaturado.value || modoEdicao.value)

const geracaoVemDoCadastro = computed(() => {
  const valor = dadosGeracaoRealMensal.value?.[mes.value]
  return valor !== undefined && valor !== null && Number(valor) > 0
})

// Validação inline (F7): erro aparece após blur do campo ou tentativa de salvar.
const erroConsumo = computed(() =>
  tocado.consumo && consumoUsinaMes.value === null
    ? 'Informe o consumo da usina no mês.'
    : ''
)
const erroFatura = computed(() =>
  tocado.fatura && faturaEnergia.value === null
    ? 'Informe a fatura de energia do mês.'
    : ''
)
const formValido = computed(
  () => consumoUsinaMes.value !== null && faturaEnergia.value !== null
)

const parametrosAuditoria = computed(() => ({
  tarifa: usina.value?.comercializacao?.valor_kwh ?? null,
  mediaKwh: usina.value?.dado_geracao?.media ?? null,
  menorGeracaoKwh: usina.value?.dado_geracao?.menor_geracao ?? null,
  fioB: fioB.value,
  percentualLei: percentualLei.value,
  rede: previewMes.value?.geracao?.rede ?? null,
  descontoRedeKwh: previewMes.value?.geracao?.desconto_rede_kwh ?? null,
}))

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

const novoLancamento = computed(() => ({
  geracaoKwh: mesGeracao.value,
  consumoKwh: consumoUsinaMes.value,
  faturaReais: faturaEnergia.value,
  adicionalReais: adicionalCuo.value,
  valorFinalReais: numeroOuNulo(previewMes.value?.termos?.valor_final_reais),
}))

const mesRevertivel = computed(
  () =>
    Boolean(ultimoRevertivel.value) &&
    ultimoRevertivel.value.mes_nome === mes.value &&
    Number(ultimoRevertivel.value.ano) === ano.value
)

// ---------------------------------------------------------------- expectativa
const menorGeracao = computed(() => Number(usina.value?.dado_geracao?.menor_geracao) || 0)

const colunasExpectativa = [
  { key: 'mes', label: 'Mês' },
  { key: 'geracao', label: 'Geração', numeric: true },
  { key: 'media', label: 'Média geração', numeric: true },
  { key: 'fixo', label: 'Fixo', numeric: true },
  { key: 'injetado', label: 'Injetado', numeric: true },
  { key: 'creditado', label: 'Creditado', numeric: true },
  { key: 'cuo', label: 'CUO', numeric: true },
  { key: 'valorFinal', label: 'Valor final a receber', numeric: true },
]

function ucfirst(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

const linhasExpectativa = computed(() =>
  projecaoAnual.value.map((linha) => ({
    mes: ucfirst(linha.mes_nome),
    geracao: formatKwh(linha.geracao_kwh),
    geracaoEhMenor: Number(linha.geracao_kwh) === menorGeracao.value && menorGeracao.value > 0,
    media: formatKwh(linha.media_kwh),
    fixo: formatReais(linha.fixo_reais),
    injetado: formatReais(linha.injetado_reais),
    creditado: formatReais(linha.creditado_reais),
    cuo: formatReais(linha.cuo_reais),
    valorFinal: formatReais(linha.valor_final_reais),
  }))
)

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

// Expansão por linha (F8): mostra os inputs de cada lançamento.
const mesesExpandidos = ref(new Set())

function alternarDetalhes(chave) {
  const novo = new Set(mesesExpandidos.value)
  if (novo.has(chave)) novo.delete(chave)
  else novo.add(chave)
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
async function carregarUsinas() {
  carregandoUsinas.value = true
  erroUsinas.value = ''
  try {
    usinas.value = await api.listarUsinas()
    if (!usinaIdRota.value) carregarEstadosUsinas()
  } catch (error) {
    erroUsinas.value = 'Não foi possível carregar a lista de usinas. Verifique a conexão e tente de novo.'
  } finally {
    carregandoUsinas.value = false
  }
}

// Badge "Mês pendente/faturado" na fila de trabalho do nível 1 (dores 1 e 2).
// Usa os mesmos endpoints anuais já existentes (sem mudança no backend) e a
// mesma regra de temDadosMes() para a competência corrente.
async function carregarEstadosUsinas() {
  const lista = usinas.value
  if (!lista.length) return
  const seq = ++estadosUsinasSeq
  const resultado = {}
  const fila = [...lista]
  const CONCORRENCIA = 4

  async function worker() {
    while (fila.length) {
      if (seq !== estadosUsinasSeq) return
      const u = fila.shift()
      try {
        const [faturamento, geracao] = await Promise.all([
          api.obterFaturamentoAnual(u.usi_id, anoAtual),
          api.obterGeracaoReal(u.usi_id, anoAtual),
        ])
        const tem =
          Number(geracao?.[chaveMesAtual] || 0) > 0 ||
          Number(faturamento?.faturamento_usina?.[chaveMesAtual] || 0) > 0 ||
          Number(faturamento?.valor_acumulado_reserva?.[chaveMesAtual] || 0) > 0 ||
          Number(faturamento?.creditos_distribuidos?.[chaveMesAtual] || 0) > 0
        resultado[u.usi_id] = tem ? 'faturado' : 'pendente'
        if (seq === estadosUsinasSeq) estadosMesUsinas.value = { ...resultado }
      } catch (error) {
        // Sem estado para esta usina: a opção fica sem badge (falha silenciosa).
      }
    }
  }

  await Promise.all(Array.from({ length: Math.min(CONCORRENCIA, fila.length) }, worker))
}

async function carregarAno() {
  if (!usina.value?.usi_id) return
  carregandoAno.value = true
  erroAno.value = ''
  mesesExpandidos.value = new Set()
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

// Reidrata o formulário com o que existe salvo para a competência (F1/F12).
function reidratarFormulario() {
  const geracaoCadastrada = dadosGeracaoRealMensal.value?.[mes.value]
  mesGeracao.value = numeroOuNulo(geracaoCadastrada)

  const salvo = inputsSalvos.value?.[mes.value] || {}
  consumoUsinaMes.value = numeroOuNulo(salvo.consumo)
  faturaEnergia.value = numeroOuNulo(salvo.fatura_energia)
  adicionalCuo.value = null

  tocado.consumo = false
  tocado.fatura = false
  previewMes.value = null
  previewError.value = ''
  agendarPreview()
}

// ---------------------------------------------------------------- rota (F5)
function navegar(usiId, novoAno = ano.value, novoMes = mes.value) {
  router.push(`/faturar/${usiId}/${novoAno}/${novoMes}`)
}

function selecionarUsina(usiId) {
  if (!usiId) return
  navegar(usiId, ano.value, mes.value)
}

function trocarUsina() {
  router.push('/faturar')
}

watch(
  () => route.params,
  async (params, anteriores) => {
    if (route.name !== 'faturar') return
    const idParam = params.usinaId ? Number(params.usinaId) : null

    if (!idParam) {
      usina.value = null
      modoEdicao.value = false
      // Recarrega o estado do mês corrente: pode ter mudado no nível 2.
      if (usinas.value.length) carregarEstadosUsinas()
      return
    }

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
    modoEdicao.value = false

    if (!usina.value || Number(usina.value.usi_id) !== idParam) {
      await carregarUsina(idParam)
    } else if (anoMudou && anteriores) {
      await carregarAno()
    }
    reidratarFormulario()
  },
  { immediate: true }
)

carregarUsinas()

// ---------------------------------------------------------------- preview
function agendarPreview() {
  if (previewTimer) clearTimeout(previewTimer)
  previewTimer = setTimeout(() => carregarPreview(), 300)
}

async function carregarPreview() {
  if (!usina.value?.usi_id || !mes.value || !ano.value) {
    previewMes.value = null
    return
  }
  // Sem nenhum input preenchido não há o que simular (F12: null ≠ 0).
  if (
    mesGeracao.value === null &&
    consumoUsinaMes.value === null &&
    faturaEnergia.value === null &&
    adicionalCuo.value === null
  ) {
    previewMes.value = null
    previewError.value = ''
    return
  }

  const seq = ++previewSeq
  previewLoading.value = true
  previewError.value = ''
  try {
    const dados = await api.obterPreview(usina.value.usi_id, ano.value, mesIndex.value, {
      geracaoBrutaKwh: mesGeracao.value,
      faturaEnergia: faturaEnergia.value,
      adicionalCuo: adicionalCuo.value,
      consumo: consumoUsinaMes.value,
    })
    if (seq !== previewSeq) return
    previewMes.value = dados
  } catch (error) {
    if (seq !== previewSeq) return
    previewMes.value = null
    previewError.value = 'Não foi possível calcular a simulação. Verifique a conexão e tente de novo.'
  } finally {
    if (seq === previewSeq) previewLoading.value = false
  }
}

watch([mesGeracao, consumoUsinaMes, faturaEnergia, adicionalCuo], () => {
  agendarPreview()
})

onBeforeUnmount(() => {
  if (previewTimer) clearTimeout(previewTimer)
})

// ---------------------------------------------------------------- ações
function iniciarRefaturamento() {
  modoEdicao.value = true
  reidratarFormulario()
}

function cancelarRefaturamento() {
  modoEdicao.value = false
  reidratarFormulario()
}

function aoClicarFaturar() {
  tocado.consumo = true
  tocado.fatura = true
  if (!formValido.value) return
  // Guard de sobrescrita (F1): mês já faturado SEMPRE passa pelo diff.
  if (mesFaturado.value) {
    dialogRefaturarAberto.value = true
    return
  }
  executarFaturamento()
}

async function executarFaturamento() {
  dialogRefaturarAberto.value = false
  if (!usina.value?.usi_id || !formValido.value) return

  isSalvando.value = true
  try {
    // 1) UPSERT do consumo do mês (não zera os outros 11 meses — F2).
    await api.salvarConsumoMes(usina.value.usi_id, ano.value, mesIndex.value, consumoUsinaMes.value)

    // 2) Lançamento: o backend calcula e persiste (payload só com inputs).
    await api.salvarCalculo(usina.value.usi_id, ano.value, mesIndex.value, {
      geracaoBrutaKwh: mesGeracao.value,
      consumo: consumoUsinaMes.value,
      faturaEnergia: faturaEnergia.value,
      adicionalCuo: adicionalCuo.value,
    })

    const valorFinal = previewMes.value?.termos?.valor_final_reais
    swal.fire({
      icon: 'success',
      title: 'Faturamento registrado',
      text:
        valorFinal != null
          ? `${competenciaLabel.value} faturado: ${formatReais(valorFinal)}`
          : `${competenciaLabel.value} faturado.`,
    })

    modoEdicao.value = false
    await carregarAno()
    reidratarFormulario()
  } catch (error) {
    const mensagem =
      error.response?.data?.error ||
      error.response?.data?.message ||
      'Não foi possível salvar o faturamento. Verifique os dados e tente de novo.'
    swal.fire({ icon: 'error', title: `Erro ao faturar ${competenciaLabel.value}`, text: mensagem })
  } finally {
    isSalvando.value = false
  }
}

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
    modoEdicao.value = false
    await carregarAno()
    reidratarFormulario()
  } catch (error) {
    const mensagem = error.response?.data?.error || 'Não foi possível reverter o lançamento.'
    swal.fire({ icon: 'error', title: 'Erro ao reverter', text: mensagem })
  } finally {
    isRevertendo.value = false
  }
}

async function baixarPdf() {
  // Observações entram no diálogo do PDF (deixou de ser campo solto na página).
  const resultado = await swal.fire({
    title: `Baixar PDF — ${competenciaLabel.value}`,
    input: 'textarea',
    inputLabel: 'Observações (opcional)',
    inputValue: observacoes.value,
    inputPlaceholder: 'Texto exibido no PDF da fatura',
    showCancelButton: true,
    confirmButtonText: 'Gerar PDF',
    cancelButtonText: 'Cancelar',
  })
  if (!resultado.isConfirmed) return
  observacoes.value = resultado.value || ''

  try {
    swal.fire({
      title: 'Gerando PDF...',
      html: 'Aguarde enquanto preparamos o documento.',
      allowOutsideClick: false,
      didOpen: () => swal.showLoading(),
    })

    const blob = await api.gerarPdfUsina(usina.value.usi_id, {
      observacoes: observacoes.value,
      mes: mesIndex.value,
      ano: ano.value,
      fatura: faturaEnergia.value ?? 0,
      adicionalCuo: adicionalCuo.value,
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

// ---------------------------------------------------------------- abas
const ABAS = [
  { id: 'apuracao', label: 'Apuração' },
  { id: 'expectativa', label: 'Expectativa anual' },
  { id: 'historico', label: 'Histórico' },
]

function aoNavegarAbas(evento) {
  const atual = ABAS.findIndex((a) => a.id === aba.value)
  if (evento.key === 'ArrowRight') {
    aba.value = ABAS[(atual + 1) % ABAS.length].id
  } else if (evento.key === 'ArrowLeft') {
    aba.value = ABAS[(atual - 1 + ABAS.length) % ABAS.length].id
  } else {
    return
  }
  evento.preventDefault()
  // Roving tabindex (WAI-ARIA): o foco acompanha a aba ativa.
  nextTick(() => document.getElementById(`tab-${aba.value}`)?.focus())
}
</script>

<template>
  <div class="faturar">
    <!-- ============================ Nível 1: seleção de usina ============================ -->
    <div v-if="!usinaIdRota" class="faturar__selecao">
      <SectionCard eyebrow="Faturamento" title="Selecione a usina para faturar">
        <p class="faturar__selecao-texto">
          Busque pelo nome do cliente. O estado de {{ MESES[chaveMesAtual] }} e a média de
          geração contratada aparecem ao lado de cada usina.
        </p>

        <div v-if="erroUsinas" class="faturar__erro" role="alert">
          <p>{{ erroUsinas }}</p>
          <BaseButton variant="secondary" size="sm" @click="carregarUsinas">Tentar de novo</BaseButton>
        </div>
        <p v-else-if="carregandoUsinas" class="faturar__carregando">Carregando usinas…</p>
        <UsinaCombobox
          v-else
          :usinas="usinas"
          :model-value="null"
          :estados-mes="estadosMesUsinas"
          :mes-label="MESES[chaveMesAtual]"
          @update:model-value="selecionarUsina"
        />
      </SectionCard>
    </div>

    <!-- ============================ Nível 2: workspace ============================ -->
    <template v-else>
      <ContextHeader
        :usina="usina"
        :fio-b="usina ? fioB : null"
        :percentual-lei="usina ? percentualLei : null"
        :reserva-total="reservaTotal"
        @trocar-usina="trocarUsina"
      />

      <div class="faturar__workspace">
        <div v-if="erroPagina" class="faturar__erro" role="alert">
          <p>{{ erroPagina }}</p>
          <BaseButton variant="secondary" size="sm" @click="carregarUsina(usinaIdRota)">
            Tentar de novo
          </BaseButton>
        </div>
        <p v-else-if="carregandoPagina" class="faturar__carregando">Carregando usina…</p>

        <template v-else-if="usina">
          <CompetenciaSelector
            :ano="ano"
            :mes="mes"
            :estados-por-mes="estadosPorMes"
            :min-ano="MIN_ANO"
            @update:ano="(novoAno) => navegar(usina.usi_id, novoAno, mes)"
            @update:mes="(novoMes) => navegar(usina.usi_id, ano, novoMes)"
          />

          <div v-if="erroAno" class="faturar__erro" role="alert">
            <p>{{ erroAno }}</p>
            <BaseButton variant="secondary" size="sm" @click="carregarAno">Tentar de novo</BaseButton>
          </div>

          <!-- Abas -->
          <div
            class="faturar__tabs"
            role="tablist"
            aria-label="Seções do faturamento"
            @keydown="aoNavegarAbas"
          >
            <button
              v-for="abaItem in ABAS"
              :id="`tab-${abaItem.id}`"
              :key="abaItem.id"
              class="faturar__tab"
              :class="{ 'faturar__tab--ativa': aba === abaItem.id }"
              type="button"
              role="tab"
              :aria-selected="aba === abaItem.id ? 'true' : 'false'"
              :aria-controls="`painel-${abaItem.id}`"
              :tabindex="aba === abaItem.id ? 0 : -1"
              @click="aba = abaItem.id"
            >
              {{ abaItem.label }}
            </button>
          </div>

          <!-- ============ Aba: Apuração ============ -->
          <div
            v-show="aba === 'apuracao'"
            id="painel-apuracao"
            role="tabpanel"
            aria-labelledby="tab-apuracao"
            class="faturar__painel"
          >
            <SectionCard eyebrow="Apuração" :title="`Apuração de ${competenciaLabel}`">
              <p v-if="carregandoAno" class="faturar__carregando">Carregando dados do mês…</p>

              <!-- Estado JÁ FATURADO (leitura) -->
              <LancamentoReadonly
                v-else-if="mesFaturado && !modoEdicao"
                :dados="dadosLancamento"
                :competencia-label="competenciaLabel"
                :revertivel="mesRevertivel"
                @refaturar="iniciarRefaturamento"
                @reverter="confirmarEstorno(mes)"
                @pdf="baixarPdf"
              />

              <!-- Estado A FATURAR / refaturando (formulário) -->
              <template v-else>
                <div
                  v-if="modoEdicao"
                  class="faturar__aviso-refaturamento"
                  role="status"
                >
                  <BaseBadge variant="warning" dot>Refaturando {{ competenciaLabel }}</BaseBadge>
                  <span>
                    Os campos foram preenchidos com os valores do lançamento. Ao salvar, você
                    confirma a substituição em um diálogo com o comparativo.
                  </span>
                  <BaseButton variant="ghost" size="sm" @click="cancelarRefaturamento">
                    Cancelar refaturamento
                  </BaseButton>
                </div>

                <div class="faturar__campos">
                  <BaseField
                    label="Geração bruta"
                    :hint="geracaoVemDoCadastro ? 'Pré-preenchido do cadastro de geração real.' : 'Sem geração cadastrada para este mês — informe o valor medido.'"
                  >
                    <NumberInput
                      v-model="mesGeracao"
                      suffix="kWh"
                      :min="0"
                      placeholder="0,00"
                    />
                  </BaseField>

                  <div @focusout="tocado.consumo = true">
                    <BaseField label="Consumo da usina" required :error="erroConsumo">
                      <NumberInput
                        v-model="consumoUsinaMes"
                        suffix="kWh"
                        :min="0"
                        placeholder="0,00"
                      />
                    </BaseField>
                  </div>

                  <div @focusout="tocado.fatura = true">
                    <BaseField label="Fatura de energia" required :error="erroFatura">
                      <NumberInput
                        v-model="faturaEnergia"
                        prefix="R$"
                        :min="0"
                        placeholder="0,00"
                      />
                    </BaseField>
                  </div>

                  <BaseField
                    label="Adicional CUO"
                    optional-label
                    hint="Use valores negativos para abater."
                  >
                    <NumberInput v-model="adicionalCuo" prefix="R$" placeholder="0,00" />
                  </BaseField>
                </div>

                <PreviewPanel
                  class="faturar__preview"
                  :preview="previewMes"
                  :loading="previewLoading"
                  :error="previewError"
                  @retry="carregarPreview"
                />

                <AuditoriaAccordion
                  class="faturar__auditoria"
                  :preview="previewMes"
                  :parametros="parametrosAuditoria"
                />

                <footer class="faturar__rodape-card">
                  <BaseButton variant="secondary" @click="baixarPdf">Baixar PDF</BaseButton>
                  <BaseButton
                    variant="primary"
                    glow
                    :loading="isSalvando"
                    :disabled="!formValido || previewLoading"
                    @click="aoClicarFaturar"
                  >
                    Faturar {{ competenciaLabel }}
                  </BaseButton>
                </footer>
              </template>
            </SectionCard>
          </div>

          <!-- ============ Aba: Expectativa anual ============ -->
          <div
            v-show="aba === 'expectativa'"
            id="painel-expectativa"
            role="tabpanel"
            aria-labelledby="tab-expectativa"
            class="faturar__painel"
          >
            <SectionCard eyebrow="Expectativa" :title="`Expectativa anual de ${ano}`">
              <p class="faturar__explicacao">
                Previsão baseada na <strong>geração projetada</strong> da usina. O CUO considera
                apenas o Fio B — a fatura real de cada mês entra na apuração mensal.
              </p>

              <p v-if="carregandoAno" class="faturar__carregando">Carregando projeção…</p>
              <template v-else>
                <DataTable :columns="colunasExpectativa" :rows="linhasExpectativa">
                  <template #cell-geracao="{ row, value }">
                    <span :class="{ 'faturar__menor-geracao': row.geracaoEhMenor }">{{ value }}</span>
                  </template>
                  <template #empty>Sem projeção disponível para {{ ano }}.</template>
                </DataTable>

                <div v-if="chartData" class="faturar__grafico">
                  <Bar :data="chartData" :options="chartOptions" />
                </div>
              </template>
            </SectionCard>
          </div>

          <!-- ============ Aba: Histórico ============ -->
          <div
            v-show="aba === 'historico'"
            id="painel-historico"
            role="tabpanel"
            aria-labelledby="tab-historico"
            class="faturar__painel"
          >
            <SectionCard eyebrow="Histórico" :title="`Lançamentos de ${ano}`">
              <p class="faturar__explicacao">
                Geração do mês (bruta, em kWh). A competência segue a seleção de ano acima.
              </p>

              <p v-if="carregandoAno" class="faturar__carregando">Carregando lançamentos…</p>
              <template v-else>
                <DataTable :columns="colunasHistorico" :rows="linhasHistorico">
                  <template #cell-detalhes="{ row }">
                    <button
                      type="button"
                      class="faturar__detalhes-toggle"
                      :aria-expanded="row._detalhes ? 'true' : 'false'"
                      :aria-controls="`detalhes-${row.chave}`"
                      :aria-label="`Detalhes do lançamento de ${row.mes}`"
                      @click="alternarDetalhes(row.chave)"
                    >
                      <svg
                        class="faturar__detalhes-chevron"
                        :class="{ 'faturar__detalhes-chevron--aberto': row._detalhes }"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <polyline points="9 6 15 12 9 18" />
                      </svg>
                    </button>
                  </template>
                  <template #row-details="{ row }">
                    <div :id="`detalhes-${row.chave}`" class="faturar__detalhes-lancamento">
                      <p class="faturar__detalhes-titulo">Inputs do lançamento de {{ row.mes }}/{{ ano }}</p>
                      <dl class="faturar__detalhes-pares">
                        <div class="faturar__detalhes-par">
                          <dt>Geração bruta</dt>
                          <dd>{{ row.geracao }}</dd>
                        </div>
                        <div class="faturar__detalhes-par">
                          <dt>Consumo da usina</dt>
                          <dd>{{ row.consumoInput }}</dd>
                        </div>
                        <div class="faturar__detalhes-par">
                          <dt>Fatura de energia</dt>
                          <dd>{{ row.faturaInput }}</dd>
                        </div>
                      </dl>
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
                      class="faturar__acao-bloqueada"
                      title="Apenas o último lançamento pode ser revertido"
                    >—</span>
                  </template>
                  <template #empty>Nenhum lançamento registrado em {{ ano }}.</template>
                </DataTable>

                <details v-if="linhasTrilha.length" class="faturar__trilha">
                  <summary class="faturar__trilha-summary">
                    <svg
                      class="faturar__trilha-chevron"
                      width="18"
                      height="18"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.75"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      aria-hidden="true"
                    >
                      <polyline points="9 6 15 12 9 18" />
                    </svg>
                    Trilha de auditoria (lançamentos e reversões)
                  </summary>
                  <div class="faturar__trilha-conteudo">
                    <DataTable :columns="colunasTrilha" :rows="linhasTrilha" />
                  </div>
                </details>
              </template>
            </SectionCard>
          </div>
        </template>
      </div>

      <ConfirmRefaturarDialog
        :aberto="dialogRefaturarAberto"
        :competencia-label="competenciaLabel"
        :atual="dadosLancamento"
        :novo="novoLancamento"
        @confirmar="executarFaturamento"
        @cancelar="dialogRefaturarAberto = false"
      />
    </template>
  </div>
</template>

<style scoped>
.faturar {
  font-family: var(--font-body);
  color: var(--color-ink);
}

/* ---------- Nível 1 ---------- */
.faturar__selecao {
  max-width: 640px;
  margin: var(--space-9) auto;
  padding: 0 var(--space-5);
}

.faturar__selecao-texto {
  margin: 0 0 var(--space-4);
  font-size: var(--fs-sm);
  color: var(--color-graphite);
}

/* ---------- Nível 2 ---------- */
.faturar__workspace {
  max-width: var(--max-w-app);
  margin: 0 auto;
  padding: var(--space-6) var(--space-5) var(--space-10);
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

/* ---------- Estados de carga/erro ---------- */
.faturar__carregando {
  margin: 0;
  padding: var(--space-4) 0;
  font-size: var(--fs-sm);
  color: var(--color-slate);
}

.faturar__erro {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
  background: var(--color-danger-soft);
  border-radius: var(--radius-md);
}

.faturar__erro p {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--color-danger);
}

/* ---------- Abas ---------- */
.faturar__tabs {
  display: flex;
  gap: var(--space-1);
  border-bottom: 1px solid var(--color-mist);
}

.faturar__tab {
  border: none;
  background: transparent;
  padding: var(--space-3) var(--space-4);
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-graphite);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  cursor: pointer;
  transition:
    color var(--dur-hover) var(--ease-standard),
    border-color var(--dur-hover) var(--ease-standard);
}

.faturar__tab:hover {
  color: var(--color-primary-deep);
}

.faturar__tab:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
  border-radius: var(--radius-sm);
}

.faturar__tab--ativa {
  color: var(--color-primary-deep);
  border-bottom-color: var(--color-primary);
}

.faturar__painel {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

/* ---------- Apuração ---------- */
.faturar__aviso-refaturamento {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  margin-bottom: var(--space-5);
  background: var(--color-warning-soft);
  border-radius: var(--radius-md);
  font-size: var(--fs-sm);
  color: var(--color-graphite);
}

.faturar__campos {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--space-5);
  margin-bottom: var(--space-5);
}

.faturar__preview {
  margin-bottom: var(--space-5);
}

.faturar__rodape-card {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-3);
  margin-top: var(--space-6);
  padding-top: var(--space-5);
  border-top: 1px solid var(--color-mist);
}

/* ---------- Expectativa ---------- */
.faturar__explicacao {
  margin: 0 0 var(--space-4);
  font-size: var(--fs-sm);
  color: var(--color-graphite);
}

.faturar__menor-geracao {
  color: var(--color-danger);
  font-weight: var(--fw-bold);
}

.faturar__grafico {
  width: 100%;
  height: 380px;
  margin-top: var(--space-6);
}

/* ---------- Histórico ---------- */
.faturar__detalhes-toggle {
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

.faturar__detalhes-toggle:hover {
  color: var(--color-primary-deep);
}

.faturar__detalhes-toggle:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.faturar__detalhes-chevron {
  transition: transform var(--dur-hover) var(--ease-standard);
}

.faturar__detalhes-chevron--aberto {
  transform: rotate(90deg);
}

.faturar__detalhes-lancamento {
  padding: var(--space-3) var(--space-4);
  background: var(--color-linen);
  border-radius: var(--radius-md);
}

.faturar__detalhes-titulo {
  margin: 0 0 var(--space-3);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-graphite);
}

.faturar__detalhes-pares {
  margin: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--space-4);
}

.faturar__detalhes-par {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.faturar__detalhes-par dt {
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-slate);
}

.faturar__detalhes-par dd {
  margin: 0;
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--fs-body);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
}

.faturar__acao-bloqueada {
  color: var(--color-smoke);
  cursor: help;
}

.faturar__trilha {
  margin-top: var(--space-5);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
}

.faturar__trilha-summary {
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

.faturar__trilha-summary::-webkit-details-marker {
  display: none;
}

.faturar__trilha-summary:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.faturar__trilha-chevron {
  flex: none;
  color: var(--color-slate);
  transition: transform var(--dur-hover) var(--ease-standard);
}

.faturar__trilha[open] > .faturar__trilha-summary .faturar__trilha-chevron {
  transform: rotate(90deg);
}

.faturar__trilha-conteudo {
  padding: 0 var(--space-4) var(--space-4);
}

@media (prefers-reduced-motion: reduce) {
  .faturar__tab,
  .faturar__trilha-chevron,
  .faturar__detalhes-chevron {
    transition: none;
  }
}
</style>
