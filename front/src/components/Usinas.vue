<template>
  <div class="container usinas-page">
    <header class="page-header">
      <h4 class="page-title">Lista de Usinas</h4>
    </header>

    <section class="filtros-container">
      <label for="buscaUsina" class="filtro-label">Buscar por nome do cliente</label>
      <div class="filtro-acoes">
        <input
          id="buscaUsina"
          v-model.trim="termoBusca"
          type="text"
          class="form-control"
          placeholder="Digite o nome do cliente"
          @keyup.enter="aplicarBusca"
          :disabled="carregando"
        />
        <button class="btn btn-primary" @click="aplicarBusca" :disabled="carregando">
          Buscar
        </button>
        <button
          class="btn btn-outline-secondary"
          @click="limparBusca"
          :disabled="carregando || (!termoBusca && !termoBuscaAplicado)"
        >
          Limpar
        </button>
      </div>
    </section>

    <section class="table-card">
      <div class="table-responsive-custom">
        <table class="tabela-usinas">
          <thead>
            <tr>
              <th>Nome do Cliente</th>
              <th>Endereco</th>
              <th>Status</th>
              <th>Rede</th>
              <th>Media Geracao (kWh)</th>
              <th>Unidade Consumidor</th>
              <th>CIA Energia</th>
              <th>Data Conexao</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(usina, index) in usinas" :key="usina.usi_id || `${usina.nome_cliente}-${usina.unidade_consumidor}-${index}`">
              <td class="col-nome">
                <router-link v-if="usina.usi_id" :to="`/usina/${usina.usi_id}`" class="link-cliente">
                  {{ usina.nome_cliente }}
                </router-link>
                <span v-else>{{ usina.nome_cliente }}</span>
              </td>
              <td class="col-endereco">{{ usina.endereco }}</td>
              <td>
                <span class="badge status-badge" :class="statusBadgeClass(usina.status)">
                  {{ statusLabel(usina.status) }}
                </span>
              </td>
              <td>{{ usina.rede || '-' }}</td>
              <td>{{ usina.media_geracao_kwh ?? 0 }} kWh</td>
              <td class="nowrap">{{ usina.unidade_consumidor || '-' }}</td>
              <td>{{ usina.cia_energia || '-' }}</td>
              <td class="nowrap">{{ formatDate(usina.data_conexao) }}</td>
            </tr>
            <tr v-if="!usinas.length">
              <td colspan="8" class="text-center py-4">{{ mensagemSemResultados }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="paginacao-container" v-if="totalRegistros > 0">
      <div class="paginacao-info">
        Mostrando {{ inicioRegistro }} a {{ fimRegistro }} de {{ totalRegistros }} usinas
      </div>
      <div class="paginacao-acoes">
        <label for="porPagina">Itens por pagina</label>
        <select
          id="porPagina"
          class="form-select form-select-sm seletor-pagina"
          v-model.number="porPagina"
          @change="alterarPorPagina"
          :disabled="carregando"
        >
          <option v-for="opcao in opcoesPorPagina" :key="opcao" :value="opcao">
            {{ opcao }}
          </option>
        </select>

        <button
          class="btn btn-sm btn-outline-secondary"
          @click="irParaPagina(paginaAtual - 1)"
          :disabled="paginaAtual === 1 || carregando"
        >
          Anterior
        </button>

        <span class="pagina-atual">Pagina {{ paginaAtual }} de {{ ultimaPagina }}</span>

        <button
          class="btn btn-sm btn-outline-secondary"
          @click="irParaPagina(paginaAtual + 1)"
          :disabled="paginaAtual === ultimaPagina || carregando"
        >
          Proxima
        </button>
      </div>
    </section>
  </div>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
  name: 'ListaUsinas',
  data() {
    return {
      usinas: [],
      termoBusca: '',
      termoBuscaAplicado: '',
      paginaAtual: 1,
      porPagina: 10,
      totalRegistros: 0,
      ultimaPagina: 1,
      carregando: false,
      opcoesPorPagina: [5, 10, 20, 50]
    };
  },
  computed: {
    inicioRegistro() {
      if (!this.totalRegistros) return 0;
      return (this.paginaAtual - 1) * this.porPagina + 1;
    },
    fimRegistro() {
      return Math.min(this.paginaAtual * this.porPagina, this.totalRegistros);
    },
    mensagemSemResultados() {
      if (this.termoBuscaAplicado) {
        return `Nenhuma usina encontrada para "${this.termoBuscaAplicado}".`;
      }

      return 'Nenhuma usina encontrada.';
    }
  },
  methods: {
    async fetchUsinas(page = this.paginaAtual) {
      this.carregando = true;

      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const token = localStorage.getItem('token');
        const buscaAtiva = this.termoBuscaAplicado.length >= 2;
        const endpoint = buscaAtiva ? '/usina/busca' : '/usinas/listagem';
        const params = {
          page,
          per_page: this.porPagina
        };

        if (buscaAtiva) {
          params.nome_cliente = this.termoBuscaAplicado;
        }

        const response = await axios.get(`${baseURL}${endpoint}`, {
          params,
          headers: {
            Authorization: `Bearer ${token}`
          }
        });

        const payload = response.data;

        if (Array.isArray(payload)) {
          this.usinas = payload.map(this.normalizarUsinaListagem);
          this.totalRegistros = payload.length;
          this.paginaAtual = 1;
          this.ultimaPagina = 1;
          return;
        }

        this.usinas = (payload.data || []).map(this.normalizarUsinaListagem);
        this.totalRegistros = Number(payload.total || 0);
        this.paginaAtual = Number(payload.current_page || page);
        this.ultimaPagina = Number(payload.last_page || 1);

        if (!this.usinas.length && this.paginaAtual > this.ultimaPagina) {
          await this.fetchUsinas(this.ultimaPagina);
        }
      } catch (error) {
        console.error('Erro ao buscar usinas:', error);
        Swal.fire({
          icon: 'error',
          title: 'Erro ao carregar',
          text: 'Nao foi possivel carregar a lista de usinas.',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
      } finally {
        this.carregando = false;
      }
    },
    aplicarBusca() {
      const termo = this.termoBusca.trim();

      if (!termo) {
        this.termoBuscaAplicado = '';
        this.fetchUsinas(1);
        return;
      }

      if (termo.length < 2) {
        Swal.fire({
          icon: 'warning',
          title: 'Busca invalida',
          text: 'Digite pelo menos 2 caracteres para buscar.',
          confirmButtonColor: '#f28c1f',
          confirmButtonText: 'Entendi'
        });
        return;
      }

      this.termoBuscaAplicado = termo;
      this.fetchUsinas(1);
    },
    limparBusca() {
      if (!this.termoBusca && !this.termoBuscaAplicado) return;

      this.termoBusca = '';
      this.termoBuscaAplicado = '';
      this.fetchUsinas(1);
    },

    irParaPagina(page) {
      if (page < 1 || page > this.ultimaPagina || page === this.paginaAtual || this.carregando) return;
      this.fetchUsinas(page);
    },
    alterarPorPagina() {
      this.fetchUsinas(1);
    },
    normalizarTexto(texto) {
      return String(texto || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
    },
    statusBadgeClass(status) {
      const statusNormalizado = this.normalizarTexto(status);

      if (statusNormalizado === 'concluido') return 'bg-success';
      if (statusNormalizado === 'aguardando troca de titularidade') return 'bg-danger';
      if (statusNormalizado === 'troca solicitada') return 'bg-warning text-dark';
      return 'bg-secondary';
    },
    statusLabel(status) {
      const statusNormalizado = this.normalizarTexto(status);

      if (statusNormalizado === 'concluido') return 'Conectado';
      if (statusNormalizado === 'aguardando troca de titularidade') return 'Nao Conectado';
      if (statusNormalizado === 'troca solicitada') return 'Em processo';
      return 'Indefinido';
    },
    normalizarUsinaListagem(item) {
      const cidade = item?.cliente?.endereco?.cidade || '-';
      const estado = item?.cliente?.endereco?.estado || '-';

      return {
        usi_id: item?.usi_id ?? item?.id ?? null,
        nome_cliente: item?.nome_cliente || item?.cliente?.nome || '-',
        endereco: item?.endereco || `${cidade} - ${estado}`,
        status: item?.status || '-',
        rede: item?.rede || '-',
        media_geracao_kwh: item?.media_geracao_kwh ?? item?.dado_geracao?.media ?? 0,
        unidade_consumidor: item?.unidade_consumidor || item?.uc || '-',
        cia_energia: item?.cia_energia || item?.comercializacao?.cia_energia || '-',
        data_conexao: item?.data_conexao || item?.comercializacao?.data_conexao || null
      };
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
.usinas-page {
  max-width: 1280px;
  padding: 28px 20px 36px;
}

.page-header {
  display: flex;
  justify-content: center;
  margin-bottom: 14px;
}

.page-title {
  margin: 0;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: 0.2px;
}

.nowrap {
  white-space: nowrap;
}

.filtros-container {
  margin-bottom: 14px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.filtro-label {
  display: inline-block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #1f2937;
}

.filtro-acoes {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 8px;
  align-items: center;
}

.table-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
  overflow: hidden;
}

.table-responsive-custom {
  width: 100%;
  overflow-x: auto;
}

.tabela-usinas {
  width: 100%;
  min-width: 1020px;
  font-size: 0.93rem;
  border-collapse: collapse;
  margin: 0;
  background-color: #fff;
}

.tabela-usinas th,
.tabela-usinas td {
  padding: 12px 14px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
  vertical-align: middle;
}

.tabela-usinas th {
  font-weight: 600;
  white-space: nowrap;
  font-size: 0.9rem;
  background-color: #111827;
  color: #fff;
}

.tabela-usinas tbody tr:nth-child(even) {
  background-color: #f8fafc;
}

.tabela-usinas tbody tr:hover {
  background-color: #eef6ff;
}

.col-nome {
  min-width: 180px;
  color: black;
  text-decoration: none;
}

.link-cliente {
  color: black;
  text-decoration: none;
}

.col-endereco {
  min-width: 190px;
  line-height: 1.35;
}

.status-badge {
  font-size: 0.73rem;
  font-weight: 600;
  border-radius: 999px;
  padding: 0.35rem 0.58rem;
}

.paginacao-container {
  margin-top: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
  padding: 10px 12px;
}

.paginacao-info {
  color: #495057;
  font-size: 0.9rem;
}

.paginacao-acoes {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.paginacao-acoes label {
  margin: 0;
  color: #374151;
  font-size: 0.9rem;
}

.seletor-pagina {
  width: 88px;
}

.pagina-atual {
  min-width: 120px;
  text-align: center;
  font-weight: 600;
  color: #1f2937;
}

@media (max-width: 768px) {
  .usinas-page {
    padding: 20px 12px 28px;
  }

  .filtro-acoes {
    grid-template-columns: 1fr;
  }

  .paginacao-container {
    align-items: flex-start;
  }

  .paginacao-acoes {
    width: 100%;
  }
}
</style>
