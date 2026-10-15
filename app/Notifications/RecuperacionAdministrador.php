<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class RecuperacionAdministrador extends Notification {
    public function __construct(public string $token,public string $email,public string $usuario) {}
    public function via($notifiable): array { return ['mail']; }
    public function toMail($notifiable): MailMessage {
        return (new MailMessage)->subject('Autorizar recuperación de contraseña')
            ->line('Se solicitó recuperar el acceso de '.$this->usuario.' ('.$this->email.').')
            ->action('Restablecer contraseña',route('password.reset',['token'=>$this->token,'email'=>$this->email]))
            ->line('Verifica la identidad de la persona antes de autorizar. El enlace vence en '.config('auth.passwords.users.expire',60).' minutos.');
    }
}
