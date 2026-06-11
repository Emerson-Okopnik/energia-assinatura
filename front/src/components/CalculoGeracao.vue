<template>
  <div class="container mt-5">
    <h3 class="mb-4">Gestão de Faturamento Usina</h3>
    <div class="mb-5">
      <label for="usinaSelect">Selecione a Usina:</label>
      <select id="usinaSelect" v-model="selectedUsinaId" @change="carregarDados" class="form-select">
        <option disabled value="">Selecione uma usina</option>
        <option v-for="usina in usinas" :key="usina.usi_id" :value="usina.usi_id">
          {{ usina.cliente.nome }} - {{ usina.dado_geracao?.media ?? 0 }} kWh
        </option>
      </select>
    </div>

    <h4 class="my-4">Cálculo de Geração da Usina - Expectativa</h4>
    <div class="row mb-3 align-items-end">
      <div class="col-md-6">
        <small class="text-muted">
          Previsão baseada na <strong>geração projetada</strong> da usina. O CUO considera apenas o Fio B —
          a fatura real de cada mês entra na <strong>apuração mensal</strong> abaixo.
        </small>
      </div>
      <div class="col-md-3">
        <label>Fio B (R$)</label>
        <div class="campo-info">{{ formatReais(fioB) }}</div>
      </div>
      <div class="col-md-3">
        <label>Percentual Lei 14300/23 (%)</label>
        <div class="campo-info">{{ formatNumero(percentualLei) }}%</div>
      </div>
    </div>

    <table class="table table-bordered">
      <thead class="table-dark">
        <tr>
          <th>Mês</th>
          <th>Geração</th>
          <th>Média Geração</th>
          <th>Fixo</th>
          <th>Injetado</th>
          <th>Creditado</th>
          <th>CUO</th>
          <th>Valor Final a Receber</th>
        </tr>
      </thead>
      <tbody v-if="projecaoAnual.length">
        <tr v-for="linha in projecaoAnual" :key="linha.mes">
          <td>{{ linha.mes_nome ? (linha.mes_nome.charAt(0).toUpperCase() + linha.mes_nome.slice(1)) : '' }}</td>
          <td :class="{ 'text-danger': linha.geracao_kwh === menorGeracao }">{{ formatKwh(linha.geracao_kwh) }}</td>
          <td>{{ formatKwh(linha.media_kwh) }}</td>
          <td>{{ formatReais(linha.fixo_reais) }}</td>
          <td>{{ formatReais(linha.injetado_reais) }}</td>
          <td>{{ formatReais(linha.creditado_reais) }}</td>
          <td>{{ formatReais(linha.cuo_reais) }}</td>
          <td>{{ formatReais(linha.valor_final_reais) }}</td>
        </tr>
      </tbody>
      <tbody v-else>
        <tr>
          <td colspan="8" class="text-center text-muted">
            Selecione uma usina para ver a projeção anual.
          </td>
        </tr>
      </tbody>
    </table>

    <div class="grafico-container mt-5" style="width: 860px; height: 400px;">
      <Bar v-if="chartData && chartData.labels" :data="chartData" :options="chartOptions" />
    </div>

    <h4 class="my-4">Faturamento da Usina no Mês</h4>
    <div class="row mb-5">
      <div class="col-md-2">
        <label for="anoFaturamentoInput">Ano:</label>
        <input id="anoFaturamentoInput" type="number" class="form-control" v-model.number="anoFaturamento" />
      </div>
      <div class="col-md-3">
        <label for="mes">Selecione o mês:</label>
        <select id="mes" v-model="mesSelecionado" class="form-select">
          <option v-for="(label, key) in meses" :key="key" :value="key">
            {{ label }}
          </option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="mesGeracao">Geração de {{ meses[mesSelecionado] }}:</label>
        <input id="mesGeracao" type="number" step="0.01" class="form-control" v-model.number="mesGeracao" @input="agendarPreview" />
      </div>
      <div class="col-md-4">
        <label for="valorGeracaoMes">Valor Gerado (R$):</label>
        <div class="campo-info">{{ formatReais(previewMes?.termos?.valor_variavel_reais) }}</div>
      </div>
    </div>

    <div class="row mb-4">
      <div class="col-md-4">
        <label for="consumoUsinaMes">Consumo da usina em {{ meses[mesSelecionado] || 'Mês' }} (kWh)</label>
        <input
          id="consumoUsinaMes"
          type="number"
          step="0.01"
          class="form-control"
          v-model.number="consumoUsinaMes"
          :disabled="!mesSelecionado || !usina"
          required
        />
      </div>
      <div class="col-md-4">
        <label for="fatura">Fatura de Energia da Usina (R$) — {{ meses[mesSelecionado] || 'mês' }}</label>
        <input
          id="fatura"
          type="number"
          step="0.01"
          class="form-control"
          v-model.number="faturaEnergia"
          :disabled="!mesSelecionado || !usina"
          @input="agendarPreview"
        />
      </div>
      <div class="col-md-4">
        <label for="adicionalCuo">Adicional (R$)</label>
        <input
          id="adicionalCuo"
          type="number"
          step="0.01"
          class="form-control"
          v-model.number="adicionalCuo"
          placeholder="Ex: 15,00 ou -10,00"
          :disabled="!mesSelecionado || !usina"
          @input="agendarPreview"
        />
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-md-4">
        <label>Valor em Créditos (R$):</label>
        <div class="campo-info">{{ formatReais(previewMes?.termos?.credito_reais) }}</div>
      </div>
      <div class="col-md-4">
        <label>Energia acumulada (kWh):</label>
        <div class="campo-info">{{ formatKwh(previewMes?.reserva?.guardado_kwh) }}</div>
      </div>
      <div class="col-md-4">
        <label>Valor Total a Ser pago (R$):</label>
        <div class="campo-info">{{ formatReais(previewMes?.termos?.valor_final_reais) }}</div>
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-md-6">
        <label>Emissão de CO₂ evitada (kg):</label>
        <div class="campo-info">{{ formatNumero(previewMes?.parametros?.co2_evitado_kg) }}</div>
      </div>
      <div class="col-md-6">
        <label>Árvores equivalentes:</label>
        <div class="campo-info">{{ formatNumero(previewMes?.parametros?.arvores_equivalentes) }}</div>
      </div>
    </div>

    <!-- Breakdown de auditoria (REGRAS_DE_CALCULO.md §8/§9) -->
    <div v-if="previewMes" class="card mb-5">
      <div class="card-header fw-bold">Detalhamento de Auditoria</div>
      <div class="card-body">
        <h6 class="text-muted">Geração Líquida (§9)</h6>
        <table class="table table-sm table-bordered mb-4">
          <tbody>
            <tr>
              <th>Geração bruta</th>
              <td>{{ formatKwh(previewMes.geracao?.bruta_kwh) }}</td>
            </tr>
            <tr>
              <th>Desconto de rede ({{ previewMes.geracao?.rede || '—' }})</th>
              <td>− {{ formatKwh(previewMes.geracao?.desconto_rede_kwh) }}</td>
            </tr>
            <tr>
              <th>Consumo do mês</th>
              <td>{{ formatKwh(previewMes.geracao?.consumo_kwh) }}</td>
            </tr>
            <tr class="table-light fw-bold">
              <th>Geração líquida</th>
              <td>{{ formatKwh(previewMes.geracao?.liquida_kwh) }}</td>
            </tr>
          </tbody>
        </table>

        <h6 class="text-muted">Crédito Resgatado por Origem — FIFO (§6)</h6>
        <table v-if="consumoFifo.length" class="table table-sm table-bordered mb-4">
          <thead class="table-light">
            <tr>
              <th>Mês de origem</th>
              <th>Energia consumida</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in consumoFifo" :key="`fifo-${idx}`">
              <td>{{ item.origem }}</td>
              <td>{{ formatKwh(item.kwh) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted mb-4">Nenhum crédito resgatado neste mês.</p>

        <h6 class="text-muted">Crédito Expirado (§7)</h6>
        <table v-if="expiracao.length" class="table table-sm table-bordered mb-0">
          <thead class="table-light">
            <tr>
              <th>Mês de origem</th>
              <th>Energia expirada</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in expiracao" :key="`exp-${idx}`">
              <td>{{ item.origem }}</td>
              <td>{{ formatKwh(item.kwh) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted mb-0">Nenhum crédito expirou neste mês.</p>
      </div>
    </div>

    <table class="table table-bordered mt-4">
      <thead class="table-dark">
        <tr>
          <th>Mês</th>
          <th>Geração</th>
          <th>Valor Guardado</th>
          <th>Creditado</th>
          <th>Valor Pago</th>
          <th v-if="ultimoRevertivel">Ação</th>
        </tr>
      </thead>
      <tbody v-if="temDadosGeracaoTabela">
        <template v-for="([chaveMes, labelMes]) in Object.entries(meses)" :key="chaveMes">
          <tr v-if="temDadosMes(chaveMes)">
            <td>{{ labelMes }}</td>
            <td>{{ formatKwh(dadosGeracaoRealMensal[chaveMes]) }}</td>
            <td>{{ formatKwh(dadosFaturamentoAnual?.valor_acumulado_reserva?.[chaveMes]) }}</td>
            <td>{{ formatReais(dadosFaturamentoAnual?.creditos_distribuidos?.[chaveMes]) }}</td>
            <td>{{ formatReais(dadosFaturamentoAnual?.faturamento_usina?.[chaveMes]) }}</td>
            <td v-if="ultimoRevertivel">
              <button
                v-if="ultimoRevertivel.mes_nome === chaveMes && ultimoRevertivel.ano === anoFaturamento"
                class="btn btn-sm btn-outline-danger"
                :disabled="isRevertendo"
                @click="confirmarEstorno(chaveMes)"
              >
                {{ isRevertendo ? 'Revertendo...' : 'Reverter' }}
              </button>
            </td>
          </tr>
        </template>
      </tbody>
      <tbody v-else>
        <tr>
          <td :colspan="ultimoRevertivel ? 6 : 5" class="text-center">Nenhum dado de geração real disponível para o ano selecionado.</td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td :colspan="ultimoRevertivel ? 6 : 5" class="text-center">
            <button class="btn btn-secondary me-2" @click="voltarAno">← Ano Anterior</button>
            <strong>{{ anoFaturamento }}</strong>
            <button class="btn btn-secondary ms-2" @click="avancarAno">Próximo Ano →</button>
          </td>
        </tr>
        <p>Geração já descontado o consumo</p>
      </tfoot>
    </table>

    <div v-if="historicoEstorno.length" class="mb-4">
      <button class="btn btn-link p-0 text-secondary" @click="mostrarHistorico = !mostrarHistorico">
        {{ mostrarHistorico ? '▲ Ocultar histórico de alterações' : '▼ Ver histórico de alterações' }}
      </button>
      <table v-if="mostrarHistorico" class="table table-sm table-bordered mt-2">
        <thead class="table-light">
          <tr>
            <th>Competência</th>
            <th>Lançado por</th>
            <th>Lançado em</th>
            <th>Revertido por</th>
            <th>Revertido em</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in historicoEstorno" :key="h.he_id">
            <td>{{ ucfirst(h.mes_nome) }}/{{ h.ano }}</td>
            <td>{{ h.lancado_por || '—' }}</td>
            <td>{{ formatarData(h.created_at) }}</td>
            <td>{{ h.revertido_por || '—' }}</td>
            <td>{{ h.revertido_em ? formatarData(h.revertido_em) : '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-end mb-5">
      <button class="btn btn-success" @click="salvarValoresMensais" :disabled="isSalvandoConsumoUsina">
        {{ isSalvandoConsumoUsina ? 'Salvando consumo...' : 'Salvar Valores' }}
      </button>
    </div>

    <div class="mb-4">
      <h5>Reserva Total Acumulada</h5>
      <p :class="['fs-5 fw-bold p-2 rounded', reservaClasse]">
        {{ formatKwh(reservaTotal) }}
      </p>
    </div>

    <div class="mb-4">
      <label for="observacoes" class="form-label">Observações:</label>
      <textarea id="observacoes" v-model="observacoes" rows="3" class="form-control"></textarea>
    </div>

    <button @click="gerarPDF" class="btn btn-orange">
      Baixar PDF da Usina
    </button>
    <div class="mt-4 d-flex align-items-center">
      <button type="button" class="btn btn-secondary ms-2" @click="goBack">Voltar</button>
    </div>
  </div>
</template>

<script>
import Swal from 'sweetalert2';
import axios from 'axios';
import {
  Chart as ChartJS,
  Title, Tooltip, Legend, LineElement, PointElement,
  BarElement, CategoryScale, LinearScale
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import { formatKwh, formatNumero, formatReais } from '../utils/formatters.js';

ChartJS.register(
  Title, Tooltip, Legend, BarElement, LineElement,
  PointElement, CategoryScale, LinearScale
);

export default {
  components: { Bar },
  data() {
    return {
      usinas: [],
      selectedUsinaId: '',
      fioB: 0.13,
      faturaEnergia: 0,
      adicionalCuo: 0,
      percentualLei: 45,
      mesesGeracao: {},
      chartData: null,
      chartOptions: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top' } },
        scales: { y: { stacked: true }, x: { stacked: true } }
      },
      meses: {
        janeiro: "Janeiro", fevereiro: "Fevereiro", marco: "Março", abril: "Abril",
        maio: "Maio", junho: "Junho", julho: "Julho", agosto: "Agosto",
        setembro: "Setembro", outubro: "Outubro", novembro: "Novembro", dezembro: "Dezembro"
      },
      mesSelecionado: '',
      mesGeracao: 0,
      // Resposta do GET .../preview — fonte ÚNICA de exibição do mês.
      previewMes: null,
      previewTimer: null,
      // Projeção anual (Expectativa) — 12 meses do GET .../projecao.
      projecaoAnual: [],
      projecaoTimer: null,
      // Inputs salvos por mês (fatura/consumo) p/ pré-preencher ao reabrir o mês.
      inputsSalvos: {},
      menorGeracao: 0,
      usina: null,
      anoFaturamento: new Date().getFullYear(),
      dadosFaturamentoAnual: null,
      dadosGeracaoRealMensal: {},
      observacoes: '',
      consumoUsinaMes: null,
      isSalvandoConsumoUsina: false,
      ultimoRevertivel: null,
      historicoEstorno: [],
      mostrarHistorico: false,
      isRevertendo: false,
    };
  },
  watch: {
    mesSelecionado() {
      // Pré-preenche geração, consumo e fatura com o que foi SALVO do mês (se houver)
      // e dispara o preview — para a Expectativa do mês bater com o que foi gravado.
      const geracaoCadastrada = Number(this.dadosGeracaoRealMensal?.[this.mesSelecionado]) || 0;
      const salvo = this.inputsSalvos?.[this.mesSelecionado] || {};
      this.mesGeracao = geracaoCadastrada;
      this.consumoUsinaMes = (salvo.consumo !== null && salvo.consumo !== undefined) ? salvo.consumo : null;
      this.faturaEnergia = (salvo.fatura_energia !== null && salvo.fatura_energia !== undefined) ? salvo.fatura_energia : 0;
      this.adicionalCuo = 0;
      this.previewMes = null;
      // chartData NÃO é zerado: o gráfico é a projeção anual (independe do mês).
      if (this.usina?.usi_id && this.mesSelecionado && geracaoCadastrada > 0) {
        this.agendarPreview();
      }
    },
    consumoUsinaMes() {
      this.agendarPreview();
    },
  },
  computed: {
    consumoFifo() {
      return Array.isArray(this.previewMes?.consumo_fifo) ? this.previewMes.consumo_fifo : [];
    },
    expiracao() {
      return Array.isArray(this.previewMes?.expiracao) ? this.previewMes.expiracao : [];
    },
    reservaTotal() {
      return this.dadosFaturamentoAnual?.valor_acumulado_reserva?.total ?? 0;
    },
    reservaClasse() {
      return Number(this.reservaTotal) >= 0 ? 'text-success' : 'text-danger';
    },
    temDadosGeracaoTabela() {
      return Object.keys(this.meses).some((mes) => this.temDadosMes(mes));
    },
  },
  methods: {
    formatReais,
    formatKwh,
    formatNumero,
    temDadosMes(chaveMes) {
      const geracao = Number(this.dadosGeracaoRealMensal?.[chaveMes] || 0);
      const faturamento = Number(this.dadosFaturamentoAnual?.faturamento_usina?.[chaveMes] || 0);
      const reserva = Number(this.dadosFaturamentoAnual?.valor_acumulado_reserva?.[chaveMes] || 0);
      const creditado = Number(this.dadosFaturamentoAnual?.creditos_distribuidos?.[chaveMes] || 0);

      return geracao > 0 || faturamento > 0 || reserva > 0 || creditado > 0;
    },
    async fetchUsinas() {
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');
      try {
        const response = await axios.get(`${baseURL}/usina`, {
          headers: { Authorization: `Bearer ${token}` }
        });
        this.usinas = response.data;
      } catch (err) {
        console.error('Erro ao buscar usinas:', err);
      }
    },
    mesIndexAtual() {
      return Object.keys(this.meses).indexOf(this.mesSelecionado) + 1;
    },
    // Agenda (debounce) a busca do preview no backend.
    agendarPreview() {
      if (this.previewTimer) {
        clearTimeout(this.previewTimer);
      }
      this.previewTimer = setTimeout(() => this.carregarPreview(), 300);
    },
    // O frontend NÃO calcula nada: só lê o preview do backend.
    async carregarPreview() {
      if (!this.usina?.usi_id || !this.mesSelecionado || !this.anoFaturamento) {
        this.previewMes = null;
        this.chartData = null;
        return;
      }

      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');
      const mesIndex = this.mesIndexAtual();

      // Só envia consumo quando o usuário digitou um — senão o backend usa o consumo
      // já cadastrado do mês (dados_consumo_usina), mantendo a expectativa coerente.
      const params = {
        geracao_bruta_kwh: Number(this.mesGeracao) || 0,
        fatura_energia: Number(this.faturaEnergia) || 0,
        adicional_cuo: Number(this.adicionalCuo) || 0,
      };
      if (this.consumoUsinaMes !== null && this.consumoUsinaMes !== '') {
        params.consumo = Number(this.consumoUsinaMes) || 0;
      }

      try {
        const response = await axios.get(
          `${baseURL}/usinas/${this.usina.usi_id}/faturamento/${this.anoFaturamento}/mes/${mesIndex}/preview`,
          {
            headers: { Authorization: `Bearer ${token}` },
            params,
          }
        );

        this.previewMes = response.data?.data ?? response.data ?? null;
        // O gráfico e a tabela de Expectativa vêm da projeção anual (não do preview do mês).
      } catch (error) {
        console.error('Erro ao carregar preview do faturamento:', error);
        this.previewMes = null;
        this.chartData = null;
      }
    },
    async carregarFaturamentoAno() {
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');
      const headers = { Authorization: `Bearer ${token}` };

      try {
        const [faturamentoResp, geracaoResp, revertivelResp, historicoResp] = await Promise.all([
          axios.get(`${baseURL}/creditos-distribuidos-usina/usina/${this.selectedUsinaId}/ano/${this.anoFaturamento}`, { headers }),
          axios.get(`${baseURL}/dados-geracao-real-usina/usina/${this.selectedUsinaId}`, { headers }),
          axios.get(`${baseURL}/usinas/${this.selectedUsinaId}/ultimo-revertivel`, { headers }),
          axios.get(`${baseURL}/usinas/${this.selectedUsinaId}/historico-estorno`, { headers }),
        ]);

        this.dadosFaturamentoAnual = faturamentoResp.data[0];

        const geracaoDados = Array.isArray(geracaoResp.data) ? geracaoResp.data : [];
        const geracaoAno = geracaoDados.find(item => Number(item.ano) === Number(this.anoFaturamento));
        this.dadosGeracaoRealMensal = geracaoAno?.dados_geracao_real || {};

        this.ultimoRevertivel = revertivelResp.data;
        this.historicoEstorno = Array.isArray(historicoResp.data) ? historicoResp.data : [];
      } catch (error) {
        console.error('Erro ao carregar dados de faturamento ou geração:', error);
        this.dadosFaturamentoAnual = null;
        this.dadosGeracaoRealMensal = {};
        this.ultimoRevertivel = null;
        this.historicoEstorno = [];
      }
    },
    avancarAno() {
      this.anoFaturamento++;
      this.carregarFaturamentoAno();
      this.carregarProjecao();
      this.carregarInputsSalvos();
      this.agendarPreview();
    },
    voltarAno() {
      if (this.anoFaturamento > 2024) { // limite inferior
        this.anoFaturamento--;
        this.carregarFaturamentoAno();
        this.carregarProjecao();
        this.carregarInputsSalvos();
        this.agendarPreview();
      }
    },
    // O gráfico LÊ os termos do preview do mês (sem cálculo local).
    gerarGrafico() {
      if (!this.projecaoAnual.length) {
        this.chartData = null;
        return;
      }

      const p = this.projecaoAnual;
      const labels = p.map(l => (l.mes_nome ? l.mes_nome.charAt(0).toUpperCase() + l.mes_nome.slice(1) : ''));

      this.chartData = {
        labels,
        datasets: [
          { type: 'bar', label: 'Fixo', data: p.map(l => Number(l.fixo_reais) || 0), backgroundColor: '#60a5fa', stack: 'montagem', order: 2 },
          { type: 'bar', label: 'Injetado', data: p.map(l => Number(l.injetado_reais) || 0), backgroundColor: '#FFA500', stack: 'montagem', order: 3 },
          { type: 'bar', label: 'Creditado', data: p.map(l => Number(l.creditado_reais) || 0), backgroundColor: '#4ade80', stack: 'montagem', order: 4 },
          { type: 'bar', label: 'CUO', data: p.map(l => Number(l.cuo_reais) || 0), backgroundColor: '#f87171', stack: 'montagem', order: 5 },
          { type: 'line', label: 'Valor Final a Receber', data: p.map(l => Number(l.valor_final_reais) || 0), borderColor: '#1e40af', borderWidth: 2, fill: false, pointRadius: 3, tension: 0.3, order: 1 }
        ]
      };
    },
    agendarProjecao() {
      if (this.projecaoTimer) clearTimeout(this.projecaoTimer);
      this.projecaoTimer = setTimeout(() => this.carregarProjecao(), 300);
    },
    async carregarInputsSalvos() {
      if (!this.usina?.usi_id || !this.anoFaturamento) {
        this.inputsSalvos = {};
        return;
      }
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');
      try {
        const response = await axios.get(
          `${baseURL}/usinas/${this.usina.usi_id}/inputs-salvos/${this.anoFaturamento}`,
          { headers: { Authorization: `Bearer ${token}` } }
        );
        this.inputsSalvos = response.data?.data ?? {};
      } catch (error) {
        console.error('Erro ao carregar inputs salvos:', error);
        this.inputsSalvos = {};
      }
    },
    async carregarProjecao() {
      if (!this.usina?.usi_id || !this.anoFaturamento) {
        this.projecaoAnual = [];
        this.chartData = null;
        return;
      }
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');
      try {
        // Projeção é uma PREVISÃO: não usa fatura real (CUO só com Fio B).
        const response = await axios.get(
          `${baseURL}/usinas/${this.usina.usi_id}/projecao/${this.anoFaturamento}`,
          { headers: { Authorization: `Bearer ${token}` } }
        );
        this.projecaoAnual = response.data?.data ?? [];
        this.gerarGrafico();
      } catch (error) {
        console.error('Erro ao carregar projeção anual:', error);
        this.projecaoAnual = [];
        this.chartData = null;
      }
    },
    async carregarDados() {
      if (!this.selectedUsinaId) return;

      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');
      this.consumoUsinaMes = null;
      this.previewMes = null;
      this.chartData = null;
      try {
        const response = await axios.get(`${baseURL}/usina/${this.selectedUsinaId}`, {
          headers: { Authorization: `Bearer ${token}` }
        });

        const usina = response.data;
        const g = usina.dado_geracao || {};
        const comercializacao = usina.comercializacao || {};

        const fioB = parseFloat(comercializacao.fio_b);
        const percentualLei = parseFloat(comercializacao.percentual_lei);

        this.fioB = Number.isFinite(fioB) && fioB > 0 ? fioB : 0.13;
        this.percentualLei = Number.isFinite(percentualLei) && percentualLei > 0 ? percentualLei : 45;
        this.menorGeracao = Number(g.menor_geracao) || 0;

        this.mesesGeracao = {
          Janeiro: g.janeiro, Fevereiro: g.fevereiro, Março: g.marco,
          Abril: g.abril, Maio: g.maio, Junho: g.junho,
          Julho: g.julho, Agosto: g.agosto, Setembro: g.setembro,
          Outubro: g.outubro, Novembro: g.novembro, Dezembro: g.dezembro
        };

        this.usina = response.data;
        await this.carregarFaturamentoAno();
        await this.carregarProjecao();
        await this.carregarInputsSalvos();
      } catch (error) {
        console.error('Erro ao carregar dados da usina:', error);
      }
    },

    async salvarConsumoUsina({ silencioso = false } = {}) {
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');

      if (!this.usina?.usi_id || !this.mesSelecionado || !this.anoFaturamento) {
        Swal.fire({
          icon: 'warning',
          title: 'Dados insuficientes',
          text: 'Selecione a usina, mês e ano antes de salvar o consumo.',
        });
        return false;
      }

      if (this.consumoUsinaMes === null || Number.isNaN(this.consumoUsinaMes)) {
        Swal.fire({
          icon: 'warning',
          title: 'Consumo obrigatório',
          text: 'Informe o consumo da usina para o mês selecionado.',
        });
        return false;
      }

      const cliId = this.usina?.cli_id || this.usina?.cliente?.cli_id;
      if (!cliId) {
        Swal.fire({
          icon: 'error',
          title: 'Cliente não encontrado',
          text: 'Não foi possível identificar o cliente da usina para salvar o consumo.',
        });
        return false;
      }

      this.isSalvandoConsumoUsina = true;

      try {
        const headers = { Authorization: `Bearer ${token}` };
        const mesIndex = this.mesIndexAtual();

        // UPSERT: atualiza só o mês no registro do ano (não cria duplicata nem zera
        // os outros meses) — corrige o bug das múltiplas linhas de consumo.
        await axios.post(
          `${baseURL}/usinas/${this.usina.usi_id}/consumo/${this.anoFaturamento}/mes/${mesIndex}`,
          { consumo: parseFloat(this.consumoUsinaMes || 0) },
          { headers }
        );

        if (!silencioso) {
          Swal.fire({
            icon: 'success',
            title: 'Consumo salvo!',
            text: 'O consumo da usina foi registrado para o mês selecionado.',
          });
        }

        return true;
      } catch (error) {
        console.error('Erro ao salvar consumo da usina:', error);
        Swal.fire({
          icon: 'error',
          title: 'Falha ao salvar',
          text: 'Não foi possível registrar o consumo da usina. Tente novamente.',
        });
        return false;
      } finally {
        this.isSalvandoConsumoUsina = false;
      }
    },

    async salvarValoresMensais() {
      const token = localStorage.getItem('token');
      const baseURL = import.meta.env.VITE_API_URL;
      const idempotencyKey = self.crypto?.randomUUID?.() || Date.now().toString();
      const headers = {
        Authorization: `Bearer ${token}`,
        'Idempotency-Key': idempotencyKey
      };

      try {
        if (!this.usina?.usi_id || !this.mesSelecionado || !this.anoFaturamento) {
          throw new Error('Usina, mês ou ano não definido');
        }

        if (this.consumoUsinaMes === null || Number.isNaN(this.consumoUsinaMes)) {
          Swal.fire({
            icon: 'warning',
            title: 'Consumo obrigatório',
            text: 'Informe o consumo da usina antes de salvar os valores.',
          });
          return;
        }

        const consumoSalvo = await this.salvarConsumoUsina({ silencioso: true });

        if (!consumoSalvo) {
          throw new Error('Falha ao salvar consumo da usina');
        }

        const mesIndex = this.mesIndexAtual();

        // O payload envia somente INPUTS; o backend calcula e persiste.
        const payload = {
          geracao_bruta_kwh: parseFloat(this.mesGeracao || 0),
          consumo: parseFloat(this.consumoUsinaMes || 0),
          fatura_energia: parseFloat(this.faturaEnergia || 0),
          adicional_cuo: parseFloat(this.adicionalCuo || 0),
        };

        await axios.post(
          `${baseURL}/usinas/${this.usina.usi_id}/faturamento/${this.anoFaturamento}/mes/${mesIndex}/calculo`,
          payload,
          { headers }
        );

        Swal.fire({
          icon: 'success',
          title: 'Valores salvos!',
          text: 'As informações da usina foram atualizadas com sucesso.',
          confirmButtonColor: '#3085d6',
          confirmButtonText: 'OK'
        });

        await this.carregarFaturamentoAno();
        await this.carregarPreview();

      } catch (error) {
        console.error('Erro ao salvar valores mensais:', error);
        Swal.fire({
          icon: 'error',
          title: 'Erro ao salvar',
          text: 'Verifique se os dados estão corretos ou se a usina foi carregada.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
      }
    },

    ucfirst(str) {
      if (!str) return '';
      return str.charAt(0).toUpperCase() + str.slice(1);
    },

    formatarData(isoString) {
      if (!isoString) return '—';
      const d = new Date(isoString);
      return d.toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: 'America/Sao_Paulo',
      });
    },

    async confirmarEstorno(chaveMes) {
      const label = this.meses[chaveMes];
      const result = await Swal.fire({
        icon: 'warning',
        title: `Reverter ${label}/${this.anoFaturamento}?`,
        html: 'Esta ação desfaz o lançamento e limpa o cache do PDF deste mês.<br>O lançamento poderá ser feito novamente após a reversão.',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, reverter',
        cancelButtonText: 'Cancelar',
      });

      if (result.isConfirmed) {
        await this.executarEstorno(chaveMes);
      }
    },

    async executarEstorno(chaveMes) {
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');
      const mesIndex = Object.keys(this.meses).indexOf(chaveMes) + 1;

      this.isRevertendo = true;
      try {
        await axios.post(
          `${baseURL}/usinas/${this.selectedUsinaId}/faturamento/${this.anoFaturamento}/mes/${mesIndex}/estorno`,
          {},
          { headers: { Authorization: `Bearer ${token}` } }
        );

        Swal.fire({
          icon: 'success',
          title: 'Revertido!',
          text: 'O lançamento foi desfeito. Você pode lançar novamente.',
          confirmButtonColor: '#3085d6',
        });

        await this.carregarFaturamentoAno();
      } catch (error) {
        const msg = error.response?.data?.error || 'Não foi possível reverter o lançamento.';
        Swal.fire({ icon: 'error', title: 'Erro ao reverter', text: msg, confirmButtonColor: '#d33' });
      } finally {
        this.isRevertendo = false;
      }
    },

    async gerarPDF() {
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');

      const hoje = new Date();
      const anoAtual = hoje.getFullYear();

      const mesGeracao = [
        'janeiro', 'fevereiro', 'marco', 'abril',
        'maio', 'junho', 'julho', 'agosto',
        'setembro', 'outubro', 'novembro', 'dezembro'
      ].indexOf(this.mesSelecionado) + 1;
      const anoGeracao = this.anoFaturamento || anoAtual;

      try {
        Swal.fire({
          title: 'Gerando PDF...',
          html: 'Aguarde enquanto preparamos o documento.',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        const response = await axios.get(`${baseURL}/gerar-pdf-usina/${this.selectedUsinaId}`, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
          params: {
            observacoes: this.observacoes,
            mes: mesGeracao,
            ano: anoGeracao,
            fatura: this.faturaEnergia,
            adicional_cuo: Number(this.adicionalCuo) || 0,
          },
          responseType: 'blob'
        });

        Swal.close();

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `fatura ${this.usina.cliente.nome} - ${this.selectedUsinaId}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);

      } catch (error) {
        console.error('Erro ao gerar PDF:', error);
        Swal.close();
        Swal.fire({
          icon: 'error',
          title: 'Erro ao gerar PDF',
          text: 'Não foi possível gerar o PDF.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Fechar'
        });
      }
    },
    goBack() {
      this.$router.push('/Home');
    },

  },
  mounted() {
    this.fetchUsinas();

    // Define o mês atual como chave
    const index = new Date().getMonth(); // 0 a 11
    const chaveAtual = Object.keys(this.meses)[index];
    this.mesSelecionado = chaveAtual;
  }
}
</script>

<style scoped>
label {
  font-weight: 500;
}

.text-danger {
  color: #dc3545 !important;
}

.btn-orange{
  color: white;
  background-color: #f28c1f;
}

.btn-orange:hover{
  color: white;
  background-color: #d97706;
}

.campo-info {
  background-color: #f8f9fa;
  border: 1px solid #ced4da;
  border-radius: 0.375rem;
  min-height: calc(2.25rem + 2px);
  padding: 0.375rem 0.75rem;
  display: flex;
  align-items: center;
  font-weight: 600;
}
</style>
