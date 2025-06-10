<template>
  <div class="container">
    <h4 class="mb-4 titulo">Lista de Usinas</h4>
    <div class="tabela-centralizada">
      <table class="table table-bordered table-hover">
        <thead class="table-dark">
          <tr>
            <th>Nome do Cliente</th>
            <th>CPF/CNPJ</th>
            <th>E-mail</th>
            <th>Localização</th>
            <th>Status</th>
            <th>Média Geração (kWh)</th>
            <th>Menor Geração</th>
            <th>Valor kWh</th>
            <th>Valor Final Médio</th>
            <th>CIA Energia</th>
            <th>Conexão</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="usina in usinas" :key="usina.usi_id">
            <td>
              <router-link :to="`/usina/${usina.usi_id}`" class="text-dark">
                {{ usina.cliente.nome }}
              </router-link>
            </td>
            <td>{{ usina.cliente.cpf_cnpj }}</td>
            <td>{{ usina.cliente.email }}</td>
            <td>{{ usina.cliente.endereco.cidade }} - {{ usina.cliente.endereco.estado }}</td>
            <td>
              <span v-if="usina.status === 'Concluído'" class="badge bg-success">Conectado</span>
              <span v-else-if="usina.status === 'Aguardando troca de titularidade'" class="badge bg-danger">Não Conectado</span>
              <span v-else-if="usina.status === 'Troca solicitada'" class="badge bg-warning text-dark">Em processo</span>
              <span v-else class="badge bg-secondary">Indefinido</span>
            </td>
            <td>{{ usina.dado_geracao.media }} Kwh</td>
            <td>{{ usina.dado_geracao.menor_geracao }} Kwh</td>
            <td>{{ usina.comercializacao.valor_kwh }}</td>
            <td>{{ usina.comercializacao.valor_final_media }}</td>
            <td>{{ usina.comercializacao.cia_energia }}</td>
            <td>{{ formatDate(usina.comercializacao.data_conexao) }}</td>
            <td class="text-center">
              <button class="btn btn-sm btn-danger" @click="deletarUsina(usina.usi_id)">
                <i class="fas fa-trash-alt"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
  
<script>
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
  name: 'ListaUsinas',
  data() {
    return {
      usinas: []
    };
  },
  methods: {
    async fetchUsinas() {
      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const token = localStorage.getItem('token');
        const response = await axios.get(`${baseURL}/usina`, {
          headers: {
            Authorization: `Bearer ${token}`
          }
        });
        this.usinas = response.data;
      } catch (error) {
        console.error('Erro ao buscar usinas:', error);
        Swal.fire({
          icon: 'error',
          title: 'Erro ao carregar',
          text: 'Não foi possível carregar a lista de usinas.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
      }
    },

    async deletarUsina(usi_id) {
      const confirmacao = await Swal.fire({
        icon: 'warning',
        title: 'Excluir usina?',
        text: 'Esta ação é irreversível. Deseja continuar?',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
      });

      if (!confirmacao.isConfirmed) return;

      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const token = localStorage.getItem('token');

        await axios.delete(`${baseURL}/usina/${usi_id}`, {
          headers: {
            Authorization: `Bearer ${token}`
          }
        });

        await this.fetchUsinas();

        Swal.fire({
          icon: 'success',
          title: 'Usina excluída',
          text: 'A usina foi removida com sucesso.',
          confirmButtonColor: '#f28c1f'
        });

      } catch (error) {
        console.error('Erro ao excluir usina:', error);

        Swal.fire({
          icon: 'error',
          title: 'Erro ao excluir',
          text: 'Não foi possível excluir a usina.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
      }
    },

    formatDate(dataISO) {
      if (!dataISO) return '-';
      const data = new Date(dataISO);
      return data.toLocaleDateString('pt-BR');
    }
  },

  created() {
    this.fetchUsinas();
  }
};
</script>

  
  <style scoped>
    .tabela-centralizada {
      display: flex;
      justify-content: center;

    }
    th, td {
      vertical-align: middle;
      white-space: nowrap;
    }
    table {
      min-width: 1100px;
    }
    a {
      text-decoration: none;
    }
    .btn-danger i {
      color: white;
    }
  </style>
  