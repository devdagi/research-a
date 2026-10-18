class ApiException implements Exception {
  ApiException({
    required this.message,
    this.statusCode,
    this.body,
  });

  final String message;
  final int? statusCode;
  final String? body;

  @override
  String toString() =>
      'ApiException($statusCode): $message${body != null ? ' — $body' : ''}';
}

class EnrollmentException implements Exception {
  EnrollmentException(this.message);
  final String message;

  @override
  String toString() => message;
}

class PermissionDeniedException implements Exception {
  PermissionDeniedException(this.permission);
  final String permission;

  @override
  String toString() => 'Permission denied: $permission';
}
