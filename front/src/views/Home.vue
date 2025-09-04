<template>
  <div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Updated header with Tailwind classes -->
    <div class="text-center mb-8">
      <img 
        src="/src/assets/logo-consorcio-lider-energy.png" 
        alt="Logo Consórcio Líder Energy" 
        class="h-24 w-auto mx-auto"
      />
    </div>

    <!-- Converted summary section to Tailwind card -->
    <div class="card mb-8 bg-gradient-to-r from-amber-50 to-orange-50 border-amber-200">
      <h5 class="text-xl font-semibold text-gray-900 mb-4">Resumo de Distribuição</h5>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <p class="text-sm text-gray-600">Geração Média Total</p>
          <p class="text-2xl font-bold text-gray-900">{{ geracaoTotal }} kWh</p>
        </div>
        <div>
          <p class="text-sm text-gray-600">Consumo Alocado</p>
          <p class="text-2xl font-bold text-gray-900">{{ consumoTotal }} kWh</p>
        </div>
        <div>
          <p class="text-sm text-gray-600">Saldo Disponível</p>
          <p class="text-2xl font-bold" :class="creditosClasse">{{ saldoDisponivel }} kWh</p>
        </div>
      </div>
    </div>

    <!-- Updated table section with Tailwind responsive design -->
    <div class="card p-0 overflow-hidden">
      <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
        <h4 class="text-lg font-semibold text-gray-900">Relatório de Usinas e Consumidores</h4>
      </div>
      
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-800">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Cliente Usina</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Cidade Usina</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Geração Média</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Consumo Total</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Saldo Disponível</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Vendedor</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Concessionária</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Formulário</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-for="(usina, usinaId) in usinasMapeadas" :key="usinaId">
              <tr 
                @click="toggleExpandir(usinaId)" 
                class="cursor-pointer hover:bg-gray-50 transition-colors duration-200"
                :style="{ backgroundColor: coresUsina[usinaId] }"
              >
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ usina.usina.cliente.nome }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ usina.usina.cliente.endereco.cidade }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ usina.usina.dado_geracao.media }} kWh</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ totalConsumo(usinaId).toFixed(2) }} kWh</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold" :class="classeSaldo(usinaId)">
                  {{ saldoDisponivelUsina(usinaId).toFixed(2) }} kWh
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ usina.usina.vendedor.nome }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ usina.usina.comercializacao.cia_energia }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <button 
                    class="btn-primary text-sm py-1 px-3" 
                    @click.stop="gerarPDFConsumidores(usinaId)"
                  >
                    PDF
                  </button>
                </td>
              </tr>
              <tr v-if="usinasExpandida.includes(usinaId)" class="bg-gray-50">
                <td colspan="8" class="p-0">
                  <div class="overflow-x-auto">
                    <table class="min-w-full">
                      <thead class="bg-gray-100">
                        <tr>
                          <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Nome do Consumidor</th>
                          <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Cidade</th>
                          <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Consumo Médio</th>
                          <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Vendedor</th>
                          <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Concessionária</th>
                          <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Excedente de Geração</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-200">
                        <tr 
                          v-for="consumidor in usina.consumidores" 
                          :key="consumidor.con_id"
                          class="bg-white"
                        >
                          <td class="px-6 py-3 text-sm text-gray-900">{{ consumidor.cliente.nome }}</td>
                          <td class="px-6 py-3 text-sm text-gray-900">{{ consumidor.cliente.endereco.cidade }}</td>
                          <td class="px-6 py-3 text-sm text-gray-900">{{ consumidor.dado_consumo.media }} kWh</td>
                          <td class="px-6 py-3 text-sm text-gray-900">{{ consumidor.vendedor.nome || '—' }}</td>
                          <td class="px-6 py-3 text-sm text-gray-900">{{ consumidor.cia_energia }}</td>
                          <td class="px-6 py-3 text-sm text-gray-900">
                            {{ ((consumidor.dado_consumo.media * 100)/ usina.usina.dado_geracao.media).toFixed(2) + ' %'}}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import Swal from 'sweetalert2';
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
      return valor > 0 ? 'text-success-600' : valor < 0 ? 'text-red-600' : 'text-gray-900';
    }
  },
  methods: {
    classeSaldo(usi_id) {
      const saldo = this.saldoDisponivelUsina(usi_id);
      if (saldo > 0) return 'text-success-600';
      if (saldo < 0) return 'text-red-600';
      return 'text-gray-900';
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
    async gerarPDFConsumidores(usi_id) {
      const baseURL = import.meta.env.VITE_API_URL;
      const token = localStorage.getItem("token");

      try {
        Swal.fire({
          title: 'Gerando PDF...',
          html: 'Aguarde enquanto preparamos o documento.',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        const response = await axios.get(`${baseURL}/gerar-pdf-consumidores/${usi_id}`, {
          headers: { Authorization: `Bearer ${token}` },
          responseType: "blob"
        });

        Swal.close();

        const blob = new Blob([response.data], { type: "application/pdf" });
        const url = window.URL.createObjectURL(blob);

        const link = document.createElement("a");
        link.href = url;
        link.setAttribute("download", `consumidores-usina-${usi_id}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);

      } catch (error) {
        console.error("Erro ao gerar PDF:", error);
        Swal.close();
        Swal.fire({
          icon: "error",
          title: "Erro ao gerar PDF",
          text: "Não foi possível gerar o PDF.",
          confirmButtonColor: "#d33",
          confirmButtonText: "Fechar"
        });
      }
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
      this.calcularTotais();

    } catch (error) {
      console.error('Erro ao buscar dados:', error);
      Swal.fire({
        icon: 'error',
        title: 'Erro ao carregar dados',
        text: 'Sessão expirada ou erro na API.',
        confirmButtonColor: '#d33'
      });
      this.$router.push({ name: 'Login' });
    }
  }
};
</script>
