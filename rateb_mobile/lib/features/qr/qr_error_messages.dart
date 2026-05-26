import '../../core/api/api_exception.dart';

/// Enterprise-facing QR login error copy — never exposes raw backend codes.
String friendlyQrErrorMessage(Object error) {
  if (error is ApiException) {
    return _fromApi(error);
  }
  return 'Unable to verify your workforce identity. Please try again.';
}

String _fromApi(ApiException e) {
  switch (e.code) {
    case 'expired':
      return 'This workforce badge has expired. Request a new QR from RATEB System Settings.';
    case 'invalid_signature':
      return 'This badge could not be authenticated. Use a current QR from RATEB System Settings.';
    case 'invalid_format':
    case 'invalid':
      return 'Unrecognized workforce badge. Check the QR or use password sign-in.';
    case 'nonce_reused':
      return 'This identity badge has already been used. Request a new QR from your administrator.';
    case 'unauthorized':
    case 'invalid_credentials':
      return 'This badge is not authorized for mobile access.';
    case 'config_error':
      return 'Workforce identity service is temporarily unavailable. Try again shortly or use password sign-in.';
    case 'network_error':
      return 'No network connection. Check your internet and try again.';
  }

  if (e.statusCode == 401) {
    return 'Workforce badge could not be verified. It may be expired or invalid.';
  }
  if (e.statusCode == 0) {
    return 'No network connection. Check your internet and try again.';
  }
  if (e.statusCode == 408) {
    return 'Connection timed out. Check your network and try again.';
  }

  final msg = e.message.trim().toLowerCase();
  if (msg.contains('expired')) {
    return 'This workforce badge has expired. Request a new QR from RATEB System Settings.';
  }
  if (msg.contains('network') || msg.contains('reach server')) {
    return 'No network connection. Check your internet and try again.';
  }

  final raw = e.message.trim();
  if (raw.isNotEmpty &&
      !raw.toLowerCase().contains('exception') &&
      raw.length < 120) {
    return raw;
  }
  return 'Unable to verify your workforce identity. Please try again.';
}
