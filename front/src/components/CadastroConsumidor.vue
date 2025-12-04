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
              <input
                id="name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.nome }"
                v-model="form.nome"
                @input="errors.nome = ''"
              />
              <div v-if="errors.nome" class="invalid-feedback">{{ errors.nome }}</div>
            </div>
            <div class="col-md-6">
              <label for="cpf/cnpj">CPF/CNPJ</label>
              <input
                id="cpf/cnpj"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.cpf_cnpj }"
                v-model="form.cpf_cnpj"
                @input="errors.cpf_cnpj = ''"
              />
              <div v-if="errors.cpf_cnpj" class="invalid-feedback">{{ errors.cpf_cnpj }}</div>
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-5">
              <label for="endereco">Endereço</label>
              <input
                id="endereco"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.rua }"
                v-model="form.rua"
                @input="errors.rua = ''"
              />
              <div v-if="errors.rua" class="invalid-feedback">{{ errors.rua }}</div>
            </div>
            <div class="col-md-1">
              <label for="numero">Número</label>
              <input
                id="numero"
                type="number"
                class="form-control"
                :class="{ 'is-invalid': errors.numero }"
                v-model="form.numero"
                @input="errors.numero = ''"
              />
              <div v-if="errors.numero" class="invalid-feedback">{{ errors.numero }}</div>
            </div>
            <div class="col-md-3">
              <label for="bairro">Bairro</label>
              <input
                id="bairro"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.bairro }"
                v-model="form.bairro"
                @input="errors.bairro = ''"
              />
              <div v-if="errors.bairro" class="invalid-feedback">{{ errors.bairro }}</div>
            </div>
            <div class="col-md-3">
              <label for="complemento">Complemento</label>
              <input
                id="complemento"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.complemento }"
                v-model="form.complemento"
                @input="errors.complemento = ''"
              />
              <div v-if="errors.complemento" class="invalid-feedback">{{ errors.complemento }}</div>
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-md-4">
              <label for="cidade">Cidade</label>
              <input
                id="cidade"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.cidade }"
                v-model="form.cidade"
                @input="errors.cidade = ''"
              />
              <div v-if="errors.cidade" class="invalid-feedback">{{ errors.cidade }}</div>
            </div>
            <div class="col-md-4">
              <label for="estado">Estado</label>
              <input
                id="estado"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.estado }"
                v-model="form.estado"
                @input="errors.estado = ''"
              />
              <div v-if="errors.estado" class="invalid-feedback">{{ errors.estado }}</div>
            </div>
            <div class="col-md-4">
              <label for="cep">CEP</label>
              <input
                id="cep"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.cep }"
                v-model="form.cep"
                @input="errors.cep = ''"
              />
              <div v-if="errors.cep" class="invalid-feedback">{{ errors.cep }}</div>
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-md-6">
              <label for="telefone">Telefone</label>
              <input
                id="telefone"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.telefone }"
                v-model="form.telefone"
                @input="errors.telefone = ''"
              />
              <div v-if="errors.telefone" class="invalid-feedback">{{ errors.telefone }}</div>
            </div>
            <div class="col-md-6">
              <label for="email">E-mail</label>
              <input
                id="email"
                type="email"
                class="form-control"
                :class="{ 'is-invalid': errors.email }"
                v-model="form.email"
                @input="errors.email = ''"
              />
              <div v-if="errors.email" class="invalid-feedback">{{ errors.email }}</div>
            </div>
          </div>
          <div class="row mb-2 mt-2">
            <div class="col-md-3">
              <label for="vendedor">Vendedor</label>
              <select id="vendedor" class="form-control" :class="{ 'is-invalid': errors.vendedor }" v-model="form.vendedor" @change="errors.vendedor = ''">
                <option disabled value="">Selecione o Vendedor</option>
                <option v-for="v in vendedor" :key="v.ven_id" :value="v.ven_id">
                  {{ v.nome }}
                </option>
              </select>
              <div v-if="errors.vendedor" class="invalid-feedback">{{ errors.vendedor }}</div>
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
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="ciaenergia">CIA Energia</label>
              <select id="ciaenergia" class="form-control" :class="{ 'is-invalid': errors.cia_energia }" v-model="form.cia_energia" @change="errors.cia_energia = ''">
                <option disabled value="">Selecione a CIA de Energia</option>
                <option v-for="cia in ciasEnergia" :key="cia" :value="cia">
                  {{ cia }}
                </option>
              </select>
              <div v-if="errors.cia_energia" class="invalid-feedback">{{ errors.cia_energia }}</div>
            </div>
            <div class="col-md-4">
              <label for="uc">Unidade Consumidora</label>
              <input id="uc" type="text" class="form-control" v-model="form.uc" />
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-6">
              <label>Rede</label>
              <div>
                <label class="me-2">
                  <input type="radio" value="Monofásico" v-model="form.rede" /> Monofásico
                </label>
                <label class="me-2">
                  <input type="radio" value="Bifásico" v-model="form.rede" /> Bifásico
                </label>
                <label>
                  <input type="radio" value="Trifásico" v-model="form.rede" /> Trifásico
                </label>
              </div>
              <div v-if="errors.rede" class="invalid-feedback d-block">{{ errors.rede }}</div>
            </div>
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
            <button type="button" class="btn btn-submit" @click="submitForm">Salvar</button>
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
        complemento: '',
        cep: '',
        telefone: '',
        email: '',
        cia_energia: '',
        vendedor: '',
        uc: '',
        rede: '',
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
      const meses = Object.keys(this.meses).map((mes) => this.form[mes]);
      const soma = meses.reduce((acc, val) => acc + (parseFloat(val) || 0), 0);
      let media = soma / 12;
      switch (this.form.rede) {
        case 'Trifásico':
          media += 100;
          break;
        case 'Bifásico':
          media += 50;
          break;
        case 'Monofásico':
          media += 30;
          break;
      }
      this.form.media = parseFloat(media.toFixed(2));
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
        'cia_energia',
        'rede'
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
        rede: '',
      };
    },
    async submitForm() {
      if (!this.validateForm()) {
        return;
      }
      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const token = localStorage.getItem('token');

        // 1. Cadastrar Endereço
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
          uc: this.form.uc,
          rede: this.form.rede,
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

.btn-submit{
  color: white;
  background-color: #f28c1f;
}

.btn-submit:hover{
  color: white;
  background-color: #d97706;
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