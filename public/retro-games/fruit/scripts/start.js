/*
 * Bootstrap do Fruit Ninja, adaptado do projeto retro para a API do chinesa.
 *
 * O original (start.js.orig) definia seu próprio fetchApi/winGame/loseGame
 * apontando para /api/retro/engine/fruit/*. Aqui essas funções vêm do arcade-bridge.js e
 * este arquivo só publica as variáveis que o engine (scripts/all.js) lê.
 *
 * As declarações continuam com `let` no escopo do script porque é assim que
 * all.js as encontra — trocar por window.* faria o engine ler undefined.
 */
let meta = 0;
let xmeta = 2;
let bet = 1;
let gameSpeed = 820;
let velocidade_game = 820;
let fruitRate = 1;

Arcade.load().then(function (round) {
    if (!round) { return; }

    var settings = round.settings || {};

    bet = Number(round.bet);
    xmeta = Number(settings.meta_multiplier);
    gameSpeed = Number(settings.drop_duration);

    // Fórmula original preservada: a taxa de fruta escala com a aposta.
    fruitRate = Number(settings.fruit_rate) * bet;

    meta = bet * xmeta;
    velocidade_game = gameSpeed;

    var script = document.createElement('script');
    script.src = 'scripts/all.js';
    document.head.appendChild(script);
});
