if (localStorage.getItem("glmdataCC") !== null) {
    localStorage.removeItem("glmdataCC");
}

if (localStorage.getItem('currentPoints') !== null) {
    localStorage.removeItem('currentPoints');
}

function scorepost(href, inputs) {
    var gform = document.createElement('form');
    gform.method = 'post';
    gform.action = href;
    gform.target = '_parent';
    for (var k in inputs) {
        var input = document.createElement('input');
        input.setAttribute('name', k);
        input.setAttribute('value', inputs[k]);
        gform.appendChild(input);
    }
    document.body.appendChild(gform);
    gform.submit();
    document.body.removeChild(gform);
}

function hideDiv(el) {
    el.style.display = 'none';
}
function showDiv(el) {
    el.style.display = '';
}

/******************* TIMER CONFIG ******************/
function updateTimer(el, seconds) {
    if (stateGame.play) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
        const formattedSeconds = remainingSeconds < 10 ? '0' + remainingSeconds : remainingSeconds;
        el.innerText = formattedMinutes + ':' + formattedSeconds;
    }
}
function startTimer(el, seconds) {
    updateTimer(el, seconds);
    const timerInterval = setInterval(function () {
        seconds--;
        if (seconds <= 0) {
            clearInterval(timerInterval);
            execRed();
        }
        updateTimer(el, seconds);
    }, 1000);
}

async function fetchApi(route, method = "GET", payload = null) {
    return await fetch(route, {
        method,
        body: payload
    }).then(res => res.json()).then(data => {
        return data
    }).catch(err => {
        return null
    })
}

async function winGame(valor) {
    const formData = new FormData()
    formData.append('ganho', valor)
    await fetchApi('/api/retro/engine/candy/win', 'POST', formData)

    location.href = '/games/candy?win_amount=' + valor
}

async function loseGame(accumuled, bet) {
    await fetchApi('/api/retro/engine/candy/lost', 'POST')

    location.href = '/games/candy?win_amount=0'
}

/******************* GAME CONFIG  *********************/
const rounds = {
    '1': {
        round: 1,
        timer: 90,
        meta: 20,
        coinRate: 0.01
    }
}
const stateGame = {
    play: true,
    currentRound: 1
}
function setText(el, value) {
    el.innerText = value;
}

async function getData() {
    const data = await fetchApi('/api/retro/engine/candy/info')

    if (!data || !data.last_balance || !data.last_balance.amount) {
        alert("Você precisa iniciar um jogo")
        location.href = '/games/candy'
        return
    }

    bet = data.last_balance.amount
    rounds['1'].meta_multiplier = data.settings.meta_multiplier
    rounds['1'].timer = data.settings.timer
    rounds['1'].coinRate = Number(data.settings.coin_rate) * Number(bet)

    document.querySelector("#tutorial").innerText = `Você tem ${data.settings.timer} segundos para fazer ${data.settings.meta_multiplier * bet} reais.`
    document.querySelector("#startGame").style.display = 'block'
}
window.addEventListener('load', (event) => {
    getData()
})

const els = {
    currentRound: () => {
        return document.querySelector(`#round-${stateGame.currentRound}`);
    },
    currentMeta: () => {
        return document.querySelector(`#round-${stateGame.currentRound} .currentMeta`);
    },
    currentTimer: () => {
        return document.querySelector(`#round-${stateGame.currentRound} .currentTimer`);
    }
}
function extTriggerPoints(currentPoints) {
    if (!stateGame.play) {
        return
    }
    localStorage.setItem("currentPoints", currentPoints)
    if (currentPoints >= rounds[stateGame.currentRound].meta_multiplier * bet) {
        execGreen();
    }
}

function execGreen() {
    document.querySelector("#finishGame").style.display = 'block'
}

function execRed() {
    if (stateGame.play) {
        // Protegido: o botao pode ja ter sido removido pelo encerramento
        // manual, e a excecao impediria o loseGame de rodar — rodada que
        // nao fecha nao debita na aposta seguinte.
        var _fg = document.querySelector("#finishGame");
        if (_fg) { _fg.remove(); }
        stateGame.play = false;
        loseGame(0, bet)
        alert("Seu tempo acabou, você perdeu!")
    }
}
function loadGame() {
    var currentRound = els.currentRound();
    var currentMeta = els.currentMeta();
    var currentTimer = els.currentTimer();

    currentRound.style.display = '';
    setText(currentMeta, "R$ " + rounds[stateGame.currentRound].meta_multiplier * bet);
    startTimer(currentTimer, rounds[stateGame.currentRound].timer);
}

window.addEventListener('load', (event) => {
    var container = document.querySelector('#containerFormBet');
    var btnStart = document.querySelector('#startGame');
    btnStart.addEventListener('click', () => {
        hideDiv(container);
        loadGame();
    });
});

window.addEventListener("blur", function () {
    document.getElementById('focusHelper').style['display'] = "block"
})
window.addEventListener("focus", function () {
    document.getElementById('focusHelper').style['display'] = "none"
})

window.addEventListener('load', (event) => {
    document.querySelector("#finishGame").addEventListener('click', async () => {
        document.querySelector("#finishGame").remove()
        const winAmount = Number(localStorage.getItem("currentPoints"))
        winGame(winAmount)
    })
})