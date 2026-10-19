<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContrasenaRestablecida extends Notification
{
    public function __construct(public string $usuario, public string $enlace) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('KARMA - Tu contraseña fue restablecida')
            ->greeting('Hola, '.$this->usuario)
            ->line('Se completó el restablecimiento de la contraseña de tu cuenta '.$this->usuario.'.')
            ->line('Si el administrador realizó el cambio y no conoces la nueva contraseña, utiliza el siguiente enlace para elegir tu propia clave.')
            ->action('Elegir mi contraseña', $this->enlace)
            ->line('El enlace es de un solo uso y vence en '.config('auth.passwords.users.expire', 60).' minutos. No lo compartas.')
            ->line('Si tú elegiste la nueva contraseña, ya puedes iniciar sesión con ella.')
            ->line('Si no solicitaste este cambio, contacta inmediatamente al administrador.');
    }
}
