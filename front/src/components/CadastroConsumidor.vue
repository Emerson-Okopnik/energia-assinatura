<template>
  <div>
    <div v-if="errorMessage" class="alert-float alert alert-danger">
      {{ errorMessage }}
    </div>
    <div v-if="successMessage" class="alert-float alert alert-success">
      {{ successMessage }}
    </div>
    <div class="container mt-5 pt-5">
      <div class="row">
        <div class="col-md-12">
          <div class="d-flex align-items-center mb-3">
            <h4 class="mb-0">Cadastro de Consumidores</h4>
            <div class="mx-3">
              <button class="btn btn-success btn-sm me-2">Conectado</button>
              <button class="btn btn-danger btn-sm">Não Conectado</button>
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-6">
              <label for="name">Nome</label>
              <input id="name" type="text" class="form-control" v-model="form.nome" />
            </div>
            <div class="col-md-6">
              <label for="cpf/cnpj">CPF/CNPJ</label>
              <input id="cpf/cnpj" type="text" class="form-control" v-model="form.cpf_cnpj" />
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-6">
              <label for="endereco">Endereço</label>
              <input id="endereco" type="text" class="form-control" v-model="form.rua" />
            </div>
            <div class="col-md-2">
              <label for="numero">Número</label>
              <input id="numero" type="number" class="form-control" v-model="form.numero" />
            </div>
            <div class="col-md-4">
              <label for="bairro">Bairro</label>
              <input id="bairro" type="text" class="form-control" v-model="form.bairro" />
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-md-4">
              <label for="cidade">Cidade</label>
              <input id="cidade" type="text" class="form-control" v-model="form.cidade" />
            </div>
            <div class="col-md-4">
              <label for="estado">Estado</label>
              <input id="estado" type="text" class="form-control" v-model="form.estado" />
            </div>
            <div class="col-md-4">
              <label for="cep">CEP</label>
              <input id="cep" type="text" class="form-control" v-model="form.cep" />
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-md-6">
              <label for="telefone">Telefone</label>
              <input id="telefone" type="text" class="form-control" v-model="form.telefone" />
            </div>
            <div class="col-md-6">
              <label for="email">E-mail</label>
              <input id="email" type="email" class="form-control" v-model="form.email" />
            </div>
          </div>
          <div class="row mb-2 mt-2">
            <div class="col-md-3">
              <label for="vendedor">Vendedor</label>
              <select id="vendedor" class="form-control" v-model="form.vendedor">
                <option disabled value="">Selecione o Vendedor</option>
                <option v-for="v in vendedor" :key="v.ven_id" :value="v.ven_id">
                  {{ v.nome }}
                </option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="data_entrega">Data de Entrega</label>
              <input id="data_entrega" type="date" class="form-control" v-model="form.data_entrega" />
            </div>
            <div class="col-md-3">
              <label for="status">Status</label>
              <select id="status" class="form-control" v-model="form.status">
                <option disabled value="">Selecione o status</option>
                <option v-for="valorStatus in status" :key="valorStatus" :value="valorStatus">
                  {{ valorStatus }}
                </option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="alocacao">Alocação</label>
              <select id="alocacao" class="form-control" v-model="form.alocacao">
                <option disabled value="">Selecione o status da Alocação</option>
                <option v-for="valorAlocacao in opcoesAlocacao" :key="valorAlocacao" :value="valorAlocacao">
                  {{ valorAlocacao }}
                </option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <label for="ciaenergia">CIA Energia</label>
            <select id="ciaenergia" class="form-control" v-model="form.cia_energia">
              <option disabled value="">Selecione a CIA de Energia</option>
              <option v-for="cia in ciasEnergia" :key="cia" :value="cia">
                {{ cia }}
              </option>
            </select>
          </div>
          <h5 class="mt-4">Dados de Consumo</h5>
          <div class="row">
            <div v-for="(mesLabel, mesKey) in meses" :key="mesKey" class="col-2 mb-2">
              <label :for="'consumo-' + mesKey">{{ mesKey }}</label>
              <input :id="'consumo-' + mesKey" type="number" class="form-control" v-model.number="form[mesKey]" />
            </div>
          </div>

          <div class="col-md-3 mt-3">
            <label for="media">Média</label>
            <input id="media" type="number" class="form-control" :value="mediaConsumo" readonly />
          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-primary" @click="submitForm">Salvar</button>
            <button type="button" class="btn btn-secondary ms-2" @click="goBack">Cancelar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "axios";

export default {
  data() {
    return {
      form: {
        nome: '',
        cpf_cnpj: '',
        rua: '',
        numero: null,
        cidade: '',
        estado: '',
        bairro: '',
        cep: '',
        telefone: '',
        email: '',
        cia_energia: '',
        vendedor: '', 
        data_entrega: '',
        status: '',
        alocacao: '',
        janeiro: 0, fevereiro: 0, marco: 0, abril: 0,
        maio: 0, junho: 0, julho: 0, agosto: 0,
        setembro: 0, outubro: 0, novembro: 0, dezembro: 0,
        media: 0
      },
      vendedor: [],
      status: ['Envio dos documentos para assinatura', 'Aderido'],
      opcoesAlocacao: ['Parado', 'Alocado'],
      ciasEnergia: ['CELESC', 'COPEL', 'RGE'],
      meses: {
        janeiro: 'Jan',
        fevereiro: 'Fev',
        marco: 'Mar',
        abril: 'Abr',
        maio: 'Mai',
        junho: 'Jun',
        julho: 'Jul',
        agosto: 'Ago',
        setembro: 'Set',
        outubro: 'Out',
        novembro: 'Nov',
        dezembro: 'Dez'
      },
      successMessage: '',
      errorMessage: ''
    };
  },
  computed: {
    mediaConsumo() {
      const meses = Object.values(this.meses).map((_, i) => this.form[Object.keys(this.meses)[i]]);
      const soma = meses.reduce((acc, val) => acc + (parseFloat(val) || 0), 0);
      this.form.media = parseFloat((soma / 12).toFixed(2));
      return this.form.media;
    }
  },
  mounted() {
    this.fetchVendedores();
  },
  methods: {
    async fetchVendedores() {
      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const token = localStorage.getItem('token');
        const response = await axios.get(`${baseURL}/vendedor`, {
          headers: { Authorization: `Bearer ${token}` },
        });
        this.vendedor = response.data;
      } catch (error) {
        console.error("Erro ao carregar vendedores:", error);
      }
    },
    async submitForm() {
      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const token = localStorage.getItem('token');

        // 1. Cadastrar Endereço
        const enderecoPayload = {
          rua: this.form.rua,
          cidade: this.form.cidade,
          estado: this.form.estado,
          complemento: this.form.bairro,
          cep: this.form.cep,
          numero: this.form.numero ?? 0
        };

        const enderecoResponse = await axios.post(`${baseURL}/endereco`, enderecoPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const end_id = enderecoResponse.data.id;

        // 2. Cadastrar Cliente
        const clientePayload = {
          nome: this.form.nome,
          cpf_cnpj: this.form.cpf_cnpj,
          telefone: this.form.telefone,
          email: this.form.email,
          end_id: end_id
        };

        const clienteResponse = await axios.post(`${baseURL}/cliente`, clientePayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const cli_id = clienteResponse.data.id;

        // 3. Cadastrar Dados de Consumo
        const consumoPayload = {
          janeiro: this.form.janeiro,
          fevereiro: this.form.fevereiro,
          marco: this.form.marco,
          abril: this.form.abril,
          maio: this.form.maio,
          junho: this.form.junho,
          julho: this.form.julho,
          agosto: this.form.agosto,
          setembro: this.form.setembro,
          outubro: this.form.outubro,
          novembro: this.form.novembro,
          dezembro: this.form.dezembro,
          media: this.form.media
        };

        const consumoResponse = await axios.post(`${baseURL}/consumo`, consumoPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const dcon_id = consumoResponse.data.id;

        // 4. Cadastrar Consumidor
        const consumidorPayload = {
          cli_id: cli_id,
          dcon_id: dcon_id,
          cia_energia: this.form.cia_energia,
          ven_id: this.form.vendedor,
          data_entrega: this.form.data_entrega,
          status: this.form.status,
          alocacao: this.form.alocacao
        };

        await axios.post(`${baseURL}/consumidor`, consumidorPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        this.successMessage = "Consumidor cadastrado com sucesso!";
        this.errorMessage = "";

      } catch (error) {
        this.successMessage = "";
        this.errorMessage = error.response?.data?.message || "Erro ao cadastrar consumidor.";
        console.error("❌ Erro:", error);

        setTimeout(() => {
          this.errorMessage = '';
        }, 3000);
      }
    },
    goBack() {
      this.$router.push('/consumidores');
    }
  }
};
</script>

<style scoped>
label {
  font-weight: 500;
}

.alert-float {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  min-width: 250px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  animation: fadeOut 3s forwards;
}

@keyframes fadeOut {
  0% {
    opacity: 1;
  }

  80% {
    opacity: 1;
  }

  100% {
    opacity: 0;
    display: none;
  }
}
</style>