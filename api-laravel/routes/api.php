<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EnderecoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConsumidorController;
use App\Http\Controllers\DadoConsumoController;
use App\Http\Controllers\DadoGeracaoController;
use App\Http\Controllers\ComercializacaoController;
use App\Http\Controllers\UsinaController;
use App\Http\Controllers\UsinaConsumidorController;
use App\Http\Controllers\CreditosDistribuidosController;
use App\Http\Controllers\CreditosDistribuidosUsinaController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\ValorAcumuladoReservaController;
use App\Http\Controllers\FaturamentoUsinaController;
use App\Http\Controllers\VendedorController;
use App\Http\Controllers\DadosGeracaoRealController;
use App\Http\Controllers\DadosGeracaoRealUsinaController;
use App\Http\Controllers\CalculoGeracaoController;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/hello', function () {
    return response('Hello World', 200);
});
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:api')->group(function () {
  Route::get('/home', [AuthController::class, 'user']);
  Route::get('/user', [AuthController::class, 'user']);
  Route::get('/users',[AuthController::class,'index']);
  Route::get('/users/{id}',[AuthController::class,'show']);

  Route::post('/endereco', [EnderecoController::class, 'store']);
  Route::get('/endereco', [EnderecoController::class, 'index']);
  Route::get('/endereco/{id}', [EnderecoController::class, 'show']);
  Route::put('/endereco/{id}', [EnderecoController::class, 'update']);
  Route::delete('/endereco/{id}', [EnderecoController::class, 'destroy']);

  Route::post('/cliente', [ClienteController::class, 'store']);
  Route::get('/cliente', [ClienteController::class, 'index']);
  Route::get('/cliente/{id}', [ClienteController::class, 'show']);
  Route::put('/cliente/{id}', [ClienteController::class, 'update']);
  Route::delete('/cliente/{id}', [ClienteController::class, 'destroy']);

  Route::post('/consumo', [DadoConsumoController::class, 'store']);
  Route::get('/consumo', [DadoConsumoController::class, 'index']);
  Route::get('/consumo/{id}', [DadoConsumoController::class, 'show']);
  Route::put('/consumo/{id}', [DadoConsumoController::class, 'update']);
  Route::delete('/consumo/{id}', [DadoConsumoController::class, 'destroy']);

  Route::post('/consumidor', [ConsumidorController::class, 'store']);
  Route::get('/consumidor', [ConsumidorController::class, 'index']);
  Route::get('/consumidor/{id}', [ConsumidorController::class, 'show']);
  Route::put('/consumidor/{id}', [ConsumidorController::class, 'update']);
  Route::delete('/consumidor/{id}', [ConsumidorController::class, 'destroy']);
  Route::get('/consumidores/nao-vinculados', [ConsumidorController::class, 'consumidoresNaoVinculados']);

  Route::post('/geracao', [DadoGeracaoController::class, 'store']);
  Route::get('/geracao', [DadoGeracaoController::class, 'index']);
  Route::get('/geracao/{id}', [DadoGeracaoController::class, 'show']);
  Route::patch('/geracao/{id}', [DadoGeracaoController::class, 'update']);
  Route::delete('/geracao/{id}', [DadoGeracaoController::class, 'destroy']);

  Route::post('/comercializacao', [ComercializacaoController::class, 'store']);
  Route::get('/comercializacao', [ComercializacaoController::class, 'index']);
  Route::get('/comercializacao/{id}', [ComercializacaoController::class, 'show']);
  Route::put('/comercializacao/{id}', [ComercializacaoController::class, 'update']);
  Route::delete('/comercializacao/{id}', [ComercializacaoController::class, 'destroy']);

  Route::post('/usina', [UsinaController::class, 'store']);
  Route::get('/usina', [UsinaController::class, 'index']);
  Route::get('/usina/{id}', [UsinaController::class, 'show']);
  Route::put('/usina/{id}', [UsinaController::class, 'update']);
  Route::delete('/usina/{id}', [UsinaController::class, 'destroy']);
  Route::get('/usinas/nao-vinculadas', [UsinaController::class, 'usinasNaoVinculadas']);
  Route::get('/usina/{id}/anos', [UsinaController::class, 'listarAnos']);

  Route::post('/usina-consumidor', [UsinaConsumidorController::class, 'store']);
  Route::get('/usina-consumidor', [UsinaConsumidorController::class, 'index']);
  Route::get('/usina-consumidor/{id}', [UsinaConsumidorController::class, 'show']);
  Route::put('/usina-consumidor/{id}', [UsinaConsumidorController::class, 'update']);
  Route::delete('/usina-consumidor/{id}', [UsinaConsumidorController::class, 'destroy']);
  Route::delete('/usina-consumidor/usina/{usi_id}/consumidor/{con_id}', [UsinaConsumidorController::class, 'destroyVinculo']);

  Route::post('/creditos-distribuidos', [CreditosDistribuidosController::class, 'store']);
  Route::get('/creditos-distribuidos', [CreditosDistribuidosController::class, 'index']);
  Route::get('/creditos-distribuidos/{id}', [CreditosDistribuidosController::class, 'show']);
  Route::patch('/creditos-distribuidos/{id}', [CreditosDistribuidosController::class, 'update']);
  Route::delete('/creditos-distribuidos/{id}', [CreditosDistribuidosController::class, 'destroy']);

  Route::post('/creditos-distribuidos-usina', [CreditosDistribuidosUsinaController::class, 'store']);
  Route::get('/creditos-distribuidos-usina', [CreditosDistribuidosUsinaController::class, 'index']);
  Route::get('/creditos-distribuidos-usina/{id}', [CreditosDistribuidosUsinaController::class, 'show']);
  Route::put('/creditos-distribuidos-usina/{id}', [CreditosDistribuidosUsinaController::class, 'update']);
  Route::delete('/creditos-distribuidos-usina/{id}', [CreditosDistribuidosUsinaController::class, 'destroy']);
  Route::get('/creditos-distribuidos-usina/usina/{usiId}/ano/{ano}', [CreditosDistribuidosUsinaController::class, 'porAnoEUsina']);

  Route::post('/valor-acumulado-reserva', [ValorAcumuladoReservaController::class, 'store']);
  Route::get('/valor-acumulado-reserva', [ValorAcumuladoReservaController::class, 'index']);
  Route::get('/valor-acumulado-reserva/{id}', [ValorAcumuladoReservaController::class, 'show']);
  Route::patch('/valor-acumulado-reserva/{id}', [ValorAcumuladoReservaController::class, 'update']);
  Route::delete('/valor-acumulado-reserva/{id}', [ValorAcumuladoReservaController::class, 'destroy']);

  Route::post('/faturamento-usina', [FaturamentoUsinaController::class, 'store']);
  Route::get('/faturamento-usina', [FaturamentoUsinaController::class, 'index']);
  Route::get('/faturamento-usina/{id}', [FaturamentoUsinaController::class, 'show']);
  Route::patch('/faturamento-usina/{id}', [FaturamentoUsinaController::class, 'update']);
  Route::delete('/faturamento-usina/{id}', [FaturamentoUsinaController::class, 'destroy']);

  Route::post('/vendedor', [VendedorController::class, 'store']);
  Route::get('/vendedor', [VendedorController::class, 'index']);
  Route::get('/vendedor/{id}', [VendedorController::class, 'show']);
  Route::put('/vendedor/{id}', [VendedorController::class, 'update']);
  Route::delete('/vendedor/{id}', [VendedorController::class, 'destroy']);

  Route::post('/dados-geracao-real', [DadosGeracaoRealController::class, 'store']);
  Route::get('/dados-geracao-real', [DadosGeracaoRealController::class, 'index']);
  Route::get('/dados-geracao-real/{id}', [DadosGeracaoRealController::class, 'show']);
  Route::patch('/dados-geracao-real/{id}', [DadosGeracaoRealController::class, 'update']);
  Route::delete('/dados-geracao-real/{id}', [DadosGeracaoRealController::class, 'destroy']);

  Route::post('/usinas/{usi_id}/faturamento/{ano}/mes/{mes}/calculo', [\App\Http\Controllers\CalculoGeracaoController::class, 'calcular']);

  Route::post('/dados-geracao-real-usina', [DadosGeracaoRealUsinaController::class, 'store']);
  Route::get('/dados-geracao-real-usina', [DadosGeracaoRealUsinaController::class, 'index']);
  Route::get('/dados-geracao-real-usina/{id}', [DadosGeracaoRealUsinaController::class, 'show']);
  Route::put('/dados-geracao-real-usina/{id}', [DadosGeracaoRealUsinaController::class, 'update']);
  Route::delete('/dados-geracao-real-usina/{id}', [DadosGeracaoRealUsinaController::class, 'destroy']);
  Route::get('/dados-geracao-real-usina/usina/{usi_id}', [DadosGeracaoRealUsinaController::class, 'byUsinaId']);

  Route::get('/gerar-pdf-usina/{id}', [PDFController::class, 'gerarUsinaPDF']);
  Route::get('/gerar-pdf-consumidores/{id}', [PDFController::class, 'gerarConsumidoresPDF']);
});
