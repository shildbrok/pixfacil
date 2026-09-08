<x-filament::page>
    <style>
        .gc-wrap{display:grid;gap:16px}.gc-hero{padding:20px 22px;border:1px solid rgba(57,242,92,.18);border-radius:22px;background:radial-gradient(circle at 10% 0%,rgba(57,242,92,.10),transparent 34%),#080b09}.gc-hero h2{margin:0;color:#fff;font-size:24px;font-weight:950}.gc-hero p{max-width:920px;margin:7px 0 0;color:#a3aea6;font-size:13px;line-height:1.55}.gc-note{margin-top:12px;padding:11px 13px;border:1px solid rgba(57,242,92,.16);border-radius:13px;background:rgba(57,242,92,.045);color:#b7c2ba;font-size:12px;line-height:1.5}.gc-back{display:inline-flex;align-items:center;margin-top:13px;padding:8px 12px;border:1px solid rgba(57,242,92,.3);border-radius:10px;color:#57f372;text-decoration:none;font-size:12px;font-weight:850}.gc-table{padding:10px;border:1px solid rgba(57,242,92,.14);border-radius:20px;background:#070a08}
    </style>

    <div class="gc-wrap">
        <section class="gc-hero">
            <h2>Capas de Vitrine</h2>
            <p>Substitua apenas a arte usada na vitrine PixFácil. A capa original recebida do provedor continua intacta, então sincronizações do catálogo não apagam sua identidade visual.</p>

            @if(!$this->migrationReady())
                <div class="gc-note"><strong>Migration pendente:</strong> execute <code>php artisan migrate</code> para liberar as capas personalizadas.</div>
            @else
                <div class="gc-note"><strong>Recomendação:</strong> use artes 16:9 em 960×540 ou maior, com personagem/logo centralizados e texto longe das bordas. A mesma capa é usada na Home do PC e do celular.</div>
            @endif

            <a class="gc-back" href="{{ \App\Filament\Pages\AdminGamesPage::getUrl() }}">← Voltar para Jogos</a>
        </section>

        <section class="gc-table">
            {{ $this->table }}
        </section>
    </div>
</x-filament::page>
