<?php



namespace App\Jobs;

use App\Services\Meta\MetaConversionsApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMetaEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public string $eventName;


    public array $userData;


    public array $customData;


    public ?string $eventId;


    public array $context;


    public function __construct(
        string $eventName,
        array $userData,
        array $customData = [],
        ?string $eventId = null,
        array $context = []
    ) {
        $this->eventName  = $eventName;
        $this->userData   = $userData;
        $this->customData = $customData;
        $this->eventId    = $eventId;
        $this->context    = $context;
    }


    public function handle(MetaConversionsApi $meta): void
    {
        $meta->sendEvent(
            $this->eventName,
            $this->userData,
            $this->customData,
            $this->eventId,
            $this->context
        );
    }
}
