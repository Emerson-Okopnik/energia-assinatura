<template>
  <div class="container mt-5">
    <div class="text-center mb-5">
      <img src="/src/assets/logo-consorcio-lider-energy.png" alt="Logo Consórcio Líder Energy" style="width: 30%;" />
    </div>
    <div class="resumo-geracao mb-4">
      <h5>Resumo de Distribuição</h5>
      <p><strong>Geração Média Total:</strong> {{ geracaoTotal }} kWh</p>
      <p><strong>Consumo Alocado:</strong> {{ consumoTotal }} kWh</p>
      <p><strong>Saldo Disponível:</strong> <span :class="creditosClasse">{{ saldoDisponivel }} kWh</span></p>
    </div>

    <h4 class="mt-5">Relatório de Usinas e Consumidores</h4>
    <table class="tabela-usinas">
      <thead class="table-dark">
        <tr>
          <th>Cliente Usina</th>
          <th>Cidade Usina</th>
          <th>Geração Média</th>
          <th>Consumo Total</th>
          <th>Saldo Disponível</th>
          <th>Vendedor</th>
          <th>Concessionária Usina</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="(usina, usinaId) in usinasMapeadas" :key="usinaId">
          <tr @click="toggleExpandir(usinaId)" style="cursor: pointer"
            :style="{ backgroundColor: coresUsina[usinaId] }">
            <td>{{ usina.usina.cliente.nome }}</td>
            <td>{{ usina.usina.cliente.endereco.cidade }}</td>
            <td>{{ usina.usina.dado_geracao.media }} kWh</td>
            <td>{{ totalConsumo(usinaId).toFixed(2) }} kWh</td>
            <td :class="classeSaldo(usinaId)"><b>{{ saldoDisponivelUsina(usinaId).toFixed(2) }} kWh</b></td>
            <td>{{ usina.usina.vendedor.nome }}</td>
            <td>{{ usina.usina.comercializacao.cia_energia }}</td>
          </tr>
          <tr v-if="usinasExpandida.includes(usinaId)">
            <td colspan="7" style="padding: 0">
              <table class="mb-0 table-sm w-100">
                <thead :style="{ backgroundColor: coresUsina[usinaId] }" style="color:black">
                  <tr>
                    <th>Nome do Consumidor</th>
                    <th>Cidade</th>
                    <th>Consumo Médio</th>
                    <th>Vendedor</th>
                    <th>Concessionária</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="consumidor in usina.consumidores" :key="consumidor.con_id" :style="{ backgroundColor: coresUsina[usinaId], opacity: 0.70 }">
                    <td>{{ consumidor.cliente.nome }}</td>
                    <td>{{ consumidor.cliente.endereco.cidade }}</td>
                    <td>{{ consumidor.dado_consumo.media }} kWh</td>
                    <td>{{ consumidor.vendedor.nome || '—' }}</td>
                    <td>{{ consumidor.cia_energia }}</td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      user: {},
      relatorio: [],
      coresUsina: {},
      usinasMapeadas: {},
      usinasExpandida: [],
      geracaoTotal: 0,
      consumoTotal: 0,
      saldoDisponivel: 0
    };
  },
  computed: {
    creditosClasse() {
      const valor = parseFloat(this.saldoDisponivel);
      if (isNaN(valor)) return '';
      return valor > 0 ? 'text-success' : valor < 0 ? 'text-danger' : 'text-dark';
    }
  },
  methods: {
    classeSaldo(usi_id) {
      const saldo = this.saldoDisponivelUsina(usi_id);
      if (saldo > 0) return 'text-success';
      if (saldo < 0) return 'text-danger';
      return 'text-dark';
    },
    toggleExpandir(usi_id) {
      const index = this.usinasExpandida.indexOf(usi_id);
      if (index >= 0) {
        this.usinasExpandida.splice(index, 1);
      } else {
        this.usinasExpandida.push(usi_id);
      }
    },
    totalConsumo(usi_id) {
      const consumidores = this.usinasMapeadas[usi_id]?.consumidores || [];
      return consumidores.reduce((total, c) => total + (c.dado_consumo?.media || 0), 0);
    },
    saldoDisponivelUsina(usi_id) {
      const geracao = this.usinasMapeadas[usi_id]?.usina?.dado_geracao?.media || 0;
      return geracao - this.totalConsumo(usi_id);
    },
    calcularTotais() {
      const usinasSomadas = new Set();
      let geracao = 0;
      let consumo = 0;

      Object.values(this.usinasMapeadas).forEach(({ usina, consumidores }) => {
        if (!usinasSomadas.has(usina.usi_id)) {
          geracao += usina?.dado_geracao?.media || 0;
          usinasSomadas.add(usina.usi_id);
        }
        consumidores.forEach(c => {
          consumo += c.dado_consumo?.media || 0;
        });
      });

      this.geracaoTotal = geracao.toFixed(2);
      this.consumoTotal = consumo.toFixed(2);
      this.saldoDisponivel = (geracao - consumo).toFixed(2);
    },
    gerarCorPastel() {
      const r = Math.floor(Math.random() * 40 + 215);
      const g = Math.floor(Math.random() * 40 + 215);
      const b = Math.floor(Math.random() * 40 + 215);
      return `rgba(${r}, ${g}, ${b}, 0.5)`;
    },
    atribuirCoresPorUsina(data) {
      const cores = {};
      data.forEach(item => {
        if (!cores[item.usi_id]) {
          cores[item.usi_id] = this.gerarCorPastel();
        }
      });
      this.coresUsina = cores;
    },
    organizarRelatorio() {
      const mapa = {};
      this.relatorio.forEach(item => {
        const usiId = item.usina.usi_id;
        if (!mapa[usiId]) {
          mapa[usiId] = {
            usina: item.usina,
            consumidores: []
          };
        }
        mapa[usiId].consumidores.push(item.consumidor);
      });
      this.usinasMapeadas = mapa;
    }
  },
  async created() {
    const baseURL = import.meta.env.VITE_API_URL;
    const token = localStorage.getItem('token');
    try {
      const [userRes, relatorioRes] = await Promise.all([
        axios.get(`${baseURL}/user`, {
          headers: { Authorization: `Bearer ${token}` }
        }),
        axios.get(`${baseURL}/usina-consumidor`, {
          headers: { Authorization: `Bearer ${token}` }
        })
      ]);

      this.user = userRes.data;
      this.relatorio = relatorioRes.data;

      this.organizarRelatorio();
      this.atribuirCoresPorUsina(this.relatorio);
      this.calcularTotais();

    } catch (error) {
      console.error('Erro ao buscar dados:', error);
      alert('Sessão expirada ou erro na API.');
      this.$router.push({ name: 'Login' });
    }
  }
};
</script>

<style scoped>
.tabela-usinas {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.tabela-usinas th,
.tabela-usinas td,
.table th,
.table td {
  padding: 8px;
  border: 1px solid #dee2e6;
  min-width: 160px;
  text-align: left;
}

.tabela-usinas thead,
.table thead {
  background-color: #212529;
  color: #fff;
}

.table-usinas-light th {
  background-color: #f8f9fa;
  color: #212529;
}

.resumo-geracao {
  padding: 16px;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  background-color: #f8f9fa;
}

a {
  text-decoration: none;
}

.text-success {
  color: #198754 !important;
}

.text-danger {
  color: #dc3545 !important;
}
</style>
