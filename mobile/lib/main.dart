import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';

import 'app.dart';
import 'services/background_service.dart';

/// Avoid system/VPN proxies breaking emulator → host API calls.
class _DirectHttpOverrides extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) {
    final client = super.createHttpClient(context);
    client.findProxy = (_) => 'DIRECT';
    client.connectionTimeout = const Duration(seconds: 20);
    return client;
  }
}

void main() async {
  HttpOverrides.global = _DirectHttpOverrides();
  WidgetsFlutterBinding.ensureInitialized();
  FlutterForegroundTask.initCommunicationPort();
  await BackgroundService.init();
  // Power-on / app process start: restore GPS + live-audio FGS if enrolled.
  await BackgroundService.restoreIfEnrolled();
  runApp(const CompanyDeviceManagerApp());
}
