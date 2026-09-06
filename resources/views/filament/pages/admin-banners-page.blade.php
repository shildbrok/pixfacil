<x-filament::page>
    <style>
        .bn-wrap{display:grid;gap:18px}.bn-card{border:1px solid rgba(163,163,163,.18);border-radius:24px;background:linear-gradient(135deg,rgba(20,20,20,.96),rgba(30,30,30,.92));box-shadow:0 18px 48px rgba(0,0,0,.18);overflow:hidden}.bn-hero{padding:20px 22px;background:radial-gradient(circle at top left,rgba(57,242,92,.16),transparent 34%),radial-gradient(circle at top right,rgba(57,242,92,.10),transparent 32%)}.bn-title{margin:0;color:#fff;font-size:25px;font-weight:950;letter-spacing:-.04em}.bn-sub{margin:7px 0 0;color:#d4d4d4;font-size:13px;line-height:1.5;max-width:980px}.bn-note{margin-top:12px;border:1px solid rgba(57,242,92,.22);border-radius:16px;background:rgba(20,83,45,.18);padding:12px;color:#bbf7d0;font-size:12px;line-height:1.55}.bn-stats{display:grid;gap:10px;padding:0 20px 18px}@media(min-width:900px){.bn-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(min-width:1300px){.bn-stats{grid-template-columns:repeat(6,minmax(0,1fr))}}.bn-stat{border:1px solid rgba(163,163,163,.14);border-radius:15px;background:rgba(10,10,10,.28);padding:12px;min-height:78px}.bn-stat span{display:block;color:#a3a3a3;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}.bn-stat strong{display:block;margin-top:4px;color:#fff;font-size:18px;font-weight:950}.bn-stat small{display:block;margin-top:4px;color:#a3a3a3;font-size:11px}.bn-preview{display:grid;gap:12px;padding:0 20px 18px}@media(min-width:900px){.bn-preview{grid-template-columns:repeat(3,minmax(0,1fr))}}.bn-banner{position:relative;border:1px solid rgba(163,163,163,.14);border-radius:16px;background:rgba(10,10,10,.25);overflow:hidden}.bn-banner img{width:100%;height:135px;object-fit:cover;display:block;background:#0a0a0a}.bn-banner div{padding:10px}.bn-banner strong{display:block;color:#fff;font-size:12px}.bn-banner small{display:block;margin-top:3px;color:#a3a3a3;font-size:11px}.bn-pills{display:flex;gap:5px;flex-wrap:wrap;margin-top:7px}.bn-pills i{font-style:normal;padding:3px 7px;border-radius:999px;background:rgba(57,242,92,.08);border:1px solid rgba(57,242,92,.18);color:#86efac;font-size:9px;font-weight:800}.bn-table{padding:10px}.bn-table .fi-ta-header{padding:10px 12px}.bn-table .fi-ta-filters{padding:0 10px 10px!important}.bn-table .fi-input,.bn-table .fi-select-input,.bn-table input,.bn-table select{min-height:38px!important}.bn-table .fi-input-wrp,.bn-table .fi-select{border-radius:12px!important}
    </style>

    @php($stats = $this->stats())
    @php($previewBanners = $this->getPreviewBanners())

    <div class="bn-wrap">
        <section class="bn-card">
            <div class="bn-hero">
                <h2 class="bn-title">Banners da Plataforma</h2>
                <p class="bn-sub">Gerencie as campanhas que aparecem no desktop e no mobile sem editar arquivos do tema.</p>
                <div class="bn-note">Use <strong>Ativo</strong> para publicar/despublicar, defina a <strong>ordem</strong> e escolha se o banner aparece no <strong>Desktop</strong>, <strong>Mobile</strong> ou nos dois.</div>
            </div>

            <div class="bn-stats">
                <div class="bn-stat"><span>Total</span><strong>{{ $stats['total'] }}</strong><small>Banners cadastrados</small></div>
                <div class="bn-stat"><span>Ativos</span><strong>{{ $stats['active'] }}</strong><small>Publicados agora</small></div>
                <div class="bn-stat"><span>Desktop</span><strong>{{ $stats['desktop'] }}</strong><small>Visíveis em telas grandes</small></div>
                <div class="bn-stat"><span>Mobile</span><strong>{{ $stats['mobile'] }}</strong><small>Visíveis em celulares</small></div>
                <div class="bn-stat"><span>Carrossel / Home</span><strong>{{ $stats['carousel'] }} / {{ $stats['home'] }}</strong><small>Distribuição por posição</small></div>
                <div class="bn-stat"><span>Última edição</span><strong style="font-size:12px">{{ $stats['latest'] ? \Carbon\Carbon::parse($stats['latest'])->format('d/m/Y H:i') : '-' }}</strong><small>Atualização mais recente</small></div>
            </div>

            @if($previewBanners->isNotEmpty())
                <div class="bn-preview">
                    @foreach($previewBanners as $banner)
                        <div class="bn-banner">
                            @if($this->imageUrl($banner->image, $banner->updated_at))
                                <img src="{{ $this->imageUrl($banner->image, $banner->updated_at) }}" alt="Banner">
                            @endif
                            <div>
                                <strong>#{{ $banner->sort_order }} · {{ $this->typeLabel($banner->type) }}</strong>
                                <small>{{ \Illuminate\Support\Str::limit($banner->description ?: $banner->link ?: 'Sem descrição', 70) }}</small>
                                <span class="bn-pills">
                                    @if($banner->show_desktop)<i>Desktop</i>@endif
                                    @if($banner->show_mobile)<i>Mobile</i>@endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="bn-card bn-table">{{ $this->table }}</section>
    </div>
</x-filament::page>
