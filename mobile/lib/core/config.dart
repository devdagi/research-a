/// Runtime configuration from compile-time defines (no hardcoded production URLs).
class AppConfig {
  AppConfig._();

  /// Host LAN IP (emulator often reaches this when 10.0.2.2 is blocked by VPN).
  /// Override: --dart-define=API_BASE_URL=http://10.0.2.2/cdm
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://192.168.8.109/cdm',
  );

  static const String turnUrl = String.fromEnvironment(
    'TURN_URL',
    defaultValue: '',
  );

  static const String turnUsername = String.fromEnvironment(
    'TURN_USERNAME',
    defaultValue: '',
  );

  static const String turnCredential = String.fromEnvironment(
    'TURN_CREDENTIAL',
    defaultValue: '',
  );

  static bool get isTurnConfigured =>
      turnUrl.isNotEmpty &&
      turnUsername.isNotEmpty &&
      turnCredential.isNotEmpty;

  static const Duration heartbeatInterval = Duration(minutes: 5);
  static const Duration commandPollInterval = Duration(minutes: 2);
}
