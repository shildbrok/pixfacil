/*
 * Substituto local e inerte do PokiSDK.
 *
 * Os engines vieram de builds distribuídos pela Poki e chamam PokiSDK.* para
 * anúncios e telemetria. Aqui dentro isso não faz sentido: seria uma dependência
 * de CDN externa no meio de uma partida valendo dinheiro, com anúncio podendo
 * aparecer em cima do jogo. Este stub responde tudo sem fazer requisição.
 */
(function (global) {
    'use strict';

    if (global.PokiSDK) { return; }

    function resolved() {
        return Promise.resolve();
    }

    global.PokiSDK = {
        adBlockerOn: false,
        init: resolved,
        setDebug: function () {},
        gameLoadingStart: function () {},
        gameLoadingFinished: function () {},
        gameLoadingProgress: function () {},
        gameplayStart: function () {},
        gameplayStop: function () {},
        commercialBreak: resolved,
        rewardedBreak: function () { return Promise.resolve(false); },
        happyTime: function () {},
        shareableURL: function () { return Promise.resolve(global.location.href); },
        getURLParam: function (name) {
            return new URLSearchParams(global.location.search).get(name) || '';
        },
        captureError: function () {},
        roundStart: function () {},
        roundEnd: function () {}
    };
}(window));
