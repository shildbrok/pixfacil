<?php



namespace App\Notifications;

use App\Models\AggregatorWallet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\MailNotificationGuard;

class AggregatorWalletLowBalanceNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly AggregatorWallet $wallet)
    {
    }

    public function via(object $notifiable): array
    {
        return MailNotificationGuard::canSend() ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $balance = 'R$ '.number_format((float) $this->wallet->balance, 2, ',', '.');
        $threshold = 'R$ '.number_format((float) ($this->wallet->notify_threshold ?? 0), 2, ',', '.');

        return (new MailMessage())
            ->subject('Alerta de saldo do agregador')
            ->greeting('Alerta de saldo')
            ->line('A carteira monitorada ficou abaixo do limite configurado.')
            ->line('Carteira: '.$this->wallet->name)
            ->line('Saldo atual: '.$balance)
            ->line('Limite configurado: '.$threshold)
            ->line('Verifique a página Saldo agregador no painel administrativo.');
    }
}