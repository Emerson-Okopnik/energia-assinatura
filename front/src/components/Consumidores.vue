<template>
    <div class="container mt-5 pt-5">
      <h4 class="mb-4">Lista de Consumidores</h4>
  
      <table class="tabela-usinas">
        <thead class="table-dark">
          <tr>
            <th>Nome</th>
            <th>CPF / CNPJ</th>
            <th>Telefone</th>
            <th>Status</th>
            <th>Cidade</th>
            <th>Consumo Médio</th>
            <th>CIA Energia</th>
            <th>UC</th>
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
            <td class="nowrap">{{ consumidor.cliente.cpf_cnpj }}</td>
            <td class="nowrap">{{ consumidor.cliente.telefone }}</td>
            <td>
              <span v-if="consumidor.status === 'Aderido'" class="badge bg-success">Conectado</span>
              <span v-else-if="consumidor.status === 'Aguardando troca de titularidade'" class="badge bg-danger">Não Conectado</span>
              <span v-else-if="consumidor.status === 'Envio dos documentos para assinatura'" class="badge bg-warning text-dark">Em processo</span>
              <span v-else class="badge bg-secondary">Status indefinido</span>
            </td>
            <!--<td>
              {{ consumidor.cliente.endereco.rua }}, Nº {{ consumidor.cliente.endereco.numero }}<br>
              {{ consumidor.cliente.endereco.cidade }} - {{ consumidor.cliente.endereco.estado }}
            </td>-->
            <td>
              {{ consumidor.cliente.endereco.cidade }}
            </td>
            <td>{{ consumidor.dado_consumo.media }} Kwh</td>
            <td>{{ consumidor.cia_energia }}</td>
            <td>{{ consumidor.uc }}</td>
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
  .nowrap {
    white-space: nowrap;
  }

    .resumo-geracao {
    background-color: #fff8e7;
    border: 1px solid #fcd34d;
    border-radius: 12px;
    padding: 20px 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  }

  .resumo-geracao h5 {
    font-weight: 600;
    margin-bottom: 12px;
  }

  .resumo-geracao p {
    font-size: 0.95rem;
    margin-bottom: 6px;
  }

  .tabela-usinas {
    width: 100%;
    font-size: 0.95rem;
    border-collapse: collapse;
    margin-top: 20px;
    background-color: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
  }

  .tabela-usinas th,
  .tabela-usinas td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
  }


  .tabela-usinas th {
    font-weight: 600;
    white-space: nowrap;
  }

  .table-sm th,
  .table-sm td {
    padding: 6px 10px;
    font-size: 0.85rem;
  }

  .tabela-usinas thead,
  .table thead {
    background-color: #212529;
    color: #fff;
  }

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
  