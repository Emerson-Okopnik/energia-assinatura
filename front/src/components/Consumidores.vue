<template>
    <div class="container mt-5 pt-5">
      <h4 class="mb-4">Lista de Consumidores</h4>
  
      <table class="table table-bordered table-hover">
        <thead class="table-dark">
          <tr>
            <th>Nome</th>
            <th>CPF/CNPJ</th>
            <th>Telefone</th>
            <th>E-mail</th>
            <th>Endereço</th>
            <th>Consumo Médio</th>
            <th>CIA Energia</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="consumidor in consumidores" :key="consumidor.con_id">
            <td>
              <router-link :to="`/consumidor/${consumidor.con_id}`" class="text-dark">
                {{ consumidor.cliente.nome }}
              </router-link>
            </td>
            <td>{{ consumidor.cliente.cpf_cnpj }}</td>
            <td>{{ consumidor.cliente.telefone }}</td>
            <td>{{ consumidor.cliente.email }}</td>
            <td>
              {{ consumidor.cliente.endereco.rua }}, Nº {{ consumidor.cliente.endereco.numero }}<br>
              {{ consumidor.cliente.endereco.cidade }} - {{ consumidor.cliente.endereco.estado }}
            </td>
            <td>{{ consumidor.dado_consumo.media }}</td>
            <td>{{ consumidor.cia_energia }}</td>
            <td class="text-center">
              <button class="btn btn-sm btn-danger" @click="deletarConsumidor(consumidor.con_id)">
              <i class="fas fa-trash-alt"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'ListaConsumidores',
    data() {
      return {
        consumidores: []
      };
    },
    methods: {
      async carregarConsumidores() {
        try {
          const baseURL = import.meta.env.VITE_API_URL;
          const token = localStorage.getItem('token');
          const response = await axios.get(`${baseURL}/consumidor`, {
            headers: {
              Authorization: `Bearer ${token}`
            }
          });
          this.consumidores = response.data;
        } catch (error) {
          console.error('Erro ao buscar consumidores:', error);
        }
      },
      async deletarConsumidor(con_id) {
        if (!confirm("Tem certeza que deseja excluir este consumidor?")) return;

        try {
          const baseURL = import.meta.env.VITE_API_URL;
          const token = localStorage.getItem('token');
          await axios.delete(`${baseURL}/consumidor/${con_id}`, {
            headers: {
              Authorization: `Bearer ${token}`
            }
          });
          this.carregarConsumidores(); // Atualiza a lista
        } catch (error) {
          console.error('Erro ao deletar consumidor:', error);
          alert("Erro ao tentar excluir o consumidor.");
        }
      }
    },
    created() {
      this.carregarConsumidores();
    }
  };
  </script>
  
  <style scoped>
  th, td {
    vertical-align: middle;
  }

  a {
    text-decoration: none;
  }
  .btn-danger i {
    color: white;
  }
  </style>
  