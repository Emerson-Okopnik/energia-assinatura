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
            <h4 class="mb-0">Editar Consumidor</h4>
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
            <div class="col-md-5">
              <label for="endereco">Endereço</label>
              <input id="endereco" type="text" class="form-control" v-model="form.rua" />
            </div>
            <div class="col-md-2">
              <label for="numero">Número</label>
              <input id="numero" type="number" class="form-control" v-model="form.numero" />
            </div>
            <div class="col-md-5">
              <label for="bairro">Bairro</label>
              <input id="bairro" type="text" class="form-control" v-model="form.bairro" />
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-md-5">
              <label for="cidade">Cidade</label>
              <input id="cidade" type="text" class="form-control" v-model="form.cidade" />
            </div>
            <div class="col-md-3">
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
              <select id="vendedor" class="form-control"v-model="form.vendedor">
                <option disabled value="">Selecione o Vendedor</option>
                <option v-for="valorVendedor in vendedor" :key="valorVendedor" :value="valorVendedor">{{ valorVendedor }}</option>
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
                <option v-for="valorStatus in status" :key="valorStatus" :value="valorStatus">{{ valorStatus }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="alocacao">Alocação</label>
              <select id="alocacao" class="form-control" v-model="form.alocacao">
                <option disabled value="">Selecione o status da Alocação</option>
                <option v-for="valorAlocacao in opcoesAlocacao" :key="valorAlocacao" :value="valorAlocacao">{{ valorAlocacao }}</option>
              </select>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-4">
              <label for="ciaenergia">CIA Energia</label>
              <select id="ciaenergia" class="form-control" v-model="form.cia_energia">
                <option disabled value="">Selecione a CIA de Energia</option>
                <option v-for="cia in ciasEnergia" :key="cia" :value="cia">{{ cia }}</option>
              </select>
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
            <button type="submit" class="btn btn-primary" @click="atualizarConsumidor">Atualizar</button>
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
      conId: null,
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
      status: ['Envio dos documentos para assinatura', 'Aderido'],
      opcoesAlocacao: ['Parado', 'Alocado'],
      ciasEnergia: ['CELESC','COPEL','RGE'],
      vendedor: [
        "Adilson Valdecir Ansini",
        "Adrina Aparecida Pereira Torres",
        "Agner Fernanda Costa Luz",
        "Ailton Passos",
        "AlaMir Francisco Bazzani",
        "Algacir Losi",
        "Aline Schardong",
        "Alexandre Felix",
        "Anderson Barbosa Da Silva",
        "Anderson Jorge Hoffmann",
        "Cleberson Caetano De Oliveira",
        "Cristiano Gonçalves Da Silva",
        "Cristy Da Costa Carvalho",
        "Dilcimara Westarb",
        "Eder Zanata",
        "Edenilson Stafin",
        "Edison Rettka Dias",
        "Edson Antunes Coelho",
        "Edson Jose Worm",
        "Edson Mateus Rech Giovanella",
        "Elizeu Bento",
        "Elton Fernandes",
        "Everton Pereira",
        "Fábio Capistrano",
        "Felix Pablo Morais Bleichwehl",
        "Fernando Vitorino",
        "Flavio Puntel",
        "Francis Alex Tavares Lima",
        "Gean Carlos Rodrigues",
        "Gilberto Gonçalves Da Rocha",
        "Gilmar Schwetler",
        "Gilson Rosinei Koderer",
        "Hugo Henrique Negrello",
        "Ilario Marcos",
        "Ivanildo Varela",
        "Jederson Felacio Fernandes",
        "João Victor Dos Santos",
        "Joniclei Rodrigues",
        "Jose Carlos Kutti",
        "Jucemar Schetz Junior",
        "Joziel Euclides Pinto Da Silva",
        "Laercio João Laurindo",
        "Luciano Borgonha",
        "Mateus Medeiros",
        "Moacir Bernardino Luiz",
        "Osmar Stopa",
        "Rafael De Souza",
        "Renan Ferro",
        "Renato Krueger",
        "Ricardo Da Silva",
        "Roberto Carlos Rodrigues De Franca",
        "Rodrigo Figura",
        "Roque Nogueira",
        "Sandro Valentin",
        "Sidinei Campestrini",
        "Silvia Aparecida Koman",
        "Thales Cadona",
        "Tiago Rafael Alves",
        "Tony Willian Caetano",
        "Vitoldo Paulhak",
        "Wagner Bernardino Cerqueira",
        "Wagner Felipe Dos Santos",
        "Wilson Wan Zuit"
      ],
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
    mediaConsumo() {
      const valores = Object.values(this.meses).map((_, i) => this.form[Object.keys(this.meses)[i]]);
      const soma = valores.reduce((acc, val) => acc + (parseFloat(val) || 0), 0);
      this.form.media = parseFloat((soma / 12).toFixed(2));
      return this.form.media;
    }
  },
  methods: {
    async carregarDados() {
      try {
        const token = localStorage.getItem('token');
        const { id } = this.$route.params;
        this.conId = id;

        const response = await axios.get(`http://localhost:8000/api/consumidor/${id}`, {
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
          bairro: data.cliente.endereco.complemento,
          cep: data.cliente.endereco.cep,
          telefone: data.cliente.telefone,
          email: data.cliente.email,
          cia_energia: data.cia_energia,
          vendedor: data.vendedor,
          data_entrega: this.formatarDataISOParaDate(data?.data_entrega),
          status: data.status,
          alocacao: data.alocacao,
          janeiro: data.dado_consumo.janeiro,
          fevereiro: data.dado_consumo.fevereiro,
          marco: data.dado_consumo.marco,
          abril: data.dado_consumo.abril,
          maio: data.dado_consumo.maio,
          junho: data.dado_consumo.junho,
          julho: data.dado_consumo.julho,
          agosto: data.dado_consumo.agosto,
          setembro: data.dado_consumo.setembro,
          outubro: data.dado_consumo.outubro,
          novembro: data.dado_consumo.novembro,
          dezembro: data.dado_consumo.dezembro,
          media: data.dado_consumo.media,
          cli_id: data.cli_id,
          end_id: data.cliente.end_id,
          dcon_id: data.dado_consumo.dcon_id
        };
      } catch (error) {
        this.errorMessage = "Erro ao carregar dados do consumidor.";
        console.error(error);
      }
    },
    formatarDataISOParaDate(dataISO) {
      return dataISO ? dataISO.substring(0, 10) : '';
    },
    async atualizarConsumidor() {
      try {
        const token = localStorage.getItem('token');

        // 1. Atualizar Endereço
        const enderecoPayload = {
          rua: this.form.rua,
          cidade: this.form.cidade,
          estado: this.form.estado,
          complemento: this.form.bairro,
          cep: this.form.cep,
          numero: this.form.numero ?? 0
        };

        await axios.put(`http://localhost:8000/api/endereco/${this.form.end_id}`, enderecoPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        // 2. Atualizar Cliente
        const clientePayload = {
          nome: this.form.nome,
          cpf_cnpj: this.form.cpf_cnpj,
          telefone: this.form.telefone,
          email: this.form.email,
          end_id: this.form.end_id
        };

        await axios.put(`http://localhost:8000/api/cliente/${this.form.cli_id}`, clientePayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        // 3. Atualizar Dados de Consumo
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

        await axios.put(`http://localhost:8000/api/consumo/${this.form.dcon_id}`, consumoPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        // 4. Atualizar Consumidor
        const consumidorPayload = {
          cli_id: this.form.cli_id,
          dcon_id: this.form.dcon_id,
          cia_energia: this.form.cia_energia,
          vendedor: this.form.vendedor,
          data_entrega: this.form.data_entrega,
          status: this.form.status,
          alocacao: this.form.alocacao
        };

        await axios.put(`http://localhost:8000/api/consumidor/${this.conId}`, consumidorPayload, {
          headers: { Authorization: `Bearer ${token}` },
        });

        this.successMessage = "Dados atualizados com sucesso!";
        this.errorMessage = "";
        setTimeout(() => (this.successMessage = ''), 3000);

      } catch (error) {
        this.successMessage = '';
        this.errorMessage = "Erro ao atualizar consumidor.";
        console.error(error);
      }
    },
    goBack() {
      this.$router.push('/consumidores');
    }
  },
  mounted() {
    this.carregarDados();
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
