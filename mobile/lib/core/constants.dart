class StorageKeys {
  static const deviceToken = 'device_token';
  static const deviceId = 'device_id';
  static const deviceName = 'device_name';
  static const enrolledAt = 'enrolled_at';
  static const locationConsent = 'location_consent';
  static const notificationsConsent = 'notifications_consent';
}

class ApiPaths {
  static const enroll = '/api/v1/device/enroll';
  static const me = '/api/v1/device/me';
  static const unenroll = '/api/v1/device/unenroll';
  static const heartbeat = '/api/v1/device/heartbeat';
  static const status = '/api/v1/device/status';
  static const location = '/api/v1/device/location';
  static const commands = '/api/v1/device/commands';
  static const consents = '/api/v1/device/consents';
  static const liveSessionsPending = '/api/v1/device/live-sessions/pending';

  static String commandAck(String uuid) => '/api/v1/device/commands/$uuid/ack';
  static String liveSessionRespond(String uuid) =>
      '/api/v1/device/live-sessions/$uuid/respond';
  static String liveSessionStart(String uuid) =>
      '/api/v1/device/live-sessions/$uuid/start';
  static String liveSessionEnd(String uuid) =>
      '/api/v1/device/live-sessions/$uuid/end';
  static String liveSessionOffer(String uuid) =>
      '/api/v1/device/live-sessions/$uuid/offer';
  static String liveSessionIce(String uuid) =>
      '/api/v1/device/live-sessions/$uuid/ice';
  static String liveSessionSignaling(String uuid) =>
      '/api/v1/device/live-sessions/$uuid/signaling';
}

