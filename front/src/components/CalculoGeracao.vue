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
    <div class="row mb-3">
      <div class="col-md-4">
        <label for="fatura">Fatura de Energia da Usina (R$)</label>
        <input id="fatura" type="number" step="0.01" class="form-control" v-model.number="faturaEnergia" />
      </div>
      <div class="col-md-4">
        <label>Fio B (R$)</label>
        <div class="campo-info">R$ {{ formatCurrency(fioB) }}</div>
      </div>
      <div class="col-md-4">
        <label>Percentual Lei 14300/23 (%)</label>
        <div class="campo-info">{{ formatPercent(percentualLei) }}%</div>
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
      <tbody>
        <tr v-for="(valor, mes) in mesesGeracao" :key="mes">
          <td>{{ mes }}</td>
          <td :class="{ 'text-danger': valor == menorGeracao }">{{ valor.toFixed(2) }} kWh</td>
          <td>{{ mediaGeracao }} kWh</td>
          <td>R$ {{ fixo.toFixed(2) }}</td>
          <td>R$ {{ injetado(valor).toFixed(2) }}</td>
          <td>R$ {{ creditadoTabela(valor).toFixed(2) }}</td>
          <td>R$ {{ cuo(valor).toFixed(2) }}</td>
          <td>R$ {{ valorFinalTabela(valor).toFixed(2) }}</td>
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
        <input id="mesGeracao" type="number" step="0.01" class="form-control" v-model.number="mesGeracao" @input="atualizarValores" />
      </div>
      <div class="col-md-4">
        <label for="valorGeracaoMes">Valor Gerado (R$):</label>
        <input id="valorGeracaoMes" type="number" step="0.01" class="form-control" v-model.number="valorGeracaoMes" readonly />
      </div>
    </div>

    <div class="row mb-4">
      <div class="col-md-6">
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
    </div>

    <div class="row mb-5">
      <div class="col-md-4">
        <label for="credito">Valor em Créditos (R$):</label>
        <input id="credito" type="number" step="0.01" class="form-control" v-model.number="credito" readonly />
      </div>
      <div class="col-md-4">
        <label for="valorGuardado">Energia acumulada (kWh):</label>
        <input id="valorGuardado" type="number" step="0.01" class="form-control" v-model.number="valorGuardado"
          readonly />
      </div>
      <div class="col-md-4">
        <label for="valorTotal">Valor Total a Ser pago (R$):</label>
        <input id="valorTotal" type="number" step="0.01" class="form-control" v-model.number="valorTotal" readonly />
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-md-6">
        <label for="co2Evitado">Emissão de CO₂ evitada (kg):</label>
        <input id="co2Evitado" type="number" step="0.01" class="form-control" v-model.number="co2Evitado" readonly />
      </div>
      <div class="col-md-6">
        <label for="arvoresPlantadas">Árvores equivalentes:</label>
        <input id="arvoresPlantadas" type="number" step="0.01" class="form-control" v-model.number="arvoresPlantadas" readonly />
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
        </tr>
      </thead>
      <tbody v-if="temDadosGeracaoTabela">
        <template v-for="([chaveMes, labelMes]) in Object.entries(meses)" :key="chaveMes">
          <tr v-if="temDadosMes(chaveMes)">
            <td>{{ labelMes }}</td>
            <td>{{ formatKwh(dadosGeracaoRealMensal[chaveMes]) }}</td>
            <td>{{ formatKwh(dadosFaturamentoAnual?.valor_acumulado_reserva?.[chaveMes]) }}</td>
            <td>R$ {{ formatMoeda(dadosFaturamentoAnual?.creditos_distribuidos?.[chaveMes]) }}</td>
            <td>R$ {{ formatMoeda(dadosFaturamentoAnual?.faturamento_usina?.[chaveMes]) }}</td>
          </tr>
        </template>
      </tbody>
      <tbody v-else>
        <tr>
          <td colspan="5" class="text-center">Nenhum dado de geração real disponível para o ano selecionado.</td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="text-center">
            <button class="btn btn-secondary me-2" @click="voltarAno">← Ano Anterior</button>
            <strong>{{ anoFaturamento }}</strong>
            <button class="btn btn-secondary ms-2" @click="avancarAno">Próximo Ano →</button>
          </td>
        </tr>
        <p>Geração já descontado o consumo</p>
      </tfoot>
    </table>


    <div class="d-flex justify-content-end mb-5">
      <button class="btn btn-success" @click="salvarValoresMensais" :disabled="isSalvandoConsumoUsina">
        {{ isSalvandoConsumoUsina ? 'Salvando consumo...' : 'Salvar Valores' }}
      </button>
    </div>

    <div class="mb-4">
      <h5>Reserva Total Acumulada</h5>
      <p :class="['fs-5 fw-bold p-2 rounded', reservaClasse]">
        {{ reservaTotal }} kWh
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
      percentualLei: 45,
      valor_kwh: 0,
      valor_fixo: 0,
      mediaGeracao: 0,
      menorGeracao: 0,
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
      valorGeracaoMes: 0,
      credito: 0,
      valorGuardado: 0,
      valorTotal: 0,
      co2Evitado: 0,
      arvoresPlantadas: 0,
      usina: null,
      anoFaturamento: new Date().getFullYear(),
      dadosFaturamentoAnual: null,
      dadosGeracaoRealMensal: {},
      observacoes: '',
      consumoUsinaMes: null,
      isSalvandoConsumoUsina: false,
    };
  },
  watch: {
    mesSelecionado() {
      this.mesGeracao = 0;
      this.valorGeracaoMes = 0;
      this.credito = 0;
      this.valorGuardado = 0;
      this.valorTotal = 0;
      this.co2Evitado = 0;
      this.arvoresPlantadas = 0;
      this.consumoUsinaMes = null;
    },
    consumoUsinaMes() {
      if (this.mesGeracao !== null && this.mesGeracao !== undefined) {
        this.atualizarValores();
      }
    }
  },
  computed: {
    valorFinalFioB() {
      return this.fioB * (this.percentualLei / 100);
    },
    fixo() {
      return this.menorGeracao * this.valor_kwh;
    },
    mesesComValorPago() {
      if (!this.usina || !this.usina.creditos_distribuidos_usina) return [];

      return Object.entries(this.mesesGeracao).filter(([mesExibicao, _]) => {
        const mesKey = Object.keys(this.meses).find(key => this.meses[key] === mesExibicao);
        const valorPago = this.usina.creditos_distribuidos_usina.faturamento_usina[mesKey];
        return valorPago && valorPago > 0;
      });
    },
    reservaTotal() {
      const total = this.dadosFaturamentoAnual?.valor_acumulado_reserva?.total ?? 0;
      return parseFloat(total).toFixed(2);
    },
    reservaClasse() {
      return this.reservaTotal >= 0 ? 'text-success' : 'text-danger';
    },
    temDadosGeracaoTabela() {
      return Object.keys(this.meses).some((mes) => this.temDadosMes(mes));
    },
  },
  methods: {
    formatKwh(value) {
      const numero = Number(value) || 0;
      return `${numero.toFixed(2)} kWh`;
    },
    formatMoeda(value) {
      const numero = Number(value) || 0;
      return numero.toFixed(2);
    },
    temDadosMes(chaveMes) {
      const geracao = Number(this.dadosGeracaoRealMensal?.[chaveMes] || 0);
      const faturamento = Number(this.dadosFaturamentoAnual?.faturamento_usina?.[chaveMes] || 0);
      const reserva = Number(this.dadosFaturamentoAnual?.valor_acumulado_reserva?.[chaveMes] || 0);
      const creditado = Number(this.dadosFaturamentoAnual?.creditos_distribuidos?.[chaveMes] || 0);

      return geracao > 0 || faturamento > 0 || reserva > 0 || creditado > 0;
    },
    formatCurrency(value) {
      const numero = Number(value) || 0;
      return numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 8 });
    },
    formatPercent(value) {
      const numero = Number(value) || 0;
      return numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
    async carregarFaturamentoAno() {
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');
      try {
        const [faturamentoResp, geracaoResp] = await Promise.all([
          axios.get(`${baseURL}/creditos-distribuidos-usina/usina/${this.selectedUsinaId}/ano/${this.anoFaturamento}`, {
            headers: { Authorization: `Bearer ${token}` }
          }),
          axios.get(`${baseURL}/dados-geracao-real-usina/usina/${this.selectedUsinaId}`, {
            headers: { Authorization: `Bearer ${token}` }
          })
        ]);

        this.dadosFaturamentoAnual = faturamentoResp.data[0];
        const geracaoDados = Array.isArray(geracaoResp.data) ? geracaoResp.data : [];
        const geracaoAnoSelecionado = geracaoDados.find(item => Number(item.ano) === Number(this.anoFaturamento));
        this.dadosGeracaoRealMensal = geracaoAnoSelecionado?.dados_geracao_real || {};

      } catch (error) {
        console.error('Erro ao carregar dados de faturamento ou geração:', error);
        this.dadosFaturamentoAnual = null;
        this.dadosGeracaoRealMensal = {};
      }
    },
    avancarAno() {
      this.anoFaturamento++;
      this.carregarFaturamentoAno();
    },
    voltarAno() {
      if (this.anoFaturamento > 2024) { // limite inferior
        this.anoFaturamento--;
        this.carregarFaturamentoAno();
      }
    },
    atualizarValores() {
      const geracao = this.calcularGeracaoLiquida(this.mesGeracao);
      const media = Number(this.mediaGeracao);
      const kwh = Number(this.valor_kwh);
      const reservaTotal = parseFloat(this.dadosFaturamentoAnual?.valor_acumulado_reserva?.total || 0);

      this.valorGeracaoMes = +(geracao * kwh).toFixed(2);
      this.valorGuardado = 0;
      this.credito = 0;

      if (geracao >= media) {
        const excedente = geracao - media;
        this.valorGuardado = +excedente.toFixed(2);
      } else if (reservaTotal > 0) {
        const faltante = media - geracao;
        const energiaCompensada = Math.min(faltante, reservaTotal);

        this.credito = +(energiaCompensada * kwh).toFixed(2);
      }

      this.valorTotal = this.valorFinal(geracao).toFixed(2);

      this.co2Evitado = +(geracao * 0.4).toFixed(2);
      this.arvoresPlantadas = +(this.co2Evitado / 20).toFixed(2);
    },
    injetado(valor) {
      const media = this.mediaGeracao;
      const menor = this.menorGeracao;
      const kwh = this.valor_kwh;

      if (valor >= media) {
        return (media - menor) * kwh;
      } else {
        return (valor - menor) * kwh;
      }
    },
    creditado(valor) {
      const media = this.mediaGeracao;
      const kwh = this.valor_kwh;
      const reservaTotal = parseFloat(this.dadosFaturamentoAnual?.valor_acumulado_reserva?.total || 0);

      if (valor < media && reservaTotal > 0) {
        const faltante = media - valor;
        const valorCredito = faltante * kwh;
        return Math.min(valorCredito, reservaTotal);
      } else {
        return 0;
      }
    },
    creditadoTabela(valor) {
      const media = this.mediaGeracao;
      const kwh = this.valor_kwh;

      if (valor < media) {
        return (media - valor) * kwh;
      } else {
        return 0;
      }
    },
    cuo(valor) {
      return -1 * (this.faturaEnergia + (valor * this.valorFinalFioB));
    },
    valorFinal(valor) {
      return this.fixo + this.injetado(valor) + this.creditado(valor) + this.cuo(valor);
    },
    valorFinalTabela(valor) {
      return this.fixo + this.injetado(valor) + this.creditadoTabela(valor) + this.cuo(valor);
    },
    calcularGeracaoLiquida(geracaoBruta) {
      const consumoMes = Number(this.consumoUsinaMes) || 0;
      const geracaoInformada = Number(geracaoBruta) || 0;
      const descontoRede = this.getDescontoRede();

      const geracaoLiquida = geracaoInformada - (consumoMes - descontoRede);
      return Math.max(geracaoLiquida, 0);
    },
    getDescontoRede() {
      const rede = (this.usina?.rede || '').toLowerCase();

      if (rede.startsWith('trifásico')) return 100;
      if (rede.startsWith('bifásico')) return 50;
      if (rede.startsWith('monofásico')) return 30;
      return 0;
    },
    gerarGrafico() {
      const meses = Object.keys(this.mesesGeracao);
      if (!meses.length) return;

      this.chartData = {
        labels: meses,
        datasets: [
          { type: 'bar', label: 'Fixo', data: meses.map(() => this.fixo), backgroundColor: '#60a5fa', stack: 'montagem', order: 2 },
          { type: 'bar', label: 'Injetado', data: meses.map(m => this.injetado(this.mesesGeracao[m])), backgroundColor: '#FFA500', stack: 'montagem', order: 3 },
          { type: 'bar', label: 'Creditado', data: meses.map(m => this.creditadoTabela(this.mesesGeracao[m])), backgroundColor: '#4ade80', stack: 'montagem', order: 4 },
          { type: 'bar', label: 'CUO', data: meses.map(m => this.cuo(this.mesesGeracao[m])), backgroundColor: '#f87171', stack: 'montagem', order: 5 },
          { type: 'line', label: 'Valor Final a Receber', data: meses.map(m => this.valorFinalTabela(this.mesesGeracao[m])), borderColor: '#1e40af', borderWidth: 2, fill: false, pointRadius: 3, tension: 0.3, order: 1 }
        ]
      };
    },
    async carregarDados() {
      if (!this.selectedUsinaId) return;

      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');
      this.consumoUsinaMes = null;
      try {
        const response = await axios.get(`${baseURL}/usina/${this.selectedUsinaId}`, {
          headers: { Authorization: `Bearer ${token}` }
        });

        const usina = response.data;
        const g = usina.dado_geracao || {};
        const comercializacao = usina.comercializacao || {};

        this.valor_kwh = parseFloat(comercializacao.valor_kwh) || 0;
        this.valor_fixo = parseFloat(comercializacao.valor_fixo) || 0;

        const fioB = parseFloat(comercializacao.fio_b);
        const percentualLei = parseFloat(comercializacao.percentual_lei);

        this.fioB = Number.isFinite(fioB) && fioB > 0 ? fioB : 0.13;
        this.percentualLei = Number.isFinite(percentualLei) && percentualLei > 0 ? percentualLei : 45;

        this.mediaGeracao = g.media || 0;
        this.menorGeracao = g.menor_geracao || 0;

        this.mesesGeracao = {
          Janeiro: g.janeiro, Fevereiro: g.fevereiro, Março: g.marco,
          Abril: g.abril, Maio: g.maio, Junho: g.junho,
          Julho: g.julho, Agosto: g.agosto, Setembro: g.setembro,
          Outubro: g.outubro, Novembro: g.novembro, Dezembro: g.dezembro
        };

        this.gerarGrafico();
        this.usina = response.data;
        await this.carregarFaturamentoAno();
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
        const mesesKeys = Object.keys(this.meses);
        const consumoPayload = mesesKeys.reduce((acc, key) => {
          acc[key] = 0;
          return acc;
        }, {});

        consumoPayload[this.mesSelecionado] = parseFloat(this.consumoUsinaMes || 0);
        consumoPayload.media = consumoPayload[this.mesSelecionado];

        const consumoResponse = await axios.post(`${baseURL}/consumo`, consumoPayload, { headers });
        const dcon_id = consumoResponse.data?.id;

        await axios.post(`${baseURL}/dados-consumo-usina`, {
          usi_id: this.usina.usi_id,
          cli_id: cliId,
          dcon_id,
          ano: this.anoFaturamento,
        }, { headers });

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

        const mesIndex = Object.keys(this.meses).indexOf(this.mesSelecionado) + 1;

        const geracaoLiquida = this.calcularGeracaoLiquida(this.mesGeracao);

        const payload = {
          mesGeracao_kwh: parseFloat(geracaoLiquida || 0),
          mediaGeracao_kwh: parseFloat(this.mediaGeracao || 0),
          reservaTotalAnterior_kwh: parseFloat(this.dadosFaturamentoAnual?.valor_acumulado_reserva?.total || 0),
          tarifa_kwh: parseFloat(this.valor_kwh || 0),
          valorPago_mes: parseFloat(this.valorTotal || 0)
        };

        const resp = await axios.post(
          `${baseURL}/usinas/${this.usina.usi_id}/faturamento/${this.anoFaturamento}/mes/${mesIndex}/calculo`,
          payload,
          { headers }
        );

        const respData = resp.data?.data || {};
        this.credito = parseFloat(respData.credito_gerado_reais ?? 0);
        this.valorGuardado = parseFloat(respData.valor_guardado_kwh ?? 0);
        
        const pagamentoAtualizado = parseFloat(respData.faturamento_mes_reais ?? this.valorTotal);
        this.valorTotal = pagamentoAtualizado.toFixed(2);
        if (this.dadosFaturamentoAnual?.faturamento_usina) {
          this.dadosFaturamentoAnual.faturamento_usina[this.mesSelecionado] = pagamentoAtualizado;
        }
        
        this.co2Evitado = +parseFloat(respData.co2_evitado_kg ?? this.co2Evitado).toFixed(2);
        this.arvoresPlantadas = +parseFloat(respData.arvores_equivalentes ?? this.arvoresPlantadas).toFixed(2);

        Swal.fire({
          icon: 'success',
          title: 'Valores salvos!',
          text: 'As informações da usina foram atualizadas com sucesso.',
          confirmButtonColor: '#3085d6',
          confirmButtonText: 'OK'
        });

        await this.carregarFaturamentoAno();

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