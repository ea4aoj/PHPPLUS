<?php
// esp32.php - Programador ESP32 via Flash Agent (Mac)
// No requiere Web Serial API — usa agente Python en el Mac

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🔧 ESP32 Flash Tool</title>
  <style>
    :root {
      --primary: #2196F3; --success: #4CAF50; --error: #f44336;
      --warning: #FF9800; --info: #9C27B0; --bg: #1e1e2e;
      --card: #2a2a3e; --text: #e0e0e0; --mono: 'Fira Code', monospace;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: var(--bg); color: var(--text); line-height: 1.6;
      padding: 20px; max-width: 1000px; margin: 0 auto;
    }
    header { text-align: center; padding: 20px 0; border-bottom: 1px solid #444; margin-bottom: 25px; }
    header h1 { font-size: 1.9rem; margin-bottom: 5px; }
    header p { color: #aaa; }

    .card { background: var(--card); border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .card h3 { margin-bottom: 15px; color: var(--primary); display: flex; align-items: center; gap: 8px; }

    .status { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #333; border-radius: 8px; margin-bottom: 15px; font-family: var(--mono); font-size: 0.9rem; }
    .status.info    { border-left: 4px solid var(--primary); }
    .status.success { border-left: 4px solid var(--success); }
    .status.error   { border-left: 4px solid var(--error); }
    .status.warning { border-left: 4px solid var(--warning); }

    .btn { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; font-weight: 500; }
    .btn:hover         { background: #1976D2; transform: translateY(-1px); }
    .btn:disabled      { background: #555; cursor: not-allowed; transform: none; opacity: 0.7; }
    .btn.danger        { background: var(--error); }
    .btn.danger:hover  { background: #d32f2f; }
    .btn.success       { background: var(--success); }
    .btn.success:hover { background: #388e3c; }
    .btn.warning       { background: var(--warning); color: #111; }
    .btn.warning:hover { background: #F57C00; }
    .btn.info          { background: var(--info); }

    .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin: 15px 0; }

    .partition-row {
      display: grid; grid-template-columns: 110px 1fr 90px;
      gap: 10px; align-items: center; padding: 12px;
      background: #252538; border-radius: 8px; margin-bottom: 10px;
    }
    .partition-row label   { font-size: 0.9rem; font-weight: 500; color: #ccc; }
    .partition-row .offset { font-family: var(--mono); color: var(--warning); font-size: 0.85rem; }
    .partition-row .size   { font-size: 0.8rem; color: #888; }

    #log {
      background: #111; border: 1px solid #444; border-radius: 8px; padding: 15px;
      font-family: var(--mono); font-size: 0.85rem; max-height: 400px; overflow-y: auto;
      white-space: pre-wrap; word-break: break-all;
    }
    #log .timestamp { color: #888; margin-right: 8px; }
    #log .error   { color: var(--error); }
    #log .success { color: var(--success); }
    #log .warning { color: var(--warning); }
    #log .info    { color: var(--primary); }

    .progress-container { margin: 15px 0; }
    .progress-bar { background: #333; border-radius: 4px; overflow: hidden; height: 24px; }
    .progress-fill {
      height: 100%; background: linear-gradient(90deg, var(--success), #66BB6A);
      transition: width 0.5s; width: 0%; display: flex;
      align-items: center; justify-content: center;
      color: white; font-size: 0.8rem; font-weight: 500;
    }
    .progress-label { font-size: 0.85rem; color: #aaa; margin-top: 5px; }

    .agent-config {
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;
    }
    .agent-config input {
      background: #222; color: var(--text); border: 1px solid #555;
      border-radius: 6px; padding: 8px 12px; font-family: var(--mono);
      font-size: 0.9rem; flex: 1; min-width: 200px;
    }
    .agent-dot { width: 12px; height: 12px; border-radius: 50%; background: #666; flex-shrink: 0; }
    .agent-dot.online  { background: var(--success); box-shadow: 0 0 6px var(--success); }
    .agent-dot.offline { background: var(--error); }

    .divider { height: 1px; background: #444; margin: 20px 0; }

    details { margin: 10px 0; }
    summary { cursor: pointer; padding: 8px 0; color: var(--primary); font-weight: 500; }

    footer { text-align: center; padding: 20px; color: #777; font-size: 0.85rem; border-top: 1px solid #444; margin-top: 30px; }

    @media (max-width: 700px) {
      .partition-row { grid-template-columns: 1fr; }
      .btn-group { flex-direction: column; }
      .btn { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

  <header>
    <h1>🔧 ESP32 Flash Tool</h1>
    <p>Programador via Flash Agent — funciona en cualquier navegador</p>
  </header>

  <main>

    <!-- Configuración del agente -->
    <div class="card">
      <h3>🖥️ Flash Agent (Mac)</h3>

      <div class="agent-config">
        <span class="agent-dot" id="agentDot"></span>
        <input type="text" id="agentUrl" value="http://192.168.1.14:5555" placeholder="http://IP-Mac:5555">
        <button class="btn" id="btnCheck" onclick="checkAgent()">🔍 Verificar</button>
      </div>

      <div id="connectionStatus" class="status info">
        <span id="statusText">Pulsa "🔍 Verificar" para comprobar el agente</span>
      </div>

      <div id="portInfo" style="font-size:0.85rem;color:#aaa;margin-top:5px;font-family:var(--mono)"></div>
    </div>

    <!-- Particiones -->
    <div class="card">
      <h3>📦 Archivos a Programar</h3>

      <div class="partition-row" id="row-bootloader">
        <label>🔹 Bootloader</label>
        <input type="file" id="file-bootloader" accept=".bin" onchange="fileSelected('bootloader')">
        <span class="offset">0x1000</span>
      </div>
      <div class="partition-row" id="row-partitions">
        <label>🗂️ Partitions</label>
        <input type="file" id="file-partitions" accept=".bin" onchange="fileSelected('partitions')">
        <span class="offset">0x8000</span>
      </div>
      <div class="partition-row" id="row-firmware">
        <label>🚀 Firmware</label>
        <input type="file" id="file-firmware" accept=".bin" onchange="fileSelected('firmware')">
        <span class="offset">0x10000</span>
      </div>
      <div class="partition-row" id="row-littlefs">
        <label>📁 LittleFS</label>
        <input type="file" id="file-littlefs" accept=".bin" onchange="fileSelected('littlefs')">
        <span class="offset">0x290000</span>
      </div>

      <div class="divider"></div>

      <div class="btn-group">
        <button id="btnErase" class="btn danger" onclick="eraseFlash()">🗑️ Borrar Flash</button>
        <button id="btnFlash" class="btn success" onclick="flashDevice()" disabled>🚀 Programar</button>
      </div>

      <div id="progressContainer" class="progress-container" style="display:none">
        <div class="progress-bar">
          <div id="progressFill" class="progress-fill">0%</div>
        </div>
        <div id="progressLabel" class="progress-label">Esperando...</div>
      </div>
    </div>

    <!-- Consola -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3>📋 Consola</h3>
        <div style="display:flex;gap:8px">
          <button class="btn" style="padding:6px 12px;font-size:0.85rem" onclick="clearLog()">🗑️ Limpiar</button>
          <button class="btn" style="padding:6px 12px;font-size:0.85rem" onclick="downloadLog()">💾 Guardar</button>
        </div>
      </div>
      <div id="log"></div>
    </div>

  </main>

  <footer>
    <p>🔧 ESP32 Flash Tool • Flash Agent en Mac • esptool.py</p>
    <p>🔗 <?php echo htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']); ?></p>
  </footer>

  <script>
    let agentOnline  = false;
    let logBuffer    = [];
    let selectedFiles = {};

    const ts = () => `[${new Date().toLocaleTimeString('es-ES')}]`;

    function log(msg, type = 'info') {
      if (!msg || !msg.toString().trim()) return;
      const el    = document.getElementById('log');
      const line  = document.createElement('div');
      const clean = msg.toString().replace(/</g,'&lt;').replace(/>/g,'&gt;');
      line.innerHTML = `<span class="timestamp">${ts()}</span><span class="${type}">${clean}</span>`;
      el.appendChild(line);
      logBuffer.push(`${ts()} [${type}] ${msg}`);
      if (el.children.length > 800) el.removeChild(el.firstChild);
      el.scrollTop = el.scrollHeight;
    }

    function updateStatus(text, type = 'info') {
      document.getElementById('statusText').textContent = text;
      document.getElementById('connectionStatus').className = `status ${type}`;
    }

    function getAgentUrl() {
      return document.getElementById('agentUrl').value.trim().replace(/\/$/, '');
    }

    // ================================
    // 🔍 VERIFICAR AGENTE
    // ================================
    async function checkAgent() {
      const url = getAgentUrl();
      const dot = document.getElementById('agentDot');
      const portInfo = document.getElementById('portInfo');

      updateStatus('⏳ Verificando agente...', 'warning');
      dot.className = 'agent-dot';

      try {
        const res  = await fetch(`${url}/status`, { signal: AbortSignal.timeout(4000) });
        const data = await res.json();

        if (data.ok) {
          agentOnline = true;
          dot.className = 'agent-dot online';
          if (data.detected) {
            updateStatus(`✅ Agente online — ESP32 detectado en ${data.port}`, 'success');
            portInfo.textContent = `Puerto: ${data.port}`;
            log(`✅ Agente online. ESP32 en ${data.port}`, 'success');
          } else {
            updateStatus('⚠️ Agente online pero ESP32 no detectado — conecta el USB', 'warning');
            portInfo.textContent = 'ESP32 no detectado';
            log('⚠️ Agente online pero sin ESP32. Conecta el cable USB.', 'warning');
          }
          updateFlashButton();
        } else {
          throw new Error('Agente respondió con error');
        }
      } catch(e) {
        agentOnline = false;
        dot.className = 'agent-dot offline';
        updateStatus('❌ Agente no disponible — ¿está corriendo flash_agent.py en el Mac?', 'error');
        portInfo.textContent = '';
        log(`❌ No se puede conectar a ${url}`, 'error');
        log('💡 Ejecuta en el Mac: python3 ~/flash_agent.py', 'warning');
        document.getElementById('btnFlash').disabled = true;
      }
    }

    // ================================
    // 📄 ARCHIVOS SELECCIONADOS
    // ================================
    function fileSelected(name) {
      const input = document.getElementById(`file-${name}`);
      const file  = input.files[0];
      if (!file) { delete selectedFiles[name]; } 
      else {
        selectedFiles[name] = file;
        const kb = (file.size / 1024).toFixed(1);
        log(`📄 ${name}: ${file.name} (${kb} KB)`, 'info');
      }
      updateFlashButton();
    }

    function updateFlashButton() {
      const hasFiles = Object.keys(selectedFiles).length > 0;
      document.getElementById('btnFlash').disabled = !agentOnline || !hasFiles;
    }

    // ================================
    // 🗑️ BORRAR FLASH
    // ================================
    async function eraseFlash() {
      if (!agentOnline) { log('❌ Verifica el agente primero', 'error'); return; }
      if (!confirm('⚠️ ¿Borrar TODO el flash del ESP32?\n\nSe perderán firmware, particiones y datos.')) return;

      const url = getAgentUrl();
      document.getElementById('btnErase').disabled = true;
      document.getElementById('progressContainer').style.display = 'block';
      document.getElementById('progressLabel').textContent = 'Borrando flash... (30-60s)';

      // Animación indeterminada
      let pct = 0;
      const anim = setInterval(() => {
        pct = pct >= 90 ? 10 : pct + 2;
        document.getElementById('progressFill').style.width  = `${pct}%`;
        document.getElementById('progressFill').textContent  = `${pct}%`;
      }, 800);

      log('🗑️ Borrando flash completo...', 'warning');
      updateStatus('🗑️ Borrando flash...', 'warning');

      try {
        const res  = await fetch(`${url}/erase`, { method: 'POST', signal: AbortSignal.timeout(120000) });
        const data = await res.json();

        clearInterval(anim);
        // Mostrar output línea a línea
        data.output.split('\n').forEach(l => { if (l.trim()) log(l); });

        if (data.ok) {
          document.getElementById('progressFill').style.width = '100%';
          document.getElementById('progressFill').textContent = '100%';
          log('✅ Flash borrado correctamente', 'success');
          updateStatus('✅ Flash vacío — listo para programar', 'success');
        } else {
          log('❌ Error en borrado', 'error');
          updateStatus('❌ Error en borrado', 'error');
        }
      } catch(e) {
        clearInterval(anim);
        log(`❌ Error: ${e.message}`, 'error');
        updateStatus('❌ Error en borrado', 'error');
      } finally {
        document.getElementById('btnErase').disabled = false;
        setTimeout(() => {
          document.getElementById('progressContainer').style.display = 'none';
          document.getElementById('progressFill').style.width = '0%';
          document.getElementById('progressFill').textContent = '0%';
        }, 2000);
      }
    }

    // ================================
    // 🚀 PROGRAMAR
    // ================================
    async function flashDevice() {
      if (!agentOnline) { log('❌ Verifica el agente primero', 'error'); return; }
      const files = Object.entries(selectedFiles);
      if (!files.length) { log('⚠️ Selecciona al menos un archivo', 'warning'); return; }

      const url      = getAgentUrl();
      const formData = new FormData();

      for (const [name, file] of files) {
        formData.append(name, file);
        log(`📦 Añadiendo ${name}: ${file.name}`, 'info');
      }

      document.getElementById('btnFlash').disabled = true;
      document.getElementById('btnErase').disabled = true;
      document.getElementById('progressContainer').style.display = 'block';

      // Animación indeterminada mientras esptool trabaja
      let pct = 0;
      const anim = setInterval(() => {
        pct = pct >= 95 ? 10 : pct + 1;
        document.getElementById('progressFill').style.width  = `${pct}%`;
        document.getElementById('progressFill').textContent  = `${pct}%`;
        document.getElementById('progressLabel').textContent = 'Programando... (puede tardar varios minutos)';
      }, 1000);

      log('🚀 Enviando archivos al agente...', 'info');
      updateStatus('🚀 Programando ESP32...', 'warning');

      try {
        const res  = await fetch(`${url}/flash`, {
          method: 'POST',
          body:   formData,
          signal: AbortSignal.timeout(290000), // 5 minutos
        });
        const data = await res.json();

        clearInterval(anim);

        // Mostrar output de esptool línea a línea
        data.output.split('\n').forEach(l => {
          if (!l.trim()) return;
          const type = l.includes('error') || l.includes('Error') ? 'error'
                     : l.includes('Wrote') || l.includes('Hash') || l.includes('Leaving') ? 'success'
                     : l.includes('Warning') ? 'warning' : 'info';
          log(l, type);
        });

        if (data.ok) {
          document.getElementById('progressFill').style.width  = '100%';
          document.getElementById('progressFill').textContent  = '100%';
          document.getElementById('progressLabel').textContent = '¡Completado!';
          log('✅ ¡Programación completada! El ESP32 ejecuta el nuevo firmware.', 'success');
          updateStatus('✅ ESP32 programado correctamente', 'success');
        } else {
          log('❌ Error en programación', 'error');
          updateStatus('❌ Error en programación', 'error');
        }

      } catch(e) {
        clearInterval(anim);
        log(`❌ Error: ${e.message}`, 'error');
        updateStatus('❌ Error en programación', 'error');
      } finally {
        document.getElementById('btnErase').disabled = false;
        updateFlashButton();
        setTimeout(() => {
          document.getElementById('progressContainer').style.display = 'none';
          document.getElementById('progressFill').style.width = '0%';
          document.getElementById('progressFill').textContent = '0%';
          document.getElementById('progressLabel').textContent = '';
        }, 3000);
      }
    }

    function clearLog() {
      document.getElementById('log').innerHTML = '';
      logBuffer = [];
      log('🗑️ Consola limpiada');
    }

    function downloadLog() {
      if (!logBuffer.length) { log('⚠️ Sin logs', 'warning'); return; }
      const blob = new Blob([logBuffer.join('\n')], { type: 'text/plain' });
      const a    = document.createElement('a');
      a.href     = URL.createObjectURL(blob);
      a.download = `esp32_flash_${new Date().toISOString().slice(0,10)}.log`;
      a.click();
      URL.revokeObjectURL(a.href);
    }

    // Al cargar, verificar el agente automáticamente
    window.addEventListener('load', () => {
      log('🚀 ESP32 Flash Tool cargado');
      log('💡 Asegúrate de que flash_agent.py está corriendo en el Mac');
      checkAgent();
    });
  </script>
</body>
</html>
