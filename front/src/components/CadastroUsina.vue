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

          <!-- Header -->
          <div class="d-flex align-items-center mb-3">
            <h4 class="mb-0">Cadastro de Usinas</h4>
          </div>

          <!-- Identificação -->
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

          <!-- Endereço -->
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
              <select
                id="estado"
                class="form-control"
                :class="{ 'is-invalid': errors.estado }"
                v-model="form.estado"
                @change="errors.estado = ''"
              >
                <option disabled value="">Selecione o estado</option>
                <option v-for="sigla in estadosBrasil" :key="sigla" :value="sigla">
                  {{ sigla }}
                </option>
              </select>
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

          <!-- Informações de Processo -->
          <h5 class="mt-4">Informações do Processo</h5>
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="vendedor">Vendedor</label>
              <select id="vendedor" class="form-control" :class="{ 'is-invalid': errors.vendedor }" v-model="form.vendedor" @change="errors.vendedor = ''">
                <option disabled value="">Selecione o Vendedor</option>
                <option v-for="v in vendedor" :key="v.ven_id" :value="v.ven_id">
                  {{ v.nome }}
                </option>
              </select>
              <div v-if="errors.vendedor" class="invalid-feedback">{{ errors.vendedor }}</div>
            </div>
            <div class="col-md-4">
              <label for="andamento_processo">Status de Consumo</label>
              <input id="andamento_processo" type="text" class="form-control" v-model="form.andamento_processo" />
            </div>
            <div class="col-md-4">
              <label for="data_ass_contrato">Data Assinatura Contrato</label>
              <input id="data_ass_contrato" type="date" class="form-control" v-model="form.data_ass_contrato" />
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-3">
              <label for="data_limite_troca">Data Limite Troca Titularidade</label>
              <input
                id="data_limite_troca"
                type="date"
                class="form-control"
                :class="{ 'is-invalid': errors.data_limite_troca_titularidade }"
                v-model="form.data_limite_troca_titularidade"
              />
              <div v-if="errors.data_limite_troca_titularidade" class="invalid-feedback">
                {{ errors.data_limite_troca_titularidade }}
              </div>
            </div>
            <div class="col-md-3">
              <label for="status_usina">Status da Usina</label>
              <select
                id="status_usina"
                class="form-control"
                :class="{ 'is-invalid': errors.status }"
                v-model="form.status"
                @change="errors.status = ''"
              >
                <option disabled value="">Selecione o status</option>
                <option v-for="valorStatus in statusUsina" :key="valorStatus" :value="valorStatus">
                  {{ valorStatus }}
                </option>
              </select>
              <div v-if="errors.status" class="invalid-feedback">{{ errors.status }}</div>
            </div>
            <div class="col-md-3">
              <label for="rede_usina">Rede</label>
              <select
                id="rede_usina"
                class="form-control"
                :class="{ 'is-invalid': errors.rede }"
                v-model="form.rede"
                @change="errors.rede = ''"
              >
                <option disabled value="">Selecione o tipo de rede</option>
                <option v-for="valorRede in opcoesRede" :key="valorRede" :value="valorRede">
                  {{ valorRede }}
                </option>
              </select>
              <div v-if="errors.rede" class="invalid-feedback">{{ errors.rede }}</div>
            </div>
            <div class="col-md-3">
              <label for="uc">Unidade Consumidora</label>
              <input id="uc" type="text" class="form-control" v-model="form.uc" />
            </div>
          </div>

          <!-- Dados de Geração -->
          <h5 class="mt-4">Dados de Geração</h5>
          <div class="row">
            <div v-for="(mesLabel, mesKey) in meses" :key="mesKey" class="col-2 mb-2">
              <label :for="'consumo-' + mesKey">{{ mesKey }}</label>
              <input :id="'consumo-' + mesKey" type="number" class="form-control" v-model.number="form[mesKey]" />
            </div>
          </div>
          <div class="row mb-4 mt-3">
            <div class="col-md-3">
              <label for="media">Média</label>
              <input id="media" type="number" class="form-control" :value="mediaGeracao" readonly />
            </div>
            <div class="col-md-3">
              <label for="menorGeracao">Menor Geração</label>
              <input id="menorGeracao" type="number" class="form-control" :value="menorGeracao" readonly />
            </div>
          </div>

          <!-- Comercialização -->
          <h5>Comercialização</h5>
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="valorkwh">Valor do kWh</label>
              <input
                id="valorkwh"
                type="number"
                class="form-control"
                :class="{ 'is-invalid': errors.valor_kwh }"
                v-model="form.valor_kwh"
                @input="errors.valor_kwh = ''"
              />
              <div v-if="errors.valor_kwh" class="invalid-feedback">{{ errors.valor_kwh }}</div>
            </div>
            <div class="col-md-4">
              <label for="valorfixo">Valor Fixo</label>
              <input id="valorfixo" type="number" class="form-control" :value="valorFixoCalculado" readonly />
            </div>
            <div class="col-md-4">
              <label for="ciaenergia">CIA Energia</label>
              <select
                id="ciaenergia"
                class="form-control"
                :class="{ 'is-invalid': errors.cia_energia }"
                v-model="form.cia_energia"
                @change="errors.cia_energia = ''"
              >
                <option disabled value="">Selecione a CIA de Energia</option>
                <option v-for="cia in ciasEnergia" :key="cia" :value="cia">
                  {{ cia }}
                </option>
              </select>
              <div v-if="errors.cia_energia" class="invalid-feedback">{{ errors.cia_energia }}</div>
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="fioBComercializacao">Fio B (R$)</label>
              <input
                id="fioBComercializacao"
                type="number"
                step="0.0001"
                class="form-control"
                :class="{ 'is-invalid': errors.fio_b }"
                v-model.number="form.fio_b"
                @input="errors.fio_b = ''"
              />
              <div v-if="errors.fio_b" class="invalid-feedback">{{ errors.fio_b }}</div>
            </div>
            <div class="col-md-4">
              <label for="percentualLei">Percentual Lei 14300/23 (%)</label>
              <input
                id="percentualLei"
                type="number"
                step="0.01"
                class="form-control"
                :class="{ 'is-invalid': errors.percentual_lei }"
                v-model.number="form.percentual_lei"
                @input="errors.percentual_lei = ''"
              />
              <div v-if="errors.percentual_lei" class="invalid-feedback">{{ errors.percentual_lei }}</div>
            </div>
          </div>
          <div class="mb-3 col-md-4">
            <label for="valorfinalmedio">Valor Final Médio Projetado</label>
            <input
              id="valorfinalmedio"
              type="number"
              class="form-control"
              :class="{ 'is-invalid': errors.valor_final_medio }"
              :value="valorFinalMedioCalculado"
              readonly
            />
            <div v-if="errors.valor_final_medio" class="invalid-feedback">{{ errors.valor_final_medio }}</div>
          </div>

          <!-- Conexão -->
          <h5>Conexão</h5>
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="previsaoconexao">Previsão de Conexão</label>
              <input id="previsaoconexao" type="date" class="form-control" v-model="form.previsao_conexao" />
            </div>
            <div class="col-md-4">
              <label for="conexaofinal">Conexão Final</label>
              <input id="conexaofinal" type="date" class="form-control" v-model="form.conexao_final" />
            </div>
          </div>

          <!-- Ações -->
          <div class="mt-4 d-flex align-items-center">
            <button
              type="button"
              class="btn btn-submit"
              :disabled="isSubmitting"
              @click="submitForm"
            >
              Salvar
            </button>
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
      estadosBrasil: [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO'
      ],
      isSubmitting: false,
      form: {
        nome: '',
        cpf_cnpj: '',
        rua: '',
        bairro: '',
        complemento: '',
        numero: 0,
        cidade: '',
        estado: '',
        cep: '',
        telefone: '',
        email: '',
        cia_energia: '',
        vendedor: '',
        uc: '',
        rede: '',
        valor_kwh: 0,
        valor_fixo: 0,
        valor_final_medio: 0,
        fio_b: 0.13,
        percentual_lei: 45,
        previsao_conexao: '',
        conexao_final: '',
        data_conexao: '',
        andamento_processo: '',
        data_ass_contrato: '',
        data_limite_troca_titularidade: '',
        status: '',
        janeiro: 0, fevereiro: 0, marco: 0, abril: 0,
        maio: 0, junho: 0, julho: 0, agosto: 0,
        setembro: 0, outubro: 0, novembro: 0, dezembro: 0,
        media: 0,
      },
      vendedor: [],
      ciasEnergia: ['CELESC', 'COPEL', 'RGE'],
      opcoesRede: ['Tri', 'Bi', 'Mono'],
      statusUsina: ["Aguardando troca de titularidade", "Troca solicitada", "Concluído"],
      meses: {
        janeiro: 'Jan', fevereiro: 'Fev', marco: 'Mar', abril: 'Abr',
        maio: 'Mai', junho: 'Jun', julho: 'Jul', agosto: 'Ago',
        setembro: 'Set', outubro: 'Out', novembro: 'Nov', dezembro: 'Dez'
      },
      successMessage: '',
      errorMessage: '',
      errors: {},
    };
  },
  computed: {
    mediaGeracao() {
      const meses = Object.values(this.meses).map((_, i) => this.form[Object.keys(this.meses)[i]]);
      const soma = meses.reduce((acc, val) => acc + (parseFloat(val) || 0), 0);
      this.form.media = parseFloat((soma / 12).toFixed(2));
      return this.form.media;
    },
    menorGeracao() {
      const valores = Object.values(this.meses).map((_, i) => this.form[Object.keys(this.meses)[i]]);
      return Math.min(...valores);
    },
    valorFixoCalculado() {
      return parseFloat((this.menorGeracao * this.form.valor_kwh).toFixed(2)) || 0;
    },
    valorFinalMedioCalculado() {
      //Valor Final Projetado = (Média Geração * (Valor do kWh - ( FIO B * Percentual LEI)))
      const mediaGeracao = parseFloat(this.form.media) || 0;
      const valorKwh = parseFloat(this.form.valor_kwh) || 0;
      const fioB = parseFloat(this.form.fio_b) || 0;
      const percentualLei = parseFloat(this.form.percentual_lei) || 0;

      const descontoLei = fioB * percentualLei;
      const valorFinalMedio = mediaGeracao * (valorKwh - descontoLei);

      return parseFloat(valorFinalMedio.toFixed(2)) || 0;
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
    },
    'form.data_ass_contrato'(novaData) {
      if (!novaData) {
        this.form.data_limite_troca_titularidade = '';
        this.validarDataLimiteTroca();
        return;
      }
      const dataCalculada = this.calcularDataLimiteTroca(novaData);
      if (dataCalculada && this.form.data_limite_troca_titularidade !== dataCalculada) {
        this.form.data_limite_troca_titularidade = dataCalculada;
      }
      this.validarDataLimiteTroca();
    },
    'form.data_limite_troca_titularidade'() {
      this.validarDataLimiteTroca();
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
    calcularDataLimiteTroca(dataAssinatura) {
      if (!dataAssinatura) {
        return '';
      }
      const data = new Date(`${dataAssinatura}T00:00:00`);
      if (Number.isNaN(data.getTime())) {
        return '';
      }
      data.setDate(data.getDate() + 30);
      const ano = data.getFullYear();
      const mes = String(data.getMonth() + 1).padStart(2, '0');
      const dia = String(data.getDate()).padStart(2, '0');
      return `${ano}-${mes}-${dia}`;
    },
    validarDataLimiteTroca() {
      if (!this.form.data_ass_contrato) {
        delete this.errors.data_limite_troca_titularidade;
        return true;
      }
      const dataCalculada = this.calcularDataLimiteTroca(this.form.data_ass_contrato);
      if (!dataCalculada) {
        this.errors.data_limite_troca_titularidade = 'Data de assinatura inválida.';
        return false;
      }
      if (this.form.data_limite_troca_titularidade !== dataCalculada) {
        this.errors.data_limite_troca_titularidade = 'A data limite deve ser 30 dias após a assinatura.';
        return false;
      }
      delete this.errors.data_limite_troca_titularidade;
      return true;
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
        'cidade',
        'estado',
        'cep',
        'telefone',
        'email',
        'vendedor',
        'valor_kwh',
        'valor_final_medio',
        'fio_b',
        'percentual_lei',
        'cia_energia',
        'rede',
        'status'
      ];
      required.forEach((field) => {
        if (field === 'numero') {
          if (this.form.numero === null || this.form.numero === '') {
            this.errors.numero = 'Campo obrigatório';
          }
          return;
        }
        if (!this.form[field]) {
          this.errors[field] = 'Campo obrigatório';
        }
      });
      const datasValidas = this.validarDataLimiteTroca();
      return Object.keys(this.errors).length === 0 && datasValidas;
    },
    resetForm() {
      this.form = {
        nome: '',
        cpf_cnpj: '',
        rua: '',
        bairro: '',
        complemento: '',
        numero: 0,
        cidade: '',
        estado: '',
        cep: '',
        telefone: '',
        email: '',
        cia_energia: '',
        vendedor: '',
        uc: '',
        rede: '',
        valor_kwh: 0,
        valor_fixo: 0,
        valor_final_medio: 0,
        fio_b: 0.13,
        percentual_lei: 45,
        previsao_conexao: '',
        conexao_final: '',
        data_conexao: '',
        andamento_processo: '',
        data_ass_contrato: '',
        data_limite_troca_titularidade: '',
        status: '',
        janeiro: 0,
        fevereiro: 0,
        marco: 0,
        abril: 0,
        maio: 0,
        junho: 0,
        julho: 0,
        agosto: 0,
        setembro: 0,
        outubro: 0,
        novembro: 0,
        dezembro: 0,
        media: 0,
      };
    },
    async submitForm() {
      if (this.isSubmitting || !this.validateForm()) {
        return;
      }
      
      this.isSubmitting = true;

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

        // 3. Cadastrar Dados de Geracao
        const geracaoPayload = {
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
          media: this.form.media,
          menor_geracao: this.menorGeracao
        };

        const geracaoResponse = await axios.post(`${baseURL}/geracao`, geracaoPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const dger_id = geracaoResponse.data.id;

        // 4. Cadastrar Comercializacao
        const comercializacaoPayload = {
          valor_kwh: this.form.valor_kwh,
          valor_fixo: this.valorFixoCalculado,
          cia_energia: this.form.cia_energia,
          valor_final_media: this.valorFinalMedioCalculado,
          previsao_conexao: this.form.previsao_conexao,
          data_conexao: this.form.conexao_final,
          fio_b: this.form.fio_b,
          percentual_lei: this.form.percentual_lei
        };

        const comercializacaoResponse = await axios.post(`${baseURL}/comercializacao`, comercializacaoPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const com_id = comercializacaoResponse.data.id;

        // 5. Cadastrar Créditos Distribuídos
        const creditosDistribuidosResponse = await axios.post(`${baseURL}/creditos-distribuidos`, {}, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const cd_id = creditosDistribuidosResponse.data.id;

        // 6. Cadastrar Faturamento da Usina
        const faturamentoUsinaResponse = await axios.post(`${baseURL}/faturamento-usina`, {}, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const fa_id = faturamentoUsinaResponse.data.id;

        // 7. Cadastrar Valor Acumulado em Reserva
        const valorAcumuladoReserva = await axios.post(`${baseURL}/valor-acumulado-reserva`, {}, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const var_id = valorAcumuladoReserva.data.id;

        // 8. Cadastrar Usina
        const usinaPayload = {
          cli_id: cli_id,
          dger_id: dger_id,
          com_id: com_id,
          ven_id: this.form.vendedor,
          uc: this.form.uc,
          rede: this.form.rede,
          andamento_processo: this.form.andamento_processo,
          data_ass_contrato: this.form.data_ass_contrato,
          data_limite_troca_titularidade: this.form.data_limite_troca_titularidade,
          status: this.form.status
        };
        
        Response = await axios.post(`${baseURL}/usina`, usinaPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const usi_id = Response.data.id;

        const creditosDistribuidosUsinaPayload = {
          usi_id: usi_id,
          cli_id: cli_id,
          cd_id: cd_id,
          fa_id: fa_id,
          var_id: var_id,
          ano: new Date().getFullYear()
        }

        // 9. Cadastrar Créditos Distribuídos Usina
        await axios.post(`${baseURL}/creditos-distribuidos-usina`, creditosDistribuidosUsinaPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        // 10. Cadastrar Dados Geração Real
        const dadosGeracaoReal = await axios.post(`${baseURL}/dados-geracao-real`, {}, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const dadosGeracaoUsinaPayload = {
          usi_id: usi_id,
          cli_id: cli_id,
          dgr_id: dadosGeracaoReal.data.id,
          ano: new Date().getFullYear()
        }

        // 11. Cadastrar Dados Geração Real
        await axios.post(`${baseURL}/dados-geracao-real-usina`, dadosGeracaoUsinaPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        this.successMessage = "Usina cadastrada com sucesso!";
        this.errorMessage = "";
        this.resetForm();

      } catch (error) {
        this.successMessage = "";
        this.errorMessage = error.response?.data?.message || "Erro ao cadastrar consumidor.";
        console.error("Erro:", error);

        setTimeout(() => {
          this.errorMessage = '';
        }, 3000);
      } finally {
        this.isSubmitting = false;
      }
    },
    goBack() {
      this.$router.push('/usinas');
    },
  }
};
</script>

<style scoped>

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

label {
  font-weight: 500;
}
</style>