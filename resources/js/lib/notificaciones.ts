import Swal from 'sweetalert2';

export function notificar(mensaje: string, tipo: 'success' | 'error' = 'success'): void {
    const esExito = tipo === 'success';
    // Los errores del formulario permanecen dentro de su ámbito de foco.
    const formulario = document.querySelector<HTMLElement>('.form-dialog.show .modal-content, .form-dialog--offcanvas.show');

    void Swal.fire({
        toast: true,
        position: 'top',
        icon: tipo,
        titleText: mensaje,
        target: esExito ? document.body : formulario ?? document.body,
        showConfirmButton: false,
        showCloseButton: true,
        closeButtonAriaLabel: 'Cerrar notificación',
        timer: esExito ? 4000 : undefined,
        timerProgressBar: esExito,
        customClass: { container: 'miventa-toast-container', popup: 'miventa-toast' },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', () => Swal.stopTimer());
            toast.addEventListener('mouseleave', () => Swal.resumeTimer());
            toast.addEventListener('focusin', () => Swal.stopTimer());
            toast.addEventListener('focusout', () => Swal.resumeTimer());
        },
    });
}
