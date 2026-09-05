<?php



namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\MailNotificationGuard;

class NewWithdrawalNotification extends Notification
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
            'emails.new-withdrawal', ['usuario' => $this->name, 'valor' => \Helper::amountFormatDecimal($this->amout)]
        );
    }


    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Olá Administrador, Foi solicitado um saque de ' . \Helper::amountFormatDecimal($this->amout) . ' , pelo usuário' . $this->name,
        ];
    }
}