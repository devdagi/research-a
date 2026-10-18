import 'package:flutter/material.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:provider/provider.dart';

import '../../core/config.dart';
import '../../widgets/section_card.dart';
import '../authentication/app_state.dart';

class LiveSessionScreen extends StatefulWidget {
  const LiveSessionScreen({super.key});

  @override
  State<LiveSessionScreen> createState() => _LiveSessionScreenState();
}

class _LiveSessionScreenState extends State<LiveSessionScreen> {
  final RTCVideoRenderer _localRenderer = RTCVideoRenderer();
  bool _rendererReady = false;
  String? _statusMessage;

  @override
  void initState() {
    super.initState();
    _initRenderer();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AppState>().pollLiveSessions();
    });
  }

  Future<void> _initRenderer() async {
    await _localRenderer.initialize();
    if (mounted) setState(() => _rendererReady = true);
  }

  @override
  void dispose() {
    _localRenderer.dispose();
    super.dispose();
  }

  void _bindPreview(AppState appState) {
    final stream = appState.liveMedia.localStream;
    if (_rendererReady && _localRenderer.srcObject != stream) {
      _localRenderer.srcObject = stream;
    }
  }

  Future<void> _connectPending() async {
    final appState = context.read<AppState>();
    // WebRTC is started by the background service; refresh until it attaches.
    setState(() => _statusMessage = 'Connecting in background…');
    await appState.pollLiveSessions();
    await Future<void>.delayed(const Duration(seconds: 2));
    await appState.pollLiveSessions();
    if (!mounted) return;
    setState(() {
      _statusMessage = appState.activeLiveSession != null
          ? (appState.backgroundLiveStatus ?? 'Connected')
          : (appState.backgroundLiveStatus ??
              'Waiting for background mic… keep notification active.');
    });
  }

  Future<void> _endLocal() async {
    await context.read<AppState>().endActiveLiveSession();
    _localRenderer.srcObject = null;
    setState(() => _statusMessage = 'Session ended');
  }

  @override
  Widget build(BuildContext context) {
    final appState = context.watch<AppState>();
    _bindPreview(appState);
    final pending = appState.pendingLiveSession;
    final active = appState.activeLiveSession;
    final showPreview = active?.requestCamera == true;

    return Scaffold(
      appBar: AppBar(title: const Text('Live session')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (!AppConfig.isTurnConfigured)
            SectionCard(
              title: 'Network note',
              child: const Text(
                'Using public STUN. Same Wi‑Fi / LAN works best for live audio.',
              ),
            ),
          if (pending != null && active == null)
            SectionCard(
              title: 'Admin listen request',
              subtitle: pending.requesterName ?? 'Administrator',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (pending.purpose != null) Text(pending.purpose!),
                  Text(
                    'Requested: '
                    '${pending.requestCamera ? 'camera ' : ''}'
                    '${pending.requestMicrophone ? 'microphone' : ''}',
                  ),
                  const SizedBox(height: 12),
                  FilledButton(
                    onPressed: _connectPending,
                    child: const Text('Connect'),
                  ),
                ],
              ),
            ),
          if (active != null)
            SectionCard(
              title: 'Active session',
              child: Column(
                children: [
                  if (_rendererReady && showPreview)
                    SizedBox(
                      height: 200,
                      child: RTCVideoView(_localRenderer, mirror: true),
                    )
                  else
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 12),
                      child: Text('Microphone streaming to admin…'),
                    ),
                  if (appState.liveMedia.statusMessage != null)
                    Text(appState.liveMedia.statusMessage!),
                  const SizedBox(height: 12),
                  FilledButton.tonal(
                    onPressed: _endLocal,
                    child: const Text('End session'),
                  ),
                ],
              ),
            ),
          if (pending == null && active == null)
            const SectionCard(
              title: 'No active session',
              child: Text('Waiting for an admin listen/camera request.'),
            ),
          if (_statusMessage != null) Text(_statusMessage!),
          TextButton(
            onPressed: () => appState.pollLiveSessions(),
            child: const Text('Refresh'),
          ),
        ],
      ),
    );
  }
}
