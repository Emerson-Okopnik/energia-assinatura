<script setup>
// Lista de usinas — tela inicial do faturamento (blueprint §2).
// Fila de trabalho em DataTable com busca + paginação client-side.
// O estado do mês corrente é carregado em lote (GET /usina não traz o mês),
// reusando a mesma regra temDadosMes() da página de faturamento.
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { formatKwh } from '../utils/formatters.js'
import * as api from '../services/faturamentoApi.js'
import swal from '../utils/swal.js'

import SectionCard from '../components/base/SectionCard.vue'
import BaseField from '../components/base/BaseField.vue'
import BaseButton from '../components/base/BaseButton.vue'
import BaseBadge from '../components/base/BaseBadge.vue'
import DataTable from '../components/base/DataTable.vue'
import Paginacao from '../components/base/Paginacao.vue'

const route = useRoute()
const router = useRouter()

// ---------------------------------------------------------------- constantes
const MESES = [
  'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
]
const MESES_LABEL = [
  'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
]
const anoAtual = new Date().getFullYear()
const chaveMesAtual = MESES[new Date().getMonth()]
const labelMesAtual = MESES_LABEL[new Date().getMonth()]
const ITENS_POR_PAGINA = 12

// ---------------------------------------------------------------- estado
const usinas = ref([])
const carregando = ref(false)
const erro = ref('')

// { [usi_id]: 'faturado' | 'pendente' } — estado da competência corrente.
const estadosMesUsinas = ref({})
let estadosSeq = 0

// Busca + paginação sincronizadas com a query (?q=&page=).
const busca = ref(typeof route.query.q === 'string' ? route.query.q : '')
const buscaDebounced = ref(busca.value)
const pagina = ref(Number(route.query.page) > 0 ? Number(route.query.page) : 1)
let debounceTimer = null

// Remove acentos para a busca acento-insensível (mesma regra do UsinaCombobox).
function normalizar(texto) {
  return String(texto ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

// ---------------------------------------------------------------- derivados
function cidadeUf(u) {
  const cidade = u.cliente?.endereco?.cidade
  const uf = u.cliente?.endereco?.estado
  if (cidade && uf) return `${cidade}/${uf}`
  return cidade || uf || '—'
}

const usinasFiltradas = computed(() => {
  const termo = normalizar(buscaDebounced.value.trim())
  if (!termo) return usinas.value
  return usinas.value.filter((u) => {
    const alvo = normalizar(
      `${u.cliente?.nome ?? ''} ${u.uc ?? ''} ${cidadeUf(u)}`,
    )
    return alvo.includes(termo)
  })
})

const totalFiltrado = computed(() => usinasFiltradas.value.length)

const colunas = [
  { key: 'cliente', label: 'Cliente' },
  { key: 'uc', label: 'UC' },
  { key: 'cidade', label: 'Cidade/UF' },
  { key: 'cia', label: 'CIA energia' },
  { key: 'media', label: 'Média geração', numeric: true },
  { key: 'estado', label: `Estado de ${labelMesAtual}` },
  { key: 'acao', label: 'Ação', align: 'right' },
]

// Fatia da página atual: única lista que precisa de badge de estado.
const usinasPagina = computed(() => {
  const inicio = (pagina.value - 1) * ITENS_POR_PAGINA
  const fim = inicio + ITENS_POR_PAGINA
  return usinasFiltradas.value.slice(inicio, fim)
})

const linhas = computed(() =>
  usinasPagina.value.map((u) => ({
    usiId: u.usi_id,
    cliente: u.cliente?.nome || '—',
    uc: u.uc || '—',
    cidade: cidadeUf(u),
    cia: u.comercializacao?.cia_energia || '—',
    media: formatKwh(u.dado_geracao?.media),
    estado: estadosMesUsinas.value[u.usi_id] || null,
  })),
)

// ---------------------------------------------------------------- busca/página
watch(busca, (valor) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    buscaDebounced.value = valor
  }, 200)
})

// Reseta para a primeira página quando o filtro muda.
watch(buscaDebounced, () => {
  pagina.value = 1
})

// Sincroniza ?q=&page= na URL (sobrevive ao "voltar" do browser).
watch([buscaDebounced, pagina], ([q, p]) => {
  const query = {}
  if (q) query.q = q
  if (p > 1) query.page = String(p)
  router.replace({ query })
})

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})

// ---------------------------------------------------------------- carregamento
function temDadosMesCorrente(faturamento, geracao) {
  return (
    Number(geracao?.[chaveMesAtual] || 0) > 0 ||
    Number(faturamento?.faturamento_usina?.[chaveMesAtual] || 0) > 0 ||
    Number(faturamento?.valor_acumulado_reserva?.[chaveMesAtual] || 0) > 0 ||
    Number(faturamento?.creditos_distribuidos?.[chaveMesAtual] || 0) > 0
  )
}

// Estado do mês SOB DEMANDA: só para as usinas da página visível (12),
// não para toda a base. Evita o padrão N+1 no load (dezenas de GETs);
// resultados ficam em cache, então paginar/buscar não refaz o que já viu.
// Trade-off: o badge da próxima página só carrega ao navegar até ela.
async function carregarEstadosPaginaAtual() {
  const lista = usinasPagina.value
  if (!lista.length) return
  const seq = ++estadosSeq
  // Só as ainda não resolvidas (cache por usi_id).
  const fila = lista.filter((u) => !(u.usi_id in estadosMesUsinas.value))
  if (!fila.length) return
  const CONCORRENCIA = 4

  async function worker() {
    while (fila.length) {
      if (seq !== estadosSeq) return
      const u = fila.shift()
      try {
        const [faturamento, geracao] = await Promise.all([
          api.obterFaturamentoAnual(u.usi_id, anoAtual),
          api.obterGeracaoReal(u.usi_id, anoAtual),
        ])
        estadosMesUsinas.value = {
          ...estadosMesUsinas.value,
          [u.usi_id]: temDadosMesCorrente(faturamento, geracao)
            ? 'faturado'
            : 'pendente',
        }
      } catch (error) {
        // Sem estado para esta usina: a linha fica sem badge (falha silenciosa).
      }
    }
  }

  await Promise.all(
    Array.from({ length: Math.min(CONCORRENCIA, fila.length) }, worker),
  )
}

// Carrega o estado quando a página visível muda (load, paginação ou busca).
watch(
  usinasPagina,
  () => {
    carregarEstadosPaginaAtual()
  },
  { immediate: false },
)

async function carregarUsinas() {
  carregando.value = true
  erro.value = ''
  try {
    usinas.value = await api.listarUsinas()
    carregarEstadosPaginaAtual()
  } catch (error) {
    erro.value =
      'Não foi possível carregar a lista de usinas. Verifique a conexão e tente de novo.'
  } finally {
    carregando.value = false
  }
}

// ---------------------------------------------------------------- gestão
async function excluirUsina(usiId, nomeCliente) {
  const confirmacao = await swal.fire({
    icon: 'warning',
    title: 'Excluir usina?',
    text: `A usina de ${nomeCliente} será removida. Esta ação é irreversível.`,
    showCancelButton: true,
    confirmButtonText: 'Sim, excluir',
    cancelButtonText: 'Cancelar',
  })
  if (!confirmacao.isConfirmed) return

  try {
    await api.excluirUsina(usiId)
    usinas.value = usinas.value.filter((u) => u.usi_id !== usiId)
    const { [usiId]: _removido, ...resto } = estadosMesUsinas.value
    estadosMesUsinas.value = resto
    await swal.fire({
      icon: 'success',
      title: 'Usina excluída',
      text: 'A usina foi removida com sucesso.',
    })
  } catch (error) {
    await swal.fire({
      icon: 'error',
      title: 'Erro ao excluir',
      text: 'Não foi possível excluir a usina. Tente de novo.',
      confirmButtonText: 'Entendi',
    })
  }
}

// ---------------------------------------------------------------- navegação
function abrirUsina(usiId) {
  if (!usiId) return
  router.push(`/faturar/${usiId}/${anoAtual}/${chaveMesAtual}`)
}

carregarUsinas()
</script>

<template>
  <div class="usinas-fat">
    <SectionCard eyebrow="Faturamento" title="Usinas">
      <p class="usinas-fat__intro">
        Selecione a usina para faturar. Busque por cliente, UC ou cidade. O estado de
        {{ labelMesAtual }} aparece ao lado de cada usina.
      </p>

      <div class="usinas-fat__busca">
        <BaseField label="Buscar usina" hint="Por cliente, UC ou cidade.">
          <template #default="{ id, describedBy }">
            <div class="usinas-fat__campo">
              <svg
                class="usinas-fat__lupa"
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
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
              </svg>
              <input
                :id="id"
                v-model="busca"
                :aria-describedby="describedBy"
                type="search"
                class="usinas-fat__input"
                placeholder="Buscar por cliente, UC ou cidade"
                autocomplete="off"
              />
            </div>
          </template>
        </BaseField>
      </div>

      <div v-if="erro" class="usinas-fat__erro" role="alert">
        <p>{{ erro }}</p>
        <BaseButton variant="secondary" size="sm" @click="carregarUsinas">
          Tentar de novo
        </BaseButton>
      </div>

      <p v-else-if="carregando" class="usinas-fat__carregando">Carregando usinas…</p>

      <template v-else>
        <DataTable :columns="colunas" :rows="linhas">
          <template #cell-cliente="{ row }">
            <button
              type="button"
              class="usinas-fat__linha-link"
              @click="abrirUsina(row.usiId)"
            >
              {{ row.cliente }}
            </button>
          </template>

          <template #cell-estado="{ row }">
            <BaseBadge v-if="row.estado === 'faturado'" variant="success" dot>
              Faturado
            </BaseBadge>
            <BaseBadge v-else-if="row.estado === 'pendente'" variant="warning" dot>
              Pendente
            </BaseBadge>
            <span v-else class="usinas-fat__sem-estado" aria-hidden="true">…</span>
          </template>

          <template #cell-acao="{ row }">
            <div class="usinas-fat__acoes">
              <BaseButton variant="ghost" size="sm" @click="abrirUsina(row.usiId)">
                Abrir
              </BaseButton>
              <router-link
                :to="`/usina/${row.usiId}`"
                class="usinas-fat__acao-icone"
                :aria-label="`Editar usina de ${row.cliente}`"
                title="Editar usina"
              >
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.75"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M12 20h9" />
                  <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
                </svg>
              </router-link>
              <button
                type="button"
                class="usinas-fat__acao-icone usinas-fat__acao-icone--danger"
                :aria-label="`Excluir usina de ${row.cliente}`"
                title="Excluir usina"
                @click="excluirUsina(row.usiId, row.cliente)"
              >
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.75"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                  <line x1="10" y1="11" x2="10" y2="17" />
                  <line x1="14" y1="11" x2="14" y2="17" />
                </svg>
              </button>
            </div>
          </template>

          <template #empty>
            <span v-if="buscaDebounced.trim()">
              Nenhuma usina encontrada para “{{ buscaDebounced.trim() }}”. Tente outro termo.
            </span>
            <span v-else>Nenhuma usina cadastrada ainda.</span>
          </template>
        </DataTable>

        <div v-if="totalFiltrado > ITENS_POR_PAGINA" class="usinas-fat__paginacao">
          <Paginacao
            :total="totalFiltrado"
            :itens-por-pagina="ITENS_POR_PAGINA"
            v-model:pagina-atual="pagina"
          />
        </div>
      </template>
    </SectionCard>
  </div>
</template>

<style scoped>
.usinas-fat {
  max-width: var(--max-w-app);
  margin: 0 auto;
  font-family: var(--font-body);
  color: var(--color-ink);
}

.usinas-fat__intro {
  margin: 0 0 var(--space-5);
  font-size: var(--fs-sm);
  color: var(--color-graphite);
}

.usinas-fat__busca {
  margin-bottom: var(--space-6);
  max-width: 420px;
}

.usinas-fat__campo {
  position: relative;
  display: flex;
  align-items: center;
}

.usinas-fat__lupa {
  position: absolute;
  left: var(--space-3);
  color: var(--color-slate);
  pointer-events: none;
}

.usinas-fat__input {
  width: 100%;
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-3) + 26px);
  font-family: var(--font-body);
  font-size: var(--fs-body);
  color: var(--color-ink);
  background: var(--color-paper);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  transition: box-shadow var(--dur-hover) var(--ease-standard),
    border-color var(--dur-hover) var(--ease-standard);
}

.usinas-fat__input::placeholder {
  color: var(--color-smoke);
}

.usinas-fat__input:focus-visible {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: var(--shadow-focus);
}

.usinas-fat__linha-link {
  border: none;
  background: transparent;
  padding: 0;
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-primary-deep);
  cursor: pointer;
  text-align: left;
}

.usinas-fat__linha-link:hover {
  text-decoration: underline;
}

.usinas-fat__linha-link:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
  border-radius: var(--radius-sm);
}

.usinas-fat__sem-estado {
  color: var(--color-smoke);
  font-family: var(--font-mono);
}

.usinas-fat__acoes {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  justify-content: flex-end;
}

.usinas-fat__acao-icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  flex: none;
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  background: var(--color-paper);
  color: var(--color-graphite);
  cursor: pointer;
  text-decoration: none;
  transition:
    background-color var(--dur-hover) var(--ease-standard),
    border-color var(--dur-hover) var(--ease-standard),
    color var(--dur-hover) var(--ease-standard);
}

.usinas-fat__acao-icone:hover {
  background: rgba(243, 147, 37, 0.08);
  border-color: var(--color-primary);
  color: var(--color-primary-deep);
}

.usinas-fat__acao-icone--danger:hover {
  background: var(--color-danger-soft);
  border-color: var(--color-danger);
  color: var(--color-danger);
}

.usinas-fat__acao-icone:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.usinas-fat__erro {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
  background: var(--color-danger-soft);
  border-radius: var(--radius-md);
}

.usinas-fat__erro p {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--color-danger);
}

.usinas-fat__carregando {
  margin: 0;
  padding: var(--space-4) 0;
  font-size: var(--fs-sm);
  color: var(--color-slate);
}

.usinas-fat__paginacao {
  margin-top: var(--space-6);
  padding-top: var(--space-5);
  border-top: 1px solid var(--color-mist);
}
</style>
