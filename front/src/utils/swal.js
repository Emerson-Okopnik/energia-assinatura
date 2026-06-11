import Swal from 'sweetalert2'

/**
 * Swal pré-configurado com o design system Líder Energy.
 * Importar este mixin no lugar de `sweetalert2` direto:
 *   import swal from '@/utils/swal.js'
 */
const swal = Swal.mixin({
  confirmButtonColor: '#F39325',
  cancelButtonColor: '#5C5C5C',
  buttonsStyling: true,
  reverseButtons: true,
  customClass: {
    popup: 'swal-ds-popup',
    title: 'swal-ds-title',
    htmlContainer: 'swal-ds-text',
    confirmButton: 'swal-ds-confirm',
    cancelButton: 'swal-ds-cancel',
  },
})

export default swal
