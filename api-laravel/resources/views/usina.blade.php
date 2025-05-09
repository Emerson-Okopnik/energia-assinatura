<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Usina - {{ $usina->cliente->nome }}</title>
  <style>
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 12px;
      color: #000;
    }
    .container {
      width: 100%;
      padding: 10px;
    }
    .row {
      display: flex;
      flex-wrap: wrap;
      width: 100%;
      margin-bottom: 10px;
    }
    .col-33 {
      width: 33%;
      padding-right: 5px;
    }
    .col-67 {
      width: 67%;
      padding-left: 5px;
    }
    .col-50 {
      width: 50%;
      padding-right: 5px;
    }
    .col-50:last-child {
      padding-right: 0;
      padding-left: 5px;
    }
    .block {
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 4px;
    }
    .yellow-block    { background-color: #ffeb3b; }
    .turquoise-block { background-color: #00bcd4; }
    .green-block     { background-color: #4caf50; color: #fff; }
    .pink-block      { background-color: #e91e63; color: #fff; }
    .brown-block     { background-color: #795548; color: #fff; }
    .indigo-block    { background-color: #3f51b5; color: #fff; }
    .block-title {
      font-weight: bold;
      margin-bottom: 5px;
      font-size: 14px;
    }
    table.data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    .data-table th,
    .data-table td {
      border: 1px solid #000;
      padding: 5px;
      text-align: left;
    }
    .data-table th {
      background-color: #f0f0f0;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Dados da Usina - {{ $usina->cliente->nome }}</h1>

    <div class="row">
      <div class="col-33">
        <div class="block yellow-block">
          <div class="block-title">Dados da Usina</div>
          <p><strong>Nome:</strong> {{ $usina->nome }}</p>
          <p><strong>Cliente:</strong> {{ $usina->cliente->nome }}</p>
          <p><strong>Potência:</strong> {{ $usina->potencia }} kWp</p>
          <p><strong>Valor kWh:</strong> R$ {{ number_format($usina->comercializacao->valor_kwh, 2, ',', '.') }}</p>
        </div>
      </div>

      <div class="block" style="background-color:#f5f5f5;">
        <div class="block-title">Gráfico de Geração da Usina</div>
        <canvas id="graficoGeracao" width="800" height="400"></canvas>
      </div>

      <div class="col-67">
        <div class="block turquoise-block">
          <div class="block-title">Geração Média</div>
          <p><strong>Média:</strong> {{ number_format($usina->dadoGeracao->media, 2, ',', '.') }} kWh</p>
          <p><strong>Menor Geração:</strong> {{ number_format($usina->dadoGeracao->menor_geracao, 2, ',', '.') }} kWh</p>
        </div>

        <div class="block green-block">
          <div class="block-title">Dados Comerciais</div>
          <p><strong>Valor Fixo:</strong> R$ {{ number_format($usina->dadoGeracao->menor_geracao * $usina->comercializacao->valor_kwh, 2, ',', '.') }}</p>
          <p><strong>Modalidade:</strong> {{ $usina->comercializacao->modalidade ?? 'N/A' }}</p>
          <p><strong>Início Contrato:</strong> {{ $usina->comercializacao->inicio_contrato ?? 'N/A' }}</p>
        </div>
      </div>
    </div> 

    <div class="block">
      <div class="block-title">Geração Mensal</div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Mês</th>
            <th>Geração (kWh)</th>
            <th>Valor Fixo (R$)</th>
            <th>Injetado (R$)</th>
            <th>Creditado (R$)</th>
            <th>CUO (R$)</th>
            <th>Valor Final (R$)</th>
          </tr>
        </thead>
        <tbody>
          @foreach($dadosMensais as $mes => $dados)
            <tr>
              <td>{{ $mes }}</td>
              <td>{{ number_format($usina->dadoGeracao->{strtolower($mes)}, 2, ',', '.') }}</td>
              <td>{{ number_format($dados['fixo'], 2, ',', '.') }}</td>
              <td>{{ number_format($dados['injetado'], 2, ',', '.') }}</td>
              <td>{{ number_format($dados['creditado'], 2, ',', '.') }}</td>
              <td>{{ number_format($dados['cuo'], 2, ',', '.') }}</td>
              <td>{{ number_format($dados['valor_final'], 2, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="row">
      <div class="col-50">
        <div class="block brown-block">
          <div class="block-title">Fatura</div>
          <p><strong>Valor Médio:</strong> R$ {{ number_format(array_sum(array_column($dadosMensais, 'valor_final')) / 12, 2, ',', '.') }}</p>
        </div>
      </div>

      <div class="col-50">
        <div class="block indigo-block">
          <div class="block-title">Contatos</div>
          <p><strong>Email:</strong> {{ $usina->cliente->email ?? 'N/A' }}</p>
          <p><strong>Telefone:</strong> {{ $usina->cliente->telefone ?? 'N/A' }}</p>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const valores = {!! json_encode($valoresGeracao) !!};
      const labels = {!! json_encode($nomesMeses) !!};

      const maxGeracao = Math.max(...valores);
      const margem = maxGeracao * 0.1;
      const maxY = maxGeracao + margem;

      const ctx = document.getElementById('graficoGeracao').getContext('2d');

      new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Geração Mensal (kWh)',
            data: valores,
            fill: true,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.3,
            pointBackgroundColor: 'rgb(75, 192, 192)',
          }]
        },
        options: {
          plugins: {
            legend: {
              display: false
            },
            datalabels: {
              color: '#4F4F4F',
              anchor: 'end',
              align: 'top',
              formatter: function(value) {
                return value.toFixed(2);
              },
              font: {
                weight: 'bold'
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              suggestedMax: maxY,
              ticks: {
                precision: 0
              }
            }
          }
        },
        plugins: [ChartDataLabels]
      });

      window.chartRendered = true;
    });
  </script>
</body>
</html>
