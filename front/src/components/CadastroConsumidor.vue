<template>
  <div class="min-h-screen bg-gray-50 pt-20">
    <!-- Converted Bootstrap alerts to Tailwind toast notifications -->
    <div v-if="errorMessage" class="fixed top-20 right-4 z-50 max-w-sm">
      <div class="bg-red-500 text-white px-4 py-3 rounded-lg shadow-lg animate-fade-in">
        {{ errorMessage }}
      </div>
    </div>
    <div v-if="successMessage" class="fixed top-20 right-4 z-50 max-w-sm">
      <div class="bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg animate-fade-in">
        {{ successMessage }}
      </div>
    </div>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
      <!-- Updated header section with Tailwind layout -->
      <div class="card mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
          <h1 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">Cadastro de Consumidores</h1>
          <div class="flex space-x-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
              Conectado
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
              Não Conectado
            </span>
          </div>
        </div>

        <form @submit.prevent="submitForm" class="space-y-6">
          <!-- Personal Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nome</label>
              <input
                id="name"
                type="text"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.nome }"
                v-model="form.nome"
                @input="errors.nome = ''"
              />
              <div v-if="errors.nome" class="mt-1 text-sm text-red-600">{{ errors.nome }}</div>
            </div>
            <div>
              <label for="cpf/cnpj" class="block text-sm font-medium text-gray-700 mb-2">CPF/CNPJ</label>
              <input
                id="cpf/cnpj"
                type="text"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.cpf_cnpj }"
                v-model="form.cpf_cnpj"
                @input="errors.cpf_cnpj = ''"
              />
              <div v-if="errors.cpf_cnpj" class="mt-1 text-sm text-red-600">{{ errors.cpf_cnpj }}</div>
            </div>
          </div>

          <!-- Address Information -->
          <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-5">
              <label for="endereco" class="block text-sm font-medium text-gray-700 mb-2">Endereço</label>
              <input
                id="endereco"
                type="text"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.rua }"
                v-model="form.rua"
                @input="errors.rua = ''"
              />
              <div v-if="errors.rua" class="mt-1 text-sm text-red-600">{{ errors.rua }}</div>
            </div>
            <div class="md:col-span-1">
              <label for="numero" class="block text-sm font-medium text-gray-700 mb-2">Número</label>
              <input
                id="numero"
                type="number"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.numero }"
                v-model="form.numero"
                @input="errors.numero = ''"
              />
              <div v-if="errors.numero" class="mt-1 text-sm text-red-600">{{ errors.numero }}</div>
            </div>
            <div class="md:col-span-3">
              <label for="bairro" class="block text-sm font-medium text-gray-700 mb-2">Bairro</label>
              <input
                id="bairro"
                type="text"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.bairro }"
                v-model="form.bairro"
                @input="errors.bairro = ''"
              />
              <div v-if="errors.bairro" class="mt-1 text-sm text-red-600">{{ errors.bairro }}</div>
            </div>
            <div class="md:col-span-3">
              <label for="complemento" class="block text-sm font-medium text-gray-700 mb-2">Complemento</label>
              <input
                id="complemento"
                type="text"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.complemento }"
                v-model="form.complemento"
                @input="errors.complemento = ''"
              />
              <div v-if="errors.complemento" class="mt-1 text-sm text-red-600">{{ errors.complemento }}</div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label for="cidade" class="block text-sm font-medium text-gray-700 mb-2">Cidade</label>
              <input
                id="cidade"
                type="text"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.cidade }"
                v-model="form.cidade"
                @input="errors.cidade = ''"
              />
              <div v-if="errors.cidade" class="mt-1 text-sm text-red-600">{{ errors.cidade }}</div>
            </div>
            <div>
              <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
              <input
                id="estado"
                type="text"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.estado }"
                v-model="form.estado"
                @input="errors.estado = ''"
              />
              <div v-if="errors.estado" class="mt-1 text-sm text-red-600">{{ errors.estado }}</div>
            </div>
            <div>
              <label for="cep" class="block text-sm font-medium text-gray-700 mb-2">CEP</label>
              <input
                id="cep"
                type="text"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.cep }"
                v-model="form.cep"
                @input="errors.cep = ''"
              />
              <div v-if="errors.cep" class="mt-1 text-sm text-red-600">{{ errors.cep }}</div>
            </div>
          </div>

          <!-- Contact Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="telefone" class="block text-sm font-medium text-gray-700 mb-2">Telefone</label>
              <input
                id="telefone"
                type="text"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.telefone }"
                v-model="form.telefone"
                @input="errors.telefone = ''"
              />
              <div v-if="errors.telefone" class="mt-1 text-sm text-red-600">{{ errors.telefone }}</div>
            </div>
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 mb-2">E-mail</label>
              <input
                id="email"
                type="email"
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.email }"
                v-model="form.email"
                @input="errors.email = ''"
              />
              <div v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</div>
            </div>
          </div>

          <!-- Business Information -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label for="vendedor" class="block text-sm font-medium text-gray-700 mb-2">Vendedor</label>
              <select 
                id="vendedor" 
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.vendedor }" 
                v-model="form.vendedor" 
                @change="errors.vendedor = ''"
              >
                <option disabled value="">Selecione o Vendedor</option>
                <option v-for="v in vendedor" :key="v.ven_id" :value="v.ven_id">
                  {{ v.nome }}
                </option>
              </select>
              <div v-if="errors.vendedor" class="mt-1 text-sm text-red-600">{{ errors.vendedor }}</div>
            </div>
            <div>
              <label for="data_entrega" class="block text-sm font-medium text-gray-700 mb-2">Data de Entrega</label>
              <input id="data_entrega" type="date" class="input-field" v-model="form.data_entrega" />
            </div>
            <div>
              <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
              <select id="status" class="input-field" v-model="form.status">
                <option disabled value="">Selecione o status</option>
                <option v-for="valorStatus in status" :key="valorStatus" :value="valorStatus">
                  {{ valorStatus }}
                </option>
              </select>
            </div>
            <div>
              <label for="alocacao" class="block text-sm font-medium text-gray-700 mb-2">Alocação</label>
              <select id="alocacao" class="input-field" v-model="form.alocacao">
                <option disabled value="">Selecione o status da Alocação</option>
                <option v-for="valorAlocacao in opcoesAlocacao" :key="valorAlocacao" :value="valorAlocacao">
                  {{ valorAlocacao }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="ciaenergia" class="block text-sm font-medium text-gray-700 mb-2">CIA Energia</label>
              <select 
                id="ciaenergia" 
                class="input-field"
                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.cia_energia }" 
                v-model="form.cia_energia" 
                @change="errors.cia_energia = ''"
              >
                <option disabled value="">Selecione a CIA de Energia</option>
                <option v-for="cia in ciasEnergia" :key="cia" :value="cia">
                  {{ cia }}
                </option>
              </select>
              <div v-if="errors.cia_energia" class="mt-1 text-sm text-red-600">{{ errors.cia_energia }}</div>
            </div>
            <div>
              <label for="uc" class="block text-sm font-medium text-gray-700 mb-2">Unidade Consumidora</label>
              <input id="uc" type="text" class="input-field" v-model="form.uc" />
            </div>
          </div>

          <!-- Consumption Data -->
          <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Dados de Consumo</h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
              <div v-for="(mesLabel, mesKey) in meses" :key="mesKey">
                <label :for="'consumo-' + mesKey" class="block text-sm font-medium text-gray-700 mb-2">{{ mesKey }}</label>
                <input 
                  :id="'consumo-' + mesKey" 
                  type="number" 
                  class="input-field" 
                  v-model.number="form[mesKey]" 
                />
              </div>
            </div>

            <div class="mt-4 max-w-xs">
              <label for="media" class="block text-sm font-medium text-gray-700 mb-2">Média</label>
              <input 
                id="media" 
                type="number" 
                class="input-field bg-gray-50" 
                :value="mediaConsumo" 
                readonly 
              />
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-col sm:flex-row gap-4 pt-6">
            <button type="submit" class="btn-primary">
              Salvar
            </button>
            <button type="button" class="btn-secondary" @click="goBack">
              Cancelar
            </button>
          </div>
        </form>
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
        complemento: '',
        cep: '',
        telefone: '',
        email: '',
        cia_energia: '',
        vendedor: '', 
        uc: '',
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
      errorMessage: '',
      errors: {}
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
  watch: {
    'form.cpf_cnpj'(val) {
      const formatted = this.formatCpfCnpj(val);
      if (formatted !== val) {
        this.form.cpf_cnpj = formatted;
      }
    },
    'form.telefone'(val) {
      const formatted = this.formatTelefone(val);
      if (formatted !== val) {
        this.form.telefone = formatted;
      }
    }
  },
  methods: {
    formatCpfCnpj(value) {
      let v = (value || '').replace(/\D/g, '');
      if (v.length <= 11) {
        v = v.slice(0, 11)
          .replace(/(\d{3})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      } else {
        v = v.slice(0, 14)
          .replace(/(\d{2})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d)/, '$1/$2')
          .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
      }
      return v;
    },

    formatTelefone(value) {
      let v = (value || '').replace(/\D/g, '').slice(0, 11);
      if (v.length <= 10) {
        v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
      } else {
        v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
      }
      return v.trim();
    },
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
    validateForm() {
      this.errors = {};
      const required = [
        'nome',
        'cpf_cnpj',
        'rua',
        'numero',
        'bairro',
        'complemento',
        'cidade',
        'estado',
        'cep',
        'telefone',
        'email',
        'vendedor',
        'cia_energia'
      ];
      required.forEach((field) => {
        if (!this.form[field]) {
          this.errors[field] = 'Campo obrigatório';
        }
      });
      return Object.keys(this.errors).length === 0;
    },
    resetForm() {
      this.form = {
        nome: '',
        cpf_cnpj: '',
        rua: '',
        numero: null,
        cidade: '',
        estado: '',
        bairro: '',
        complemento: '',
        cep: '',
        telefone: '',
        email: '',
        cia_energia: '',
        vendedor: '',
        uc: '',
      };
    },
    async submitForm() {
      if (!this.validateForm()) {
        return;
      }
      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const token = localStorage.getItem('token');

        // Cadastrar Endereço
        const enderecoPayload = {
          rua: this.form.rua,
          cidade: this.form.cidade,
          estado: this.form.estado,
          bairro: this.form.bairro,
          complemento: this.form.complemento,
          cep: this.form.cep,
          numero: this.form.numero ?? 0
        };

        const enderecoResponse = await axios.post(`${baseURL}/endereco`, enderecoPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const end_id = enderecoResponse.data.id;

        // Cadastrar Cliente
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

        // Cadastrar Dados de Consumo
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

        // Cadastrar Consumidor
        const consumidorPayload = {
          cli_id: cli_id,
          dcon_id: dcon_id,
          cia_energia: this.form.cia_energia,
          uc: this.form.uc,
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
        this.resetForm();

        setTimeout(() => {
          this.successMessage = '';
        }, 3000);

      } catch (error) {
        this.successMessage = "";
        this.errorMessage = error.response?.data?.message || "Erro ao cadastrar consumidor.";
        console.error("Erro:", error);

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
@keyframes fade-in {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fade-in 0.3s ease-out;
}

.input-field {
  @apply border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm;
}

.btn-primary {
  @apply bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
}

.btn-secondary {
  @apply bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500;
}
</style>
