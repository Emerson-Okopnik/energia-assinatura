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
import Usinas from '@/components/Usinas.vue'
import Distribuicao from '@/components/Distribuicao.vue'
import AtualizarUsina from '@/components/AtualizarUsina.vue'
import Relatorios from '@/components/Relatorios.vue'
import CalculoGeracao from '@/components/CalculoGeracao.vue'


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
    meta: { requiresAuth: true },
  },
  {
    path: '/consumidores',
    name: 'consumidores',
    component: Consumidores,
    meta: { requiresAuth: true },
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
    component: Usinas,
    meta: { requiresAuth: true },
  },
  {
    path: '/distribuicao',
    name: 'distribuicao',
    component: Distribuicao,
    meta: { requiresAuth: true },
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
    meta: { requiresAuth: true },
  },
  {
    path: '/calculo-geracao',
    name: 'calculo-geracao',
    component: CalculoGeracao,
    meta: { requiresAuth: true },
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
