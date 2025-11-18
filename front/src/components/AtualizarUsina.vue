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
              <label for="name">Nome</label>
              <input id="name" type="text" class="form-control" v-model="form.nome" />
            </div>
            <div class="col-md-6">
              <label for="cpf/cnpj">CPF/CNPJ</label>
              <input id="cpf/cnpj" type="text" class="form-control" v-model="form.cpf_cnpj" />
            </div>
          </div>
          <!-- Endereço -->
          <div class="row mb-2">
            <div class="col-md-5">
              <label for="endereco">Endereço</label>
              <input id="endereco" type="text" class="form-control" v-model="form.rua" />
            </div>
            <div class="col-md-1">
              <label for="numero">Número</label>
              <input id="numero" type="number" class="form-control" v-model="form.numero" />
            </div>
            <div class="col-md-3">
              <label for="bairro">Bairro</label>
              <input id="bairro" type="text" class="form-control" v-model="form.bairro" />
            </div>
            <div class="col-md-3">
              <label for="complemento">Complemento</label>
              <input id="complemento" type="text" class="form-control" v-model="form.complemento"/>
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
          <!-- Informações de Processo -->
          <h5 class="mt-4">Informações do Processo</h5>
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="vendedor">Vendedor</label>
              <select id="vendedor" class="form-control" v-model="form.vendedor">
                <option disabled value="">Selecione o Vendedor</option>
                <option v-for="v in vendedor" :key="v.ven_id" :value="v.ven_id">
                  {{ v.nome }}
                </option>
              </select>
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
            <div class="col-md-4">
              <label for="andamento_processo">Status de Consumo</label>
              <input id="andamento_processo" type="text" class="form-control" v-model="form.andamento_processo" />
            </div>
            <div class="col-md-4">
              <label for="status_usina">Status da Usina</label>
              <select id="status_usina" class="form-control" v-model="form.status">
                <option disabled value="">Selecione o status</option>
                <option v-for="valorStatus in statusUsina" :key="valorStatus" :value="valorStatus">
                    {{ valorStatus }}
                </option>
              </select>
            </div>
            <div class="col-md-4">
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
              <input for="valorkwh" type="number" class="form-control" v-model="form.valor_kwh" />
            </div>
            <div class="col-md-4">
              <label for="valorfixo">Valor Fixo</label>
              <input id="valorfixo" type="number" class="form-control" v-model="form.valor_fixo" />
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
          </div>
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="fioB">Fio B (R$)</label>
              <input id="fioB" type="number" step="0.0001" class="form-control" v-model.number="form.fio_b" />
            </div>
            <div class="col-md-4">
              <label for="percentualLei">Percentual Lei 14300/23 (%)</label>
              <input id="percentualLei" type="number" step="0.01" class="form-control" v-model.number="form.percentual_lei" />
            </div>
          </div>
          <div class="mb-3 col-md-4">
            <label for="valorfinalmedio">Valor Final Médio Projetado</label>
            <input id="valorfinalmedio" type="number" class="form-control" v-model="form.valor_final_medio" />
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
      statusUsina: ["Aguardando troca de titularidade", "Troca solicitada", "Concluído"],
      meses: {
        janeiro: 'Jan', fevereiro: 'Fev', marco: 'Mar', abril: 'Abr',
        maio: 'Mai', junho: 'Jun', julho: 'Jul', agosto: 'Ago',
        setembro: 'Set', outubro: 'Out', novembro: 'Nov', dezembro: 'Dez'
      },
      successMessage: '',
      errorMessage: '',
      dataLimiteErro: ''
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
            valor_final_medio: data.comercializacao.valor_final_media,
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
        } catch (error) {
          this.errorMessage = "Erro ao carregar dados do consumidor.";
          console.error(error);
        }
      },
      formatarDataISOParaDate(dataISO) {
        return dataISO ? dataISO.substring(0, 10) : '';
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
      async submitForm() {
        if (!this.validarDataLimiteTroca()) {
          this.errorMessage = 'Corrija os campos destacados antes de salvar.';
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
          await axios.put(`${baseURL}/comercializacao/${this.form.com_id}`, {
            valor_kwh: this.form.valor_kwh,
            valor_fixo: this.form.valor_fixo,
            cia_energia: this.form.cia_energia,
            valor_final_media: this.form.valor_final_medio,
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

.btn-submit{
  color: white;
  background-color: #f28c1f;
}

.btn-submit:hover{
  color: white;
  background-color: #d97706;
}
</style>