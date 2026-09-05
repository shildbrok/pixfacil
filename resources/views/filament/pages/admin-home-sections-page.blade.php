<x-filament::page>
    <style>
        .hs-hero{border:1px solid rgba(163,163,163,.18);border-radius:22px;background:linear-gradient(135deg,rgba(20,20,20,.96),rgba(30,30,30,.90));padding:20px 22px;margin-bottom:16px;background-image:radial-gradient(circle at top left,rgba(249,115,22,.16),transparent 34%)}
        .hs-title{margin:0;color:#fff;font-size:22px;font-weight:900;letter-spacing:-.03em}
        .hs-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.55}
        .hs-sub b{color:#fbbf24}
    </style>

    <div class="hs-hero">
        <h2 class="hs-title">Seções da Home</h2>
        <p class="hs-sub">
            Monte a home do seu jeito. Cada seção é uma fileira de jogos, e o <b>tipo</b> decide como os jogos são escolhidos:
            <b>Destaque</b>, <b>Populares</b>, <b>Lançamentos</b>, <b>Recentes do jogador</b>, por <b>Categoria</b> ou <b>Manual</b> (você escolhe).
            Arraste as linhas para reordenar. Desative uma seção para escondê-la sem apagar.
        </p>
    </div>

    {{ $this->table }}
</x-filament::page>
