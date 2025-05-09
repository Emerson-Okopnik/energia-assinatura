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
          <option v-for="(label, key) in meses" :key="key" :value="key">
            {{ label }}
          </option>
        </select>
      </div>
      <div class="col-md-4">
        <label for="mesGeracao">Geração de {{ meses[mesSelecionado] }}:</label>
        <input id="mesGeracao" type="number" step="0.01" class="form-control" v-model.number="mesGeracao"
          @input="atualizarValores" />
      </div>
      <div class="col-md-4">
        <label for="valorGeracaoMes">Valor Gerado (R$):</label>
        <input id="valorGeracaoMes" type="number" step="0.01" class="form-control" v-model.number="valorGeracaoMes"
          readonly />
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-md-4">
        <label for="credito">Valor em Créditos (R$):</label>
        <input id="credito" type="number" step="0.01" class="form-control" v-model.number="credito" />
      </div>
      <div class="col-md-4">
        <label for="valorGuardado">Valor acumulado em Reserva (R$):</label>
        <input id="valorGuardado" type="number" step="0.01" class="form-control" v-model.number="valorGuardado" />
      </div>
      <div class="col-md-4">
        <label for="valorTotal">Valor Total a Ser pago (R$):</label>
        <input id="valorTotal" type="number" step="0.01" class="form-control" v-model.number="valorTotal" />
      </div>
    </div>

    <table class="table table-bordered" v-if="usina && usina.creditos_distribuidos_usina">
      <thead class="table-dark">
        <tr>
          <th>Mês</th>
          <th>Geração</th>
          <th>Valor Guardado</th>
          <th>Creditado</th>
          <th>Valor Pago</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="([mesExibicao, valor]) in mesesComValorPago" :key="mesExibicao">
          <td>{{ mesExibicao }}</td>
          <td>{{ valor }} kWh</td>
          <td>R$ {{usina.creditos_distribuidos_usina.valor_acumulado_reserva[Object.keys(meses).find(key => meses[key]
            === mesExibicao)]?.toFixed(2)}}</td>
          <td>R$ {{usina.creditos_distribuidos_usina.creditos_distribuidos[Object.keys(meses).find(key => meses[key] ===
            mesExibicao)]?.toFixed(2)}}</td>
          <td>R$ {{usina.creditos_distribuidos_usina.faturamento_usina[Object.keys(meses).find(key => meses[key] ===
            mesExibicao)]?.toFixed(2)}}</td>
        </tr>
      </tbody>
    </table>

    <div class="d-flex justify-content-end mb-5">
      <button class="btn btn-success" @click="salvarValoresMensais">Salvar Valores</button>
    </div>

    <div v-if="usina && usina.creditos_distribuidos_usina" class="mb-4">
      <h5>Reserva Total Acumulada</h5>
      <p :class="['fs-5 fw-bold p-2 rounded', reservaClasse]"
        style="background-color: rgba(0, 0, 0, 0.05); display: inline-block;">
        R$ {{ reservaTotalFormatada }}
      </p>
    </div>

    <button @click="gerarPDF" class="btn btn-primary">
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
      meses: {
        janeiro: "Janeiro",
        fevereiro: "Fevereiro",
        marco: "Março",
        abril: "Abril",
        maio: "Maio",
        junho: "Junho",
        julho: "Julho",
        agosto: "Agosto",
        setembro: "Setembro",
        outubro: "Outubro",
        novembro: "Novembro",
        dezembro: "Dezembro"
      },
      mesSelecionado: '',
      mesGeracao: 0,
      valorGeracaoMes: 0,
      credito: 0,
      valorGuardado: 0,
      valorTotal: 0,
      cd_id: null,
      var_id: null,
      fa_id: null,
      usina: null,
    };
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
      const total = this.usina?.creditos_distribuidos_usina?.valor_acumulado_reserva?.total ?? 0;
      return parseFloat(total);
    },
    reservaTotalFormatada() {
      return this.reservaTotal.toFixed(2);
    },
    reservaClasse() {
      return this.reservaTotal >= 0 ? 'text-success' : 'text-danger';
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
    atualizarValores() {
      const geracao = Number(this.mesGeracao);
      const media = Number(this.mediaGeracao);
      const kwh = Number(this.valor_kwh);

      this.valorGeracaoMes = +(geracao * kwh).toFixed(2);
      this.credito = this.creditado(geracao).toFixed(2);
      this.valorGuardado = 0;

      if (geracao > media) {
        const energiaExcedente = geracao - media;
        const valorExcedente = energiaExcedente * kwh;
        this.valorGuardado = parseFloat(valorExcedente.toFixed(2));
      }

      this.valorTotal = this.valorFinal(geracao).toFixed(2);
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
        this.cd_id = usina.creditos_distribuidos_usina.cd_id;
        this.var_id = usina.creditos_distribuidos_usina.var_id;
        this.fa_id = usina.creditos_distribuidos_usina.fa_id;
        this.dger_id = usina.dger_id;
        this.usina = response.data;
      } catch (error) {
        console.error('Erro ao carregar dados da usina:', error);
      }
    },
    async salvarValoresMensais() {
      const token = localStorage.getItem('token');
      const mesNome = this.mesSelecionado;
      const headers = { Authorization: `Bearer ${token}` };

      try {
        if (!this.cd_id || !this.var_id || !this.fa_id) {
          Swal.fire({
            icon: 'warning',
            title: 'Dados incompletos!',
            text: 'IDs de vínculo da usina não foram carregados. Verifique o backend.',
            confirmButtonColor: '#d33',
            confirmButtonText: 'OK'
          });
          return;
        }

        // 1. CREDITOS_DISTRIBUIDOS
        await axios.patch(`http://localhost:8000/api/creditos-distribuidos/${this.cd_id}`, {
          [mesNome]: parseFloat(this.credito)
        }, { headers });

        if (this.credito < this.valorGuardado) {
          var total_mes = this.valorGuardado + this.usina.creditos_distribuidos_usina.valor_acumulado_reserva.total
        } else {
          var total_mes = this.usina.creditos_distribuidos_usina.valor_acumulado_reserva.total - this.credito
        }

        // 2. VALOR_ACUMULADO_RESERVA
        await axios.patch(`http://localhost:8000/api/valor-acumulado-reserva/${this.var_id}`, {
          [mesNome]: parseFloat(this.valorGuardado),
          total: parseFloat(total_mes)
        }, { headers });

        // 3. FATURAMENTO_USINA
        await axios.patch(`http://localhost:8000/api/faturamento-usina/${this.fa_id}`, {
          [mesNome]: parseFloat(this.valorTotal)
        }, { headers });

        // 4. DADOS_GERACAO
        await axios.patch(`http://localhost:8000/api/geracao/${this.dger_id}`, {
          [mesNome]: parseFloat(this.mesGeracao)
        }, { headers });

        Swal.fire({
          icon: 'success',
          title: 'Valores salvos!',
          text: 'As informações da usina foram atualizadas com sucesso.',
          confirmButtonColor: '#3085d6',
          confirmButtonText: 'OK'
        });
        await this.carregarDados();
      } catch (error) {
        console.error("Erro ao salvar valores mensais:", error);
        Swal.fire({
          icon: 'error',
          title: 'Erro ao salvar',
          text: 'Verifique se os dados estão corretos ou se a usina foi carregada.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });

      }
    },
    goBack() {
      this.$router.push('/Home');
    },
    async gerarPDF() {
      const token = localStorage.getItem('token');

      try {
        // Abre o alerta de carregamento
        Swal.fire({
          title: 'Gerando PDF...',
          html: 'Aguarde enquanto preparamos o documento.',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        const response = await axios.get(`http://localhost:8000/api/gerar-pdf-usina/${this.selectedUsinaId}`, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
          responseType: 'blob'
        });

        Swal.close(); // Fecha o loading após o recebimento do PDF

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
  },
  mounted() {
    this.fetchUsinas();

    // Define o mês atual como chave
    const index = new Date().getMonth(); // 0 a 11
    const chaveAtual = Object.keys(this.meses)[index];
    this.mesSelecionado = chaveAtual;
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