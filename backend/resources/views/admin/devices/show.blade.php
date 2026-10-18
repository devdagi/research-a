@extends('admin.layouts.app')

@section('title', $device->name)

@section('content')
@php
    $loc = $device->latestLocation;
    $st = $device->latestStatus;
@endphp
<link href="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.css" rel="stylesheet">
<style>
  #live-map .mapboxgl-ctrl-attrib a { color: #94a3b8; }
  .cdm-map-shell {
    position: relative;
    border-radius: 0.85rem;
    overflow: hidden;
    border: 1px solid var(--border);
    background: #0b1220;
  }
  #live-map { height: 560px; width: 100%; }
  .cdm-map-toolbar {
    position: absolute; z-index: 2; top: 12px; left: 12px;
    display: flex; flex-wrap: wrap; gap: 0.4rem; max-width: calc(100% - 56px);
  }
  .cdm-map-toolbar button, .cdm-map-toolbar select {
    border: 1px solid rgba(148,163,184,.45);
    background: rgba(15,23,42,.88);
    color: #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.4rem 0.65rem;
    font-size: 0.8rem;
    cursor: pointer;
    backdrop-filter: blur(8px);
  }
  .cdm-map-toolbar button.active {
    background: rgba(14,165,233,.35);
    border-color: #38bdf8;
    color: #7dd3fc;
  }
  .cdm-map-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.6rem;
    margin: 0.85rem 0 0.2rem;
  }
  .cdm-map-stat {
    background: rgba(15, 23, 42, 0.55);
    border: 1px solid var(--border);
    border-radius: 0.65rem;
    padding: 0.55rem 0.7rem;
  }
  .cdm-map-stat .k { color: var(--muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: .04em; }
  .cdm-map-stat .v { font-size: 1.05rem; font-weight: 700; margin-top: 0.15rem; }
  @media (max-width: 900px) {
    .cdm-map-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    #live-map { height: 420px; }
  }
  .cdm-map-marker {
    width: 14px; height: 14px; border-radius: 50%;
    border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.35);
  }
  .cdm-map-marker.start { background: #34d399; }
  .cdm-map-marker.now {
    width: 18px; height: 18px; background: #f87171;
    box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.55);
    animation: cdm-pulse 1.6s ease-out infinite;
  }
  .cdm-map-marker.now::after {
    content: '';
    position: absolute;
    inset: -8px;
    border-radius: 50%;
    border: 2px solid rgba(248, 113, 113, 0.45);
  }
  .cdm-map-marker.now { position: relative; }
  @keyframes cdm-pulse {
    0% { box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.55); }
    70% { box-shadow: 0 0 0 14px rgba(248, 113, 113, 0); }
    100% { box-shadow: 0 0 0 0 rgba(248, 113, 113, 0); }
  }
  .mapboxgl-popup-content {
    background: #1e293b; color: #e2e8f0; border-radius: 0.6rem;
    padding: 0.65rem 0.8rem; box-shadow: 0 8px 24px rgba(0,0,0,.35);
  }
  .mapboxgl-popup-anchor-bottom .mapboxgl-popup-tip { border-top-color: #1e293b; }
</style>
<div class="grid" style="grid-template-columns: 2fr 1fr;">
    <div>
        <div class="card">
            <div class="actions" style="justify-content:space-between;">
                <div>
                    <div class="muted">{{ $device->uuid }}</div>
                    <div style="margin-top:0.4rem;">
                        <span class="badge {{ $device->status === 'active' ? 'ok' : 'danger' }}">{{ $device->status }}</span>
                        <span class="badge" id="online-badge">{{ $device->isOnline($threshold) ? 'online' : 'offline' }}</span>
                    </div>
                </div>
                @if ($device->status === 'active')
                    <form method="POST" action="{{ route('admin.devices.revoke', $device) }}" onsubmit="return confirm('Revoke this device and invalidate tokens?');">
                        @csrf
                        <button class="btn danger" type="submit">Revoke device</button>
                    </form>
                @endif
            </div>
            <table style="margin-top:1rem;">
                <tr><th>Name</th><td>{{ $device->name }}</td></tr>
                <tr><th>Platform</th><td>{{ $device->platform }}</td></tr>
                <tr><th>Model</th><td>{{ $device->model ?? '—' }}</td></tr>
                <tr><th>OS</th><td>{{ $device->os_version ?? '—' }}</td></tr>
                <tr><th>App version</th><td>{{ $device->app_version ?? '—' }}</td></tr>
                <tr><th>Enrolled by</th><td>{{ $device->enrolledBy?->name ?? '—' }}</td></tr>
                <tr><th>Enrolled</th><td>{{ $device->enrolled_at?->toDayDateTimeString() ?? '—' }}</td></tr>
                <tr><th>Last seen</th><td id="last-seen">{{ $device->last_seen_at?->toDayDateTimeString() ?? '—' }}</td></tr>
                <tr><th>Battery</th><td id="battery">{{ $st?->battery_level !== null ? $st->battery_level.'%' : '—' }}{{ $st?->is_charging ? ' (charging)' : '' }}</td></tr>
                <tr><th>Network</th><td id="network">{{ $st?->network_type ?? '—' }}</td></tr>
            </table>
        </div>

        <div class="card">
            <div class="actions" style="justify-content:space-between;align-items:center;margin-bottom:0.35rem;">
                <h2 style="margin:0;">Live location</h2>
                <span class="badge" id="loc-live-badge">{{ $loc ? 'tracking' : 'waiting' }}</span>
            </div>
            <p class="muted" id="loc-meta" style="margin-top:0.35rem;">
                @if ($loc)
                    {{ number_format($loc->latitude, 6) }}, {{ number_format($loc->longitude, 6) }}
                    · accuracy {{ $loc->accuracy !== null ? round($loc->accuracy).' m' : '—' }}
                    · {{ $loc->recorded_at?->diffForHumans() }}
                @else
                    Waiting for device location reports…
                @endif
            </p>
            <div class="cdm-map-stats">
                <div class="cdm-map-stat"><div class="k">Points</div><div class="v" id="stat-points">{{ $track->count() }}</div></div>
                <div class="cdm-map-stat"><div class="k">Distance</div><div class="v" id="stat-distance">—</div></div>
                <div class="cdm-map-stat"><div class="k">Speed</div><div class="v" id="stat-speed">—</div></div>
                <div class="cdm-map-stat"><div class="k">Accuracy</div><div class="v" id="stat-accuracy">{{ $loc?->accuracy !== null ? round($loc->accuracy).' m' : '—' }}</div></div>
            </div>
            <div class="cdm-map-shell" style="margin-top:0.85rem;">
                <div class="cdm-map-toolbar">
                    <button type="button" id="map-follow-btn" class="active" title="Keep camera on device">Follow</button>
                    <button type="button" id="map-recenter-btn" title="Jump to current position">Recenter</button>
                    <button type="button" id="map-fit-btn" title="Fit full trail">Fit trail</button>
                    <button type="button" id="map-3d-btn" class="active" title="Toggle 3D pitch">3D</button>
                    <select id="map-style-select" title="Base map">
                        <option value="mapbox">Mapbox</option>
                        <option value="osm">OpenStreetMap</option>
                    </select>
                </div>
                <div id="live-map"></div>
            </div>
            <p class="muted" id="track-meta" style="margin-top:0.65rem;font-size:0.85rem;">
                Trail: <span id="track-count">{{ $track->count() }}</span> points · green = start · red = now
            </p>
            <table style="margin-top:0.75rem;">
                <thead><tr><th>When</th><th>Lat</th><th>Lng</th><th>Accuracy</th></tr></thead>
                <tbody id="loc-history">
                @forelse ($locations->take(40) as $row)
                    <tr>
                        <td>{{ $row->recorded_at?->format('H:i:s') }}</td>
                        <td>{{ $row->latitude }}</td>
                        <td>{{ $row->longitude }}</td>
                        <td>{{ $row->accuracy !== null ? round($row->accuracy).' m' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No location history.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Battery & connectivity</h2>
            @if ($statuses->isEmpty())
                <p class="muted">No status reports yet.</p>
            @else
                <table>
                    <thead><tr><th>When</th><th>Battery</th><th>Charging</th><th>Network</th></tr></thead>
                    <tbody>
                    @foreach ($statuses as $status)
                        <tr>
                            <td>{{ $status->reported_at?->toDayDateTimeString() }}</td>
                            <td>{{ $status->battery_level !== null ? $status->battery_level.'%' : '—' }}</td>
                            <td>{{ is_null($status->is_charging) ? '—' : ($status->is_charging ? 'yes' : 'no') }}</td>
                            <td>{{ $status->network_type ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div>
        <div class="card">
            <h2 style="margin-top:0;">Listen live audio</h2>
            <p class="muted" style="font-size:0.9rem;">
                @if ($hasLiveMicConsent)
                    Mic consent is already granted on this device — listening starts without asking again.
                @else
                    First time only: the device must grant Live sessions consent once. After that, listens auto-connect.
                @endif
            </p>
            <form method="POST" action="{{ route('admin.devices.listen-audio', $device) }}"
                  onsubmit="return confirm(@json($hasLiveMicConsent ? 'Start live audio now? Device already consented — no approval prompt.' : 'Start live audio? Device must grant live-session consent once (then future listens will not ask again).'));">
                @csrf
                <button class="btn" type="submit" @disabled($device->status !== 'active')>Start listen audio</button>
            </form>
            <div id="audio-panel" style="margin-top:1rem;">
                <div class="muted" id="audio-status">No active audio session.</div>
                <canvas id="audio-wave" width="640" height="96" style="width:100%;height:96px;margin-top:0.75rem;border-radius:0.5rem;background:#0f172a;border:1px solid var(--border);display:none;"></canvas>
                <audio id="remote-audio" autoplay playsinline style="display:none;"></audio>
                <div id="audio-controls" class="actions" style="margin-top:0.75rem;display:none;">
                    <button type="button" class="btn secondary" id="audio-pause-btn">Pause</button>
                    <button type="button" class="btn danger" id="audio-stop-btn">Stop</button>
                    <span class="muted" id="audio-rec-meta" style="font-size:0.85rem;align-self:center;"></span>
                </div>
                <div id="audio-save-panel" class="card" style="margin-top:0.75rem;display:none;padding:0.85rem;background:rgba(15,23,42,0.6);">
                    <div style="margin-bottom:0.6rem;">Recording stopped. Save this audio clip?</div>
                    <div class="actions">
                        <button type="button" class="btn" id="audio-save-btn">Save recording</button>
                        <button type="button" class="btn secondary" id="audio-discard-btn">Don't save</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Issue command</h2>
            <form method="POST" action="{{ route('admin.devices.commands', $device) }}">
                @csrf
                <div class="field">
                    <label>Type</label>
                    <select name="type" required>
                        <option value="check_in">Remote check-in</option>
                        <option value="notify">Notification</option>
                        <option value="request_location">Request location</option>
                        <option value="request_status">Request status</option>
                    </select>
                </div>
                <div class="field">
                    <label>Message (optional)</label>
                    <textarea name="message" rows="3"></textarea>
                </div>
                <button class="btn" type="submit" @disabled($device->status !== 'active')>Queue command</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Request live session</h2>
            <form method="POST" action="{{ route('admin.devices.live-sessions', $device) }}">
                @csrf
                <div class="field">
                    <label>Purpose</label>
                    <input name="purpose" required maxlength="255" placeholder="Support verification">
                </div>
                <div class="field">
                    <label><input type="checkbox" name="request_camera" value="1" checked style="width:auto;"> Camera</label>
                    <label><input type="checkbox" name="request_microphone" value="1" checked style="width:auto;"> Microphone</label>
                </div>
                <button class="btn" type="submit" @disabled($device->status !== 'active')>Request session</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Recent commands</h2>
            @forelse ($commands as $command)
                <div style="margin-bottom:0.75rem;">
                    <strong>{{ $command->type }}</strong>
                    <span class="badge">{{ $command->status }}</span>
                    <div class="muted" style="font-size:0.8rem;">{{ $command->created_at?->diffForHumans() }}</div>
                </div>
            @empty
                <p class="muted">No commands.</p>
            @endforelse
        </div>

    </div>
</div>

<script src="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.js"></script>
<script>
(() => {
  const pulseUrl = @json(route('admin.devices.live-pulse', $device));
  const answerUrlTpl = @json(url('/admin/devices/'.$device->uuid.'/live-sessions'));
  const cancelUrlTpl = @json(url('/admin/live-sessions'));
  const csrf = @json(csrf_token());
  const initialTrack = @json($trackJson);
  const mapboxToken = @json(config('cdm.mapbox_access_token'));
  const mapboxStyle = @json(config('cdm.mapbox_style'));
  const audioEl = document.getElementById('remote-audio');
  const audioStatus = document.getElementById('audio-status');
  const waveCanvas = document.getElementById('audio-wave');
  const waveCtx = waveCanvas.getContext('2d');
  const audioControls = document.getElementById('audio-controls');
  const pauseBtn = document.getElementById('audio-pause-btn');
  const stopBtn = document.getElementById('audio-stop-btn');
  const recMeta = document.getElementById('audio-rec-meta');
  const savePanel = document.getElementById('audio-save-panel');
  const saveBtn = document.getElementById('audio-save-btn');
  const discardBtn = document.getElementById('audio-discard-btn');

  let pc = null;
  let boundSession = null;
  let appliedAnswer = false;
  let appliedOfferSdp = null;
  const remoteStream = new MediaStream();
  const appliedDeviceIce = new Set();

  // Flutter/mobile WebRTC SDP often includes a=ssrc … msid lines Chrome rejects.
  function normalizeSdp(sdp) {
    if (!sdp) return sdp;
    let text = String(sdp)
      .replace(/\\r\\n/g, '\n')
      .replace(/\\n/g, '\n')
      .replace(/\r\n/g, '\n')
      .replace(/\r/g, '\n');
    text = text
      .split('\n')
      .filter((line) => {
        const t = line.trim();
        if (!t) return true;
        // Drop Plan-B style ssrc attribute lines that break Chromium parsing.
        if (/^a=ssrc:\d+\s+msid:/i.test(t)) return false;
        if (/^a=ssrc:\d+\s+mslabel:/i.test(t)) return false;
        if (/^a=ssrc:\d+\s+label:/i.test(t)) return false;
        return true;
      })
      .join('\n');
    if (!text.endsWith('\n')) text += '\n';
    return text.replace(/\n/g, '\r\n');
  }

  let audioCtx = null;
  let analyser = null;
  let waveRaf = null;
  let mediaRecorder = null;
  let recordedChunks = [];
  let recordedBlob = null;
  let listening = false;
  let paused = false;
  let recStartedAt = null;
  let recTimer = null;
  let stopping = false;

  let map = null;
  let mapReady = false;
  let startMarker = null;
  let nowMarker = null;
  let lastTrackLen = 0;
  let didInitialFit = false;
  let pendingTrack = null;
  let lastTrackSnapshot = [];
  let followMode = true;
  let map3d = true;
  let currentStyleKey = 'mapbox';
  let userPanning = false;

  const emptyLineGeo = {
    type: 'Feature',
    properties: {},
    geometry: { type: 'LineString', coordinates: [] },
  };
  const emptyFcGeo = { type: 'FeatureCollection', features: [] };

  // Trail sources/layers are baked into OSM so setStyle cannot drop them
  // (Mapbox Standard → custom style often loses runtime-only layers).
  const osmStyle = {
    version: 8,
    name: 'OpenStreetMap',
    projection: { name: 'mercator' },
    sources: {
      osm: {
        type: 'raster',
        tiles: [
          'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        ],
        tileSize: 256,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxzoom: 19,
      },
      'device-trail': { type: 'geojson', data: emptyLineGeo },
      'device-accuracy': { type: 'geojson', data: emptyFcGeo },
      'device-points': { type: 'geojson', data: emptyFcGeo },
    },
    layers: [
      { id: 'osm', type: 'raster', source: 'osm' },
      {
        id: 'device-trail-glow',
        type: 'line',
        source: 'device-trail',
        layout: { 'line-join': 'round', 'line-cap': 'round' },
        paint: {
          'line-color': '#38bdf8',
          'line-width': 10,
          'line-opacity': 0.35,
        },
      },
      {
        id: 'device-trail-line',
        type: 'line',
        source: 'device-trail',
        layout: { 'line-join': 'round', 'line-cap': 'round' },
        paint: {
          'line-color': '#0ea5e9',
          'line-width': 4,
          'line-opacity': 1,
        },
      },
      {
        id: 'device-accuracy-fill',
        type: 'fill',
        source: 'device-accuracy',
        paint: { 'fill-color': '#f87171', 'fill-opacity': 0.16 },
      },
      {
        id: 'device-accuracy-outline',
        type: 'line',
        source: 'device-accuracy',
        paint: {
          'line-color': '#f87171',
          'line-width': 1.5,
          'line-opacity': 0.6,
        },
      },
      {
        id: 'device-point-halo',
        type: 'circle',
        source: 'device-points',
        paint: {
          'circle-radius': ['match', ['get', 'kind'], 'now', 14, 10],
          'circle-color': ['match', ['get', 'kind'], 'now', '#f87171', '#34d399'],
          'circle-opacity': 0.25,
        },
      },
      {
        id: 'device-point-dot',
        type: 'circle',
        source: 'device-points',
        paint: {
          'circle-radius': ['match', ['get', 'kind'], 'now', 8, 6],
          'circle-color': ['match', ['get', 'kind'], 'now', '#ef4444', '#10b981'],
          'circle-stroke-width': 2,
          'circle-stroke-color': '#ffffff',
        },
      },
    ],
  };

  function isOsmStyle(key) {
    return key === 'osm';
  }

  function resolveMapStyle(key) {
    if (isOsmStyle(key)) return osmStyle;
    return mapboxStyle || 'mapbox://styles/mapbox/standard';
  }

  function syncMapModeUi() {
    const btn3d = document.getElementById('map-3d-btn');
    if (!btn3d) return;
    btn3d.style.display = '';
    btn3d.classList.toggle('active', map3d);
    // OSM has no building extrusions, but keep camera pitch so it is not flat.
    if (map && mapReady && map3d) {
      map.easeTo({
        pitch: Math.max(map.getPitch(), 55),
        bearing: map.getBearing() || -24,
        duration: 300,
      });
    }
  }

  function fmtDuration(ms) {
    const s = Math.floor(ms / 1000);
    const m = Math.floor(s / 60);
    const r = s % 60;
    return `${String(m).padStart(2, '0')}:${String(r).padStart(2, '0')}`;
  }

  function setControlsVisible(on) {
    audioControls.style.display = on ? 'flex' : 'none';
    waveCanvas.style.display = on ? 'block' : 'none';
  }

  function drawIdleWave() {
    const w = waveCanvas.width;
    const h = waveCanvas.height;
    waveCtx.fillStyle = '#0f172a';
    waveCtx.fillRect(0, 0, w, h);
    waveCtx.strokeStyle = '#334155';
    waveCtx.beginPath();
    waveCtx.moveTo(0, h / 2);
    waveCtx.lineTo(w, h / 2);
    waveCtx.stroke();
  }

  function startWaveform() {
    if (!audioCtx) {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
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
      const w = waveCanvas.width;
      const h = waveCanvas.height;
      waveCtx.fillStyle = '#0f172a';
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
    cancelAnimationFrame(waveRaf);
    paint();
  }

  function stopWaveform() {
    cancelAnimationFrame(waveRaf);
    waveRaf = null;
    drawIdleWave();
  }

  function startRecording() {
    recordedChunks = [];
    recordedBlob = null;
    savePanel.style.display = 'none';
    const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
      ? 'audio/webm;codecs=opus'
      : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '');
    try {
      mediaRecorder = mime
        ? new MediaRecorder(remoteStream, { mimeType: mime })
        : new MediaRecorder(remoteStream);
    } catch (_) {
      mediaRecorder = null;
      recMeta.textContent = 'Live listen (recording unavailable in this browser)';
      return;
    }
    mediaRecorder.ondataavailable = (ev) => {
      if (ev.data && ev.data.size > 0) recordedChunks.push(ev.data);
    };
    mediaRecorder.start(1000);
    recStartedAt = Date.now();
    clearInterval(recTimer);
    recTimer = setInterval(() => {
      if (!recStartedAt || paused) return;
      recMeta.textContent = `Recording ${fmtDuration(Date.now() - recStartedAt)}`;
    }, 500);
    recMeta.textContent = 'Recording 00:00';
  }

  function pauseListen() {
    if (!listening || paused) return;
    paused = true;
    audioEl.muted = true;
    if (mediaRecorder && mediaRecorder.state === 'recording') {
      try { mediaRecorder.pause(); } catch (_) {}
    }
    pauseBtn.textContent = 'Resume';
    audioStatus.textContent = 'Paused';
    recMeta.textContent = (recMeta.textContent || '') + ' · paused';
  }

  function resumeListen() {
    if (!listening || !paused) return;
    paused = false;
    audioEl.muted = false;
    audioEl.play().catch(() => {});
    if (mediaRecorder && mediaRecorder.state === 'paused') {
      try { mediaRecorder.resume(); } catch (_) {}
    }
    if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
    pauseBtn.textContent = 'Pause';
    audioStatus.textContent = 'Receiving live audio…';
  }

  function togglePause() {
    if (paused) resumeListen();
    else pauseListen();
  }

  function finishRecordingBlob() {
    return new Promise((resolve) => {
      if (!mediaRecorder || mediaRecorder.state === 'inactive') {
        resolve(recordedChunks.length
          ? new Blob(recordedChunks, { type: recordedChunks[0]?.type || 'audio/webm' })
          : null);
        return;
      }
      mediaRecorder.onstop = () => {
        resolve(recordedChunks.length
          ? new Blob(recordedChunks, { type: recordedChunks[0]?.type || 'audio/webm' })
          : null);
      };
      try { mediaRecorder.stop(); } catch (_) { resolve(null); }
    });
  }

  async function stopListen({ askSave = true } = {}) {
    if (stopping) return;
    stopping = true;
    listening = false;
    paused = false;
    pauseBtn.textContent = 'Pause';
    clearInterval(recTimer);
    recTimer = null;
    stopWaveform();
    audioEl.pause();
    audioEl.srcObject = null;
    audioEl.muted = false;

    const sessionUuid = boundSession;
    recordedBlob = await finishRecordingBlob();
    mediaRecorder = null;

    if (pc) {
      try { pc.close(); } catch (_) {}
      pc = null;
    }
    remoteStream.getTracks().forEach((t) => {
      try { t.stop(); } catch (_) {}
      remoteStream.removeTrack(t);
    });
    appliedAnswer = false;
    appliedDeviceIce.clear();
    boundSession = null;
    setControlsVisible(false);

    if (sessionUuid) {
      try {
        await fetch(`${cancelUrlTpl}/${sessionUuid}/cancel`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({}),
        });
      } catch (_) {}
    }

    audioStatus.textContent = 'Stopped';
    if (askSave && recordedBlob && recordedBlob.size > 0) {
      savePanel.style.display = 'block';
      recMeta.textContent = `Clip ready (${Math.round(recordedBlob.size / 1024)} KB)`;
    } else {
      savePanel.style.display = 'none';
      recordedBlob = null;
      recordedChunks = [];
      recMeta.textContent = '';
      audioStatus.textContent = 'No active audio session.';
    }
    stopping = false;
  }

  function saveRecording() {
    if (!recordedBlob) return;
    const a = document.createElement('a');
    const url = URL.createObjectURL(recordedBlob);
    a.href = url;
    a.download = `live-audio-${new Date().toISOString().replace(/[:.]/g, '-')}.webm`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    savePanel.style.display = 'none';
    recordedBlob = null;
    recordedChunks = [];
    recMeta.textContent = '';
    audioStatus.textContent = 'Recording saved to your downloads.';
  }

  function discardRecording() {
    recordedBlob = null;
    recordedChunks = [];
    savePanel.style.display = 'none';
    recMeta.textContent = '';
    audioStatus.textContent = 'Recording discarded.';
  }

  function onLiveAudioReady() {
    if (listening) return;
    listening = true;
    paused = false;
    setControlsVisible(true);
    savePanel.style.display = 'none';
    audioStatus.textContent = 'Receiving live audio…';
    startWaveform();
    startRecording();
  }

  pauseBtn.addEventListener('click', togglePause);
  stopBtn.addEventListener('click', () => stopListen({ askSave: true }));
  saveBtn.addEventListener('click', saveRecording);
  discardBtn.addEventListener('click', discardRecording);
  drawIdleWave();

  function haversineM(a, b) {
    const R = 6371000;
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(b[1] - a[1]);
    const dLng = toRad(b[0] - a[0]);
    const lat1 = toRad(a[1]);
    const lat2 = toRad(b[1]);
    const h = Math.sin(dLat / 2) ** 2
      + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
  }

  function formatDistance(meters) {
    if (!Number.isFinite(meters) || meters <= 0) return '—';
    if (meters < 1000) return Math.round(meters) + ' m';
    return (meters / 1000).toFixed(2) + ' km';
  }

  function formatSpeed(mps) {
    if (!Number.isFinite(mps) || mps < 0) return '—';
    const kmh = mps * 3.6;
    if (kmh < 0.4) return '0 km/h';
    return kmh.toFixed(1) + ' km/h';
  }

  function bearingDeg(a, b) {
    const toRad = (d) => (d * Math.PI) / 180;
    const toDeg = (r) => (r * 180) / Math.PI;
    const lat1 = toRad(a[1]);
    const lat2 = toRad(b[1]);
    const dLng = toRad(b[0] - a[0]);
    const y = Math.sin(dLng) * Math.cos(lat2);
    const x = Math.cos(lat1) * Math.sin(lat2)
      - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
    return (toDeg(Math.atan2(y, x)) + 360) % 360;
  }

  function accuracyCircle(lng, lat, radiusM, points = 64) {
    const coords = [];
    const latRad = (lat * Math.PI) / 180;
    const mPerDegLat = 111320;
    const mPerDegLng = 111320 * Math.cos(latRad);
    for (let i = 0; i <= points; i++) {
      const t = (i / points) * Math.PI * 2;
      coords.push([
        lng + (radiusM * Math.cos(t)) / mPerDegLng,
        lat + (radiusM * Math.sin(t)) / mPerDegLat,
      ]);
    }
    return {
      type: 'Feature',
      properties: {},
      geometry: { type: 'Polygon', coordinates: [coords] },
    };
  }

  function add3dBuildings() {
    try {
      const layers = map.getStyle().layers || [];
      const labelLayerId = layers.find(
        (l) => l.type === 'symbol' && l.layout && l.layout['text-field'],
      )?.id;
      if (map.getLayer('cdm-3d-buildings')) return;
      if (!map.getSource('composite')) return;
      map.addLayer(
        {
          id: 'cdm-3d-buildings',
          source: 'composite',
          'source-layer': 'building',
          filter: ['==', 'extrude', 'true'],
          type: 'fill-extrusion',
          minzoom: 14,
          paint: {
            'fill-extrusion-color': [
              'interpolate', ['linear'], ['get', 'height'],
              0, '#64748b',
              30, '#94a3b8',
              80, '#cbd5e1',
            ],
            'fill-extrusion-height': ['coalesce', ['get', 'height'], 12],
            'fill-extrusion-base': ['coalesce', ['get', 'min_height'], 0],
            'fill-extrusion-opacity': 0.82,
          },
        },
        labelLayerId,
      );
    } catch (e) {
      console.warn('3D buildings unavailable for this style', e);
    }
  }

  function addTerrain() {
    try {
      if (!map.getSource('mapbox-dem')) {
        map.addSource('mapbox-dem', {
          type: 'raster-dem',
          url: 'mapbox://mapbox.mapbox-terrain-dem-v1',
          tileSize: 512,
          maxzoom: 14,
        });
      }
      map.setTerrain({ source: 'mapbox-dem', exaggeration: 1.35 });
      map.setFog({
        color: 'rgb(15, 23, 42)',
        'high-color': 'rgb(30, 41, 59)',
        'horizon-blend': 0.08,
        'space-color': 'rgb(15, 23, 42)',
        'star-intensity': 0.15,
      });
    } catch (e) {
      console.warn('Terrain unavailable', e);
    }
  }

  function ensureTrailLayers() {
    if (!map) return;
    try {
      if (typeof map.isStyleLoaded === 'function' && !map.isStyleLoaded()) return;

      if (!map.getSource('device-trail')) {
        map.addSource('device-trail', { type: 'geojson', data: emptyLineGeo });
      }
      if (!map.getSource('device-accuracy')) {
        map.addSource('device-accuracy', { type: 'geojson', data: emptyFcGeo });
      }
      if (!map.getSource('device-points')) {
        map.addSource('device-points', { type: 'geojson', data: emptyFcGeo });
      }

      // Paint props must work on both Mapbox and OSM (no emissive-strength).
      if (!map.getLayer('device-trail-glow')) {
        map.addLayer({
          id: 'device-trail-glow',
          type: 'line',
          source: 'device-trail',
          layout: { 'line-join': 'round', 'line-cap': 'round' },
          paint: {
            'line-color': '#38bdf8',
            'line-width': 10,
            'line-opacity': 0.35,
          },
        });
      }
      if (!map.getLayer('device-trail-line')) {
        map.addLayer({
          id: 'device-trail-line',
          type: 'line',
          source: 'device-trail',
          layout: { 'line-join': 'round', 'line-cap': 'round' },
          paint: {
            'line-color': '#0ea5e9',
            'line-width': 4,
            'line-opacity': 1,
          },
        });
      }
      if (!map.getLayer('device-accuracy-fill')) {
        map.addLayer({
          id: 'device-accuracy-fill',
          type: 'fill',
          source: 'device-accuracy',
          paint: { 'fill-color': '#f87171', 'fill-opacity': 0.16 },
        });
      }
      if (!map.getLayer('device-accuracy-outline')) {
        map.addLayer({
          id: 'device-accuracy-outline',
          type: 'line',
          source: 'device-accuracy',
          paint: {
            'line-color': '#f87171',
            'line-width': 1.5,
            'line-opacity': 0.6,
          },
        });
      }
      if (!map.getLayer('device-point-halo')) {
        map.addLayer({
          id: 'device-point-halo',
          type: 'circle',
          source: 'device-points',
          paint: {
            'circle-radius': ['match', ['get', 'kind'], 'now', 14, 10],
            'circle-color': ['match', ['get', 'kind'], 'now', '#f87171', '#34d399'],
            'circle-opacity': 0.25,
          },
        });
      }
      if (!map.getLayer('device-point-dot')) {
        map.addLayer({
          id: 'device-point-dot',
          type: 'circle',
          source: 'device-points',
          paint: {
            'circle-radius': ['match', ['get', 'kind'], 'now', 8, 6],
            'circle-color': ['match', ['get', 'kind'], 'now', '#ef4444', '#10b981'],
            'circle-stroke-width': 2,
            'circle-stroke-color': '#ffffff',
          },
        });
      }

      // Keep trail above the basemap raster / standard layers.
      ['device-trail-glow', 'device-trail-line', 'device-accuracy-fill',
        'device-accuracy-outline', 'device-point-halo', 'device-point-dot']
        .forEach((id) => {
          try { if (map.getLayer(id)) map.moveLayer(id); } catch (_) {}
        });
    } catch (e) {
      console.warn('Trail layers failed', e);
    }
  }

  function applyStyleExtras() {
    // Building extrusions / DEM terrain only on Mapbox vector styles.
    if (!isOsmStyle(currentStyleKey)) {
      try {
        if (typeof map.setProjection === 'function') map.setProjection('globe');
      } catch (_) {}
      try {
        if (typeof map.setConfigProperty === 'function') {
          map.setConfigProperty('basemap', 'lightPreset', 'dusk');
          map.setConfigProperty('basemap', 'show3dObjects', true);
        }
      } catch (_) {}
      add3dBuildings();
      addTerrain();
    } else {
      try { if (typeof map.setProjection === 'function') map.setProjection('mercator'); } catch (_) {}
      try { map.setTerrain(null); } catch (_) {}
      try { map.setFog(null); } catch (_) {}
    }
    syncMapModeUi();
    ensureTrailLayers();
    const track = pendingTrack || lastTrackSnapshot;
    if (track && track.length) {
      // Defer one frame so sources from the new style are queryable.
      requestAnimationFrame(() => {
        ensureTrailLayers();
        updateTrail(track);
      });
    }
  }

  function destroyMap() {
    if (startMarker) { try { startMarker.remove(); } catch (_) {} startMarker = null; }
    if (nowMarker) { try { nowMarker.remove(); } catch (_) {} nowMarker = null; }
    if (map) {
      try { map.remove(); } catch (_) {}
      map = null;
    }
    mapReady = false;
    didInitialFit = false;
    lastTrackLen = 0;
    const host = document.getElementById('live-map');
    if (host) host.innerHTML = '';
  }

  function ensureMap() {
    if (map) return map;
    // OSM works without a Mapbox token; Mapbox styles need one.
    if (!isOsmStyle(currentStyleKey) && !mapboxToken) {
      document.getElementById('live-map').innerHTML =
        '<div class="muted" style="display:grid;place-items:center;height:100%;padding:1rem;text-align:center;">Mapbox token missing — set MAPBOX_ACCESS_TOKEN in .env, or choose OpenStreetMap from the dropdown.</div>';
      return null;
    }
    if (mapboxToken) mapboxgl.accessToken = mapboxToken;
    map = new mapboxgl.Map({
      container: 'live-map',
      style: resolveMapStyle(currentStyleKey),
      center: [38.75, 9.03],
      zoom: 15.2,
      pitch: map3d ? 62 : 0,
      bearing: map3d ? -24 : 0,
      antialias: true,
      attributionControl: true,
    });
    map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'top-right');
    map.addControl(new mapboxgl.FullscreenControl(), 'top-right');
    map.addControl(new mapboxgl.ScaleControl({ maxWidth: 120 }), 'bottom-left');

    map.on('style.load', () => {
      mapReady = true;
      applyStyleExtras();
    });

    map.on('load', () => {
      mapReady = true;
      applyStyleExtras();
    });

    map.on('dragstart', () => {
      userPanning = true;
      followMode = false;
      document.getElementById('map-follow-btn')?.classList.remove('active');
    });

    return map;
  }

  function elMarker(kind) {
    const el = document.createElement('div');
    el.className = 'cdm-map-marker ' + kind;
    el.title = kind === 'start' ? 'Start' : 'Now';
    return el;
  }

  function popupHtml(title, point) {
    const when = point?.recorded_at ? new Date(point.recorded_at).toLocaleString() : '—';
    const acc = point?.accuracy != null ? Math.round(point.accuracy) + ' m' : '—';
    return `<strong>${title}</strong><div style="margin-top:0.35rem;font-size:0.82rem;color:#94a3b8;">${when}<br>Accuracy ${acc}</div>`;
  }

  function updateStats(track, coords) {
    let distance = 0;
    for (let i = 1; i < coords.length; i++) distance += haversineM(coords[i - 1], coords[i]);

    let speed = NaN;
    if (track.length >= 2) {
      const a = track[track.length - 2];
      const b = track[track.length - 1];
      const dt = (a.recorded_at && b.recorded_at)
        ? (new Date(b.recorded_at) - new Date(a.recorded_at)) / 1000
        : 0;
      if (dt > 0) {
        speed = haversineM(
          [Number(a.longitude), Number(a.latitude)],
          [Number(b.longitude), Number(b.latitude)],
        ) / dt;
      }
    }

    const last = track[track.length - 1];
    document.getElementById('stat-points').textContent = String(track.length);
    document.getElementById('stat-distance').textContent = formatDistance(distance);
    document.getElementById('stat-speed').textContent = formatSpeed(speed);
    document.getElementById('stat-accuracy').textContent =
      last?.accuracy != null ? Math.round(last.accuracy) + ' m' : '—';
    document.getElementById('track-count').textContent = String(track.length);

    const badge = document.getElementById('loc-live-badge');
    if (badge) {
      badge.textContent = 'live';
      badge.className = 'badge ok';
    }
  }

  function fitTrail(coords, { animate = true } = {}) {
    if (!map || !coords.length) return;
    const pitch = map3d ? 58 : 0;
    if (coords.length === 1) {
      map.flyTo({
        center: coords[0],
        zoom: 16.8,
        pitch,
        bearing: map3d ? map.getBearing() || -24 : 0,
        essential: true,
        duration: animate ? 700 : 0,
      });
      return;
    }
    const bounds = coords.reduce(
      (b, c) => b.extend(c),
      new mapboxgl.LngLatBounds(coords[0], coords[0]),
    );
    map.fitBounds(bounds, {
      padding: 56,
      maxZoom: 17.5,
      pitch,
      bearing: map.getBearing(),
      duration: animate ? 700 : 0,
    });
  }

  function updateTrail(track) {
    if (!Array.isArray(track) || track.length === 0) return;
    lastTrackSnapshot = track;
    ensureMap();
    if (!map || !mapReady) {
      pendingTrack = track;
      return;
    }
    pendingTrack = null;

    // After style switch (esp. OSM), sources/layers are gone — recreate every tick if needed.
    ensureTrailLayers();

    const coords = track
      .map((p) => [Number(p.longitude), Number(p.latitude)])
      .filter((c) => Number.isFinite(c[0]) && Number.isFinite(c[1]));
    if (!coords.length) return;

    const start = coords[0];
    const now = coords[coords.length - 1];
    const lastPoint = track[track.length - 1];
    const startPoint = track[0];
    const lineCoords = coords.length === 1 ? [coords[0], coords[0]] : coords;

    const src = map.getSource('device-trail');
    if (src) {
      src.setData({
        type: 'Feature',
        properties: {},
        geometry: { type: 'LineString', coordinates: lineCoords },
      });
    }

    const accSrc = map.getSource('device-accuracy');
    if (accSrc) {
      const radius = Math.max(8, Number(lastPoint.accuracy) || 25);
      accSrc.setData({
        type: 'FeatureCollection',
        features: [accuracyCircle(now[0], now[1], radius)],
      });
    }

    const pts = map.getSource('device-points');
    if (pts) {
      pts.setData({
        type: 'FeatureCollection',
        features: [
          {
            type: 'Feature',
            properties: { kind: 'start' },
            geometry: { type: 'Point', coordinates: start },
          },
          {
            type: 'Feature',
            properties: { kind: 'now' },
            geometry: { type: 'Point', coordinates: now },
          },
        ],
      });
    }

    try {
      if (!startMarker) {
        startMarker = new mapboxgl.Marker({ element: elMarker('start') })
          .setLngLat(start)
          .setPopup(new mapboxgl.Popup({ offset: 12 }).setHTML(popupHtml('Start', startPoint)))
          .addTo(map);
      } else {
        startMarker.setLngLat(start);
        startMarker.getPopup()?.setHTML(popupHtml('Start', startPoint));
      }

      if (!nowMarker) {
        nowMarker = new mapboxgl.Marker({ element: elMarker('now') })
          .setLngLat(now)
          .setPopup(new mapboxgl.Popup({ offset: 14 }).setHTML(popupHtml('Now', lastPoint)))
          .addTo(map);
      } else {
        nowMarker.setLngLat(now);
        nowMarker.getPopup()?.setHTML(popupHtml('Now', lastPoint));
      }
    } catch (e) {
      console.warn('HTML markers failed (circle plots still shown)', e);
    }

    updateStats(track, coords);

    let heading = map.getBearing();
    if (coords.length >= 2) {
      heading = bearingDeg(coords[coords.length - 2], coords[coords.length - 1]);
    }

    const grewALot = track.length - lastTrackLen >= 25;
    if (!didInitialFit || grewALot) {
      fitTrail(coords);
      didInitialFit = true;
      userPanning = false;
    } else if (followMode && !userPanning && track.length !== lastTrackLen) {
      map.easeTo({
        center: now,
        zoom: Math.max(map.getZoom(), 15.5),
        pitch: map3d ? map.getPitch() || 62 : 0,
        bearing: map3d ? heading : 0,
        duration: 500,
      });
    }
    lastTrackLen = track.length;

    const recent = [...track].reverse().slice(0, 40);
    const tbody = document.getElementById('loc-history');
    tbody.innerHTML = recent.map((p) => {
      const when = p.recorded_at ? new Date(p.recorded_at).toLocaleTimeString() : '—';
      const acc = p.accuracy != null ? Math.round(p.accuracy) + ' m' : '—';
      return `<tr><td>${when}</td><td>${Number(p.latitude).toFixed(6)}</td><td>${Number(p.longitude).toFixed(6)}</td><td>${acc}</td></tr>`;
    }).join('') || '<tr><td colspan="4" class="muted">No location history.</td></tr>';
  }

  document.getElementById('map-follow-btn')?.addEventListener('click', (e) => {
    followMode = !followMode;
    userPanning = false;
    e.currentTarget.classList.toggle('active', followMode);
    if (followMode && lastTrackSnapshot.length) updateTrail(lastTrackSnapshot);
  });

  document.getElementById('map-recenter-btn')?.addEventListener('click', () => {
    followMode = true;
    userPanning = false;
    document.getElementById('map-follow-btn')?.classList.add('active');
    if (lastTrackSnapshot.length) updateTrail(lastTrackSnapshot);
  });

  document.getElementById('map-fit-btn')?.addEventListener('click', () => {
    if (!lastTrackSnapshot.length) return;
    const coords = lastTrackSnapshot.map((p) => [Number(p.longitude), Number(p.latitude)]);
    followMode = false;
    userPanning = true;
    document.getElementById('map-follow-btn')?.classList.remove('active');
    ensureMap();
    if (mapReady) fitTrail(coords);
  });

  document.getElementById('map-3d-btn')?.addEventListener('click', (e) => {
    map3d = !map3d;
    e.currentTarget.classList.toggle('active', map3d);
    ensureMap();
    if (!mapReady) return;
    map.easeTo({
      pitch: map3d ? 62 : 0,
      bearing: map3d ? (map.getBearing() || -24) : 0,
      duration: 550,
    });
  });

  document.getElementById('map-style-select')?.addEventListener('change', (e) => {
    currentStyleKey = e.target.value;
    // Keep tilted view when switching to OSM (not flat).
    if (isOsmStyle(currentStyleKey) && !map3d) {
      map3d = true;
    }
    syncMapModeUi();
    // Full rebuild avoids Mapbox Standard → OSM dropping runtime GeoJSON layers.
    pendingTrack = lastTrackSnapshot.length ? lastTrackSnapshot : pendingTrack;
    destroyMap();
    ensureMap();
  });

  const styleSelect = document.getElementById('map-style-select');
  if (styleSelect && currentStyleKey) {
    const opt = [...styleSelect.options].find((o) => o.value === currentStyleKey);
    if (opt) styleSelect.value = currentStyleKey;
  }
  syncMapModeUi();

  async function ensurePeer(session) {
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
      await fetch(`${answerUrlTpl}/${session.uuid}/ice`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ candidate: ev.candidate.toJSON() }),
      });
    };
    return pc;
  }

  async function handleSession(session) {
    if (stopping) return;
    if (!session) {
      if (!listening && savePanel.style.display !== 'block') {
        audioStatus.textContent = 'No active audio session.';
        setControlsVisible(false);
      }
      return;
    }
    if (!session.request_microphone) return;

    // New listen request while save dialog is open → continue connecting.
    if (savePanel.style.display === 'block' && boundSession !== session.uuid) {
      savePanel.style.display = 'none';
      recordedBlob = null;
      recordedChunks = [];
    }

    if (session.status === 'pending') {
      audioStatus.textContent = 'Waiting for device…';
      return;
    }
    if (!session.device_offer_sdp) {
      audioStatus.textContent = 'Waiting for phone mic… leave CDM notification on (works locked; unlock once if stuck).';
      return;
    }

    try {
      const peer = await ensurePeer(session);
      const offerSdp = normalizeSdp(session.device_offer_sdp);
      if (!appliedAnswer || appliedOfferSdp !== offerSdp) {
        audioStatus.textContent = 'Got device stream — answering…';
        await peer.setRemoteDescription({ type: 'offer', sdp: offerSdp });
        const answer = await peer.createAnswer();
        await peer.setLocalDescription(answer);
        const res = await fetch(`${answerUrlTpl}/${session.uuid}/answer`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
          body: JSON.stringify({ answer_sdp: normalizeSdp(answer.sdp), ice: [] }),
        });
        if (!res.ok) {
          appliedAnswer = false;
          appliedOfferSdp = null;
          audioStatus.textContent = `Answer failed (${res.status}). Refresh and try again.`;
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
      console.warn(e);
    }
  }

  async function tick() {
    try {
      const res = await fetch(pulseUrl, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) return;
      const json = await res.json();
      const d = json.data;
      const badge = document.getElementById('online-badge');
      badge.textContent = d.online ? 'online' : 'offline';
      badge.className = 'badge ' + (d.online ? 'ok' : 'warn');
      document.getElementById('last-seen').textContent = d.last_seen_at || '—';
      document.getElementById('battery').textContent = d.battery_level != null
        ? (d.battery_level + '%' + (d.is_charging ? ' (charging)' : '')) : '—';
      document.getElementById('network').textContent = d.network_type || '—';
      if (d.location) {
        const L = d.location;
        const when = L.recorded_at ? new Date(L.recorded_at).toLocaleString() : '';
        document.getElementById('loc-meta').textContent =
          `${Number(L.latitude).toFixed(6)}, ${Number(L.longitude).toFixed(6)} · accuracy ${L.accuracy != null ? Math.round(L.accuracy) + ' m' : '—'} · ${when}`;
        const badge = document.getElementById('loc-live-badge');
        if (badge) { badge.textContent = 'live'; badge.className = 'badge ok'; }
      }
      if (Array.isArray(d.track)) {
        updateTrail(d.track);
      }
      await handleSession(d.session);
    } catch (e) {
      console.warn(e);
    }
  }

  updateTrail(initialTrack);
  tick();
  setInterval(tick, 1000);
})();
</script>
@endsection
