import { createRouter, createWebHistory } from 'vue-router'
import {
  clearAuthSession,
  getAuthToken,
  isAuthenticated,
  isTokenExpired,
} from '@/utils/auth.js'
import Login from '@/views/Login.vue'
import Register from '@/views/Register.vue'
import Home from '@/views/Home.vue'
import CadastroConsumidor from '@/components/CadastroConsumidor.vue'
import CadastroUsina from '@/components/CadastroUsina.vue'
import Consumidores from '@/components/Consumidores.vue'
import AtualizarConsumidor from '@/components/AtualizarConsumidor.vue'
import Distribuicao from '@/components/Distribuicao.vue'
import AtualizarUsina from '@/components/AtualizarUsina.vue'
import Relatorios from '@/components/Relatorios.vue'
import FaturarUsina from '@/views/FaturarUsina.vue'
import UsinasFaturamento from '@/views/UsinasFaturamento.vue'
import Auditoria from '@/components/Auditoria.vue'


const routes = [
  {
    path: '/Home',
    name: 'Home',
    component: Home,
    meta: { requiresAuth: true },
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
  },
  /*{
    path: '/register',
    name: 'Register',
    component: Register,
  },*/
  {
    path: '/cadastro-consumidor',
    name: 'cadastro-consumidor',
    component: CadastroConsumidor,
    meta: { requiresAuth: true },
  },
  {
    path: '/cadastro-usina',
    name: 'cadastro-usina',
    component: CadastroUsina,
    meta: { requiresAuth: true, titulo: 'Cadastrar usina' },
  },
  {
    path: '/consumidores',
    name: 'consumidores',
    component: Consumidores,
    meta: { requiresAuth: true, titulo: 'Consumidores' },
  },
  {
    path: '/consumidor/:id',
    name: 'atualizarconsumidor',
    component: AtualizarConsumidor,
    meta: { requiresAuth: true },
  },
  {
    path: '/usinas',
    name: 'usinas',
    component: UsinasFaturamento,
    meta: { requiresAuth: true, titulo: 'Lista de usinas' },
  },
  {
    path: '/distribuicao',
    name: 'distribuicao',
    component: Distribuicao,
    meta: { requiresAuth: true, titulo: 'Distribuir créditos' },
  },
  {
    path: '/usina/:id',
    name: 'atualizarusina',
    component: AtualizarUsina,
    meta: { requiresAuth: true },
  },
  {
    path: '/relatorio',
    name: 'relatorio',
    component: Relatorios,
    meta: { requiresAuth: true, titulo: 'Relatórios' },
  },
  {
    path: '/auditoria',
    name: 'auditoria',
    component: Auditoria,
    meta: { requiresAuth: true, titulo: 'Auditoria' },
  },
  {
    // Estado (usina/ano/mês) vive na rota: F5, deep-link e voltar funcionam.
    // Sem :usinaId, o índice de faturamento é a própria lista de usinas (§2).
    path: '/faturar/:usinaId?/:ano?/:mes?',
    name: 'faturar',
    component: FaturarUsina,
    meta: { requiresAuth: true, titulo: 'Faturar' },
    beforeEnter: (to) => {
      if (!to.params.usinaId) return { name: 'usinas' }
      return true
    },
  },
  {
    path: '/calculo-geracao',
    redirect: '/faturar',
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/Home',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})


router.beforeEach((to, from, next) => {
  const token = getAuthToken()
  if (token && isTokenExpired(token)) {
    clearAuthSession()
  }

  if (to.meta.requiresAuth && !isAuthenticated()) {
    next({ name: 'Login', replace: true })
    return
  }

  next()
})

export default router
