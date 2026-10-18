import 'package:flutter/material.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:provider/provider.dart';

import 'core/theme.dart';
import 'features/authentication/app_state.dart';
import 'features/authentication/welcome_screen.dart';
import 'features/dashboard/dashboard_screen.dart';

/// Long-lived app state — must outlive Activity/Recents teardown so
/// location + live audio keep running under the foreground service.
final AppState appStateSingleton = AppState();

class CompanyDeviceManagerApp extends StatefulWidget {
  const CompanyDeviceManagerApp({super.key});

  @override
  State<CompanyDeviceManagerApp> createState() =>
      _CompanyDeviceManagerAppState();
}

class _CompanyDeviceManagerAppState extends State<CompanyDeviceManagerApp>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (appStateSingleton.status == AppBootstrapStatus.loading) {
      appStateSingleton.bootstrap();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Unlock / return to app → force live-audio re-offer + location.
      appStateSingleton.onAppResumed();
    } else if (state == AppLifecycleState.paused) {
      // Lock screen / background — keep FGS running, do not stop mic/GPS.
      appStateSingleton.onAppPaused();
    }
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider<AppState>.value(
      value: appStateSingleton,
      child: MaterialApp(
        title: 'Company Device Manager',
        theme: AppTheme.light(),
        home: const WithForegroundTask(child: _RootGate()),
      ),
    );
  }
}

class _RootGate extends StatelessWidget {
  const _RootGate();

  @override
  Widget build(BuildContext context) {
    final appState = context.watch<AppState>();

    switch (appState.status) {
      case AppBootstrapStatus.loading:
        return const Scaffold(
          body: Center(child: CircularProgressIndicator()),
        );
      case AppBootstrapStatus.welcome:
        return const WelcomeScreen();
      case AppBootstrapStatus.dashboard:
        return const DashboardScreen();
    }
  }
}
