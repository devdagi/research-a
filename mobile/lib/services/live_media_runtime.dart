import 'dart:async';

import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:permission_handler/permission_handler.dart';

import '../models/live_session.dart';
import 'live_session_service.dart';

/// Owns WebRTC media once live-session consent is granted.
/// Survives lock-screen by detecting dead peer/tracks and re-offering.
class LiveMediaRuntime {
  LiveMediaRuntime({LiveSessionService? liveSessionService})
      : _api = liveSessionService ?? LiveSessionService();

  final LiveSessionService _api;

  RTCPeerConnection? _pc;
  MediaStream? _localStream;
  Timer? _signalingPoll;
  bool _busy = false;
  bool _remoteAnswerApplied = false;
  bool _mediaUnhealthy = false;
  final Set<String> _appliedAdminIce = {};

  LiveSession? activeSession;
  String? statusMessage;
  void Function()? onChanged;

  bool get isActive => activeSession != null;

  /// True when local mic/peer look usable (lock screen often kills tracks).
  bool get mediaHealthy {
    if (_pc == null || _localStream == null || activeSession == null) return false;
    if (_mediaUnhealthy) return false;
    if (activeSession!.requestMicrophone) {
      final audio = _localStream!.getAudioTracks();
      if (audio.isEmpty) return false;
      if (audio.every((t) => !t.enabled)) return false;
    }
    return true;
  }

  Future<void> connect(LiveSession session, {bool force = false}) async {
    if (_busy) return;
    if (!force &&
        activeSession?.uuid == session.uuid &&
        mediaHealthy) {
      return;
    }
    _busy = true;
    try {
      // Soft teardown when re-binding the same session after unlock.
      final same = activeSession?.uuid == session.uuid;
      await disconnect(notifyServer: activeSession != null && !same);

      var current = session;
      if (current.status == LiveSessionStatus.pending) {
        statusMessage = 'Approving…';
        onChanged?.call();
        current = await _api.approve(current.uuid);
      }

      if (current.requestMicrophone) {
        var mic = await Permission.microphone.status;
        if (!mic.isGranted) {
          mic = await Permission.microphone.request();
        }
        if (!mic.isGranted) {
          throw Exception('Microphone permission denied');
        }
      }
      if (current.requestCamera) {
        var cam = await Permission.camera.status;
        if (!cam.isGranted) {
          cam = await Permission.camera.request();
        }
        if (!cam.isGranted) {
          throw Exception('Camera permission denied');
        }
      }

      statusMessage = force ? 'Reconnecting microphone…' : 'Starting microphone…';
      onChanged?.call();
      await _startWebRtc(current);

      if (current.status == LiveSessionStatus.approved) {
        current = await _api.startSession(current.uuid);
      }

      activeSession = current;
      _mediaUnhealthy = false;
      statusMessage = 'Live audio connected';
      onChanged?.call();
    } catch (e) {
      statusMessage = e.toString();
      onChanged?.call();
      await disconnect(notifyServer: false);
      rethrow;
    } finally {
      _busy = false;
    }
  }

  /// After unlock / lock — rebuild offer without ending the admin session.
  Future<void> forceReconnect() async {
    final session = activeSession;
    if (session == null) return;
    await connect(session, force: true);
  }

  Future<void> _startWebRtc(LiveSession session) async {
    final ice = _api.defaultIceServers(session);
    _pc = await createPeerConnection({
      'iceServers': ice,
      'sdpSemantics': 'unified-plan',
    });
    _remoteAnswerApplied = false;
    _mediaUnhealthy = false;
    _appliedAdminIce.clear();

    _pc!.onIceConnectionState = (RTCIceConnectionState state) {
      if (state == RTCIceConnectionState.RTCIceConnectionStateFailed ||
          state == RTCIceConnectionState.RTCIceConnectionStateClosed ||
          state == RTCIceConnectionState.RTCIceConnectionStateDisconnected) {
        _mediaUnhealthy = true;
        statusMessage = 'Audio link dropped — will reconnect';
        onChanged?.call();
      } else if (state == RTCIceConnectionState.RTCIceConnectionStateConnected ||
          state == RTCIceConnectionState.RTCIceConnectionStateCompleted) {
        _mediaUnhealthy = false;
        statusMessage = 'Streaming to admin';
        onChanged?.call();
      }
    };

    _pc!.onConnectionState = (RTCPeerConnectionState state) {
      if (state == RTCPeerConnectionState.RTCPeerConnectionStateFailed ||
          state == RTCPeerConnectionState.RTCPeerConnectionStateClosed ||
          state == RTCPeerConnectionState.RTCPeerConnectionStateDisconnected) {
        _mediaUnhealthy = true;
        onChanged?.call();
      }
    };

    // Prefer audio-only constraints that keep working under FGS + lock screen.
    _localStream = await navigator.mediaDevices.getUserMedia({
      'audio': session.requestMicrophone
          ? {
              'echoCancellation': true,
              'noiseSuppression': true,
              'autoGainControl': true,
            }
          : false,
      'video': session.requestCamera
          ? {
              'facingMode': 'user',
            }
          : false,
    });

    for (final track in _localStream!.getTracks()) {
      track.enabled = true;
      await _pc!.addTrack(track, _localStream!);
    }

    _pc!.onIceCandidate = (RTCIceCandidate candidate) async {
      final map = candidate.toMap();
      if (map.isEmpty) return;
      try {
        await _api.postIceCandidate(
          session.uuid,
          Map<String, dynamic>.from(map),
        );
      } catch (_) {}
    };

    final offer = await _pc!.createOffer({
      'offerToReceiveAudio': false,
      'offerToReceiveVideo': false,
    });
    await _pc!.setLocalDescription(offer);

    await Future<void>.delayed(const Duration(milliseconds: 500));
    final local = await _pc!.getLocalDescription();
    final sdp = _normalizeSdp(local?.sdp ?? offer.sdp ?? '');
    await _api.postOffer(session.uuid, offerSdp: sdp);

    _signalingPoll?.cancel();
    _signalingPoll = Timer.periodic(const Duration(seconds: 1), (_) {
      _pollSignaling(session.uuid);
    });
    await _pollSignaling(session.uuid);
  }

  String _normalizeSdp(String sdp) {
    final lines = sdp
        .replaceAll('\r\n', '\n')
        .replaceAll('\r', '\n')
        .split('\n')
        .where((line) {
          final t = line.trim();
          if (t.startsWith('a=ssrc:') && t.contains(' msid:')) return false;
          if (t.startsWith('a=ssrc:') && t.contains(' mslabel:')) return false;
          if (t.startsWith('a=ssrc:') && RegExp(r'\slabel:').hasMatch(t)) {
            return false;
          }
          return true;
        });
    return '${lines.join('\r\n')}\r\n';
  }

  Future<void> _pollSignaling(String sessionUuid) async {
    if (_pc == null) return;
    try {
      // Re-enable tracks if the OS muted them while locked.
      for (final t in _localStream?.getAudioTracks() ?? const <MediaStreamTrack>[]) {
        if (!t.enabled) t.enabled = true;
      }

      final data = await _api.fetchSignaling(sessionUuid);
      final answer = data['admin_answer_sdp'] as String?;
      if (answer != null && answer.isNotEmpty && !_remoteAnswerApplied) {
        await _pc!.setRemoteDescription(
          RTCSessionDescription(_normalizeSdp(answer), 'answer'),
        );
        _remoteAnswerApplied = true;
        statusMessage = 'Streaming to admin';
        onChanged?.call();
      }

      final adminIce = data['admin_ice'];
      if (adminIce is List) {
        for (final raw in adminIce) {
          if (raw is! Map) continue;
          final map = Map<String, dynamic>.from(raw);
          final key =
              '${map['candidate']}|${map['sdpMid']}|${map['sdpMLineIndex']}';
          if (_appliedAdminIce.contains(key)) continue;
          _appliedAdminIce.add(key);
          try {
            await _pc!.addCandidate(
              RTCIceCandidate(
                map['candidate'] as String?,
                map['sdpMid'] as String?,
                map['sdpMLineIndex'] as int?,
              ),
            );
          } catch (_) {}
        }
      }
    } catch (_) {}
  }

  Future<void> disconnect({bool notifyServer = true}) async {
    _signalingPoll?.cancel();
    _signalingPoll = null;
    final uuid = activeSession?.uuid;
    if (notifyServer && uuid != null) {
      try {
        await _api.endSession(uuid);
      } catch (_) {}
    }
    await _pc?.close();
    _pc = null;
    await _localStream?.dispose();
    _localStream = null;
    activeSession = null;
    _remoteAnswerApplied = false;
    _mediaUnhealthy = false;
    _appliedAdminIce.clear();
    statusMessage = null;
    onChanged?.call();
  }

  MediaStream? get localStream => _localStream;

  void dispose() {
    disconnect(notifyServer: false);
  }
}
