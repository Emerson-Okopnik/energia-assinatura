<template>
  <div class="container mt-5 pt-4">
    <h4 class="mb-4">Distribuição de Créditos</h4>

    <!-- Seleção -->
    <div class="row mb-4">
      <div class="col-md-6">
        <label for="usinaSelect"><strong>Usina</strong></label>
        <select id="usinaSelect" class="form-select" v-model="usinaSelecionada" @change="carregarConsumidores">
          <option disabled value="">Selecionar Usina</option>
          <option v-for="usina in usinas" :key="usina.usi_id" :value="usina.usi_id">
            {{ usina.cliente.nome }}
          </option>
        </select>
      </div>

      <div class="col-md-6 d-flex flex-column align-items-end">
        <label class="mb-1"><strong>Créditos Disponíveis</strong></label>
        <input class="form-control" style="width: 180px;" :class="creditosClasse" v-model="creditosDisponiveis"
          readonly />
      </div>
    </div>

    <!-- Tabelas + Setas -->
    <div class="row align-items-center justify-content-center">
      <!-- Tabela: Disponíveis -->
      <div class="col-md-5">
        <label class="mb-2">Consumidores Disponíveis</label>
        <div class="table-wrapper">
          <table class="table table-bordered table-sm">
            <thead class="table-light">
              <tr>
                <th>Nome</th>
                <th>Consumo Médio</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="consumidor in consumidoresDisponiveis" :key="consumidor.con_id"
                :class="{ 'table-active': consumidorSelecionadoDisponivel?.con_id === consumidor.con_id }"
                @click="alternarSelecionadoDisponivel(consumidor)" style="cursor: pointer">
                <td>{{ consumidor.cliente.nome }}</td>
                <td>{{ consumidor.dado_consumo.media }} kWh</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Setas -->
      <div class="col-md-2 text-center">
        <div class="arrow-container">
          <div class="arrow fs-3 mb-3" @click="vincularConsumidor">&rarr;</div>
          <div class="arrow fs-3" @click="desvincularConsumidor">&larr;</div>
        </div>
      </div>

      <!-- Tabela: Alocados -->
      <div class="col-md-5">
        <label class="mb-2">Consumidores Alocados</label>
        <div class="table-wrapper">
          <table class="table table-bordered table-sm">
            <thead class="table-light">
              <tr>
                <th>Nome</th>
                <th>Consumo Médio</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="consumidor in consumidoresAlocados" :key="consumidor.con_id"
                :class="{ 'table-active': consumidorSelecionadoAlocado?.con_id === consumidor.con_id }"
                @click="alternarSelecionadoAlocado(consumidor)" style="cursor: pointer">
                <td>{{ consumidor.consumidor.cliente.nome }}</td>
                <td>{{ consumidor.consumidor.dado_consumo.media }} kWh</td>
              </tr>
            </tbody>
          </table>
        </div>
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
      usinas: [],
      usinaSelecionada: '',
      creditosDisponiveis: 0,
      consumidoresDisponiveis: [],
      consumidoresAlocados: [],
      creditosDisponiveis: '0 kWh',
      consumidorSelecionadoAlocado: null,
      consumidorSelecionadoDisponivel: null,
    };
  },
  methods: {
    async carregarUsinas() {
      const token = localStorage.getItem('token');
      const response = await axios.get('http://localhost:8000/api/usina', {
        headers: { Authorization: `Bearer ${token}` }
      });
      this.usinas = response.data;
    },

    async carregarConsumidores() {
      const token = localStorage.getItem('token');

      try {
        const [disponiveisResponse, alocadosResponse, dadosGeracao] = await Promise.all([
          axios.get('http://localhost:8000/api/consumidores/nao-vinculados', {
            headers: { Authorization: `Bearer ${token}` }
          }),
          axios.get(`http://localhost:8000/api/usina-consumidor/${this.usinaSelecionada}`, {
            headers: { Authorization: `Bearer ${token}` }
          }),
          axios.get(`http://localhost:8000/api/usina/${this.usinaSelecionada}`, {
            headers: { Authorization: `Bearer ${token}` }
          })
        ]);

        this.consumidoresDisponiveis = disponiveisResponse.data;
        this.consumidoresAlocados = alocadosResponse.data;
        this.UsinaDadosGeracao = dadosGeracao.data;

        // Cálculo dos créditos disponíveis
        const usina = alocadosResponse.data.length > 0 ? alocadosResponse.data[0].usina : null;

        if (usina && usina.dado_geracao && usina.dado_geracao.media) {
          const geracaoMedia = usina.dado_geracao.media;

          const consumoTotal = alocadosResponse.data.reduce((soma, vinculo) => {
            return soma + (vinculo.consumidor?.dado_consumo?.media || 0);
          }, 0);

          this.creditosDisponiveis = (geracaoMedia - consumoTotal).toFixed(2) + ' kWh';
        } else {
          this.creditosDisponiveis = this.UsinaDadosGeracao.dado_geracao.media + ' kWh';
        }
      } catch (error) {
        console.error('Erro ao carregar consumidores:', error);
        this.consumidoresDisponiveis = [];
        this.consumidoresAlocados = [];
      }
    },
    alternarSelecionadoAlocado(consumidor) {
      if (this.consumidorSelecionadoAlocado && this.consumidorSelecionadoAlocado.con_id === consumidor.con_id) {
        this.consumidorSelecionadoAlocado = null; // desseleciona
      } else {
        this.consumidorSelecionadoAlocado = consumidor; // seleciona
      }
    },
    async desvincularConsumidor() {
      if (!this.consumidorSelecionadoAlocado) {
        Swal.fire({
          icon: 'error',
          title: 'Erro ao salvar',
          text: 'Selecione um consumidor alocado para desvincular.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
        return;
      }

      const token = localStorage.getItem('token');
      const con_id = this.consumidorSelecionadoAlocado.con_id;
      const usi_id = this.usinaSelecionada;

      try {
        await axios.delete(`http://localhost:8000/api/usina-consumidor/usina/${usi_id}/consumidor/${con_id}`, {
          headers: { Authorization: `Bearer ${token}` }
        });

        // Atualiza a listagem após remoção
        this.consumidorSelecionadoAlocado = null;
        await this.carregarConsumidores();
      } catch (error) {
        console.error('Erro ao desvincular consumidor:', error);
        Swal.fire({
          icon: 'error',
          title: 'Erro ao salvar',
          text: 'Não foi possível desvincular o consumidor.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
      }
    },
    alternarSelecionadoDisponivel(consumidor) {
      if (
        this.consumidorSelecionadoDisponivel &&
        this.consumidorSelecionadoDisponivel.con_id === consumidor.con_id
      ) {
        this.consumidorSelecionadoDisponivel = null; // desseleciona
      } else {
        this.consumidorSelecionadoDisponivel = consumidor; // seleciona
      }
    },
    async vincularConsumidor() {
      if (!this.consumidorSelecionadoDisponivel) {
        Swal.fire({
          icon: 'error',
          title: 'Erro ao salvar',
          text: 'Selecione um consumidor disponível para vincular.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
        return;
      }

      const token = localStorage.getItem('token');
      const novoConId = this.consumidorSelecionadoDisponivel.con_id;

      let usi_id = this.usinaSelecionada;
      let cli_id = null;
      let con_ids = [];

      try {
        // Se há consumidores já alocados, fazer PUT
        if (this.consumidoresAlocados.length > 0) {
          const primeiraRelacao = this.consumidoresAlocados[0];
          const usinaInfo = primeiraRelacao.usina;

          cli_id = usinaInfo.cli_id;
          const usic_id = primeiraRelacao.usic_id;

          con_ids = this.consumidoresAlocados.map(c => c.consumidor.con_id);
          if (!con_ids.includes(novoConId)) con_ids.push(novoConId);

          await axios.put(`http://localhost:8000/api/usina-consumidor/${usic_id}`, { usi_id, cli_id, con_ids }, {
            headers: { Authorization: `Bearer ${token}` },
          });
        } else {
          // Nenhum consumidor alocado — fazer POST
          const usinaSelecionada = this.usinas.find(u => u.usi_id === parseInt(usi_id));
          if (!usinaSelecionada) {
            Swal.fire({
              icon: 'error',
              title: 'Erro ao salvar',
              text: 'Dados da usina não encontrados.',
              confirmButtonColor: '#d33',
              confirmButtonText: 'Entendi'
            });
            return;
          }

          cli_id = usinaSelecionada.cli_id;
          con_ids = [novoConId];

          await axios.post('http://localhost:8000/api/usina-consumidor', { usi_id, cli_id, con_ids }, {
            headers: { Authorization: `Bearer ${token}` },
          });
        }
        this.consumidorSelecionadoDisponivel = null;
        await this.carregarConsumidores(); // Atualiza as tabelas
      } catch (error) {
        console.error('Erro ao vincular consumidor:', error);
        Swal.fire({
          icon: 'error',
          title: 'Erro ao salvar',
          text: 'Não foi possível vincular o consumidor.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
      }
    }
  },
  mounted() {
    this.carregarUsinas();
  },
  computed: {
    creditosClasse() {
      const valorNumerico = parseFloat(this.creditosDisponiveis);
      if (isNaN(valorNumerico)) return '';
      return valorNumerico > 0 ? 'text-success' : valorNumerico < 0 ? 'text-danger' : "text-dark";
    }
  }
};
</script>

<style scoped>
.table-wrapper {
  height: auto;
  min-height: 400px;
  overflow-y: auto;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  background-color: white;
}

.arrow-container {
  display: flex;
  flex-direction: column;
  justify-content: center;
  height: 100%;
}

.arrow {
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
  user-select: none;
}

.text-success {
  color: #198754 !important;
}

.text-danger {
  color: #dc3545 !important;
}
</style>