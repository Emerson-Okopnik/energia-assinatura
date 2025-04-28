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
              <button class="btn btn-success btn-sm me-2">Conectado</button>
              <button class="btn btn-danger btn-sm me-2">Não Conectado</button>
              <button class="btn btn-warning btn-sm">Warning</button>
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

          <!-- Informações de Processo -->
          <h5 class="mt-4">Informações do Processo</h5>
          <div class="row mb-2">
            <div class="col-md-4">
              <label for="andamento_processo">Status de Consumo</label>
              <input id="andamento_processo" type="text" class="form-control" v-model="form.andamento_processo" />
            </div>
            <div class="col-md-4">
              <label for="data_ass_contrato">Data Assinatura Contrato</label>
              <input id="data_ass_contrato" type="date" class="form-control" v-model="form.data_ass_contrato" />
            </div>
            <div class="col-md-4">
              <label for="data_limite_troca">Data Limite Troca Titularidade</label>
              <input id="data_limite_troca" type="date" class="form-control"
                v-model="form.data_limite_troca_titularidade" />
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-4">
              <label for="status_usina">Status da Usina</label>
              <select id="status_usina" class="form-control" v-model="form.status">
                <option disabled value="">Selecione o status</option>
                <option v-for="valorStatus in statusUsina" :key="valorStatus" :value="valorStatus">{{ valorStatus }}
                </option>
              </select>
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
              <input id="valorfixo" type="number" class="form-control" :value="valorFixoCalculado" readonly />
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
              <input id="conexaofinal" type="date" class="form-control" v-model="form.conexao_final" />
            </div>
          </div>

          <!-- Ações -->
          <div class="mt-4 d-flex align-items-center">
            <button type="button" class="btn btn-primary" @click="submitForm">Salvar</button>
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
        cep: '',
        telefone: '',
        email: '',
        cia_energia: '',
        valor_kwh: 0,
        valor_fixo: 0,
        valor_final_medio: 0,
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
      ciasEnergia: ['CELESC', 'COPEL', 'RGE'],
      statusUsina: ["Aguardando troca de titularidade", "Troca solicitada", "Concluído"],
      meses: {
        janeiro: 'Jan', fevereiro: 'Fev', marco: 'Mar', abril: 'Abr',
        maio: 'Mai', junho: 'Jun', julho: 'Jul', agosto: 'Ago',
        setembro: 'Set', outubro: 'Out', novembro: 'Nov', dezembro: 'Dez'
      },
      successMessage: '',
      errorMessage: ''
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
    }
  },
  methods: {
    async submitForm() {
      try {
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

        const enderecoResponse = await axios.post("http://localhost:8000/api/endereco", enderecoPayload, {
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

        const clienteResponse = await axios.post("http://localhost:8000/api/cliente", clientePayload, {
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

        const geracaoResponse = await axios.post("http://localhost:8000/api/geracao", geracaoPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const dger_id = geracaoResponse.data.id;

        // 4. Cadastrar Comercializacao
        const comercializacaoPayload = {
          valor_kwh: this.form.valor_kwh,
          valor_fixo: this.valorFixoCalculado,
          cia_energia: this.form.cia_energia,
          valor_final_media: this.form.valor_final_medio,
          previsao_conexao: this.form.previsao_conexao,
          data_conexao: this.form.conexao_final
        };

        const comercializacaoResponse = await axios.post("http://localhost:8000/api/comercializacao", comercializacaoPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const com_id = comercializacaoResponse.data.id;

        // 5. Cadastrar Usina
        const usinaPayload = {
          cli_id: cli_id,
          dger_id: dger_id,
          com_id: com_id,
          andamento_processo: this.form.andamento_processo,
          data_ass_contrato: this.form.data_ass_contrato,
          data_limite_troca_titularidade: this.form.data_limite_troca_titularidade,
          status: this.form.status
        };

        await axios.post("http://localhost:8000/api/usina", usinaPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        this.successMessage = "Usina cadastrada com sucesso!";
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