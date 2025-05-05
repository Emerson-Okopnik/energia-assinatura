<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Relatório da Usina</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 12px;
      margin: 20px;
    }

    h1 {
      text-align: center;
      margin-bottom: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }

    th, td {
      border: 1px solid #333;
      padding: 6px;
      text-align: center;
    }

    th {
      background-color: #f2f2f2;
    }

    .total {
      font-weight: bold;
      background-color: #f9f9f9;
    }

    .right {
      text-align: right;
    }
  </style>
</head>
<body>
  <h1>Relatório de Faturamento da Usina - {{ $usina->cliente->nome }}</h1>

  <table>
    <thead>
      <tr>
        <th>Mês</th>
        <th>Fixo (R$)</th>
        <th>Injetado (R$)</th>
        <th>Creditado (R$)</th>
        <th>CUO (R$)</th>
        <th>Valor Final a Receber (R$)</th>
      </tr>
    </thead>
    <tbody>
      @php
        $total = 0;
      @endphp

      @foreach ($dadosMensais as $mes => $dados)
        <tr>
          <td>{{ $mes }}</td>
          <td>R$ {{ number_format($dados['fixo'], 2, ',', '.') }}</td>
          <td>R$ {{ number_format($dados['injetado'], 2, ',', '.') }}</td>
          <td>R$ {{ number_format($dados['creditado'], 2, ',', '.') }}</td>
          <td>R$ {{ number_format($dados['cuo'], 2, ',', '.') }}</td>
          <td>R$ {{ number_format($dados['valor_final'], 2, ',', '.') }}</td>
        </tr>
        @php
          $total += $dados['valor_final'];
        @endphp
      @endforeach

      <tr class="total">
        <td colspan="5" class="right">Total:</td>
        <td>R$ {{ number_format($total, 2, ',', '.') }}</td>
      </tr>
    </tbody>
  </table>

  <p><strong>Data de Emissão:</strong> {{ \Carbon\Carbon::now()->format('d/m/Y') }}</p>
</body>
</html>
