<?php



namespace App\Jobs;

use App\Services\BetCrm\BetCrmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class SendBetCrmEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $path;
    public array $payload;

    public function __construct(string $path, array $payload)
    {
        $this->path = $path;
        $this->payload = $payload;
    }

    public function handle(): void
    {
        // postRaw já engole tudo, mas mantemos a cerca aqui também: um job que
        // lança geraria log de erro do framework — e o CRM não deve gerar ruído.
        try {
            BetCrmService::postRaw($this->path, $this->payload);
        } catch (\Throwable $e) {
            // silêncio proposital
        }
    }
}
