<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro de Unidades Consumidoras</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h5 class="text-center fw-bold">
    Formulário para alteração do cadastro de Unidades Consumidoras participantes do Sistema de Compensação <br>
    <span class="text-danger">Geração Compartilhada - CONSÓRCIOS</span>
  </h5>
  <p class="mt-3">
    Solicito que o excedente de energia injetada na rede pela unidade consumidora nº. <u>_______________</u>,
    que esteja disponível para alocação nos termos da ReN Aneel 1.059/2023, seja rateada entre as unidades consumidoras abaixo relacionadas, conforme percentuais discriminados.
  </p>

  <form method="POST" action="{{ route('form.store') }}">
    @csrf
    <div class="table-responsive">
      <table class="table table-bordered align-middle text-center">
        <thead class="table-secondary">
          <tr>
            <th colspan="5">Dados da(s) Unidade(s) Consumidora(s) Beneficiária(s)</th>
            <th>(%) do Excedente de Geração Destinado à UC</th>
          </tr>
          <tr>
            <th>Nome do Titular da UC<br>(Consórcio/Consorciado)</th>
            <th>CPF/CNPJ do Titular da UC<br>(Consórcio/Consorciado)</th>
            <th>Nº de Identificação da UC Beneficiária</th>
            <th>Endereço da UC Beneficiária</th>
            <th></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @for($i = 1; $i <= 25; $i++)
            <tr>
              <td><input type="text" name="dados[{{ $i }}][nome]" class="form-control"></td>
              <td><input type="text" name="dados[{{ $i }}][cpf_cnpj]" class="form-control"></td>
              <td><input type="text" name="dados[{{ $i }}][numero_uc]" class="form-control"></td>
              <td><input type="text" name="dados[{{ $i }}][endereco_uc]" class="form-control"></td>
              <td></td>
              <td><input type="number" step="0.01" name="dados[{{ $i }}][percentual]" class="form-control"></td>
            </tr>
          @endfor
        </tbody>
        <tfoot>
          <tr>
            <td colspan="5" class="text-end fw-bold">Soma dos percentuais do excedente de geração</td>
            <td class="fw-bold">100,00%</td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="mt-3">
      <p>
        Declaro ainda estar ciente e concordar que:
        <br>a) A soma dos percentuais informados limita-se e não excede à 100%, sendo que, caso resulte em valor inferior, a diferença será alocada na unidade consumidora geradora. O número de casas decimais de alocação do excedente de geração para cada UC deve ser de no máximo duas (ex.: 9.34 %).
        <br><strong>Importante:</strong> Cálculos automáticos de soma , em função de arredondamentos, podem levar a erros levando a totalização incorreta.
      </p>
    </div>
    <button type="submit" class="btn btn-primary">Enviar</button>
  </form>
</div>
</body>
</html>
