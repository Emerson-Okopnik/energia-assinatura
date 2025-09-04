<template>
  <div class="container">
    <div class="d-flex flex-wrap gap-4 justify-content-center mb-4">  
      <!-- Card: Usinas Conectadas -->
      <div class="p-4 rounded shadow bg-white text-center my-4" style="width: 300px;">
        <h5 class="mb-2">Usinas Conectadas</h5>
        <h2 class="text-success">{{ usinasConectadas }}</h2>
      </div>

      <!-- Card: Consumidores Credenciados -->
      <div class="p-4 rounded shadow bg-white text-center my-4" style="width: 300px;">
        <h5 class="mb-2">Consumidores Credenciados</h5>
        <h2 class="text-primary">{{ consumidoresCredenciados }}</h2>
      </div>

      <!-- Card: Usinas Não Conectadas -->
      <div class="p-4 rounded shadow bg-white text-center my-4" style="width: 300px;">
        <h5 class="mb-2">Usinas Credenciadas</h5>
        <h2 class="text-danger">{{ usinasNaoConectadas }}</h2>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-4 justify-content-center mb-4">
      <!-- Card: Geração Média Total -->
      <div class="p-4 rounded shadow bg-white text-center mt-4" style="width: 300px;">
        <h5 class="mb-2">Geração Média Total</h5>
        <h2 class="text-success">{{ geracaoMediaTotal.toFixed(2) }} kWh</h2>
      </div>

      <!-- Card: Consumo Total -->
      <div class="p-4 rounded shadow bg-white text-center mt-4" style="width: 300px;">
        <h5 class="mb-2">Consumo Total</h5>
        <h2 class="text-primary">{{ consumoTotal.toFixed(2) }} kWh</h2>
      </div>

      <!-- Card: Saldo Disponível -->
      <div class="p-4 rounded shadow bg-white text-center mt-4" style="width: 300px;">
        <h5 class="mb-2">Saldo Disponível</h5>
        <h2 :class="saldoDisponivel >= 0 ? 'text-success' : 'text-danger'">
          {{ saldoDisponivel.toFixed(2) }} kWh
        </h2>
      </div>
    </div>
    
    <div class="row my-5">
      <!-- Gráfico de Consumidores Cadastrados -->
      <div class="col-md-6">
        <div class="p-4 rounded shadow bg-white" style="height: 400px;">
          <div style="width: 100%; height: 300px;">
            <h5 class="text-center mb-4">Consumidores Cadastrados por Ano</h5>
            <Line v-if="consumidoresChartData.labels.length" :data="consumidoresChartData" :options="chartOptions" />
          </div>
        </div>
      </div>

      <!-- Gráfico de Usinas Cadastradas -->
      <div class="col-md-6">
        <div class="p-4 rounded shadow bg-white" style="height: 400px;">
          <div style="width: 100%; height: 300px;">
            <h5 class="text-center mb-4">Usinas Cadastradas por Ano</h5>
            <Line v-if="usinasChartData.labels.length" :data="usinasChartData" :options="chartOptions" />
          </div>
        </div>
      </div>
    </div>

    <!-- Select das usinas -->
    <div class="row mb-5">
      <div class="col-md-10">
        <label for="usinaSelect">Selecione a Usina:</label>
        <select id="usinaSelect" v-model="selectedUsinaId" @change="onUsinaSelecionada" class="form-select">
          <option disabled value="">Selecione uma usina</option>
          <option v-for="usina in usinas" :key="usina.usi_id" :value="usina.usi_id">
            {{ usina.cliente.nome }} - {{ usina.dado_geracao?.media ?? 0 }} kWh
          </option>
        </select>
      </div>

      <div class="col-md-2">
        <label for="anosUsina">Ano:</label>
        <select id="anosUsina" v-model="anoSelecionado" @change="fetchGraficos" class="form-select">
          <option v-for="ano in anosDisponiveis" :key="ano" :value="ano">{{ ano }}</option>
        </select>
      </div>
    </div>

    <!-- Gráfico de linhas -->
    <div class="p-4 rounded shadow bg-white mb-5" style="width: 960px; height: 400px;">
      <Line :data="lineChartData" :options="chartOptions" />
    </div>

    <!-- Gráfico de barras -->
    <div class="p-4 rounded shadow bg-white" style="width: 960px; height: 400px;">
      <Bar :data="barChartData" :options="chartOptions" />
    </div>
  </div>
</template>

<script setup>
  import { ref, onMounted } from 'vue'
  import axios from 'axios'
  import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
    BarElement,
    CategoryScale,
    LinearScale,
    Filler
  } from 'chart.js'
  import { Line, Bar } from 'vue-chartjs'

  ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, BarElement, CategoryScale, LinearScale)

  const usinas = ref([])
  const selectedUsinaId = ref('')
  const lineChartData = ref({ labels: [], datasets: [] })
  const barChartData = ref({ labels: [], datasets: [] })
  const usinasConectadas = ref(0)
  const consumidoresCredenciados = ref(0)
  const usinasNaoConectadas = ref(0)
  const geracaoMediaTotal = ref(0)
  const consumoTotal = ref(0)
  const saldoDisponivel = ref(0)
  const consumidoresChartData = ref({ labels: [], datasets: [] })
  const usinasChartData = ref({ labels: [], datasets: [] })
  const anosDisponiveis = ref([])
  const anoSelecionado = ref('')

  const meses = [
    'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'
  ]

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: true }
    },
    scales: {
      y: { beginAtZero: true }
    },
    interaction: {
      intersect: false
    }
  }

  onMounted(async () => {
    await fetchUsinas()
    await fetchUsinasConectadas()
    await fetchConsumidoresCredenciados()
    await fetchUsinasNaoConectadas()
    await fetchTotaisGeracaoConsumo()
    await fetchConsumidoresPorAno()
    await fetchUsinasPorAno()
  })

  async function fetchUsinas() {
    const baseURL = import.meta.env.VITE_API_URL;
    const token = localStorage.getItem('token')
    try {
      const response = await axios.get(`${baseURL}/usina`, {
        headers: { Authorization: `Bearer ${token}` }
      })
        usinas.value = response.data
    } catch (err) {
      console.error('Erro ao buscar usinas:', err)
    }
  }

  async function fetchUsinasConectadas() {
    const baseURL = import.meta.env.VITE_API_URL;
    const token = localStorage.getItem('token')
    try {
      const response = await axios.get(`${baseURL}/usina-consumidor`, {
        headers: { Authorization: `Bearer ${token}` }
      })

      const usinasComConsumidores = new Set(response.data.map(item => item.usi_id))
      usinasConectadas.value = usinasComConsumidores.size
    } catch (error) {
      console.error('Erro ao buscar quantidade de usinas conectadas:', error)
    }
  }

  async function fetchConsumidoresCredenciados() {
    const baseURL = import.meta.env.VITE_API_URL;
    const token = localStorage.getItem('token')
    try {
      const response = await axios.get(`${baseURL}/consumidores/nao-vinculados`, {
        headers: { Authorization: `Bearer ${token}` }
      })
      consumidoresCredenciados.value = response.data.length
    } catch (error) {
      console.error('Erro ao buscar consumidores credenciados:', error)
    }
  }

  async function fetchUsinasNaoConectadas() {
    const baseURL = import.meta.env.VITE_API_URL;
    const token = localStorage.getItem('token')
    try {
      const response = await axios.get(`${baseURL}/usinas/nao-vinculadas`, {
        headers: { Authorization: `Bearer ${token}` }
      })
      usinasNaoConectadas.value = response.data.length
    } catch (error) {
      console.error('Erro ao buscar usinas não conectadas:', error)
    }
  }

  async function onUsinaSelecionada() {
    anoSelecionado.value = '' // resetar ano anterior
    anosDisponiveis.value = []

    if (!selectedUsinaId.value) return

    const baseURL = import.meta.env.VITE_API_URL
    const token = localStorage.getItem('token')

    try {
      const response = await axios.get(`${baseURL}/dados-geracao-real-usina/usina/${selectedUsinaId.value}`, {
        headers: { Authorization: `Bearer ${token}` }
      })

      const dados = response.data
      anosDisponiveis.value = [...new Set(dados.map(item => item.ano))].sort()
    } catch (err) {
      console.error('Erro ao buscar anos de geração real da usina:', err)
    }
  }

  async function fetchGraficos() {
    const baseURL = import.meta.env.VITE_API_URL
    const token = localStorage.getItem('token')
    const usinaId = selectedUsinaId.value
    const ano = anoSelecionado.value

    if (!usinaId || !ano) return

    try {
      const usinaId = selectedUsinaId.value

      // Busca dados da geração real
      const geracaoRealResponse = await axios.get(`${baseURL}/dados-geracao-real-usina/usina/${usinaId}`, {
        headers: { Authorization: `Bearer ${token}` }
      })

      // Extrai anos únicos
      const todosDados = geracaoRealResponse.data;
      anosDisponiveis.value = [...new Set(todosDados.map(item => item.ano))];

      // Filtra pelo ano selecionado
      const dadoAnoSelecionado = todosDados.find(item => item.ano == ano);
      const dadosGeracaoReal = dadoAnoSelecionado?.dados_geracao_real || {};
      const geracaoRealMensal = meses.map(m => dadosGeracaoReal[m] ?? null);

      // Busca dados da usina + consumidores
      let response = await axios.get(`${baseURL}/usina-consumidor/${usinaId}`, {
        headers: { Authorization: `Bearer ${token}` }
      })

      let data = response.data
      let dadosGeracao = null
      let geracaoMensal = []
      let consumoTotal = []
      let mediaArray = []

      if (!data.length) {
        const fallbackResponse = await axios.get(`${baseURL}/usina/${usinaId}`, {
          headers: { Authorization: `Bearer ${token}` }
        })
        const usina = fallbackResponse.data
        dadosGeracao = usina?.dado_geracao
        geracaoMensal = meses.map(m => dadosGeracao?.[m] || 0)
        consumoTotal = null
        mediaArray = new Array(12).fill(dadosGeracao?.media || 0)

        lineChartData.value = {
          labels: meses.map(m => m[0].toUpperCase() + m.slice(1)),
          datasets: [
            {
              label: 'Geração da Usina (kWh)',
              borderColor: '#4ade80',
              backgroundColor: '#bbf7d0',
              data: geracaoMensal,
              tension: 0.3,
              fill: false,
              order: 2
            },
            {
              label: 'Geração Real (kWh)',
              borderColor: '#facc15',
              backgroundColor: '#fde68a',
              data: geracaoRealMensal,
              tension: 0.3,
              fill: false,
              borderDash: [5, 5],
              order: 1
            }
          ]
        }
      } else {
          dadosGeracao = data[0].usina?.dado_geracao
          geracaoMensal = meses.map(m => dadosGeracao?.[m] || 0)
          mediaArray = new Array(12).fill(dadosGeracao?.media || 0)

          consumoTotal = Array(12).fill(0)
          data.forEach(item => {
            meses.forEach((mes, i) => {
              const consumo = item?.consumidor?.dado_consumo?.[mes]
              if (consumo) consumoTotal[i] += consumo
            })
          })

          lineChartData.value = {
            labels: meses.map(m => m[0].toUpperCase() + m.slice(1)),
            datasets: [
              {
                label: 'Consumo Total dos Consumidores (kWh)',
                borderColor: '#60a5fa',
                backgroundColor: '#dbeafe',
                data: consumoTotal,
                tension: 0.3,
                fill: false,
                order: 3
              },
              {
                label: 'Geração da Usina (kWh)',
                borderColor: '#4ade80',
                backgroundColor: '#bbf7d0',
                data: geracaoMensal,
                tension: 0.3,
                fill: false,
                order: 2
              },
              {
                label: 'Geração Real (kWh)',
                borderColor: '#facc15',
                backgroundColor: '#fde68a',
                data: geracaoRealMensal,
                tension: 0.3,
                fill: false,
                borderDash: [5, 5],
                order: 1
              },
            ]
          }
        }

        barChartData.value = {
          labels: meses.map(m => m[0].toUpperCase() + m.slice(1)),
          datasets: [
            {
              type: 'line',
              label: 'Geração Real (kWh)',
              data: geracaoRealMensal,
              borderColor: '#facc15',
              borderWidth: 2,
              fill: false,
              pointRadius: 4,
              borderDash: [5, 5],
              tension: 0.1,
              order: 0
            },
            {
              type: 'bar',
              label: 'Geração mensal (kWh)',
              data: geracaoMensal,
              backgroundColor: '#60a5fa',
              order: 2
            },
            {
              type: 'line',
              label: 'Média de geração (kWh)',
              data: mediaArray,
              borderColor: '#f87171',
              borderWidth: 2,
              fill: false,
              pointRadius: 0,
              tension: 0.1,
              order: 1
            },
          ]
        }
    } catch (error) {
      console.error('Erro ao buscar dados dos gráficos:', error)
    }
  }

  async function fetchTotaisGeracaoConsumo() {
    const baseURL = import.meta.env.VITE_API_URL;
    const token = localStorage.getItem('token')
    try {
      const response = await axios.get(`${baseURL}/usina-consumidor`, {
        headers: { Authorization: `Bearer ${token}` }
      })

      const dados = response.data

      const usinasMap = new Map()
      let consumoSoma = 0

      dados.forEach(item => {
        const usina = item.usina
        const consumidor = item.consumidor

        // Considerar a usina apenas uma vez
        if (usina && !usinasMap.has(usina.usi_id)) {
          geracaoMediaTotal.value += usina.dado_geracao?.media || 0
          usinasMap.set(usina.usi_id, true)
        }

        // Somar o consumo de todos consumidores
        if (consumidor) {
          consumoSoma += consumidor.dado_consumo?.media || 0
        }
      })

      consumoTotal.value = consumoSoma
      saldoDisponivel.value = geracaoMediaTotal.value - consumoTotal.value
    } catch (error) {
      console.error('Erro ao buscar totais:', error)
    }
  }

  async function fetchConsumidoresPorAno() {
    const baseURL = import.meta.env.VITE_API_URL;
    const token = localStorage.getItem('token')
    try {
      const response = await axios.get(`${baseURL}/consumidor`, {
        headers: { Authorization: `Bearer ${token}` }
      })

      const consumidores = response.data

      const contagemPorAno = {}

      consumidores.forEach(consumidor => {
        const ano = new Date(consumidor.created_at).getFullYear()
        contagemPorAno[ano] = (contagemPorAno[ano] || 0) + 1
      })

      const anos = Object.keys(contagemPorAno).sort()
      const contagens = anos.map(ano => contagemPorAno[ano])

      consumidoresChartData.value = {
        labels: anos,
        datasets: [
          {
            label: 'Consumidores por Ano',
            data: contagens,
            borderColor: '#60a5fa',
            backgroundColor: '#dbeafe',
            tension: 0.3,
            fill: true
          }
        ]
      }
    } catch (error) {
      console.error('Erro ao buscar consumidores:', error)
    }
  }

  async function fetchUsinasPorAno() {
    const baseURL = import.meta.env.VITE_API_URL;
    const token = localStorage.getItem('token')
    try {
      const response = await axios.get(`${baseURL}/usina`, {
        headers: { Authorization: `Bearer ${token}` }
      })

      const usinas = response.data
      const contagemPorAno = {}

      usinas.forEach(usina => {
        const ano = new Date(usina.created_at).getFullYear()
        contagemPorAno[ano] = (contagemPorAno[ano] || 0) + 1
      })

      const anos = Object.keys(contagemPorAno).sort()
      const contagens = anos.map(ano => contagemPorAno[ano])

      usinasChartData.value = {
        labels: anos,
        datasets: [
          {
            label: 'Usinas por Ano',
            data: contagens,
            borderColor: '#4ade80',
            backgroundColor: '#bbf7d0',
            tension: 0.3,
            fill: true
          }
        ]
      }
    } catch (error) {
      console.error('Erro ao buscar usinas:', error)
    }
  }
</script>

<style scoped>
  select.form-select {
    padding: 8px;
    font-size: 16px;
    margin-top: 8px;
  }

  .container {
    padding-top: 80px;
  }

  .text-success {
    color: #22c55e !important;
  }
</style>
