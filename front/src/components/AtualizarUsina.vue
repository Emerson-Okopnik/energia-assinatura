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
            <div class="mx-3">
              <span v-if="form.status === 'Concluído'" class="badge bg-success">Conectado</span>
              <span v-else-if="form.status === 'Aguardando troca de titularidade'" class="badge bg-danger">Não Conectado</span>
              <span v-else-if="form.status === 'Troca solicitada'" class="badge bg-warning text-dark">Em processo</span>
              <span v-else class="badge bg-secondary">Status indefinido</span>
            </div>
          </div>
          <!-- Identificação -->
          <div class="row mb-2">
            <div class="col-md-6">
              <label for="name">Nome <span class="required-asterisk">*</span></label>
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
              <label for="cpf/cnpj">CPF/CNPJ <span class="required-asterisk">*</span></label>
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
              <label for="endereco">Endereço <span class="required-asterisk">*</span></label>
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
              <label for="numero">Número <span class="required-asterisk">*</span></label>
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
              <label for="bairro">Bairro <span class="required-asterisk">*</span></label>
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
              <input id="complemento" type="text" class="form-control" v-model="form.complemento"/>
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="cidade">Cidade <span class="required-asterisk">*</span></label>
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
              <label for="estado">Estado <span class="required-asterisk">*</span></label>
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
              <label for="cep">CEP <span class="required-asterisk">*</span></label>
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
              <label for="telefone">Telefone <span class="required-asterisk">*</span></label>
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
              <label for="email">E-mail <span class="required-asterisk">*</span></label>
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
              <label for="vendedor">Vendedor <span class="required-asterisk">*</span></label>
              <select
                id="vendedor"
                class="form-control"
                :class="{ 'is-invalid': errors.vendedor }"
                v-model="form.vendedor"
                @change="errors.vendedor = ''"
              >
                <option disabled value="">Selecione o Vendedor</option>
                <option v-for="v in vendedor" :key="v.ven_id" :value="v.ven_id">
                  {{ v.nome }}
                </option>
              </select>
              <div v-if="errors.vendedor" class="invalid-feedback">{{ errors.vendedor }}</div>
            </div>
            <div class="col-md-4">
              <label for="data_ass_contrato">Data Assinatura Contrato</label>
              <input id="data_ass_contrato" type="date" class="form-control" v-model="form.data_ass_contrato" />
            </div>
            <div class="col-md-4">
              <label for="data_limite_troca">Data Limite Troca Titularidade</label>
              <input
                id="data_limite_troca"
                type="date"
                class="form-control"
                :class="{ 'is-invalid': dataLimiteErro }"
                v-model="form.data_limite_troca_titularidade"
              />
              <div v-if="dataLimiteErro" class="invalid-feedback d-block">
                {{ dataLimiteErro }}
              </div>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-3">
              <label for="andamento_processo">Status de Consumo</label>
              <input id="andamento_processo" type="text" class="form-control" v-model="form.andamento_processo" />
            </div>
            <div class="col-md-3">
              <label for="status_usina">Status da Usina <span class="required-asterisk">*</span></label>
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
              <label for="rede_usina">Rede <span class="required-asterisk">*</span></label>
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
              <label for="valorkwh">Valor do kWh <span class="required-asterisk">*</span></label>
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
              <label for="valorfixo">Valor Fixo (R$)</label>
              <div class="input-group">
                <span class="input-group-text">R$</span>
                <input
                  id="valorfixo"
                  type="text"
                  class="form-control"
                  :value="formatCurrency(valorFixoCalculado)"
                  readonly
                />
              </div>
            </div>
            <div class="col-md-4">
              <label for="ciaenergia">CIA Energia <span class="required-asterisk">*</span></label>
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
              <label for="fioB">Fio B (R$) <span class="required-asterisk">*</span></label>
              <input
                id="fioB"
                type="number"
                step="0.00001"
                class="form-control"
                :class="{ 'is-invalid': errors.fio_b }"
                v-model.number="form.fio_b"
                @input="errors.fio_b = ''"
              />
              <div v-if="errors.fio_b" class="invalid-feedback">{{ errors.fio_b }}</div>
            </div>
            <div class="col-md-4">
              <label for="percentualLei">Percentual Lei 14300/23 (%) <span class="required-asterisk">*</span></label>
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
            <label for="valorfinalmedio">Valor Final Médio Projetado (R$) <span class="required-asterisk">*</span></label>
            <div class="input-group">
              <span class="input-group-text">R$</span>
              <input
                id="valorfinalmedio"
                type="text"
                inputmode="decimal"
                class="form-control"
                v-model="valorFinalMedioInput"
                :class="{ 'is-invalid': errors.valor_final_medio }"
                @input="errors.valor_final_medio = ''"
                @blur="ajustarValorFinalMedio"
              />
            </div>
            <div v-if="errors.valor_final_medio" class="invalid-feedback d-block">{{ errors.valor_final_medio }}</div>
          </div>
          <!-- Conexão -->
          <h5>Conexão</h5>
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="previsaoconexao">Previsão de Conexão <span class="required-asterisk">*</span></label>
              <input
                id="previsaoconexao"
                type="date"
                class="form-control"
                :class="{ 'is-invalid': errors.previsao_conexao }"
                v-model="form.previsao_conexao"
                @input="errors.previsao_conexao = ''"
              />
              <div v-if="errors.previsao_conexao" class="invalid-feedback">{{ errors.previsao_conexao }}</div>
            </div>
            <div class="col-md-4">
              <label for="conexaofinal">Conexão Final</label>
              <input id="conexaofinal" type="date" class="form-control" v-model="form.data_conexao" />
            </div>
          </div>
          <!-- Ações -->
          <div class="mt-4 d-flex align-items-center">
            <button type="button" class="btn btn-submit" @click="submitForm">Atualizar</button>
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
        bairro: '',
        numero: 0,
        cidade: '',
        estado: '',
        bairro: '',
        complemento: '',
        cep: '',
        telefone: '',
        email: '',
        cia_energia: '',
        uc: '',
        rede: '',
        vendedor: '',
        valor_kwh: 0,
        valor_fixo: 0,
        valor_final_medio: 0,
        fio_b: 0,
        percentual_lei: 0,
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
      opcoesRede: ['Trifásico', 'Bifásico', 'Monofásico'],
      statusUsina: ["Aguardando troca de titularidade", "Troca solicitada", "Concluído"],
      meses: {
        janeiro: 'Jan', fevereiro: 'Fev', marco: 'Mar', abril: 'Abr',
        maio: 'Mai', junho: 'Jun', julho: 'Jul', agosto: 'Ago',
        setembro: 'Set', outubro: 'Out', novembro: 'Nov', dezembro: 'Dez'
      },
      successMessage: '',
      errorMessage: '',
      errors: {},
      dataLimiteErro: '',
      valorFinalMedioInput: ''
    };
  },
  computed: {
    mediaGeracao() {
      const meses = Object.values(this.meses).map((_, i) => this.form[Object.keys(this.meses)[i]]);
      const soma = meses.reduce((acc, val) => acc + (parseFloat(val) || 0), 0);

      this.form.media = parseFloat((soma / 12).toFixed(0));

      return this.form.media;
    },
    menorGeracao() {
      const valores = Object.values(this.meses).map((_, i) => this.form[Object.keys(this.meses)[i]]);
    
      return Math.min(...valores);
    },
    valorFixoCalculado() {
      const valorKwh = parseFloat(this.form.valor_kwh) || 0;
      return parseFloat((this.menorGeracao * valorKwh).toFixed(0)) || 0;
    }
  },
  watch: {
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
      async carregarDados() {
        try {
          const baseURL = import.meta.env.VITE_API_URL;
          const token = localStorage.getItem('token');
          const { id } = this.$route.params;
          this.conId = id;

          const response = await axios.get(`${baseURL}/usina/${id}`, {
            headers: { Authorization: `Bearer ${token}` }
          });

          const data = response.data;
          this.form = {
            nome: data.cliente.nome,
            cpf_cnpj: data.cliente.cpf_cnpj,
            rua: data.cliente.endereco.rua,
            numero: data.cliente.endereco.numero,
            cidade: data.cliente.endereco.cidade,
            estado: data.cliente.endereco.estado,
            bairro: data.cliente.endereco.bairro,
            complemento: data.cliente.endereco.complemento,
            cep: data.cliente.endereco.cep,
            telefone: data.cliente.telefone,
            email: data.cliente.email,
            cia_energia: data.cia_energia,
            uc: data.uc,
            rede: data.rede,
            vendedor: data.vendedor.ven_id,
            andamento_processo: data.andamento_processo,
            data_ass_contrato: this.formatarDataISOParaDate(data.data_ass_contrato),
            data_limite_troca_titularidade: this.formatarDataISOParaDate(data.data_limite_troca_titularidade),
            status: data.status,
            janeiro: data.dado_geracao.janeiro,
            fevereiro: data.dado_geracao.fevereiro,
            marco: data.dado_geracao.marco,
            abril: data.dado_geracao.abril,
            maio: data.dado_geracao.maio,
            junho: data.dado_geracao.junho,
            julho: data.dado_geracao.julho,
            agosto: data.dado_geracao.agosto,
            setembro: data.dado_geracao.setembro,
            outubro: data.dado_geracao.outubro,
            novembro: data.dado_geracao.novembro,
            dezembro: data.dado_geracao.dezembro,
            media: data.dado_geracao.media,
            valor_kwh: data.comercializacao.valor_kwh,
            valor_fixo: data.comercializacao.valor_fixo,
            valor_final_medio: this.arredondarDuasCasas(data.comercializacao.valor_final_media),
            previsao_conexao: this.formatarDataISOParaDate(data.comercializacao.previsao_conexao),
            fio_b: data.comercializacao.fio_b,
            percentual_lei: data.comercializacao.percentual_lei,
            data_conexao: this.formatarDataISOParaDate(data.comercializacao.data_conexao),
            cia_energia: data.comercializacao.cia_energia,
            usi_id: data.usi_id,
            cli_id: data.cli_id,
            end_id: data.cliente.end_id,
            dger_id: data.dado_geracao.dger_id,
            com_id: data.comercializacao.com_id
          };
          this.valorFinalMedioInput = this.formatCurrency(this.form.valor_final_medio);
        } catch (error) {
          this.errorMessage = "Erro ao carregar dados do consumidor.";
          console.error(error);
        }
      },
      formatarDataISOParaDate(dataISO) {
        return dataISO ? dataISO.substring(0, 10) : '';
      },
      arredondarDuasCasas(valor) {
        const numero = parseFloat(valor);
        if (!Number.isFinite(numero)) {
          return 0;
        }
        return Number(numero.toFixed(2));
      },
      formatCurrency(valor) {
        const numero = this.arredondarDuasCasas(valor);
        return new Intl.NumberFormat('pt-BR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).format(numero);
      },
      parseCurrency(valor) {
        if (valor === null || valor === undefined) {
          return 0;
        }
        const texto = String(valor).trim();
        if (!texto) {
          return 0;
        }
        const limpo = texto.replace(/[^\d,.-]/g, '');
        const temVirgula = limpo.includes(',');
        const temPonto = limpo.includes('.');
        let normalizado = limpo;
        if (temVirgula && temPonto) {
          normalizado = limpo.replace(/\./g, '').replace(',', '.');
        } else if (temVirgula && !temPonto) {
          normalizado = limpo.replace(',', '.');
        }
        const numero = parseFloat(normalizado);
        return Number.isFinite(numero) ? numero : 0;
      },
      ajustarValorFinalMedio() {
        const numero = this.parseCurrency(this.valorFinalMedioInput);
        this.form.valor_final_medio = this.arredondarDuasCasas(numero);
        this.valorFinalMedioInput = this.formatCurrency(this.form.valor_final_medio);
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
          this.dataLimiteErro = '';
          return true;
        }
        const dataCalculada = this.calcularDataLimiteTroca(this.form.data_ass_contrato);
        if (!dataCalculada) {
          this.dataLimiteErro = 'Data de assinatura inválida.';
          return false;
        }
        if (this.form.data_limite_troca_titularidade !== dataCalculada) {
          this.dataLimiteErro = 'A data limite deve ser 30 dias após a assinatura.';
          return false;
        }
        this.dataLimiteErro = '';
        return true;
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
          'fio_b',
          'percentual_lei',
          'cia_energia',
          'previsao_conexao',
          'valor_final_medio',
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
          if (field === 'valor_final_medio') {
            if (!this.valorFinalMedioInput) {
              this.errors.valor_final_medio = 'Campo obrigatório';
              return;
            }
            const valor = this.parseCurrency(this.valorFinalMedioInput);
            if (!valor) {
              this.errors.valor_final_medio = 'Campo obrigatório';
              return;
            }
            this.form.valor_final_medio = this.arredondarDuasCasas(valor);
            this.valorFinalMedioInput = this.formatCurrency(this.form.valor_final_medio);
            return;
          }
          if (!this.form[field]) {
            this.errors[field] = 'Campo obrigatório';
          }
        });

        const datasValidas = this.validarDataLimiteTroca();
        return Object.keys(this.errors).length === 0 && datasValidas;
      },
      async submitForm() {
        if (!this.validateForm()) {
          this.errorMessage = 'Corrija os campos obrigatórios antes de salvar.';
          return;
        }
        try {
          const baseURL = import.meta.env.VITE_API_URL;
          const token = localStorage.getItem('token');

          // Atualizar Endereço
          await axios.put(`${baseURL}/endereco/${this.form.end_id}`, {
            rua: this.form.rua,
            cidade: this.form.cidade,
            estado: this.form.estado,
            bairro: this.form.bairro,
            complemento: this.form.complemento,
            cep: this.form.cep,
            numero: this.form.numero ?? 0
          }, {
            headers: { Authorization: `Bearer ${token}` },
          });

          // Atualizar Cliente
          await axios.put(`${baseURL}/cliente/${this.form.cli_id}`, {
            nome: this.form.nome,
            cpf_cnpj: this.form.cpf_cnpj,
            telefone: this.form.telefone,
            email: this.form.email,
            end_id: this.form.end_id
          }, {
            headers: { Authorization: `Bearer ${token}` },
          });

          // Atualizar Dados de Geração
          await axios.patch(`${baseURL}/geracao/${this.form.dger_id}`, {
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
          }, {
            headers: { Authorization: `Bearer ${token}` },
          });

          // Atualizar Comercialização
          this.ajustarValorFinalMedio();
          const valorFinalMedio = this.form.valor_final_medio;

          await axios.put(`${baseURL}/comercializacao/${this.form.com_id}`, {
            valor_kwh: this.form.valor_kwh,
            valor_fixo: this.valorFixoCalculado,
            cia_energia: this.form.cia_energia,
            valor_final_media: valorFinalMedio,
            previsao_conexao: this.form.previsao_conexao,
            data_conexao: this.form.conexao_final,
            fio_b: this.form.fio_b,
            percentual_lei: this.form.percentual_lei
          }, {
            headers: { Authorization: `Bearer ${token}` },
          });

          // Atualizar Usina
          await axios.put(`${baseURL}/usina/${this.form.usi_id}`, {
            cli_id: this.form.cli_id,
            dger_id: this.form.dger_id,
            com_id: this.form.com_id,
            ven_id: this.form.vendedor,
            uc: this.form.uc,
            rede: this.form.rede,
            andamento_processo: this.form.andamento_processo,
            data_ass_contrato: this.form.data_ass_contrato,
            data_limite_troca_titularidade: this.form.data_limite_troca_titularidade,
            status: this.form.status,
          }, {
            headers: { Authorization: `Bearer ${token}` },
          });

          this.successMessage = "Usina atualizada com sucesso!";
          this.errorMessage = "";
          setTimeout(() => {
            this.$router.push('/usinas');
          }, 1500); // espera 1.5s para mostrar o alerta
        } catch (error) {
          this.successMessage = "";
          this.errorMessage = error.response?.data?.message || "Erro ao atualizar a usina.";
          console.error("Erro:", error);
          setTimeout(() => {
            this.errorMessage = '';
          }, 3000);
        }
      },
      goBack() {
        this.$router.push('/usinas');
      },
    },
    mounted() {
      this.fetchVendedores();
      this.carregarDados();
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

.required-asterisk {
  color: #dc3545;
  margin-left: 4px;
}

.btn-submit{
  color: white;
  background-color: #f28c1f;
}

.btn-submit:hover{
  color: white;
  background-color: #d97706;
}
</style>
