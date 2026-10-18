@extends('admin.layouts.app')

@section('title', 'Map & Audio')
@section('body_class', 'map-audio-mode')

@section('content')
<link href="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.css" rel="stylesheet">
<style>
  .ma-root {
    position: relative;
    flex: 1;
    min-height: 0;
    width: 100%;
    background: #0b1220;
  }
  #ma-map { position: absolute; inset: 0; width: 100%; height: 100%; }
  /* Leave room on the right for Mapbox zoom / fullscreen controls */
  .ma-toolbar {
    position: absolute; z-index: 3; top: 12px; left: 12px;
    right: 58px;
    display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center;
    pointer-events: none;
  }
  .ma-toolbar > * { pointer-events: auto; }
  .ma-toolbar button, .ma-toolbar select, .ma-toolbar a.ma-back {
    border: 1px solid rgba(148,163,184,.45);
    background: rgba(15,23,42,.9);
    color: #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.45rem 0.7rem;
    font-size: 0.82rem;
    cursor: pointer;
    backdrop-filter: blur(8px);
    text-decoration: none;
    width: auto !important;
    flex: 0 0 auto;
  }
  .ma-toolbar select {
    width: auto !important;
    min-width: 7.5rem;
    max-width: 11rem;
  }
  .ma-toolbar a.ma-back:hover { color: #7dd3fc; border-color: #38bdf8; }
  .ma-toolbar button.active {
    background: rgba(14,165,233,.35);
    border-color: #38bdf8;
    color: #7dd3fc;
  }
  .ma-chip {
    background: rgba(15,23,42,.9);
    border: 1px solid rgba(148,163,184,.35);
    color: #94a3b8;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.78rem;
    width: auto !important;
  }
  /* Keep Mapbox nav controls visible above custom UI */
  .mapboxgl-ctrl-top-right { z-index: 5; }
  .mapboxgl-ctrl-bottom-left,
  .mapboxgl-ctrl-bottom-right { z-index: 5; }
  .ma-popup-device {
    min-width: 180px;
  }
  .ma-popup-device strong { display: block; margin-bottom: 0.25rem; }
  .ma-popup-device .meta { color: #94a3b8; font-size: 0.78rem; margin-bottom: 0.55rem; }
  .ma-popup-device button {
    width: 100%;
    border: 0;
    border-radius: 0.45rem;
    padding: 0.45rem 0.6rem;
    background: #0ea5e9;
    color: #042f4a;
    font-weight: 700;
    cursor: pointer;
  }
  .mapboxgl-popup-content {
    background: #1e293b; color: #e2e8f0; border-radius: 0.65rem;
    padding: 0.75rem 0.85rem; box-shadow: 0 10px 28px rgba(0,0,0,.4);
  }
  .mapboxgl-popup-anchor-bottom .mapboxgl-popup-tip { border-top-color: #1e293b; }
  .ma-marker {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.4);
    cursor: pointer;
  }
  .ma-marker.online { background: #34d399; }
  .ma-marker.offline { background: #94a3b8; }
  .ma-marker.selected {
    width: 20px; height: 20px;
    background: #f87171;
    box-shadow: 0 0 0 0 rgba(248,113,113,.55);
    animation: ma-pulse 1.5s ease-out infinite;
  }
  .ma-marker.listening {
    background: #38bdf8;
    box-shadow: 0 0 0 0 rgba(56,189,248,.55);
    animation: ma-pulse 1.5s ease-out infinite;
  }
  @keyframes ma-pulse {
    0% { box-shadow: 0 0 0 0 rgba(248,113,113,.55); }
    70% { box-shadow: 0 0 0 14px rgba(248,113,113,0); }
    100% { box-shadow: 0 0 0 0 rgba(248,113,113,0); }
  }
  .ma-audio-dock {
    position: absolute; z-index: 4; left: 12px; right: 12px; bottom: 12px;
    display: none;
    flex-direction: column;
    gap: 0.55rem;
    padding: 0.75rem 0.9rem;
    border-radius: 0.85rem;
    border: 1px solid rgba(148,163,184,.35);
    background: rgba(15,23,42,.92);
    backdrop-filter: blur(12px);
    box-shadow: 0 12px 40px rgba(0,0,0,.45);
  }
  .ma-audio-dock.open { display: flex; }
  .ma-audio-head {
    display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;
    justify-content: space-between;
  }
  .ma-audio-head .title { font-weight: 700; }
  .ma-audio-head .status { color: #94a3b8; font-size: 0.82rem; }
  .ma-audio-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
  .ma-audio-actions button {
    border: 1px solid rgba(148,163,184,.4);
    background: rgba(30,41,59,.95);
    color: #e2e8f0;
    border-radius: 0.45rem;
    padding: 0.4rem 0.7rem;
    font-size: 0.8rem;
    cursor: pointer;
  }
  .ma-audio-actions button.primary {
    background: #0ea5e9; color: #042f4a; border-color: transparent; font-weight: 700;
  }
  .ma-audio-actions button.danger { background: #b91c1c; border-color: transparent; }
  #ma-wave {
    width: 100%; height: 88px;
    border-radius: 0.55rem;
    background: #0b1220;
    border: 1px solid rgba(71,85,105,.8);
    display: block;
  }
  .ma-empty {
    position: absolute; z-index: 2; inset: 0;
    display: none; place-items: center; text-align: center;
    color: #94a3b8; padding: 2rem; pointer-events: none;
  }
  .ma-empty.show { display: grid; }
</style>

<div class="ma-root">
  <div id="ma-map"></div>
  <div class="ma-empty" id="ma-empty">No device locations yet. Enrolled devices appear here when they report GPS.</div>
  <div class="ma-toolbar">
    <a href="{{ route('admin.dashboard') }}" class="ma-back" title="Back to admin">← Admin</a>
    <button type="button" id="ma-fit-btn">Fit all</button>
    <button type="button" id="ma-3d-btn" class="active">3D</button>
    <select id="ma-style-select" title="Base map">
      <option value="mapbox">Mapbox</option>
      <option value="osm">OpenStreetMap</option>
    </select>
    <span class="ma-chip" id="ma-count">0 devices</span>
  </div>

  <div class="ma-audio-dock" id="ma-audio-dock">
    <div class="ma-audio-head">
      <div>
        <div class="title" id="ma-sel-name">Select a device</div>
        <div class="status" id="ma-audio-status">Click a marker, then start live listen.</div>
      </div>
      <div class="ma-audio-actions">
        <button type="button" class="primary" id="ma-listen-btn">Start live listen</button>
        <button type="button" id="ma-pause-btn" style="display:none;">Pause</button>
        <button type="button" class="danger" id="ma-stop-btn" style="display:none;">Stop</button>
        <button type="button" id="ma-close-sel-btn">Close</button>
      </div>
    </div>
    <canvas id="ma-wave" width="1200" height="88"></canvas>
    <audio id="ma-remote-audio" autoplay playsinline style="display:none;"></audio>
  </div>
</div>

<script src="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.js"></script>
<script>
(() => {
  const fleetUrl = @json(route('admin.map-audio.fleet-pulse'));
  const listenUrlTpl = @json(url('/admin/devices'));
  const cancelUrlTpl = @json(url('/admin/live-sessions'));
  const csrf = @json(csrf_token());
  const mapboxToken = @json($mapboxToken);
  const mapboxStyle = @json($mapboxStyle);
  let devices = @json($devicesJson);

  const audioEl = document.getElementById('ma-remote-audio');
  const audioStatus = document.getElementById('ma-audio-status');
  const selName = document.getElementById('ma-sel-name');
  const dock = document.getElementById('ma-audio-dock');
  const waveCanvas = document.getElementById('ma-wave');
  const waveCtx = waveCanvas.getContext('2d');
  const listenBtn = document.getElementById('ma-listen-btn');
  const pauseBtn = document.getElementById('ma-pause-btn');
  const stopBtn = document.getElementById('ma-stop-btn');
  const closeSelBtn = document.getElementById('ma-close-sel-btn');
  const countEl = document.getElementById('ma-count');
  const emptyEl = document.getElementById('ma-empty');

  let map = null;
  let mapReady = false;
  let map3d = true;
  let currentStyleKey = 'mapbox';
  let selectedUuid = null;
  let listeningUuid = null;
  let markers = new Map();
  let popup = null;

  let pc = null;
  let boundSession = null;
  let appliedAnswer = false;
  let appliedOfferSdp = null;
  const remoteStream = new MediaStream();
  const appliedDeviceIce = new Set();
  let listening = false;
  let paused = false;
  let stopping = false;
  let audioCtx = null;
  let analyser = null;
  let waveRaf = null;
  let mediaRecorder = null;
  let recordedChunks = [];

  const emptyLineGeo = { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: [] } };
  const emptyFcGeo = { type: 'FeatureCollection', features: [] };

  const osmStyle = {
    version: 8,
    name: 'OpenStreetMap',
    projection: { name: 'mercator' },
    sources: {
      osm: {
        type: 'raster',
        tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
        tileSize: 256,
        attribution: '© OpenStreetMap',
        maxzoom: 19,
      },
    },
    layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
  };

  function isOsmStyle(key) { return key === 'osm'; }
  function resolveMapStyle(key) {
    if (isOsmStyle(key)) return osmStyle;
    return mapboxStyle || 'mapbox://styles/mapbox/standard';
  }

  function normalizeSdp(sdp) {
    if (!sdp) return sdp;
    let text = String(sdp)
      .replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n')
      .replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    return text.split('\n').filter((line) => {
      const t = line.trim();
      if (!t) return true;
      if (/^a=ssrc:\d+\s+msid:/i.test(t)) return false;
      if (/^a=ssrc:\d+\s+mslabel:/i.test(t)) return false;
      if (/^a=ssrc:\d+\s+label:/i.test(t)) return false;
      return true;
    }).join('\r\n') + '\r\n';
  }

  function drawIdleWave() {
    const w = waveCanvas.width, h = waveCanvas.height;
    waveCtx.fillStyle = '#0b1220';
    waveCtx.fillRect(0, 0, w, h);
    waveCtx.strokeStyle = '#334155';
    waveCtx.beginPath();
    waveCtx.moveTo(0, h / 2);
    waveCtx.lineTo(w, h / 2);
    waveCtx.stroke();
  }

  function startWaveform() {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    if (audioCtx.state === 'suspended') audioCtx.resume();
    analyser = audioCtx.createAnalyser();
    analyser.fftSize = 2048;
    const source = audioCtx.createMediaStreamSource(remoteStream);
    source.connect(analyser);
    const data = new Uint8Array(analyser.fftSize);
    const paint = () => {
      waveRaf = requestAnimationFrame(paint);
      if (paused) return;
      analyser.getByteTimeDomainData(data);
      const w = waveCanvas.width, h = waveCanvas.height;
      waveCtx.fillStyle = '#0b1220';
      waveCtx.fillRect(0, 0, w, h);
      waveCtx.lineWidth = 2;
      waveCtx.strokeStyle = '#38bdf8';
      waveCtx.beginPath();
      const slice = w / data.length;
      for (let i = 0; i < data.length; i++) {
        const v = data[i] / 128.0;
        const y = (v * h) / 2;
        const x = i * slice;
        if (i === 0) waveCtx.moveTo(x, y);
        else waveCtx.lineTo(x, y);
      }
      waveCtx.stroke();
    };
    if (waveRaf) cancelAnimationFrame(waveRaf);
    paint();
  }

  function stopWaveform() {
    if (waveRaf) cancelAnimationFrame(waveRaf);
    waveRaf = null;
    drawIdleWave();
  }

  function onLiveAudioReady() {
    listening = true;
    pauseBtn.style.display = '';
    stopBtn.style.display = '';
    listenBtn.style.display = 'none';
    audioStatus.textContent = 'Live audio connected';
    startWaveform();
    try {
      recordedChunks = [];
      mediaRecorder = new MediaRecorder(remoteStream);
      mediaRecorder.ondataavailable = (e) => { if (e.data.size) recordedChunks.push(e.data); };
      mediaRecorder.start(1000);
    } catch (_) {}
    refreshMarkers();
  }

  function elMarker(device) {
    const el = document.createElement('div');
    el.className = 'ma-marker ' + (device.online ? 'online' : 'offline');
    if (device.uuid === selectedUuid) el.classList.add('selected');
    if (device.uuid === listeningUuid) el.classList.add('listening');
    el.title = device.name;
    el.addEventListener('click', (e) => {
      e.stopPropagation();
      selectDevice(device.uuid);
    });
    return el;
  }

  function selectDevice(uuid) {
    selectedUuid = uuid;
    const d = devices.find((x) => x.uuid === uuid);
    if (!d) return;
    dock.classList.add('open');
    selName.textContent = d.name;
    if (!listening || listeningUuid !== uuid) {
      audioStatus.textContent = d.online
        ? (d.location ? 'Ready — start live listen' : 'No location yet — you can still listen')
        : 'Device offline — listen may wait for phone';
      listenBtn.style.display = '';
      pauseBtn.style.display = 'none';
      stopBtn.style.display = listening && listeningUuid === uuid ? '' : 'none';
    }
    refreshMarkers();
    if (d.location && map) {
      map.easeTo({
        center: [d.location.longitude, d.location.latitude],
        zoom: Math.max(map.getZoom(), 14),
        pitch: map3d ? Math.max(map.getPitch(), 50) : 0,
        duration: 600,
      });
    }
    if (popup) { try { popup.remove(); } catch (_) {} }
    if (d.location && map) {
      const html = `<div class="ma-popup-device"><strong>${escapeHtml(d.name)}</strong>
        <div class="meta">${d.online ? 'Online' : 'Offline'}${d.battery_level != null ? ' · ' + d.battery_level + '%' : ''}</div>
        <button type="button" data-listen="${d.uuid}">Start live listen</button></div>`;
      popup = new mapboxgl.Popup({ offset: 16, closeButton: true })
        .setLngLat([d.location.longitude, d.location.latitude])
        .setHTML(html)
        .addTo(map);
      setTimeout(() => {
        document.querySelector(`[data-listen="${d.uuid}"]`)?.addEventListener('click', () => startListen(d.uuid));
      }, 0);
    }
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  function clearMarkers() {
    markers.forEach((m) => { try { m.remove(); } catch (_) {} });
    markers.clear();
  }

  function refreshMarkers() {
    if (!map || !mapReady) return;
    const withLoc = devices.filter((d) => d.location && Number.isFinite(d.location.longitude) && Number.isFinite(d.location.latitude));
    emptyEl.classList.toggle('show', withLoc.length === 0);
    countEl.textContent = `${devices.length} device${devices.length === 1 ? '' : 's'} · ${withLoc.length} on map`;

    const seen = new Set();
    withLoc.forEach((d) => {
      seen.add(d.uuid);
      const lngLat = [d.location.longitude, d.location.latitude];
      let marker = markers.get(d.uuid);
      if (!marker) {
        marker = new mapboxgl.Marker({ element: elMarker(d) }).setLngLat(lngLat).addTo(map);
        markers.set(d.uuid, marker);
      } else {
        marker.setLngLat(lngLat);
        const el = marker.getElement();
        el.className = 'ma-marker ' + (d.online ? 'online' : 'offline');
        if (d.uuid === selectedUuid) el.classList.add('selected');
        if (d.uuid === listeningUuid) el.classList.add('listening');
      }
    });
    [...markers.keys()].forEach((uuid) => {
      if (!seen.has(uuid)) {
        try { markers.get(uuid).remove(); } catch (_) {}
        markers.delete(uuid);
      }
    });
  }

  function fitAll() {
    if (!map || !mapReady) return;
    const coords = devices
      .filter((d) => d.location)
      .map((d) => [d.location.longitude, d.location.latitude]);
    if (!coords.length) return;
    if (coords.length === 1) {
      map.easeTo({ center: coords[0], zoom: 15, pitch: map3d ? 45 : 0, duration: 500 });
      return;
    }
    const bounds = coords.reduce((b, c) => b.extend(c), new mapboxgl.LngLatBounds(coords[0], coords[0]));
    map.fitBounds(bounds, { padding: 80, maxZoom: 15, pitch: map3d ? 40 : 0, duration: 700 });
  }

  function applyMapView() {
    if (!map) return;
    try {
      // Mercator fills the screen — Mapbox Standard defaults to globe (empty sky).
      if (typeof map.setProjection === 'function') map.setProjection('mercator');
    } catch (_) {}
    try { map.resize(); } catch (_) {}
  }

  function ensureMap() {
    if (map) return map;
    if (!isOsmStyle(currentStyleKey) && !mapboxToken) {
      document.getElementById('ma-map').innerHTML =
        '<div class="muted" style="display:grid;place-items:center;height:100%;padding:1rem;text-align:center;">Mapbox token missing — set MAPBOX_ACCESS_TOKEN or choose OpenStreetMap.</div>';
      return null;
    }
    if (mapboxToken) mapboxgl.accessToken = mapboxToken;
    map = new mapboxgl.Map({
      container: 'ma-map',
      style: resolveMapStyle(currentStyleKey),
      center: [38.75, 9.03],
      zoom: 11.5,
      pitch: map3d ? 42 : 0,
      bearing: map3d ? -18 : 0,
      antialias: true,
      projection: 'mercator',
    });
    map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'top-right');
    map.addControl(new mapboxgl.FullscreenControl(), 'top-right');
    map.on('load', () => {
      mapReady = true;
      applyMapView();
      refreshMarkers();
      fitAll();
      requestAnimationFrame(() => { applyMapView(); fitAll(); });
    });
    map.on('style.load', () => {
      mapReady = true;
      applyMapView();
      refreshMarkers();
    });
    window.addEventListener('resize', () => {
      try { map?.resize(); } catch (_) {}
    });
    return map;
  }

  function destroyMap() {
    clearMarkers();
    if (popup) { try { popup.remove(); } catch (_) {} popup = null; }
    if (map) { try { map.remove(); } catch (_) {} map = null; }
    mapReady = false;
    document.getElementById('ma-map').innerHTML = '';
  }

  async function ensurePeer(deviceUuid, session) {
    if (pc && boundSession === session.uuid) return pc;
    if (pc) { try { pc.close(); } catch (_) {} }
    appliedAnswer = false;
    appliedOfferSdp = null;
    appliedDeviceIce.clear();
    remoteStream.getTracks().forEach((t) => remoteStream.removeTrack(t));
    boundSession = session.uuid;
    pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
    pc.ontrack = (ev) => {
      const track = ev.track;
      if (track && !remoteStream.getTracks().some((t) => t.id === track.id)) {
        remoteStream.addTrack(track);
      }
      audioEl.srcObject = remoteStream;
      audioEl.play().catch(() => {});
      onLiveAudioReady();
    };
    pc.onicecandidate = async (ev) => {
      if (!ev.candidate) return;
      await fetch(`${listenUrlTpl}/${deviceUuid}/live-sessions/${session.uuid}/ice`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ candidate: ev.candidate.toJSON() }),
      });
    };
    return pc;
  }

  async function handleSession(deviceUuid, session) {
    if (stopping || listeningUuid !== deviceUuid) return;
    if (!session || !session.request_microphone) return;
    if (session.status === 'pending') {
      audioStatus.textContent = 'Waiting for device…';
      return;
    }
    if (!session.device_offer_sdp) {
      audioStatus.textContent = 'Waiting for phone mic… (CDM notification on; unlock once if stuck)';
      return;
    }
    try {
      const peer = await ensurePeer(deviceUuid, session);
      const offerSdp = normalizeSdp(session.device_offer_sdp);
      if (!appliedAnswer || appliedOfferSdp !== offerSdp) {
        audioStatus.textContent = 'Got device stream — answering…';
        await peer.setRemoteDescription({ type: 'offer', sdp: offerSdp });
        const answer = await peer.createAnswer();
        await peer.setLocalDescription(answer);
        const res = await fetch(`${listenUrlTpl}/${deviceUuid}/live-sessions/${session.uuid}/answer`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
          body: JSON.stringify({ answer_sdp: normalizeSdp(answer.sdp), ice: [] }),
        });
        if (!res.ok) {
          appliedAnswer = false;
          appliedOfferSdp = null;
          audioStatus.textContent = `Answer failed (${res.status})`;
          return;
        }
        appliedAnswer = true;
        appliedOfferSdp = offerSdp;
        audioStatus.textContent = 'Connecting audio…';
      }
      for (const c of (session.device_ice || [])) {
        const key = JSON.stringify(c);
        if (appliedDeviceIce.has(key)) continue;
        appliedDeviceIce.add(key);
        try { await peer.addIceCandidate(c); } catch (_) {}
      }
    } catch (e) {
      appliedAnswer = false;
      audioStatus.textContent = 'Listen error: ' + (e && e.message ? e.message : e);
    }
  }

  async function startListen(uuid) {
    selectDevice(uuid);
    const d = devices.find((x) => x.uuid === uuid);
    if (!d) return;
    if (listening && listeningUuid && listeningUuid !== uuid) {
      await stopListen(false);
    }
    stopping = false;
    listeningUuid = uuid;
    audioStatus.textContent = 'Starting live listen…';
    listenBtn.disabled = true;
    try {
      const res = await fetch(`${listenUrlTpl}/${uuid}/listen-audio`, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) {
        audioStatus.textContent = json.message || `Listen request failed (${res.status})`;
        listeningUuid = null;
        return;
      }
      audioStatus.textContent = json.data?.message || 'Waiting for phone mic…';
      if (json.data?.session) await handleSession(uuid, json.data.session);
      refreshMarkers();
    } catch (e) {
      audioStatus.textContent = String(e);
      listeningUuid = null;
    } finally {
      listenBtn.disabled = false;
    }
  }

  async function stopListen(updateUi = true) {
    stopping = true;
    listening = false;
    paused = false;
    stopWaveform();
    try { mediaRecorder?.stop(); } catch (_) {}
    mediaRecorder = null;
    if (pc) { try { pc.close(); } catch (_) {} pc = null; }
    boundSession = null;
    appliedAnswer = false;
    appliedOfferSdp = null;
    appliedDeviceIce.clear();
    remoteStream.getTracks().forEach((t) => remoteStream.removeTrack(t));
    audioEl.srcObject = null;

    const d = devices.find((x) => x.uuid === listeningUuid);
    const sessionUuid = d?.session?.uuid;
    if (sessionUuid) {
      try {
        await fetch(`${cancelUrlTpl}/${sessionUuid}/cancel`, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        });
      } catch (_) {}
    }
    listeningUuid = null;
    if (updateUi) {
      audioStatus.textContent = 'Stopped';
      listenBtn.style.display = '';
      pauseBtn.style.display = 'none';
      stopBtn.style.display = 'none';
      pauseBtn.textContent = 'Pause';
    }
    stopping = false;
    refreshMarkers();
  }

  listenBtn.addEventListener('click', () => {
    if (selectedUuid) startListen(selectedUuid);
  });
  pauseBtn.addEventListener('click', () => {
    paused = !paused;
    pauseBtn.textContent = paused ? 'Resume' : 'Pause';
    if (paused) audioEl.pause();
    else audioEl.play().catch(() => {});
  });
  stopBtn.addEventListener('click', () => stopListen(true));
  closeSelBtn.addEventListener('click', () => {
    if (listening) stopListen(true);
    selectedUuid = null;
    dock.classList.remove('open');
    if (popup) { try { popup.remove(); } catch (_) {} popup = null; }
    refreshMarkers();
  });

  document.getElementById('ma-fit-btn').addEventListener('click', fitAll);
  document.getElementById('ma-3d-btn').addEventListener('click', (e) => {
    map3d = !map3d;
    e.currentTarget.classList.toggle('active', map3d);
    if (!mapReady) return;
    applyMapView();
    map.easeTo({ pitch: map3d ? 42 : 0, bearing: map3d ? -18 : 0, duration: 500 });
  });
  document.getElementById('ma-style-select').addEventListener('change', (e) => {
    currentStyleKey = e.target.value;
    if (isOsmStyle(currentStyleKey) && !map3d) map3d = true;
    destroyMap();
    ensureMap();
  });

  async function tick() {
    try {
      const res = await fetch(fleetUrl, { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const json = await res.json();
      devices = Array.isArray(json.data) ? json.data : [];
      refreshMarkers();
      if (listeningUuid) {
        const d = devices.find((x) => x.uuid === listeningUuid);
        if (d?.session) await handleSession(listeningUuid, d.session);
      }
    } catch (e) {
      console.warn(e);
    }
  }

  drawIdleWave();
  ensureMap();
  tick();
  setInterval(tick, 2000);
})();
</script>
@endsection
