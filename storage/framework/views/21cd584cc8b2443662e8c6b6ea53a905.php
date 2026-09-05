<?php if (isset($component)) { $__componentOriginalbe23554f7bded3778895289146189db7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe23554f7bded3778895289146189db7 = $attributes; } ?>
<?php $component = Filament\View\LegacyComponents\Page::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::page'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Filament\View\LegacyComponents\Page::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <style>
        .og-logs {
            --og-bg: #0a0a0a;
            --og-card: rgba(20, 20, 20, .92);
            --og-card-2: rgba(30, 30, 30, .72);
            --og-line: rgba(163, 163, 163, .18);
            --og-text: #e5e7eb;
            --og-muted: #a3a3a3;
            --og-soft: rgba(163, 163, 163, .08);
            --og-primary: #fb923c;
            --og-green: #22c55e;
            --og-yellow: #f59e0b;
            --og-red: #ef4444;
            --og-purple: #f97316;
        }

        .og-logs * {
            box-sizing: border-box;
        }

        .og-shell {
            display: grid;
            gap: 18px;
        }

        .og-hero {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--og-line);
            border-radius: 24px;
            background:
                radial-gradient(circle at top left, rgba(251, 146, 60, .22), transparent 32%),
                radial-gradient(circle at top right, rgba(249, 115, 22, .17), transparent 30%),
                linear-gradient(135deg, rgba(20, 20, 20, .98), rgba(10, 10, 10, .98));
            box-shadow: 0 22px 70px rgba(0, 0, 0, .32);
        }

        .og-hero-inner {
            position: relative;
            display: grid;
            gap: 20px;
            padding: 24px;
        }

        @media (min-width: 1024px) {
            .og-hero-inner {
                grid-template-columns: 1.2fr .8fr;
                align-items: center;
            }
        }

        .og-kicker {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(251, 146, 60, .26);
            border-radius: 999px;
            background: rgba(251, 146, 60, .08);
            padding: 6px 10px;
            color: #fed7aa;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .og-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--og-green);
            box-shadow: 0 0 18px rgba(34, 197, 94, .75);
        }

        .og-title {
            margin: 12px 0 0;
            color: white;
            font-size: clamp(24px, 3vw, 36px);
            font-weight: 850;
            letter-spacing: -0.04em;
            line-height: 1.05;
        }

        .og-subtitle {
            margin: 10px 0 0;
            max-width: 780px;
            color: var(--og-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .og-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .og-stat {
            border: 1px solid var(--og-line);
            border-radius: 18px;
            background: rgba(10, 10, 10, .44);
            padding: 14px;
        }

        .og-stat-label {
            color: var(--og-muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .og-stat-value {
            margin-top: 5px;
            color: white;
            font-size: 18px;
            font-weight: 850;
            line-height: 1.1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .og-panel {
            border: 1px solid var(--og-line);
            border-radius: 22px;
            background: var(--og-card);
            box-shadow: 0 16px 45px rgba(0, 0, 0, .22);
            overflow: hidden;
        }

        .og-panel-head {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--og-line);
            background: linear-gradient(180deg, rgba(30, 30, 30, .78), rgba(20, 20, 20, .6));
        }

        @media (min-width: 768px) {
            .og-panel-head {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        .og-panel-title {
            margin: 0;
            color: var(--og-text);
            font-size: 15px;
            font-weight: 850;
        }

        .og-panel-desc {
            margin: 4px 0 0;
            color: var(--og-muted);
            font-size: 12px;
        }

        .og-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid var(--og-line);
            background: rgba(20, 20, 20, .82);
            color: #d4d4d4;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 750;
            white-space: nowrap;
        }

        .og-filters {
            display: grid;
            gap: 14px;
            padding: 18px 20px 20px;
        }

        @media (min-width: 900px) {
            .og-filters {
                grid-template-columns: 1.1fr 1.5fr .9fr .8fr;
            }
        }

        .og-field label {
            display: block;
            margin-bottom: 7px;
            color: #d4d4d4;
            font-size: 12px;
            font-weight: 850;
        }

        .og-field input,
        .og-field select {
            width: 100%;
            min-height: 43px;
            border: 1px solid rgba(163, 163, 163, .23);
            border-radius: 14px;
            background: rgba(10, 10, 10, .42);
            color: white;
            padding: 0 13px;
            outline: none;
            transition: .16s ease;
        }

        .og-field input:focus,
        .og-field select:focus {
            border-color: rgba(251, 146, 60, .7);
            box-shadow: 0 0 0 4px rgba(251, 146, 60, .12);
        }

        .og-help {
            margin-top: 6px;
            color: var(--og-muted);
            font-size: 11px;
        }

        .og-tip-grid {
            display: grid;
            gap: 12px;
            padding: 0 20px 20px;
        }

        @media (min-width: 900px) {
            .og-tip-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .og-tip {
            border: 1px solid var(--og-line);
            border-radius: 18px;
            background: rgba(10, 10, 10, .32);
            padding: 14px;
        }

        .og-tip strong {
            display: block;
            margin-bottom: 6px;
            color: white;
            font-size: 12px;
        }

        .og-tip p {
            margin: 0;
            color: var(--og-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .og-list {
            max-height: 68vh;
            overflow: auto;
        }

        .og-event {
            padding: 16px 18px;
            border-bottom: 1px solid var(--og-line);
            transition: .16s ease;
        }

        .og-event:hover {
            background: rgba(251, 146, 60, .045);
        }

        .og-event-top {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        @media (min-width: 900px) {
            .og-event-top {
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
            }
        }

        .og-event-main {
            min-width: 0;
            display: flex;
            gap: 12px;
        }

        .og-icon {
            flex: 0 0 auto;
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 14px;
            border: 1px solid var(--og-line);
            background: rgba(20, 20, 20, .9);
            font-weight: 900;
        }

        .og-level-error .og-icon { color: #fecaca; background: rgba(239, 68, 68, .13); border-color: rgba(239, 68, 68, .25); }
        .og-level-warning .og-icon { color: #fde68a; background: rgba(245, 158, 11, .13); border-color: rgba(245, 158, 11, .25); }
        .og-level-info .og-icon { color: #fed7aa; background: rgba(249, 115, 22, .13); border-color: rgba(249, 115, 22, .25); }

        .og-event-title {
            margin: 0;
            color: var(--og-text);
            font-size: 14px;
            font-weight: 850;
            line-height: 1.35;
        }

        .og-event-hint {
            margin: 4px 0 0;
            color: var(--og-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .og-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .og-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .02em;
            white-space: nowrap;
        }

        .og-badge-error { background: rgba(239, 68, 68, .13); color: #fecaca; border: 1px solid rgba(239, 68, 68, .24); }
        .og-badge-warning { background: rgba(245, 158, 11, .13); color: #fde68a; border: 1px solid rgba(245, 158, 11, .24); }
        .og-badge-info { background: rgba(249, 115, 22, .13); color: #fed7aa; border: 1px solid rgba(249, 115, 22, .24); }
        .og-badge-gateway { background: rgba(251, 146, 60, .12); color: #fed7aa; border: 1px solid rgba(251, 146, 60, .24); }

        .og-details {
            margin-top: 12px;
        }

        .og-details summary {
            cursor: pointer;
            width: fit-content;
            color: #fdba74;
            font-size: 12px;
            font-weight: 850;
            list-style: none;
        }

        .og-details summary::-webkit-details-marker {
            display: none;
        }

        .og-code {
            margin-top: 10px;
            border: 1px solid rgba(163, 163, 163, .18);
            border-radius: 16px;
            background: #0a0a0a;
            padding: 14px;
            overflow: auto;
        }

        .og-code pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            color: #d4d4d4;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            line-height: 1.55;
        }

        .og-empty {
            padding: 58px 20px;
            text-align: center;
        }

        .og-empty-mark {
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            margin: 0 auto 14px;
            border-radius: 22px;
            background: rgba(34, 197, 94, .12);
            color: #86efac;
            font-size: 28px;
            font-weight: 900;
            border: 1px solid rgba(34, 197, 94, .24);
        }

        .og-empty h3 {
            margin: 0;
            color: white;
            font-size: 16px;
            font-weight: 900;
        }

        .og-empty p {
            margin: 7px auto 0;
            max-width: 460px;
            color: var(--og-muted);
            font-size: 13px;
            line-height: 1.55;
        }
    </style>

    <?php
        $rows = $this->rows;
        $errorCount = collect($rows)->whereIn('level', ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])->count();
        $warningCount = collect($rows)->where('level', 'WARNING')->count();

        $gatewayFor = function (string $line): ?string {
            $lower = strtolower($line);

            return match (true) {
                str_contains($lower, 'digitopay') => 'DIGITOPAY',
                str_contains($lower, 'pixup') => 'PIXUP',
                str_contains($lower, 'xgate') => 'XGATE',
                str_contains($lower, 'podpay') => 'PODPAY',
                str_contains($lower, 'gerapix') => 'GERAPIX',
                str_contains($lower, 'playfiver') => 'PLAYFIVER',
                default => null,
            };
        };

        $levelClass = function (string $level): string {
            return match (true) {
                in_array($level, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true) => 'og-level-error',
                $level === 'WARNING' => 'og-level-warning',
                $level === 'INFO' => 'og-level-info',
                default => '',
            };
        };

        $levelBadgeClass = function (string $level): string {
            return match (true) {
                in_array($level, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true) => 'og-badge-error',
                $level === 'WARNING' => 'og-badge-warning',
                $level === 'INFO' => 'og-badge-info',
                default => 'og-badge-info',
            };
        };
    ?>

    <div class="og-logs">
        <div class="og-shell">
            <section class="og-hero">
                <div class="og-hero-inner">
                    <div>
                        <div class="og-kicker"><span class="og-dot"></span> Logs Laravel</div>
                        <h1 class="og-title">Monitor operacional</h1>
                        <p class="og-subtitle">
                            Painel enxuto para localizar falhas reais sem expor ruído de operação, token, QR Code ou payload sensível.
                        </p>
                    </div>

                    <div class="og-stats">
                        <div class="og-stat">
                            <div class="og-stat-label">Arquivo</div>
                            <div class="og-stat-value"><?php echo e($file ?: 'laravel.log'); ?></div>
                        </div>
                        <div class="og-stat">
                            <div class="og-stat-label">Tamanho</div>
                            <div class="og-stat-value"><?php echo e($this->selectedFileSize); ?></div>
                        </div>
                        <div class="og-stat">
                            <div class="og-stat-label">Erros filtrados</div>
                            <div class="og-stat-value"><?php echo e($errorCount); ?></div>
                        </div>
                        <div class="og-stat">
                            <div class="og-stat-label">Avisos filtrados</div>
                            <div class="og-stat-value"><?php echo e($warningCount); ?></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="og-panel">
                <div class="og-panel-head">
                    <div>
                        <h2 class="og-panel-title">Filtros</h2>
                        <p class="og-panel-desc">Use o modo “Somente erros” para suporte normal. Use “Todos” só para depuração.</p>
                    </div>
                    <span class="og-chip">Modo limpo ativo</span>
                </div>

                <div class="og-filters">
                    <div class="og-field">
                        <label>Arquivo de log</label>
                        <select wire:model.live="file">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->logFiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $logFile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($logFile); ?>"><?php echo e($logFile); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                        <div class="og-help">Origem: storage/logs</div>
                    </div>

                    <div class="og-field">
                        <label>Buscar</label>
                        <input wire:model.live.debounce.500ms="search" type="text" placeholder="gateway, playfiver, saque, cpf, token, pix..." />
                        <div class="og-help">Filtra pela linha completa do log.</div>
                    </div>

                    <div class="og-field">
                        <label>Nível</label>
                        <select wire:model.live="levelFilter">
                            <option value="errors">Somente erros</option>
                            <option value="warnings">Erros e avisos</option>
                            <option value="all">Todos</option>
                        </select>
                        <div class="og-help">Padrão recomendado: erros.</div>
                    </div>

                    <div class="og-field">
                        <label>Linhas finais</label>
                        <select wire:model.live="limit">
                            <option value="100">100 linhas</option>
                            <option value="300">300 linhas</option>
                            <option value="500">500 linhas</option>
                            <option value="1000">1000 linhas</option>
                            <option value="2000">2000 linhas</option>
                        </select>
                        <div class="og-help">Evita carregar arquivo inteiro.</div>
                    </div>
                </div>

                <div class="og-tip-grid">
                    <div class="og-tip">
                        <strong>Oculto por padrão</strong>
                        <p>INFO, DEBUG, token obtido, PIX gerado, callback OK e payload de sucesso.</p>
                    </div>

                    <div class="og-tip">
                        <strong>Mostrado para suporte</strong>
                        <p>Falha de gateway, IP bloqueado, token inválido, CPF/chave PIX inválida e erro de conexão.</p>
                    </div>

                    <div class="og-tip">
                        <strong>Diagnóstico automático</strong>
                        <p>Cada linha recebe uma explicação curta para o operador entender sem ler stack trace completo.</p>
                    </div>
                </div>
            </section>

            <section class="og-panel">
                <div class="og-panel-head">
                    <div>
                        <h2 class="og-panel-title">Eventos encontrados</h2>
                        <p class="og-panel-desc">Clique em “Ver linha técnica” só quando precisar do log bruto.</p>
                    </div>

                    <span class="og-chip">
                        Filtro:
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($levelFilter === 'errors'): ?>
                            somente erros
                        <?php elseif($levelFilter === 'warnings'): ?>
                            erros e avisos
                        <?php else: ?>
                            todos
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </span>
                </div>

                <div class="og-list">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $gateway = $gatewayFor($row['line']);
                            $isError = in_array($row['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true);
                            $isWarning = $row['level'] === 'WARNING';
                        ?>

                        <article class="og-event <?php echo e($levelClass($row['level'])); ?>">
                            <div class="og-event-top">
                                <div class="og-event-main">
                                    <div class="og-icon">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isError): ?>
                                            !
                                        <?php elseif($isWarning): ?>
                                            ?
                                        <?php else: ?>
                                            i
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>

                                    <div>
                                        <h3 class="og-event-title"><?php echo e($row['label']); ?></h3>
                                        <p class="og-event-hint"><?php echo e($row['hint']); ?></p>
                                    </div>
                                </div>

                                <div class="og-badges">
                                    <span class="og-badge <?php echo e($levelBadgeClass($row['level'])); ?>"><?php echo e($row['level']); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gateway): ?>
                                        <span class="og-badge og-badge-gateway"><?php echo e($gateway); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>

                            <details class="og-details">
                                <summary>Ver linha técnica</summary>
                                <div class="og-code">
                                    <pre><?php echo e($row['line']); ?></pre>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="og-empty">
                            <div class="og-empty-mark">✓</div>
                            <h3>Nenhum erro encontrado</h3>
                            <p>O filtro atual não encontrou falhas relevantes neste arquivo. Para ver logs informativos, altere o nível para “Todos”.</p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </section>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe23554f7bded3778895289146189db7)): ?>
<?php $attributes = $__attributesOriginalbe23554f7bded3778895289146189db7; ?>
<?php unset($__attributesOriginalbe23554f7bded3778895289146189db7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe23554f7bded3778895289146189db7)): ?>
<?php $component = $__componentOriginalbe23554f7bded3778895289146189db7; ?>
<?php unset($__componentOriginalbe23554f7bded3778895289146189db7); ?>
<?php endif; ?><?php /**PATH /home/bulls777/htdocs/bulls777.bet/resources/views/filament/pages/laravel-logs-page.blade.php ENDPATH**/ ?>