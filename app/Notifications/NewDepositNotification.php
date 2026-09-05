<?php



namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\MailNotificationGuard;

class NewDepositNotification extends Notification
{
    use Queueable;


    public $name;


    public $amout;


    public function __construct($name, $amout)
    {
        $this->name  = $name;
        $this->amout = $amout;
    }


    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (MailNotificationGuard::canSend()) {
            $channels[] = 'mail';
        }

        return $channels;
    }


    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->view(
            'emails.new-deposit', ['usuario' => $this->name, 'valor' => \Helper::amountFormatDecimal($this->amout)]
        );
    }


    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Olá Administrador, Informamos que um novo depósito no valor de ' . \Helper::amountFormatDecimal($this->amout) . ' , realizado pelo usuário' . $this->name,
        ];
    }
}