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
        <label for="fioB">Fio B (R$)</label>
        <input id="fioB" type="number" step="0.01" class="form-control" v-model.number="fioB" />
      </div>
      <div class="col-md-4">
        <label for="fatura">Fatura de Energia da Usina (R$)</label>
        <input id="fatura" type="number" step="0.01" class="form-control" v-model.number="faturaEnergia" />
      </div>
      <div class="col-md-4">
        <label for="percentual">Percentual Lei 14300/23 (%)</label>
        <input id="percentual" type="number" step="0.01" class="form-control" v-model.number="percentualLei" />
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
          <td :class="{ 'text-danger': valor == menorGeracao }">{{ valor }} kWh</td>
          <td>{{ mediaGeracao }} kWh</td>
          <td>R$ {{ fixo.toFixed(2) }}</td>
          <td>R$ {{ injetado(valor).toFixed(2) }}</td>
          <td>R$ {{ creditado(valor).toFixed(2) }}</td>
          <td>R$ {{ cuo(valor).toFixed(2) }}</td>
          <td>R$ {{ valorFinal(valor).toFixed(2) }}</td>
        </tr>
      </tbody>
    </table>

    <div class="grafico-container mt-5" style="width: 860px; height: 400px;">
      <Bar v-if="chartData && chartData.labels" :data="chartData" :options="chartOptions" />
    </div>

    <h4 class="my-4">Faturamento da Usina no Mês</h4>
    <div class="row mb-5">
      <div class="col-md-4">
        <label for="mes">Selecione o mês:</label>
        <select id="mes" v-model="mesSelecionado" class="form-select">
          <option v-for="(mes, index) in meses" :key="index" :value="index">
            {{ mes }}
          </option>
        </select>
      </div>
      <div class="col-md-4">
        <label for="mesGeracao">Geração de {{ meses[mesSelecionado] }}:</label>
        <input id="mesGeracao" type="number" step="0.01" class="form-control" v-model.number="mesGeracao" />
      </div>
      <div class="col-md-4">
        <label for="valorGeracaoMes">Valor Faturado (R$):</label>
        <input id="valorGeracaoMes" type="number" step="0.01" class="form-control" v-model.number="valorGeracaoMes"
          readonly />
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-md-4">
        <label for="credito">Valor em Créditos (R$):</label>
        <input id="credito" type="number" step="0.01" class="form-control" v-model.number="credito" />
      </div>
    </div>

    <div class="mt-4 d-flex align-items-center">
      <button type="button" class="btn btn-primary ms-2" @click="goBack">Voltar</button>
    </div>
  </div>
</template>

<script>
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
      faturaEnergia: 100,
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
      meses: [
        "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho",
        "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"
      ],
      mesSelecionado: new Date().getMonth(),
      mesGeracao: 0,
      valorGeracaoMes: 0,
      credito: 0
    };
  },
  watch: {
    mesGeracao(newVal) {
      // Atualiza o valor faturado
      this.valorGeracaoMes = (newVal * this.valor_kwh).toFixed(2);

      // Atualiza o saldo de crédito
      this.credito = ((newVal - this.mediaGeracao) * this.valor_kwh).toFixed(2);
    }
  },
  computed: {
    valorFinalFioB() {
      return this.fioB * (this.percentualLei / 100);
    },
    fixo() {
      return this.menorGeracao * this.valor_kwh;
    }
  },
  methods: {
    async fetchUsinas() {
      const token = localStorage.getItem('token');
      try {
        const response = await axios.get('http://localhost:8000/api/usina', {
          headers: { Authorization: `Bearer ${token}` }
        });
        this.usinas = response.data;
      } catch (err) {
        console.error('Erro ao buscar usinas:', err);
      }
    },
    injetado(valor) {
      if (valor > this.mediaGeracao) {
        return (this.mediaGeracao - this.menorGeracao) * this.valor_kwh;
      } else {
        return (valor - this.menorGeracao) * this.valor_kwh;
      }
    },
    creditado(valor) {
      if (valor < this.mediaGeracao) {
        return (this.mediaGeracao - valor) * this.valor_kwh;
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
    gerarGrafico() {
      const meses = Object.keys(this.mesesGeracao);
      if (!meses.length) return;

      this.chartData = {
        labels: meses,
        datasets: [
          { type: 'bar', label: 'Fixo', data: meses.map(() => this.fixo), backgroundColor: '#60a5fa', stack: 'montagem', order: 2 },
          { type: 'bar', label: 'Injetado', data: meses.map(m => this.injetado(this.mesesGeracao[m])), backgroundColor: '#FFA500', stack: 'montagem', order: 3 },
          { type: 'bar', label: 'Creditado', data: meses.map(m => this.creditado(this.mesesGeracao[m])), backgroundColor: '#4ade80', stack: 'montagem', order: 4 },
          { type: 'bar', label: 'CUO', data: meses.map(m => this.cuo(this.mesesGeracao[m])), backgroundColor: '#f87171', stack: 'montagem', order: 5 },
          { type: 'line', label: 'Valor Final a Receber', data: meses.map(m => this.valorFinal(this.mesesGeracao[m])), borderColor: '#1e40af', borderWidth: 2, fill: false, pointRadius: 3, tension: 0.3, order: 1 }
        ]
      };
    },
    async carregarDados() {
      if (!this.selectedUsinaId) return;

      const token = localStorage.getItem('token');
      try {
        const response = await axios.get(`http://localhost:8000/api/usina/${this.selectedUsinaId}`, {
          headers: { Authorization: `Bearer ${token}` }
        });

        const usina = response.data;
        const g = usina.dado_geracao;

        this.valor_kwh = usina.comercializacao.valor_kwh || 0;
        this.valor_fixo = usina.comercializacao.valor_fixo || 0;
        this.mediaGeracao = g.media || 0;
        this.menorGeracao = g.menor_geracao || 0;

        this.mesesGeracao = {
          Janeiro: g.janeiro, Fevereiro: g.fevereiro, Março: g.marco,
          Abril: g.abril, Maio: g.maio, Junho: g.junho,
          Julho: g.julho, Agosto: g.agosto, Setembro: g.setembro,
          Outubro: g.outubro, Novembro: g.novembro, Dezembro: g.dezembro
        };

        this.gerarGrafico();
      } catch (error) {
        console.error('Erro ao carregar dados da usina:', error);
      }
    },
    goBack() {
      this.$router.push('/Home');
    }
  },
  mounted() {
    this.fetchUsinas();
  }
};
</script>

<style scoped>
label {
  font-weight: 500;
}

.text-danger {
  color: #dc3545 !important;
}
</style>