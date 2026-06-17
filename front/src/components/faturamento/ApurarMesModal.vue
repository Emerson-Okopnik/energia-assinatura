<script setup>
// Modal de Apuração (blueprint §4) — conferência em UMA tela rolável:
// inputs → preview (sempre visível) → auditoria → confirmar/refaturar/PDF.
// O frontend NÃO calcula nada: lê preview do backend e persiste via faturamentoApi.
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'

import swal from '../../utils/swal.js'
import { formatReais } from '../../utils/formatters.js'
import { consumoValido, faturaValida } from '../../utils/validacaoLancamento.js'
import * as api from '../../services/faturamentoApi.js'

import BaseBadge from '../base/BaseBadge.vue'
import BaseButton from '../base/BaseButton.vue'
import BaseField from '../base/BaseField.vue'
import NumberInput from '../base/NumberInput.vue'
import PreviewPanel from './PreviewPanel.vue'
import AuditoriaAccordion from './AuditoriaAccordion.vue'
import ConfirmRefaturarDialog from './ConfirmRefaturarDialog.vue'

const CHAVES_MESES = [
  'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
]
const MESES = {
  janeiro: 'Janeiro', fevereiro: 'Fevereiro', marco: 'Março', abril: 'Abril',
  maio: 'Maio', junho: 'Junho', julho: 'Julho', agosto: 'Agosto',
  setembro: 'Setembro', outubro: 'Outubro', novembro: 'Novembro', dezembro: 'Dezembro',
}

const props = defineProps({
  aberto: { type: Boolean, default: false },
  // usina completa (obterUsina): usa usi_id, cliente.nome, dado_geracao, comercializacao
  usina: { type: Object, default: null },
  ano: { type: Number, required: true },
  // chave de mês em pt-BR ('janeiro' … 'dezembro')
  mes: { type: String, required: true },
  // geração bruta cadastrada para o mês (null ≠ 0 — F12); vem de dadosGeracaoRealMensal
  geracaoCadastrada: { type: [Number, null], default: null },
  // inputs persistidos do mês: { fatura_energia, consumo } | undefined
  inputsSalvos: { type: Object, default: null },
  // mês já faturado → modo refaturamento
  modoRefaturar: { type: Boolean, default: false },
  // { tarifa, mediaKwh, menorGeracaoKwh, fioB, percentualLei, rede, descontoRedeKwh }
  parametrosAuditoria: { type: Object, default: null },
})

const emit = defineEmits(['confirmado', 'fechar'])

// ---------------------------------------------------------------- refs DOM
const painelRef = ref(null)

// ---------------------------------------------------------------- derivados
const mesIndex = computed(() => CHAVES_MESES.indexOf(props.mes) + 1)
const competenciaLabel = computed(() => `${MESES[props.mes] ?? ''}/${props.ano}`)

// ---------------------------------------------------------------- formulário
const mesGeracao = ref(null)
const consumoUsinaMes = ref(null)
const faturaEnergia = ref(null)
const adicionalCuo = ref(null)
const tocado = reactive({ consumo: false, fatura: false })

const geracaoVemDoCadastro = computed(
  () => props.geracaoCadastrada !== null && Number(props.geracaoCadastrada) > 0
)

// Validade de cada campo — fonte única em utils/validacaoLancamento (SOLID/DRY).
const consumoEhValido = computed(() => consumoValido(consumoUsinaMes.value))
const faturaEhValida = computed(() => faturaValida(faturaEnergia.value))

// Validação inline por blur (F7): mensagem só após o campo ser tocado.
const erroConsumo = computed(() =>
  tocado.consumo && !consumoEhValido.value
    ? 'Informe o consumo da usina no mês.'
    : ''
)
const erroFatura = computed(() =>
  tocado.fatura && !faturaEhValida.value
    ? 'Informe a fatura de energia (maior que zero).'
    : ''
)
const formValido = computed(() => consumoEhValido.value && faturaEhValida.value)

function numeroOuNulo(valor) {
  if (valor === null || valor === undefined || valor === '') return null
  const numero = Number(valor)
  return Number.isFinite(numero) ? numero : null
}

// Reidrata o formulário com o que existe salvo para a competência (F1/F12).
function reidratar() {
  mesGeracao.value = numeroOuNulo(props.geracaoCadastrada)
  consumoUsinaMes.value = numeroOuNulo(props.inputsSalvos?.consumo)
  faturaEnergia.value = numeroOuNulo(props.inputsSalvos?.fatura_energia)
  adicionalCuo.value = null
  tocado.consumo = false
  tocado.fatura = false
  previewMes.value = null
  previewError.value = ''
  agendarPreview()
}

// ---------------------------------------------------------------- preview
const previewMes = ref(null)
const previewLoading = ref(false)
const previewError = ref('')
let previewTimer = null
let previewSeq = 0

function agendarPreview() {
  if (previewTimer) clearTimeout(previewTimer)
  previewTimer = setTimeout(() => carregarPreview(), 300)
}

async function carregarPreview() {
  if (!props.usina?.usi_id || !props.mes || !props.ano) {
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
    const dados = await api.obterPreview(props.usina.usi_id, props.ano, mesIndex.value, {
      geracaoBrutaKwh: mesGeracao.value,
      faturaEnergia: faturaEnergia.value,
      adicionalCuo: adicionalCuo.value,
      consumo: consumoUsinaMes.value,
    })
    if (seq !== previewSeq) return // descarta resposta stale
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
  if (props.aberto) agendarPreview()
})

// ---------------------------------------------------------------- diff refaturar
const dialogRefaturarAberto = ref(false)

const dadosAtuais = computed(() => ({
  geracaoKwh: numeroOuNulo(props.geracaoCadastrada),
  consumoKwh: numeroOuNulo(props.inputsSalvos?.consumo),
  faturaReais: numeroOuNulo(props.inputsSalvos?.fatura_energia),
  adicionalReais: null,
  valorFinalReais: null,
}))

const novosDados = computed(() => ({
  geracaoKwh: mesGeracao.value,
  consumoKwh: consumoUsinaMes.value,
  faturaReais: faturaEnergia.value,
  adicionalReais: adicionalCuo.value,
  valorFinalReais: numeroOuNulo(previewMes.value?.termos?.valor_final_reais),
}))

// ---------------------------------------------------------------- confirmar
const isSalvando = ref(false)

function aoConfirmar() {
  tocado.consumo = true
  tocado.fatura = true
  if (!formValido.value) return
  // Guard de sobrescrita (F1): mês já faturado SEMPRE passa pelo diff.
  if (props.modoRefaturar) {
    dialogRefaturarAberto.value = true
    return
  }
  executarFaturamento()
}

async function executarFaturamento() {
  dialogRefaturarAberto.value = false
  if (!props.usina?.usi_id || !formValido.value) return

  isSalvando.value = true
  try {
    // 1) UPSERT do consumo do mês (não zera os outros 11 meses — F2).
    await api.salvarConsumoMes(props.usina.usi_id, props.ano, mesIndex.value, consumoUsinaMes.value)

    // 2) Lançamento: o backend calcula e persiste (payload só com inputs).
    await api.salvarCalculo(props.usina.usi_id, props.ano, mesIndex.value, {
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

    emit('confirmado')
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

// ---------------------------------------------------------------- PDF
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

  try {
    swal.fire({
      title: 'Gerando PDF...',
      html: 'Aguarde enquanto preparamos o documento.',
      allowOutsideClick: false,
      didOpen: () => swal.showLoading(),
    })

    const blob = await api.gerarPdfUsina(props.usina.usi_id, {
      observacoes,
      mes: mesIndex.value,
      ano: props.ano,
      fatura: faturaEnergia.value ?? 0,
      adicionalCuo: adicionalCuo.value,
    })

    swal.close()

    const url = window.URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `fatura ${props.usina.cliente?.nome} - ${props.usina.usi_id}.pdf`)
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

// ---------------------------------------------------------------- ciclo de vida
function fechar() {
  emit('fechar')
}

function onKeydown(evento) {
  if (evento.key === 'Escape') {
    evento.stopPropagation()
    // Não fecha o modal por baixo do diálogo de refaturamento.
    if (dialogRefaturarAberto.value) return
    fechar()
    return
  }
  if (evento.key !== 'Tab') return
  // Focus-trap: mantém o Tab dentro do painel.
  const focaveis = painelRef.value?.querySelectorAll(
    'button:not([disabled]), [href], input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'
  )
  if (!focaveis || focaveis.length === 0) return
  const primeiro = focaveis[0]
  const ultimo = focaveis[focaveis.length - 1]
  if (evento.shiftKey && document.activeElement === primeiro) {
    evento.preventDefault()
    ultimo.focus()
  } else if (!evento.shiftKey && document.activeElement === ultimo) {
    evento.preventDefault()
    primeiro.focus()
  }
}

watch(
  () => props.aberto,
  async (aberto) => {
    if (aberto) {
      reidratar()
      await nextTick()
      // Foca o primeiro campo ao abrir.
      painelRef.value?.querySelector('input')?.focus()
    } else {
      if (previewTimer) clearTimeout(previewTimer)
      dialogRefaturarAberto.value = false
      previewSeq += 1 // invalida qualquer preview em voo
    }
  }
)

onBeforeUnmount(() => {
  if (previewTimer) clearTimeout(previewTimer)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="apurar">
      <div
        v-if="aberto"
        class="apurar__overlay"
        @click.self="fechar"
        @keydown="onKeydown"
      >
        <div
          ref="painelRef"
          class="apurar__painel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="apurar-titulo"
        >
          <header class="apurar__cabecalho">
            <div>
              <span class="apurar__eyebrow">Conferência</span>
              <h2 id="apurar-titulo" class="apurar__titulo">Apurar {{ competenciaLabel }}</h2>
            </div>
            <div class="apurar__cabecalho-lado">
              <BaseBadge v-if="modoRefaturar" variant="warning" dot>
                Refaturando {{ competenciaLabel }}
              </BaseBadge>
              <button
                type="button"
                class="apurar__fechar"
                aria-label="Fechar"
                @click="fechar"
              >
                <svg
                  width="20" height="20" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                  stroke-linejoin="round" aria-hidden="true"
                >
                  <path d="M18 6 6 18" />
                  <path d="m6 6 12 12" />
                </svg>
              </button>
            </div>
          </header>

          <div class="apurar__corpo">
            <!-- (a) Inputs -->
            <div class="apurar__campos">
              <BaseField
                label="Geração bruta"
                :hint="geracaoVemDoCadastro ? 'Pré-preenchido do cadastro de geração real.' : 'Sem geração cadastrada para este mês — informe o valor medido.'"
              >
                <NumberInput v-model="mesGeracao" suffix="kWh" :min="0" placeholder="0,00" />
              </BaseField>

              <div @focusout="tocado.consumo = true">
                <BaseField label="Consumo da usina" required :error="erroConsumo">
                  <NumberInput v-model="consumoUsinaMes" suffix="kWh" :min="0" placeholder="0,00" />
                </BaseField>
              </div>

              <div @focusout="tocado.fatura = true">
                <BaseField label="Fatura de energia" required :error="erroFatura">
                  <NumberInput v-model="faturaEnergia" prefix="R$" :min="0" placeholder="0,00" />
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

            <!-- (b) Preview (sempre visível) -->
            <PreviewPanel
              :preview="previewMes"
              :loading="previewLoading"
              :error="previewError"
              @retry="carregarPreview"
            />

            <!-- (c) Auditoria -->
            <AuditoriaAccordion
              :preview="previewMes"
              :parametros="parametrosAuditoria"
            />
          </div>

          <footer class="apurar__rodape">
            <BaseButton variant="ghost" @click="fechar">Cancelar</BaseButton>
            <div class="apurar__rodape-direita">
              <BaseButton variant="secondary" @click="baixarPdf">Baixar PDF</BaseButton>
              <BaseButton
                variant="primary"
                glow
                :loading="isSalvando"
                :disabled="!formValido || previewLoading || Boolean(previewError)"
                @click="aoConfirmar"
              >
                Confirmar faturamento de {{ competenciaLabel }}
              </BaseButton>
            </div>
          </footer>
        </div>
      </div>
    </Transition>

    <ConfirmRefaturarDialog
      :aberto="dialogRefaturarAberto"
      :competencia-label="competenciaLabel"
      :atual="dadosAtuais"
      :novo="novosDados"
      @confirmar="executarFaturamento"
      @cancelar="dialogRefaturarAberto = false"
    />
  </Teleport>
</template>

<style scoped>
.apurar__overlay {
  position: fixed;
  inset: 0;
  z-index: 1070;
  background: var(--color-ink-overlay);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: var(--space-7) var(--space-5);
  overflow-y: auto;
}

.apurar__painel {
  width: 100%;
  max-width: 720px;
  margin: auto;
  background: var(--color-paper);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - var(--space-9));
}

/* ---------- cabeçalho ---------- */
.apurar__cabecalho {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-6) var(--space-6) var(--space-4);
  border-bottom: 1px solid var(--color-mist);
}

.apurar__eyebrow {
  font-family: var(--font-body);
  font-size: var(--fs-eyebrow);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--color-primary-deep);
}

.apurar__titulo {
  margin: var(--space-1) 0 0;
  font-family: var(--font-display);
  font-size: var(--fs-h4);
  font-weight: var(--fw-extra);
  line-height: var(--lh-snug);
  letter-spacing: -0.01em;
  color: var(--color-ink);
}

.apurar__cabecalho-lado {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex: none;
}

.apurar__fechar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  border-radius: var(--radius-md);
  color: var(--color-slate);
  cursor: pointer;
  transition: background-color var(--dur-hover) var(--ease-standard);
}

.apurar__fechar:hover {
  background: rgba(61, 61, 61, 0.06);
  color: var(--color-ink);
}

.apurar__fechar:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

/* ---------- corpo rolável ---------- */
.apurar__corpo {
  padding: var(--space-5) var(--space-6);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.apurar__campos {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--space-5);
}

/* ---------- rodapé ---------- */
.apurar__rodape {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  flex-wrap: wrap;
  padding: var(--space-4) var(--space-6);
  border-top: 1px solid var(--color-mist);
}

.apurar__rodape-direita {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}

/* ---------- transição de entrada (280ms ease-out-quart, sem bounce) ---------- */
.apurar-enter-active,
.apurar-leave-active {
  transition: opacity var(--dur-enter) var(--ease-out-quart);
}

.apurar-enter-active .apurar__painel,
.apurar-leave-active .apurar__painel {
  transition: transform var(--dur-enter) var(--ease-out-quart),
    opacity var(--dur-enter) var(--ease-out-quart);
}

.apurar-enter-from,
.apurar-leave-to {
  opacity: 0;
}

.apurar-enter-from .apurar__painel,
.apurar-leave-to .apurar__painel {
  transform: translateY(8px);
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .apurar-enter-active,
  .apurar-leave-active,
  .apurar-enter-active .apurar__painel,
  .apurar-leave-active .apurar__painel {
    transition: none;
  }
  .apurar-enter-from .apurar__painel,
  .apurar-leave-to .apurar__painel {
    transform: none;
  }
}
</style>
