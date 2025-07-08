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
      <tbody v-if="dadosFaturamentoAnual && dadosFaturamentoAnual.faturamento_usina">
        <template v-for="([chaveMes, labelMes]) in Object.entries(meses)" :key="chaveMes">
          <tr v-if="dadosFaturamentoAnual.faturamento_usina[chaveMes] > 0">
            <td>{{ labelMes }}</td>
            <td>{{ dadosGeracaoRealMensal[chaveMes]?.toFixed(2) || 0 }} kWh</td>
            <td>{{ dadosFaturamentoAnual.valor_acumulado_reserva[chaveMes]?.toFixed(2) }} kWh</td>
            <td>R$ {{ dadosFaturamentoAnual.creditos_distribuidos[chaveMes]?.toFixed(2) }}</td>
            <td>R$ {{ dadosFaturamentoAnual.faturamento_usina[chaveMes]?.toFixed(2) }}</td>
          </tr>
        </template>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="text-center">
            <button class="btn btn-secondary me-2" @click="voltarAno">← Ano Anterior</button>
            <strong>{{ anoFaturamento }}</strong>
            <button class="btn btn-secondary ms-2" @click="avancarAno">Próximo Ano →</button>
          </td>
        </tr>
      </tfoot>
    </table>


    <div class="d-flex justify-content-end mb-5">
      <button class="btn btn-success" @click="salvarValoresMensais">Salvar Valores</button>
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
      cd_id: null,
      var_id: null,
      fa_id: null,
      usina: null,
      anoFaturamento: new Date().getFullYear(),
      dadosFaturamentoAnual: null,
      dadosGeracaoRealMensal: {},
      observacoes: '',
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
      const total = this.dadosFaturamentoAnual?.valor_acumulado_reserva?.total ?? 0;
      return parseFloat(total).toFixed(2);
    },
    reservaClasse() {
      return this.reservaTotal >= 0 ? 'text-success' : 'text-danger';
    },
  },
  methods: {
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
        this.dadosGeracaoRealMensal = geracaoResp.data[0]?.dados_geracao_real || {};

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
      const geracao = Number(this.mesGeracao);
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
      try {
        const response = await axios.get(`${baseURL}/usina/${this.selectedUsinaId}`, {
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
        this.usina = response.data;
        await this.carregarFaturamentoAno();
      } catch (error) {
        console.error('Erro ao carregar dados da usina:', error);
      }
    },
    async salvarValoresMensais() {
      const token = localStorage.getItem('token');
      const headers = { Authorization: `Bearer ${token}` };
      const mesNome = this.mesSelecionado;
      const ano = this.anoFaturamento;

      try {
        if (!this.usina?.usi_id || !ano) throw new Error("Usina ou ano não definido");

        const geracao = parseFloat(this.mesGeracao);
        const media = parseFloat(this.mediaGeracao);
        const reservaTotal = parseFloat(this.reservaTotal || 0);
        const valorGuardadoFloat = parseFloat(this.valorGuardado || 0);
        const creditoFloat = parseFloat(this.credito || 0);

        if (geracao < media && reservaTotal <= 0) this.credito = 0;

        const registroAnual = await this.verificaOuCriaAnoRegistro(ano, mesNome, geracao, headers);

        await this.atualizaValoresMensais(registroAnual, mesNome, geracao, valorGuardadoFloat, creditoFloat, headers);

        await this.atualizaTotalAnoAnterior(ano, valorGuardadoFloat, headers);

        Swal.fire({
          icon: 'success',
          title: 'Valores salvos!',
          text: 'As informações da usina foram atualizadas com sucesso.',
          confirmButtonColor: '#3085d6',
          confirmButtonText: 'OK'
        });

        await this.carregarFaturamentoAno();

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

    async verificaOuCriaAnoRegistro(ano, mesNome, geracao, headers) {
      const baseURL = import.meta.env.VITE_API_URL;
      
      const response = await axios.get(
        `${baseURL}/usina/${this.selectedUsinaId}/anos`,
        { headers }
      );

      const registrosAnos = response.data.anos || [];
      const anoExiste = registrosAnos.includes(parseInt(ano));

      // Se o ano ainda não existe, cria todos os vínculos necessários
      if (!anoExiste) {
        return await this.criarNovoRegistroAnual(ano, mesNome, geracao, headers);
      }

      // Se o ano existe, tenta buscar os vínculos existentes
      const [vinculo, geracaoResp] = await Promise.all([
        axios.get(`${baseURL}/creditos-distribuidos-usina/usina/${this.usina.usi_id}/ano/${ano}`, { headers }),
        axios.get(`${baseURL}/dados-geracao-real-usina/usina/${this.usina.usi_id}`, { headers })
      ]);

      // Se os vínculos não existem, cria agora
      if (!vinculo.data?.length) {
        return await this.criarNovoRegistroAnual(ano, mesNome, geracao, headers);
      }

      // Se tudo ok, retorna os IDs normalmente
      return {
        cd_id: vinculo.data[0].cd_id,
        var_id: vinculo.data[0].var_id,
        fa_id: vinculo.data[0].fa_id,
        dgr_id: geracaoResp.data[0]?.dados_geracao_real?.dgr_id ?? null
      };
    },
    async criarNovoRegistroAnual(ano, mesNome, geracao, headers) {
      const baseURL = import.meta.env.VITE_API_URL;
      
      const [cd, varr, fa] = await Promise.all([
        axios.post(`${baseURL}/creditos-distribuidos`, {}, { headers }),
        axios.post(`${baseURL}/valor-acumulado-reserva`, {}, { headers }),
        axios.post(`${baseURL}/faturamento-usina`, {}, { headers })
      ]);

      // Criação do vínculo
      await axios.post(`${baseURL}/creditos-distribuidos-usina`, {
        usi_id: this.usina.usi_id,
        cli_id: this.usina.cli_id,
        cd_id: cd.data.id,
        var_id: varr.data.id,
        fa_id: fa.data.id,
        ano
      }, { headers });

      // Criação dos dados de geração real
      const dgr = await axios.post(`${baseURL}/dados-geracao-real`, {
        [mesNome]: geracao
      }, { headers });

      await axios.post(`${baseURL}/dados-geracao-real-usina`, {
        usi_id: this.usina.usi_id,
        cli_id: this.usina.cli_id,
        dgr_id: dgr.data.id,
        ano
      }, { headers });

      // Buscar total do ano anterior
      const anoAnterior = ano - 1;
      let totalAnterior = 0;

      try {
        const res = await axios.get(
          `${baseURL}/creditos-distribuidos-usina/usina/${this.usina.usi_id}/ano/${anoAnterior}`,
          { headers }
        );

        const vinculoAnterior = res.data[0];
        if (vinculoAnterior?.var_id) {
          const reservaAnterior = await axios.get(
            `${baseURL}/valor-acumulado-reserva/${vinculoAnterior.var_id}`, { headers }
          );
          totalAnterior = parseFloat(reservaAnterior.data.total || 0);
        }
      } catch (error) {
        console.warn('Não foi possível buscar o total do ano anterior:', error);
      }

      // Atualiza o total do novo ano com o valor acumulado anterior
      await axios.patch(`${baseURL}/valor-acumulado-reserva/${varr.data.id}`, {
        total: totalAnterior
      }, { headers });
      console.log(totalAnterior);
      return {
        cd_id: cd.data.id,
        var_id: varr.data.id,
        fa_id: fa.data.id,
        dgr_id: dgr.data.id
      };
    },
    async atualizaValoresMensais(registro, mes, geracao, guardado, credito, headers) {
      const baseURL = import.meta.env.VITE_API_URL;
      const total_mes = parseFloat(this.reservaTotal || 0) + guardado - (credito / this.valor_kwh);

      await Promise.all([
        axios.patch(`${baseURL}/creditos-distribuidos/${registro.cd_id}`, {
          [mes]: parseFloat(credito)
        }, { headers }),

        axios.patch(`${baseURL}/valor-acumulado-reserva/${registro.var_id}`, {
          [mes]: guardado,
          total: total_mes
        }, { headers }),

        axios.patch(`${baseURL}/faturamento-usina/${registro.fa_id}`, {
          [mes]: parseFloat(this.valorTotal)
        }, { headers }),

        axios.patch(`${baseURL}/dados-geracao-real/${registro.dgr_id}`, {
          [mes]: geracao
        }, { headers })
      ]);
    },

    async atualizaTotalAnoAnterior(anoAtual, valorGuardado, headers) {
      const baseURL = import.meta.env.VITE_API_URL;

      try {
        const anoAnterior = anoAtual - 1;
        const response = await axios.get(`${baseURL}/creditos-distribuidos-usina/usina/${this.usina.usi_id}/ano/${anoAnterior}`, { headers });

        if (!response.data[0]?.var_id) return;

        const ResponseAnoAtual = await axios.get(`${baseURL}/creditos-distribuidos-usina/usina/${this.usina.usi_id}/ano/${anoAtual}`, { headers });
        const totalAnterior = parseFloat(response.data[0].valor_acumulado_reserva.total || 0);
        const novoTotal = totalAnterior + valorGuardado;

        await axios.patch(`${baseURL}/valor-acumulado-reserva/${ResponseAnoAtual.data[0].var_id}`, {
          total: novoTotal
        }, { headers });

      } catch (error) {
        console.warn('Não foi possível atualizar o total do ano anterior:', error);
      }
    },
    async gerarPDF() {
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem('token');

      // Pega mês e ano atuais
      const hoje = new Date();
      const mesAtual = hoje.getMonth() + 1; // getMonth() vai de 0 a 11
      const anoAtual = hoje.getFullYear();

      // Usa os valores passados no componente se existirem, senão usa os atuais
      const mesGeracao = this.mesGeracao || mesAtual;
      const anoGeracao = this.anoGeracao || anoAtual;

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
</style>