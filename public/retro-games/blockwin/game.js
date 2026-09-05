/*
 * Block Win - puzzle de blocos 8x8.
 *
 * Motor original do projeto br2.abilitypay.app (resources/views/game.blade.php),
 * com os pontos de integracao trocados para a API de arcade do chinesa. A
 * mecanica, as formas, a pontuacao e a dificuldade sao as mesmas.
 */
function iniciarBlockWin(rodada){
  const SIZE = 8;
  const CELL = 46, GAP = 2;
  const TRAY_CELL = 26;

  /*
   * Tudo o que era injetado pelo Blade vem agora da rodada que o servidor
   * abriu. A aposta e a meta nao passam pelo cliente por escolha do jogador:
   * quem manda e o registro em arcade_rounds.
   */
  const ENTRADA = Number(window.bet);
  const META = Number(rodada.meta);
  const GAME_CONFIG = Arcade.settings || {};
  const TARGET_DIFFICULTY = String(GAME_CONFIG.difficulty || 'normal');
  const SCORE_MULTIPLIER = Number(GAME_CONFIG.score_multiplier || 1);
  const EASY_START_MOVES = Number(GAME_CONFIG.easy_start_moves || 4);
  const COLORS = ['#ef4444','#f97316','#eab308','#22c55e','#3b82f6','#a855f7','#ec4899','#06b6d4'];
  const SHAPES_EASY = [
    [[0,0]],
    [[0,0],[0,1]],
    [[0,0],[1,0]],
    [[0,0],[0,1],[0,2]],
    [[0,0],[1,0],[2,0]],
    [[0,0],[0,1],[1,0],[1,1]],
    [[0,0],[1,0],[1,1]],
    [[0,0],[0,1],[1,0]],
  ];
  const SHAPES_HARD = [
    [[0,0],[0,1],[0,2],[0,3]],
    [[0,0],[1,0],[2,0],[3,0]],
    [[0,0],[0,1],[0,2],[0,3],[0,4]],
    [[0,0],[1,0],[2,0],[3,0],[4,0]],
    [[0,0],[0,1],[0,2],[1,0],[1,1],[1,2],[2,0],[2,1],[2,2]],
    [[0,0],[1,0],[2,0],[2,1]],
    [[0,0],[1,0],[2,0],[0,1]],
    [[0,0],[0,1],[0,2],[1,0]],
    [[0,0],[0,1],[0,2],[1,2]],
    [[0,0],[0,1],[1,1],[2,1]],
    [[0,1],[1,1],[2,0],[2,1]],
    [[0,0],[0,1],[0,2],[1,1]],
    [[0,1],[1,0],[1,1],[1,2]],
    [[0,0],[1,0],[1,1],[2,0]],
    [[0,1],[1,0],[1,1],[2,1]],
    [[0,0],[0,1],[1,1],[1,2]],
    [[0,1],[0,2],[1,0],[1,1]],
    [[0,1],[1,0],[1,1],[1,2],[2,1]],
  ];
  const SHAPES = SHAPES_EASY.concat(SHAPES_HARD);

  let board = [];
  let boardCells = [];
  let tray = [null,null,null];
  let score = 0;
  let combo = 0;
  let dragState = null;
  let scoreSaved = false;
  let gameOver = false;
  let placements = 0;
  let cellSize = CELL;

  const boardEl = document.getElementById('board');
  const scoreMoneyEl = document.getElementById('scoreMoney');
  const overlayEl = document.getElementById('overlay');
  const finalMoneyEl = document.getElementById('finalMoney');
  const finalTitleEl = document.getElementById('finalTitle');
  const finalMessageEl = document.getElementById('finalMessage');
  const saveStatusEl = document.getElementById('saveStatus');

  function moneyFromScore(points) {
    return (points / 100) * ENTRADA * SCORE_MULTIPLIER;
  }

  function formatBRL(value) {
    return 'R$ ' + Number(value).toFixed(2).replace('.', ',');
  }

  function syncCellSize() {
    const styles = getComputedStyle(boardEl);
    const raw = styles.getPropertyValue('--cell').trim();
    const parsed = parseFloat(raw);
    if (!Number.isNaN(parsed)) cellSize = parsed;
  }

  function normalizeShape(cells){
    const minR = Math.min(...cells.map(c=>c[0]));
    const minC = Math.min(...cells.map(c=>c[1]));
    return cells.map(([r,c])=>[r-minR, c-minC]);
  }

  function buildBoard(){
    boardEl.innerHTML = '';
    board = [];
    boardCells = [];
    for(let r=0;r<SIZE;r++){
      board.push(new Array(SIZE).fill(null));
      const row = [];
      for(let c=0;c<SIZE;c++){
        const cell = document.createElement('div');
        cell.className = 'cell';
        boardEl.appendChild(cell);
        row.push(cell);
      }
      boardCells.push(row);
    }
    syncCellSize();
  }

  function renderCell(r,c){
    const cell = boardCells[r][c];
    const val = board[r][c];
    cell.classList.remove('preview-valid','preview-invalid');
    if(val){
      cell.style.background = val;
      cell.classList.add('filled');
    } else {
      cell.style.background = '';
      cell.classList.remove('filled');
    }
  }

  function renderBoard(){
    for(let r=0;r<SIZE;r++) for(let c=0;c<SIZE;c++) renderCell(r,c);
  }

  function difficultyParams(){
    // Início sempre fácil; depois aplica a dificuldade do admin
    if (placements < EASY_START_MOVES) {
      return { phase: 'easy', bigChance: 8, awkwardChance: 0, deadChance: 0 };
    }

    const base = {
      facil:   { bigChance: Number(GAME_CONFIG.big_piece_chance || 20), awkwardChance: Number(GAME_CONFIG.awkward_chance || 10), deadChance: Number(GAME_CONFIG.dead_chance || 0) },
      normal:  { bigChance: Number(GAME_CONFIG.big_piece_chance || 40), awkwardChance: Number(GAME_CONFIG.awkward_chance || 30), deadChance: Number(GAME_CONFIG.dead_chance || 8) },
      dificil: { bigChance: Number(GAME_CONFIG.big_piece_chance || 70), awkwardChance: Number(GAME_CONFIG.awkward_chance || 55), deadChance: Number(GAME_CONFIG.dead_chance || 22) },
      extremo: { bigChance: Number(GAME_CONFIG.big_piece_chance || 90), awkwardChance: Number(GAME_CONFIG.awkward_chance || 80), deadChance: Number(GAME_CONFIG.dead_chance || 40) },
    }[TARGET_DIFFICULTY] || {
      bigChance: 40, awkwardChance: 30, deadChance: 8
    };

    // Escala um pouco mais a cada jogada após o início fácil
    const extra = Math.min(25, Math.floor((placements - EASY_START_MOVES) * 3));
    return {
      phase: 'target',
      bigChance: Math.min(98, base.bigChance + extra),
      awkwardChance: Math.min(95, base.awkwardChance + extra),
      deadChance: Math.min(60, base.deadChance + Math.floor(extra / 2)),
    };
  }

  function countPlacements(cells){
    let count = 0;
    for(let r=0;r<SIZE;r++){
      for(let c=0;c<SIZE;c++){
        if(canPlace(cells, r, c)) count++;
      }
    }
    return count;
  }

  function sampleRawShape(params){
    let pool;
    if (params.phase === 'easy') {
      pool = SHAPES_EASY;
    } else if (Math.random() * 100 < params.awkwardChance) {
      pool = SHAPES_HARD;
    } else if (Math.random() * 100 < params.bigChance) {
      pool = SHAPES.filter(s => s.length >= 4);
      if (!pool.length) pool = SHAPES_HARD;
    } else {
      pool = SHAPES_EASY;
    }
    const raw = pool[Math.floor(Math.random() * pool.length)];
    return normalizeShape(raw).map(p => p.slice());
  }

  function randomShape(){
    const params = difficultyParams();
    const candidates = [];

    for (let i = 0; i < 14; i++) {
      const shape = sampleRawShape(params);
      candidates.push({ shape, places: countPlacements(shape) });
    }

    // Fase fácil: escolhe a peça mais "encaixável"
    if (params.phase === 'easy') {
      candidates.sort((a, b) => b.places - a.places || a.shape.length - b.shape.length);
      return (candidates.find(c => c.places > 0) || candidates[0]).shape;
    }

    // Fase difícil: pode entregar peça morta (sem encaixe)
    if (params.deadChance > 0 && Math.random() * 100 < params.deadChance) {
      const dead = candidates.filter(c => c.places === 0);
      if (dead.length) return dead[Math.floor(Math.random() * dead.length)].shape;
    }

    // Prefere peças com poucos encaixes (mais difíceis), mas jogáveis
    const playable = candidates.filter(c => c.places > 0).sort((a, b) => a.places - b.places || b.shape.length - a.shape.length);
    if (playable.length) {
      const hardSlice = playable.slice(0, Math.max(1, Math.ceil(playable.length * 0.4)));
      return hardSlice[Math.floor(Math.random() * hardSlice.length)].shape;
    }

    return candidates[0].shape;
  }

  function shapeSize(cells){
    const maxR = Math.max(...cells.map(c=>c[0]));
    const maxC = Math.max(...cells.map(c=>c[1]));
    return {rows: maxR+1, cols: maxC+1};
  }

  function createPieceElement(shape, color, size){
    size = size || TRAY_CELL;
    const {rows, cols} = shapeSize(shape.cells);
    const wrap = document.createElement('div');
    wrap.className = 'piece';
    wrap.style.width = (cols*size)+'px';
    wrap.style.height = (rows*size)+'px';
    shape.cells.forEach(([r,c])=>{
      const b = document.createElement('div');
      b.className = 'piece-cell';
      b.style.width = (size-3)+'px';
      b.style.height = (size-3)+'px';
      b.style.left = (c*size)+'px';
      b.style.top = (r*size)+'px';
      b.style.background = color;
      wrap.appendChild(b);
    });
    return wrap;
  }

  function fillTraySlot(i){
    const shape = { cells: randomShape() };
    const color = COLORS[Math.floor(Math.random()*COLORS.length)];
    const pieceData = { shape: shape.cells, color };
    tray[i] = pieceData;
    const slot = document.getElementById('slot-'+i);
    slot.innerHTML = '';
    const el = createPieceElement(shape, color);
    el.addEventListener('pointerdown', (e)=>onPointerDown(e, i, el));
    slot.appendChild(el);
    pieceData.el = el;
  }

  function traySlotsEmpty(){
    return tray.every(t=>t===null);
  }

  function refillTrayIfNeeded(){
    if(traySlotsEmpty()){
      for(let i=0;i<3;i++) fillTraySlot(i);
    }
  }

  function canPlace(cells, anchorR, anchorC){
    for(const [dr,dc] of cells){
      const r = anchorR+dr, c = anchorC+dc;
      if(r<0||r>=SIZE||c<0||c>=SIZE) return false;
      if(board[r][c]) return false;
    }
    return true;
  }

  function hasAnyMove(){
    for(const p of tray){
      if(!p) continue;
      for(let r=0;r<SIZE;r++){
        for(let c=0;c<SIZE;c++){
          if(canPlace(p.shape, r, c)) return true;
        }
      }
    }
    return false;
  }

  function placeShape(cells, anchorR, anchorC, color){
    cells.forEach(([dr,dc])=>{
      board[anchorR+dr][anchorC+dc] = color;
    });
    score += cells.length;
    checkLines();
    updateScore();
  }

  function checkLines(){
    const fullRows = [];
    const fullCols = [];
    for(let r=0;r<SIZE;r++){
      if(board[r].every(v=>v)) fullRows.push(r);
    }
    for(let c=0;c<SIZE;c++){
      let full = true;
      for(let r=0;r<SIZE;r++) if(!board[r][c]) { full=false; break; }
      if(full) fullCols.push(c);
    }
    const total = fullRows.length + fullCols.length;
    if(total>0){
      combo++;
      score += total*10*combo;
      const toClear = new Set();
      fullRows.forEach(r=>{ for(let c=0;c<SIZE;c++) toClear.add(r+','+c); });
      fullCols.forEach(c=>{ for(let r=0;r<SIZE;r++) toClear.add(r+','+c); });
      toClear.forEach(key=>{
        const [r,c] = key.split(',').map(Number);
        boardCells[r][c].style.opacity = '0.15';
      });
      setTimeout(()=>{
        toClear.forEach(key=>{
          const [r,c] = key.split(',').map(Number);
          board[r][c] = null;
          boardCells[r][c].style.opacity = '1';
          renderCell(r,c);
        });
      }, 160);
    } else {
      combo = 0;
    }
  }

  function updateScore(){
    const acumulado = moneyFromScore(score);
    scoreMoneyEl.textContent = formatBRL(acumulado);
    const bateu = acumulado >= META;
    scoreMoneyEl.style.color = bateu ? '#2dff6e' : '#ffe14a';
    scoreMoneyEl.style.textShadow = bateu
      ? '0 0 18px rgba(45,255,110,0.55)'
      : '0 3px 0 rgba(0,0,0,0.35)';
  }

  function clearPreview(){
    for(let r=0;r<SIZE;r++) for(let c=0;c<SIZE;c++){
      boardCells[r][c].classList.remove('preview-valid','preview-invalid');
    }
  }

  function showPreview(cells, anchorR, anchorC){
    clearPreview();
    const valid = canPlace(cells, anchorR, anchorC);
    cells.forEach(([dr,dc])=>{
      const r = anchorR+dr, c = anchorC+dc;
      if(r>=0&&r<SIZE&&c>=0&&c<SIZE){
        boardCells[r][c].classList.add(valid?'preview-valid':'preview-invalid');
      }
    });
    return valid;
  }

  function onPointerDown(e, slotIndex, pieceEl){
    e.preventDefault();
    if (gameOver) return;
    syncCellSize();
    const pieceData = tray[slotIndex];
    if(!pieceData) return;
    const rect = pieceEl.getBoundingClientRect();
    const grabC = Math.floor((e.clientX-rect.left)/TRAY_CELL);
    const grabR = Math.floor((e.clientY-rect.top)/TRAY_CELL);

    const ghost = createPieceElement({cells: pieceData.shape}, pieceData.color, cellSize);
    ghost.classList.add('ghost');
    document.body.appendChild(ghost);
    pieceEl.style.visibility = 'hidden';
    dragState = { slotIndex, pieceData, grabR, grabC, ghost };
    positionGhost(e.clientX, e.clientY);
    updatePreviewFromPointer(e.clientX, e.clientY);
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp);
  }

  function positionGhost(clientX, clientY){
    if(!dragState) return;
    const { ghost, grabR, grabC } = dragState;
    ghost.style.left = (clientX - grabC*cellSize - cellSize/2) + 'px';
    ghost.style.top = (clientY - grabR*cellSize - cellSize/2 - 40) + 'px';
  }

  function getAnchorFromPointer(clientX, clientY){
    const boardRect = boardEl.getBoundingClientRect();
    const padding = 8;
    const px = clientX - boardRect.left - padding;
    const py = clientY - boardRect.top - padding - 40;
    const col = Math.floor(px/(cellSize+GAP));
    const row = Math.floor(py/(cellSize+GAP));
    return {anchorR: row - dragState.grabR, anchorC: col - dragState.grabC};
  }

  function updatePreviewFromPointer(clientX, clientY){
    if(!dragState) return;
    const {anchorR, anchorC} = getAnchorFromPointer(clientX, clientY);
    dragState.lastAnchor = {anchorR, anchorC};
    showPreview(dragState.pieceData.shape, anchorR, anchorC);
  }

  function onPointerMove(e){
    positionGhost(e.clientX, e.clientY);
    updatePreviewFromPointer(e.clientX, e.clientY);
  }

  function onPointerUp(e){
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    if(!dragState) return;
    const {pieceData, slotIndex, ghost} = dragState;
    const {anchorR, anchorC} = dragState.lastAnchor || getAnchorFromPointer(e.clientX,e.clientY);
    clearPreview();
    ghost.remove();

    if(canPlace(pieceData.shape, anchorR, anchorC)){
      placeShape(pieceData.shape, anchorR, anchorC, pieceData.color);
      renderBoard();
      tray[slotIndex] = null;
      document.getElementById('slot-'+slotIndex).innerHTML = '';
      placements++;

      // Meta batida: finaliza a partida imediatamente
      if (moneyFromScore(score) >= META) {
        setTimeout(() => endGame(), 220);
        dragState = null;
        return;
      }

      refillTrayIfNeeded();
      setTimeout(()=>{
        if (gameOver) return;
        if(!hasAnyMove()) endGame();
      }, 200);
    } else {
      pieceData.el.style.visibility = 'visible';
    }
    dragState = null;
  }

  /*
   * Liquida a rodada no servidor. O valor enviado e uma reivindicacao: o
   * ArcadeController corta no teto gravado em arcade_rounds, entao mexer
   * nisto pelo navegador nao aumenta o pagamento.
   */
  function saveScoreToServer(){
    if (scoreSaved) return;
    scoreSaved = true;

    const acumulado = moneyFromScore(score);
    const bateuMeta = acumulado >= META && META > 0;

    saveStatusEl.textContent = 'Encerrando a rodada...';

    if (bateuMeta) {
      saveStatusEl.textContent = 'Prêmio creditado! Voltando...';
      Arcade.win(acumulado);
    } else {
      saveStatusEl.textContent = 'Acumulado: ' + formatBRL(acumulado) + ' · Meta: ' + formatBRL(META);
      Arcade.lose();
    }
  }

  function endGame(){
    if (gameOver) return;
    gameOver = true;

    const acumulado = moneyFromScore(score);
    const bateuMeta = acumulado >= META && META > 0;

    if (bateuMeta) {
      finalTitleEl.textContent = 'Meta batida!';
      finalTitleEl.style.color = '#2dff6e';
      finalMessageEl.innerHTML = 'Você ganhou: <strong id="finalMoney">' + formatBRL(acumulado) + '</strong>';
    } else {
      finalTitleEl.textContent = 'Meta não atingida';
      finalTitleEl.style.color = '#ef4444';
      finalMessageEl.innerHTML = 'Você perdeu a entrada de <strong>' + formatBRL(ENTRADA) + '</strong>';
    }

    overlayEl.classList.remove('hidden');
    saveScoreToServer();
  }

  /* HUD: entrada a esquerda, meta a direita. */
  const entradaEl = document.getElementById('hudEntrada');
  const metaEl = document.getElementById('hudMeta');
  const metaTextoEl = document.getElementById('metaTexto');
  if (entradaEl) entradaEl.textContent = formatBRL(ENTRADA);
  if (metaEl) metaEl.textContent = formatBRL(META);
  if (metaTextoEl) metaTextoEl.textContent = formatBRL(META);

  window.addEventListener('resize', syncCellSize);

  buildBoard();
  renderBoard();
  refillTrayIfNeeded();
  updateScore();
}

/*
 * Sem rodada aberta nao ha aposta nem meta, e o jogo rodaria com bet 0 - por
 * isso ele so e montado depois que o Arcade responde. O proprio load() manda o
 * jogador de volta para a tela de escolha quando nao existe rodada.
 */
Arcade.load().then(function (rodada) {
  if (rodada) { iniciarBlockWin(rodada); }
});
