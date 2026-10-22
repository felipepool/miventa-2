<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo con el enlace para crear una contraseña nueva. Recuerda también el
 * código del negocio y el usuario, que son los datos para ingresar.
 */
class RestablecerContrasena extends Notification
{
    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Crea una contraseña nueva para MiVenta')
            ->greeting("Hola, {$notifiable->name}")
            ->line('Recibimos una solicitud para cambiar la contraseña de tu cuenta en MiVenta.')
            ->action('Crear contraseña nueva', $url)
            ->line("El enlace vence en {$minutos} minutos.")
            ->line('Tus datos para ingresar:')
            ->line("Código de negocio: **{$notifiable->negocio?->codigo}** · Usuario: **{$notifiable->username}**")
            ->line('Si no pediste este cambio, ignora este correo: tu contraseña actual sigue funcionando.')
            ->salutation('Saludos, el equipo de MiVenta');
    }
}
