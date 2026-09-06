/* PixFácil Retro Bridge v1 — client is NOT a source of truth for money. */
(function (global) {
    'use strict';

    var params = new URLSearchParams(global.location.search);
    var config = {
        slug: params.get('slug') || '',
        baseurl: (params.get('baseurl') || global.location.origin).replace(/\/+$/, ''),
        back: params.get('back') || '/retro',
        velo: parseFloat(params.get('velo')) || null
    };

    global.bet = 0;
    global.coinRate = 0.01;
    global.metaMultiplier = 2;
    global.playerSpeed = 10;
    global.xmeta = 2;
    global.currentAction = 'playing';

    var settled = false;

    function apiUrl(action) {
        return config.baseurl + '/api/retro/engine/' + encodeURIComponent(config.slug) + '/' + action;
    }

    function request(action, method, body) {
        var headers = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'};
        if (method === 'POST') headers['Content-Type'] = 'application/json';

        return fetch(apiUrl(action), {
            method: method,
            headers: headers,
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : null
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                return {ok: res.ok, status: res.status, data: data};
            });
        }).catch(function () { return {ok: false, status: 0, data: {}}; });
    }

    function goBack(extra) {
        var url = config.back || '/retro';
        try { (global.top || global).location.href = url + (extra || ''); }
        catch (e) { global.location.href = url + (extra || ''); }
    }

    var Arcade = {
        settings: {},
        load: function () {
            return request('info', 'GET').then(function (res) {
                if (!res.ok || !res.data || typeof res.data.bet === 'undefined') {
                    var url = (res.data && res.data.play_url) || config.back;
                    alert((res.data && res.data.message) || 'Inicie uma nova rodada para jogar.');
                    if (url) { try {(global.top || global).location.href = url;} catch(e){global.location.href=url;} }
                    return null;
                }
                var s = res.data.settings || {};
                global.bet = Number(res.data.bet);
                global.coinRate = Number(s.coin_rate || 0.01);
                global.metaMultiplier = Number(s.meta_multiplier || 2);
                global.xmeta = global.metaMultiplier;
                global.playerSpeed = config.velo !== null ? config.velo : Number(s.player_speed || 1);
                Arcade.settings = s;
                Arcade.meta = Number(res.data.meta);
                Arcade.maxPayout = Number(res.data.max_payout);
                return res.data;
            });
        },
        win: function (amount, options) {
            if (settled) return Promise.resolve(Arcade.payout || 0);
            settled = true;
            global.currentAction = 'win';
            var voltar = !(options && options.voltar === false);
            // O valor visual do jogo não é enviado ao backend: resultado e payout
            // são exclusivamente server-authoritative.
            return request('win', 'POST', {}).then(function (res) {
                if (!res.ok) {
                    settled = false;
                    alert((res.data && res.data.message) || 'Não foi possível liquidar a rodada.');
                    return 0;
                }
                var paid = Number((res.data && res.data.payout) || 0);
                var outcome = String((res.data && res.data.outcome) || (paid > 0 ? 'won' : 'lost'));
                Arcade.payout = paid;
                if (outcome === 'won' && paid > 0) {
                    global.currentAction = 'win';
                    try { global.PixFacilOriginals && global.PixFacilOriginals.event('win', {payout: paid}); } catch (e) {}
                } else {
                    global.currentAction = 'lose';
                    try { global.PixFacilOriginals && global.PixFacilOriginals.event('lose'); } catch (e) {}
                }
                if (voltar) goBack('?win_amount=' + encodeURIComponent(paid));
                return paid;
            });
        },
        lose: function (options) {
            if (settled) return Promise.resolve();
            settled = true;
            global.currentAction = 'lose';
            var voltar = !(options && options.voltar === false);
            return request('lost', 'POST').then(function () {
                try { global.PixFacilOriginals && global.PixFacilOriginals.event('lose'); } catch (e) {}
                if (voltar) goBack('?win_amount=0');
            });
        },
        voltar: function () { goBack(''); },
        toMoney: function (points) { return Number(points) * global.coinRate; },
        reachedMeta: function (amount) { return Number(amount) >= global.bet * global.metaMultiplier; },
        config: config
    };

    global.Arcade = Arcade;
    global.winGame = function (value) { return Arcade.win(value); };
    global.loseGame = function () { return Arcade.lose(); };
    global.fetchApi = function (route, method, payload) {
        var action = String(route || '').split('?')[0].split('/').pop();
        if (action === 'win') {
            var value = payload && payload.get ? payload.get('ganho') : (payload && payload.ganho) || 0;
            return Arcade.win(value);
        }
        if (action === 'lost') return Arcade.lose();
        if (action === 'info') return Arcade.load();
        return Promise.resolve(null);
    };
}(window));
