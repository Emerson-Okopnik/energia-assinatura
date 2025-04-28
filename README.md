# 🌞 Sistema de Gestão de Usinas e Consumidores Fotovoltaicos

Este projeto é uma aplicação para **gerenciamento de usinas fotovoltaicas**, **controle de consumidores vinculados**, **cálculo de geração**, **análises financeiras** e **relatórios gráficos**.

---

## 📄 Requisitos

- Node.js
- Composer
- PHP >= 7.4
- MySQL ou PostgreSQL

---

## ⚡ Funcionalidades

- Cadastro de **Usinas** (com dados de geração, localização, comercialização e status de processo).
- Cadastro de **Consumidores** (dados de consumo, vendedor responsável, status de adesão).
- **Vinculação** de consumidores a usinas.
- Cálculo automático de:
  - **Saldo de geração** vs **consumo**.
  - **Valor final a receber** mês a mês (considerando Fio B, Lei 14.300/23, custo operacional e faturamento).
  - **Média de recebimento** anual.
- Relatórios:
  - **Gráfico de linha** comparando geração e consumo por mês.
  - **Gráfico de barras empilhadas** (Fixo, Injetado, Creditado e CUO).
  - **Dashboard de KPIs**:
    - Total de consumidores credenciados.
    - Total de consumidores não vinculados.
    - Total de usinas conectadas e não conectadas.
    - Geração média total e saldo disponível.
- Visualização de:
  - Consumidores **não vinculados**.
  - Usinas **não conectadas**.
  - Evolução **anual** de cadastros de consumidores e usinas.

---

## 🛠️ Tecnologias Utilizadas

- **Vue 3** + **Vite** (Frontend)
- **Laravel 11** + **JWT** (Backend/API RESTful)
- **Postgresql** (Banco de dados)
- **Chart.js** + **vue-chartjs** (Gráficos)
- **Bootstrap 5** (Estilização e responsividade)

---

## 📦 Instalação

1. Clone o repositório:
   ```bash
   git clone https://github.com/seu-usuario/seu-repo.git

2. Instale as dependências do frontend:
   ````bash
   npm install

3. Instale Axios no frontend:  
     ```bash
     npm install vue axios

4. Instale o Vite no frontend:
     ```bash
     npm install vite   

5. Instale o Chart.js no frontend:
     ```bash
     npm install chart.js

6. Instale as dependências do backend:
   ````bash
   composer install

7. Copie ou crie o .env:
   ````bash
   cp .env.example .env

8. Gere as migrations:
    ```bash
    php artisan migrate
    
9. Instale o pacote JWT do Laravel:
   ```bash
   composer require tymon/jwt-auth
  
10. Publique o arquivo de configuração do JWT:
    ```bash
    php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
   
12. Gere uma chave secreta para o JWT:
    ```bash
    php artisan jwt:secret

13. Inicie o Laravel:
    ```bash
    php artisan serve --host=localhost

14. Inicie Vue.js:
     ```bash
     npm run dev

---

## 🧑‍💻 Autor

Desenvolvido por Emerson Okopnik

Contato: emer00k@gmail.com
